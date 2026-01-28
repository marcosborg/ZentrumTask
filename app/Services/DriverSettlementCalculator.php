<?php

namespace App\Services;

use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DriverSettlementCalculator
{
    /**
     * @return array{created:int, skipped:int, missing_profiles:int}
     */
    public function calculate(string $periodStart, string $periodEnd, ?int $driverId = null): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        $balances = PlatformDriverBalance::query()
            ->whereNotNull('driver_id')
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_end', '<=', $end->toDateString())
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->get(['driver_id', 'net_amount', 'tips_amount']);

        $grouped = $balances->groupBy('driver_id');

        $created = 0;
        $skipped = 0;
        $missingProfiles = 0;

        foreach ($grouped as $driverId => $driverBalances) {
            $exists = DriverSettlement::query()
                ->where('driver_id', $driverId)
                ->whereDate('period_start', $start->toDateString())
                ->whereDate('period_end', $end->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;
                Log::info('Settlement skip: already exists', [
                    'driver_id' => $driverId,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ]);

                continue;
            }

            $profile = $this->resolveActiveProfile((int) $driverId, $start, $end);

            if (! $profile) {
                $missingProfiles++;
                Log::info('Settlement skip: missing billing profile', [
                    'driver_id' => $driverId,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ]);

                continue;
            }

            $netTotal = round((float) $driverBalances->sum('net_amount'), 2);
            $tipsTotal = (float) $driverBalances->sum('tips_amount');

            $percentCompany = (float) $profile->percent_company;
            $percentDriver = (float) $profile->percent_driver;
            $rentAmount = (float) ($profile->vehicle_rent_value ?? 0);

            $companyShare = round($netTotal * ($percentCompany / 100), 2);
            $driverShare = round($netTotal * ($percentDriver / 100), 2);
            $amountPayable = round($driverShare - $rentAmount, 2);

            DriverSettlement::query()->create([
                'driver_id' => $driverId,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'net_total' => $netTotal,
                'tips_total' => $tipsTotal,
                'company_share' => $companyShare,
                'driver_share' => $driverShare,
                'amount_payable' => $amountPayable,
                'rules_snapshot' => [
                    'billing_profile_id' => $profile->id,
                    'percent_company' => $percentCompany,
                    'percent_driver' => $percentDriver,
                    'vehicle_rent_type' => $profile->vehicle_rent_type?->value ?? (string) $profile->vehicle_rent_type,
                    'vehicle_rent_value' => $rentAmount,
                ],
            ]);

            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_profiles' => $missingProfiles,
        ];
    }

    private function resolveActiveProfile(int $driverId, Carbon $start, Carbon $end): ?DriverBillingProfile
    {
        $profile = DriverBillingProfile::query()
            ->where('driver_id', $driverId)
            ->where('active', true)
            ->where(function ($query) use ($end): void {
                $query->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $end->toDateString());
            })
            ->where(function ($query) use ($start): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $start->toDateString());
            })
            ->orderByDesc('valid_from')
            ->first();

        return $profile;
    }
}
