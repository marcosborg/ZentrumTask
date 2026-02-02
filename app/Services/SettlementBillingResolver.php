<?php

namespace App\Services;

use App\Enums\VehicleRentType;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\VehicleAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SettlementBillingResolver
{
    /**
     * @return array{
     *     status: 'ok'|'missing'|'ambiguous',
     *     profile: DriverBillingProfile|null,
     *     valid_profiles: Collection<int, DriverBillingProfile>
     * }
     */
    public function resolveBillingProfile(Driver $driver, Carbon $start, Carbon $end, ?Collection $profiles = null): array
    {
        $profiles = $profiles ?? DriverBillingProfile::query()
            ->where('driver_id', $driver->id)
            ->where('active', true)
            ->get();

        $valid = $profiles->filter(function (DriverBillingProfile $profile) use ($start, $end): bool {
            $validFrom = $profile->valid_from ? Carbon::parse($profile->valid_from) : null;
            $validTo = $profile->valid_to ? Carbon::parse($profile->valid_to) : null;

            $startsBeforeEnd = $validFrom ? $validFrom->lte($end) : true;
            $endsAfterStart = $validTo ? $validTo->gte($start) : true;

            return $startsBeforeEnd && $endsAfterStart;
        })->values();

        if ($valid->count() === 0) {
            return [
                'status' => 'missing',
                'profile' => null,
                'valid_profiles' => $valid,
            ];
        }

        if ($valid->count() > 1) {
            return [
                'status' => 'ambiguous',
                'profile' => null,
                'valid_profiles' => $valid,
            ];
        }

        return [
            'status' => 'ok',
            'profile' => $valid->first(),
            'valid_profiles' => $valid,
        ];
    }

    public function calculateRentalDays(Driver $driver, Carbon $start, Carbon $end, ?Collection $allocations = null): int
    {
        $allocations = $allocations ?? VehicleAllocation::query()
            ->where('driver_id', $driver->id)
            ->where('starts_at', '<=', $end)
            ->where(function ($query) use ($start): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $start);
            })
            ->get();

        if ($allocations->isEmpty()) {
            return 0;
        }

        $days = [];

        foreach ($allocations as $allocation) {
            $allocationStart = $allocation->starts_at instanceof Carbon
                ? $allocation->starts_at
                : Carbon::parse($allocation->starts_at);
            $allocationEnd = $allocation->ends_at
                ? ($allocation->ends_at instanceof Carbon ? $allocation->ends_at : Carbon::parse($allocation->ends_at))
                : $end;

            $overlapStart = $allocationStart->copy()->max($start)->startOfDay();
            $overlapEnd = $allocationEnd->copy()->min($end)->endOfDay();

            if ($overlapStart->gt($overlapEnd)) {
                continue;
            }

            $cursor = $overlapStart->copy()->startOfDay();
            $last = $overlapEnd->copy()->startOfDay();

            while ($cursor->lte($last)) {
                $days[$cursor->toDateString()] = true;

                if (count($days) >= 7) {
                    return 7;
                }

                $cursor->addDay();
            }
        }

        return min(count($days), 7);
    }

    public function calculateRentTotal(DriverBillingProfile $profile, int $days): float
    {
        $value = (float) ($profile->vehicle_rent_value ?? 0);

        if ($value <= 0 || $days <= 0) {
            return 0.0;
        }

        $type = $profile->vehicle_rent_type;

        if ($type === VehicleRentType::Weekly || $type?->value === VehicleRentType::Weekly->value) {
            $daily = $value / 7;
        } elseif ($type?->value === 'daily') {
            $daily = $value;
        } else {
            return 0.0;
        }

        return round($daily * $days, 2);
    }

    /**
     * @return array{
     *     profile_status: 'ok'|'missing'|'ambiguous',
     *     billing_profile_id: int|null,
     *     billing_profile_label: string|null,
     *     rental_days: int,
     *     rent_total: float,
     *     percent_company: float|null,
     *     percent_driver: float|null,
     *     withholding_label: string,
     *     vat_label: string,
     *     vat_refund_mode: string|null
     * }
     */
    public function resolveSettlementBilling(
        Driver $driver,
        Carbon $start,
        Carbon $end,
        ?Collection $profiles = null,
        ?Collection $allocations = null
    ): array {
        $profileResult = $this->resolveBillingProfile($driver, $start, $end, $profiles);
        $status = $profileResult['status'];
        $profile = $profileResult['profile'];

        $rentalDays = $status === 'ok'
            ? $this->calculateRentalDays($driver, $start, $end, $allocations)
            : 0;

        $rentTotal = $status === 'ok' && $profile
            ? $this->calculateRentTotal($profile, $rentalDays)
            : 0.0;

        $percentCompany = $status === 'ok' && $profile ? $this->normalizePercent($profile->percent_company) : null;
        $percentDriver = $status === 'ok' && $profile ? $this->normalizePercent($profile->percent_driver) : null;

        $withholdingLabel = '—';
        if ($status === 'ok' && $profile) {
            $withholdingLabel = ($profile->apply_withholding_tax && (float) $profile->withholding_tax_percent > 0)
                ? 'Sim ('.number_format((float) $profile->withholding_tax_percent, 2, ',', ' ').'%)'
                : 'Nao';
        }

        $vatLabel = '—';
        if ($status === 'ok' && $profile) {
            $percent = (float) $profile->vat_percent;
            $mode = $profile->vat_refund_mode?->value;
            $receivesVat = $percent === 23.0 && in_array($mode, ['refund', 'refund_to_driver', 'driver', 'driver_delivers_vat'], true);

            if ($receivesVat) {
                $vatLabel = 'Sim (23%)';
            } elseif ($percent === 23.0 && $mode && $mode !== 'none') {
                $vatLabel = 'Nao (sem devolucao)';
            } elseif ($percent === 23.0) {
                $vatLabel = 'Nao';
            } else {
                $vatLabel = 'Nao';
            }
        }

        $profileLabel = null;
        if ($profile) {
            $from = $profile->valid_from?->format('d/m/Y') ?? 'Sem inicio';
            $to = $profile->valid_to?->format('d/m/Y') ?? 'Sem fim';
            $profileLabel = "ID {$profile->id} ({$from} - {$to})";
        }

        return [
            'profile_status' => $status,
            'billing_profile_id' => $profile?->id,
            'billing_profile_label' => $profileLabel,
            'rental_days' => $rentalDays,
            'rent_total' => $rentTotal,
            'percent_company' => $percentCompany,
            'percent_driver' => $percentDriver,
            'withholding_label' => $withholdingLabel,
            'vat_label' => $vatLabel,
            'vat_refund_mode' => $profile?->vat_refund_mode?->value ?? null,
        ];
    }

    private function normalizePercent(float|int|string|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $number = (float) $value;

        if ($number > 0 && $number <= 1) {
            return round($number * 100, 2);
        }

        return round($number, 2);
    }
}
