<?php

namespace App\Services;

class ParticipationService
{
    public function resolve(int $driverId, \DateTimeInterface|string $date, \DateTimeInterface|string|null $end = null): ?\App\Models\DriverParticipation
    {
        $start = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $finish = \Illuminate\Support\Carbon::parse($end ?? $date)->toDateString();
        $matches = \App\Models\DriverParticipation::query()->where('driver_id', $driverId)->where('status', '!=', 'preparing')
            ->whereDate('starts_at', '<=', $finish)->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>', $start))->get();
        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->isEmpty()) {
            $legacy = \App\Models\DriverParticipation::query()->where('driver_id', $driverId)->where('is_legacy', true)->whereNull('ends_at')->first();
            if ($legacy && $legacy->starts_at->toDateString() > $start && \App\Models\DriverParticipation::query()->where('driver_id', $driverId)->count() === 1) {
                $legacy->update(['starts_at' => $start]);

                return $legacy;
            }
        }

        return null;
    }

    public function activate(\App\Models\DriverParticipation $participation): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($participation): void {
            \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
            $participation->refresh();
            if ($participation->status !== 'preparing') {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'A participação já foi ativada.']);
            }
            $date = $participation->starts_at->toDateString();
            if ($participation->operation === \App\Enums\TvdeOperation::Slot) {
                abort_unless(config('slots.enabled'), 403);
                $vehicle = $participation->vehicle;
                $profile = $participation->billingProfiles()->active()->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))->exists();
                $checkup = $vehicle?->checkups()->whereNotNull('completed_at')->where('completed_at', '<=', \Illuminate\Support\Carbon::parse($date, config('slots.timezone'))->endOfDay()->utc())->latest('completed_at')->first();
                $pack = $this->packFor($participation, $date);
                if (! $vehicle || $vehicle->operation !== 'slot' || (int) $vehicle->owner_driver_id !== (int) $participation->driver_id || ! $participation->documents_approved_at || ! $participation->contract_file || ! $profile || ! $checkup || $checkup->nextDueAt()->toDateString() < $date || ! $pack) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Confirme viatura própria, documentação, contrato, perfil fiscal, pack e check-up inicial válido.']);
                }
            }
            $participation->update(['status' => 'active']);
            $participation->balance()->firstOrCreate(['driver_id' => $participation->driver_id], ['operation' => $participation->operation->value, 'current_balance' => 0, 'is_settled' => false]);
            if ($participation->vehicle_id) {
                \App\Models\VehicleAllocation::query()->create(['vehicle_id' => $participation->vehicle_id, 'driver_id' => $participation->driver_id, 'driver_participation_id' => $participation->id, 'operation' => $participation->operation->value, 'starts_at' => $participation->starts_at->copy()->setTimezone(config('slots.timezone'))->startOfDay()->utc(), 'status' => 'active']);
            }
        });
    }

    public function transition(\App\Models\DriverParticipation $origin, \App\Models\DriverParticipation $target): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($origin, $target): void {
            \App\Models\Driver::query()->whereKey($origin->driver_id)->lockForUpdate()->firstOrFail();
            $origin->refresh();
            $target->refresh();
            $date = $target->starts_at;
            if ($origin->driver_id !== $target->driver_id || $origin->operation === $target->operation || $origin->ends_at || ! $date->isMonday() || $date->toDateString() <= $origin->starts_at->toDateString() || $date->toDateString() < now(config('slots.timezone'))->toDateString()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'A transição deve ser futura, à segunda-feira, entre operações distintas do mesmo motorista.']);
            }
            $this->assertEditableFrom($origin, $date->toDateString());
            $origin->update(['ends_at' => $date->toDateString(), 'status' => 'closed']);
            $boundary = \Illuminate\Support\Carbon::parse($date->toDateString(), config('slots.timezone'))->utc();
            \App\Models\VehicleAllocation::query()->where('driver_participation_id', $origin->id)->whereNull('ends_at')->update(['ends_at' => $boundary->copy()->subSecond(), 'status' => 'closed']);
            $this->activate($target);
        });
    }

    public function suspend(\App\Models\DriverParticipation $participation, string $start, string $end, string $reason): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($participation, $start, $end, $reason): void {
            \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
            if ($end < $start || $start < $participation->starts_at->toDateString() || ($participation->ends_at && $end >= $participation->ends_at->toDateString()) || trim($reason) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'Indique um período válido dentro da participação e um motivo.']);
            }
            $this->assertEditableFrom($participation, $start);
            if ($participation->suspensions()->whereDate('starts_at', '<=', $end)->whereDate('ends_at', '>=', $start)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'Este período já está suspenso.']);
            }
            $participation->suspensions()->create(['starts_at' => $start, 'ends_at' => $end, 'reason' => $reason, 'approved_by' => auth()->id()]);
        });
    }

    public function assignPack(\App\Models\DriverParticipation $participation, \App\Models\SlotPack $pack, string $start): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($participation, $pack, $start): void {
            \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
            $initial = ! $participation->packs()->exists();
            if ($participation->operation !== \App\Enums\TvdeOperation::Slot || $start < $participation->starts_at->toDateString() || ($participation->ends_at && $start >= $participation->ends_at->toDateString()) || $pack->valid_from->toDateString() > $start || ($pack->valid_to && $pack->valid_to->toDateString() < $start) || (! $initial && (! \Illuminate\Support\Carbon::parse($start)->isMonday() || $start < now(config('slots.timezone'))->next(\Illuminate\Support\Carbon::MONDAY)->toDateString()))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'Indique um pack válido e a segunda-feira seguinte para a mudança.']);
            }
            $this->assertEditableFrom($participation, $start);
            if ($participation->packs()->whereDate('starts_at', '>=', $start)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'Já existe uma mudança agendada.']);
            }
            $participation->packs()->whereNull('ends_at')->update(['ends_at' => $start]);
            $participation->packs()->create(['slot_pack_id' => $pack->id, 'starts_at' => $start]);
        });
    }

    public function packFor(\App\Models\DriverParticipation $participation, string $date): ?\App\Models\SlotPack
    {
        return $participation->packs()->with('pack')->whereDate('starts_at', '<=', $date)->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>', $date))->latest('starts_at')->first()?->pack;
    }

    public function assertEditableFrom(\App\Models\DriverParticipation $participation, string $date): void
    {
        if ($participation->settlements()->whereDate('period_end', '>=', $date)->exists() || \App\Models\DriverWeekStatement::query()->where('driver_participation_id', $participation->id)->whereDate('week_end_date', '>=', $date)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['starts_at' => 'O período já foi processado. Registe uma correção documentada na origem.']);
        }
    }

    public function close(\App\Models\DriverParticipation $participation, string $date): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($participation, $date): void {
            \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
            $this->assertEditableFrom($participation, $date);
            $participation->update(['ends_at' => $date, 'status' => 'closed']);
            $boundary = \Illuminate\Support\Carbon::parse($date, config('slots.timezone'))->utc();
            \App\Models\VehicleAllocation::query()->where('driver_participation_id', $participation->id)->whereNull('ends_at')->update(['ends_at' => $boundary->subSecond(), 'status' => 'closed']);
        });
    }

    public function regularize(\App\Models\DriverParticipation $participation, float $amount, string $description): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($participation, $amount, $description): void {
            if (trim($description) === '' || ! is_finite($amount) || round($amount, 2) === 0.0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Indique um valor não nulo e descreva a regularização.']);
            }
            \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
            $participation = \App\Models\DriverParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();
            $balance = $participation->balance()->firstOrCreate(['driver_id' => $participation->driver_id], ['operation' => $participation->operation->value, 'current_balance' => 0]);
            $balance->update(['current_balance' => round((float) $balance->current_balance + $amount, 2)]);
            \App\Models\DriverBalanceMovement::query()->create(['driver_id' => $participation->driver_id, 'driver_participation_id' => $participation->id, 'operation' => $participation->operation->value, 'driver_balance_id' => $balance->id, 'amount' => round($amount, 2), 'type' => 'regularization', 'description' => $description]);
        });
    }

    public function setFiscalProfile(\App\Models\DriverParticipation $participation, array $data): void
    {
        abort_unless(config('slots.enabled') && $participation->operation === \App\Enums\TvdeOperation::Slot, 403);
        \Illuminate\Support\Facades\DB::transaction(function () use ($participation, $data): void {
            \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
            $this->assertEditableFrom($participation, $data['valid_from']);
            if ($participation->billingProfiles()->whereDate('valid_from', '>=', $data['valid_from'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['valid_from' => 'Já existe um perfil nesta data ou posterior.']);
            }
            $participation->billingProfiles()->where('active', true)->whereNull('valid_to')->update(['valid_to' => \Illuminate\Support\Carbon::parse($data['valid_from'])->subDay()->toDateString()]);
            $participation->billingProfiles()->create(array_merge($data, ['driver_id' => $participation->driver_id, 'operation' => 'slot', 'active' => true, 'percent_company' => 0, 'percent_driver' => 100, 'tips_to_driver' => true, 'vehicle_rent_type' => 'none']));
        });
    }
}
