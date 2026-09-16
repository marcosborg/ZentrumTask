<?php

namespace App\Services;

use App\Models\RentalVan;
use App\Models\User;
use App\Models\VanBlock;
use App\Models\VanReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VanRentalService
{
    public const TIMEZONE = 'Europe/Lisbon';

    public function date(string $value, string $field = 'starts_at'): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, self::TIMEZONE);
            if (! $date || $date->format('Y-m-d\TH:i') !== $value || $date->minute % 30 !== 0) {
                throw new \InvalidArgumentException;
            }
            foreach ([-3600, 3600] as $seconds) {
                if ($date->utc()->addSeconds($seconds)->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i') === $value) {
                    throw new \InvalidArgumentException;
                }
            }

            return $date->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Escolha uma data e hora válida, em intervalos de 30 minutos. Evite a hora repetida na mudança de horário.']);
        }
    }

    public function assertAvailable(RentalVan $van, CarbonImmutable $start, CarbonImmutable $end, ?int $except = null, ?int $buffer = null): void
    {
        $buffer ??= $van->buffer_minutes;
        $from = $start->subMinutes($buffer);
        $until = $end->addMinutes($buffer);
        $reservations = $van->relationLoaded('reservations')
            ? $van->reservations->whereIn('status', ['confirmed', 'in_progress'])->filter(fn ($item): bool => $item->id !== $except)
            : $van->reservations()->whereIn('status', ['confirmed', 'in_progress'])->when($except, fn ($query) => $query->whereKeyNot($except))->get();
        foreach ($reservations as $reservation) {
            $gap = max($buffer, $reservation->buffer_minutes);
            if ($start < $reservation->ends_at->addMinutes($gap) && $end->addMinutes($gap) > $reservation->starts_at) {
                throw ValidationException::withMessages(['starts_at' => 'A carrinha não está disponível neste período. Escolha outro horário.']);
            }
        }
        $blocked = $van->relationLoaded('blocks')
            ? $van->blocks->contains(fn ($block): bool => $block->starts_at < $until && $block->ends_at > $from)
            : $van->blocks()->where('starts_at', '<', $until)->where('ends_at', '>', $from)->exists();
        if ($blocked) {
            throw ValidationException::withMessages(['starts_at' => 'A carrinha não está disponível neste período. Escolha outro horário.']);
        }
    }

    public function quote(RentalVan $van, array $data, ?VanReservation $reservation = null): array
    {
        if ($van->status !== 'published' || ! in_array($data['mode'] ?? '', ['self_drive', 'with_driver'], true) || ! $van->{$data['mode']}) {
            throw ValidationException::withMessages(['mode' => 'Esta modalidade não está disponível.']);
        }
        $start = $this->date($data['starts_at']);
        $end = $this->date($data['ends_at'], 'ends_at');
        if ($start < CarbonImmutable::now()->addHours($reservation ? 0 : $van->lead_hours) || $end <= $start || $end > $start->addDays(30)) {
            throw ValidationException::withMessages(['ends_at' => 'Escolha um período futuro até 30 dias, respeitando a antecedência mínima.']);
        }
        foreach ([$start, $end] as $date) {
            $time = $date->setTimezone(self::TIMEZONE)->format('H:i:s');
            if ($time < $van->opens_at || $time > $van->closes_at) {
                throw ValidationException::withMessages(['starts_at' => 'Levantamento e devolução devem respeitar o horário apresentado.']);
            }
        }
        $this->assertAvailable($van, $start, $end, $reservation?->id, $reservation?->buffer_minutes);
        $rate = $reservation?->hourly_rate ?? (int) $van->{$data['mode'].'_rate'};
        if ($rate <= 0) {
            throw ValidationException::withMessages(['mode' => 'Tarifa indisponível. Contacte a equipa.']);
        }
        $pricingUnit = $reservation?->terms['pricing_unit'] ?? ($van->{$data['mode'].'_pricing_unit'} ?? 'hour');
        $duration = $start->diffInSeconds($end);
        $billableUnits = $pricingUnit === 'two_days'
            ? max(1, (int) ceil($duration / (48 * 3600)))
            : max($reservation?->terms['minimum_hours'] ?? $van->minimum_hours, (int) ceil($duration / 3600));
        $terms = $reservation?->terms ?? [
            ...$van->only(['minimum_hours', 'pickup_location', 'mileage_terms', 'fuel_terms', 'cancellation_terms', 'rental_terms']),
            'pricing_unit' => $pricingUnit,
        ];

        return [
            'starts_at' => $start, 'ends_at' => $end,
            'hourly_rate' => $rate, 'billable_hours' => $billableUnits, 'pricing_unit' => $pricingUnit,
            'estimated_total' => $rate * $billableUnits, 'deposit' => $reservation?->deposit ?? $van->deposit,
            'buffer_minutes' => $reservation?->buffer_minutes ?? $van->buffer_minutes,
            'terms' => $terms,
        ];
    }

    public function reserve(RentalVan $van, array $data): VanReservation
    {
        return $van->getConnection()->transaction(function () use ($van, $data): VanReservation {
            $van = RentalVan::query()->lockForUpdate()->findOrFail($van->id);
            $existing = VanReservation::query()->where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                return $existing;
            }
            $reservation = $van->reservations()->create([
                ...Arr::only($data, ['submission_key', 'mode', 'name', 'email', 'phone', 'purpose', 'origin', 'destination', 'loading_help', 'notes']),
                ...$this->quote($van, $data),
                'reference' => 'CAR-'.strtoupper((string) Str::ulid()), 'status' => 'pending',
            ]);
            $reservation->events()->create(['to_status' => 'pending', 'reason' => 'Pedido recebido pelo website.']);

            return $reservation;
        });
    }

    public function transition(VanReservation $reservation, string $status, User $actor, string $reason, array $driver = []): VanReservation
    {
        return $reservation->getConnection()->transaction(function () use ($reservation, $status, $actor, $reason, $driver): VanReservation {
            $van = RentalVan::query()->lockForUpdate()->findOrFail($reservation->rental_van_id);
            $reservation = VanReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $allowed = ['pending' => ['confirmed', 'rejected', 'cancelled'], 'confirmed' => ['cancelled', 'in_progress'], 'in_progress' => ['completed']];
            if (! in_array($status, $allowed[$reservation->status] ?? [], true) || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Transição inválida ou motivo em falta.']);
            }
            $old = $reservation->status;
            if ($status === 'confirmed') {
                $this->quote($van, ['mode' => $reservation->mode, 'starts_at' => $reservation->starts_at->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i'), 'ends_at' => $reservation->ends_at->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i')], $reservation);
                if ($reservation->mode === 'with_driver') {
                    if (blank($driver['driver_name'] ?? null) || ! ($driver['driver_verified'] ?? false)) {
                        throw ValidationException::withMessages(['driver_name' => 'Identifique o motorista e confirme a disponibilidade.']);
                    }
                    $reservation->fill(Arr::only($driver, ['driver_name', 'driver_verified']));
                }
            }
            if ($status === 'in_progress' && $reservation->starts_at->isFuture()) {
                throw ValidationException::withMessages(['reason' => 'A reserva ainda não começou.']);
            }
            $reservation->status = $status;
            $reservation->save();
            $reservation->events()->create(['user_id' => $actor->id, 'from_status' => $old, 'to_status' => $status, 'reason' => $reason]);

            return $reservation;
        });
    }

    public function reschedule(VanReservation $reservation, string $start, string $end, User $actor, string $reason, array $driver = []): void
    {
        $reservation->getConnection()->transaction(function () use ($reservation, $start, $end, $actor, $reason, $driver): void {
            $van = RentalVan::query()->lockForUpdate()->findOrFail($reservation->rental_van_id);
            $reservation = VanReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (! in_array($reservation->status, ['pending', 'confirmed'], true) || blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Só pode reagendar pedidos pendentes ou confirmados, indicando o motivo.']);
            }
            if ($reservation->status === 'confirmed' && $reservation->mode === 'with_driver') {
                if (blank($driver['driver_name'] ?? null) || ! ($driver['driver_verified'] ?? false)) {
                    throw ValidationException::withMessages(['driver_name' => 'Confirme novamente o motorista para o novo horário.']);
                }
                $reservation->fill(Arr::only($driver, ['driver_name', 'driver_verified']));
            }
            $before = $reservation->only(['starts_at', 'ends_at', 'estimated_total']);
            $reservation->fill($this->quote($van, ['mode' => $reservation->mode, 'starts_at' => $start, 'ends_at' => $end], $reservation))->save();
            $reservation->events()->create(['user_id' => $actor->id, 'from_status' => $reservation->status, 'to_status' => $reservation->status, 'reason' => $reason, 'details' => ['before' => $before, 'after' => $reservation->only(['starts_at', 'ends_at', 'estimated_total'])]]);
        });
    }

    public function block(RentalVan $van, string $start, string $end, string $reason, User $actor): VanBlock
    {
        return $van->getConnection()->transaction(function () use ($van, $start, $end, $reason, $actor): VanBlock {
            $van = RentalVan::query()->lockForUpdate()->findOrFail($van->id);
            $from = $this->date($start);
            $until = $this->date($end, 'ends_at');
            if ($until <= $from || blank($reason)) {
                throw ValidationException::withMessages(['ends_at' => 'Indique um período válido e o motivo.']);
            }
            $this->assertAvailable($van, $from, $until);

            return $van->blocks()->create(['starts_at' => $from, 'ends_at' => $until, 'reason' => $reason, 'user_id' => $actor->id]);
        });
    }
}
