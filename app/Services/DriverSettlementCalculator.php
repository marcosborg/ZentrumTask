<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\DriverBalance;
use App\Models\DriverBalanceMovement;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Models\PrioTransaction;
use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
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

        $prioExpenses = PrioTransaction::query()
            ->whereNotNull('driver_id')
            ->where('assignment_status', 'ok')
            ->whereBetween('occurred_at', [$start, $end])
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->selectRaw('driver_id, COALESCE(SUM(net_amount), 0) as total')
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $viaVerdeExpenses = ViaVerdeTransaction::query()
            ->whereNotNull('driver_id')
            ->where('assignment_status', 'ok')
            ->whereBetween('occurred_at', [$start, $end])
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->selectRaw('driver_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $adjustmentExpenses = DriverAdjustment::query()
            ->whereDate('starts_at', '<=', $end->toDateString())
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->get()
            ->groupBy('driver_id');

        $created = 0;
        $skipped = 0;
        $missingProfiles = 0;
        $driverIds = $grouped->keys()->map(fn ($id): int => (int) $id)->values();
        $driversById = Driver::query()->whereIn('id', $driverIds)->get()->keyBy('id');
        $allocationsByDriver = VehicleAllocation::query()
            ->whereIn('driver_id', $driverIds)
            ->where('starts_at', '<=', $end)
            ->where(function ($query) use ($start): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $start);
            })
            ->get()
            ->groupBy('driver_id');
        $billingResolver = app(SettlementBillingResolver::class);

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
            $prioTotal = (float) ($prioExpenses[$driverId]->total ?? 0);
            $viaVerdeTotal = (float) ($viaVerdeExpenses[$driverId]->total ?? 0);
            $adjustmentsTotal = $this->sumAdjustmentsForPeriod(
                $adjustmentExpenses[$driverId] ?? collect(),
                $start,
                $end
            );
            $expensesTotal = round($prioTotal + $viaVerdeTotal + $adjustmentsTotal, 2);

            $percentCompany = (float) $profile->percent_company;
            $percentDriver = (float) $profile->percent_driver;
            $driver = $driversById->get((int) $driverId);

            if (! $driver instanceof Driver) {
                $missingProfiles++;
                Log::warning('Settlement skip: driver not found for balance group', [
                    'driver_id' => $driverId,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                ]);

                continue;
            }

            $billing = $billingResolver->resolveSettlementBilling(
                $driver,
                $start,
                $end,
                collect([$profile]),
                $allocationsByDriver->get((int) $driverId, collect())
            );
            $rentTotal = round((float) ($billing['rent_total'] ?? 0), 2);

            $netWithoutTips = round($netTotal - $tipsTotal, 2);
            $companyShare = round($netWithoutTips * ($percentCompany / 100), 2);
            $driverShare = round($netWithoutTips * ($percentDriver / 100), 2);
            $amountPayableBase = round($driverShare + $tipsTotal - $expensesTotal - $rentTotal, 2);
            $vatPercent = (float) ($profile->vat_percent ?? 0);
            $vatMultiplier = $profile->vat_refund_mode === \App\Enums\VatRefundMode::DriverDeliversVat && $vatPercent > 0
                ? 1 + ($vatPercent / 100)
                : 1;
            $amountPayable = round($amountPayableBase * $vatMultiplier, 2);
            $balance = DriverBalance::query()->firstOrCreate(
                ['driver_id' => $driverId],
                [
                    'current_balance' => 0,
                    'is_settled' => false,
                ]
            );
            $carryOverBalance = $this->resolveCarryOverBalance((int) $driverId, $start, $balance);
            $amountDue = round($carryOverBalance + $amountPayable, 2);

            $settlement = DriverSettlement::query()->create([
                'driver_id' => $driverId,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'net_total' => $netTotal,
                'tips_total' => $tipsTotal,
                'expenses_total' => $expensesTotal,
                'carry_over_balance' => $carryOverBalance,
                'company_share' => $companyShare,
                'driver_share' => $driverShare,
                'amount_payable' => $amountPayable,
                'amount_due' => $amountDue,
                'is_paid' => false,
                'rules_snapshot' => [
                    'billing_profile_id' => $profile->id,
                    'percent_company' => $percentCompany,
                    'percent_driver' => $percentDriver,
                    'vehicle_rent_type' => $profile->vehicle_rent_type?->value ?? (string) $profile->vehicle_rent_type,
                    'vehicle_rent_value' => (float) ($profile->vehicle_rent_value ?? 0),
                    'rental_days' => (int) ($billing['rental_days'] ?? 0),
                    'rent_total' => $rentTotal,
                    'net_without_tips' => $netWithoutTips,
                    'amount_payable_base' => $amountPayableBase,
                    'carry_over_balance' => $carryOverBalance,
                    'amount_due' => $amountDue,
                ],
            ]);

            $balance->forceFill([
                'current_balance' => $amountDue,
                'last_settlement_id' => $settlement->id,
                'is_settled' => false,
                'settled_at' => null,
            ])->save();

            DriverBalanceMovement::query()->create([
                'driver_id' => $driverId,
                'driver_balance_id' => $balance->id,
                'driver_settlement_id' => $settlement->id,
                'amount' => $amountPayable,
                'type' => 'settlement',
                'description' => "Settlement {$start->toDateString()} - {$end->toDateString()}",
            ]);

            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_profiles' => $missingProfiles,
        ];
    }

    private function resolveCarryOverBalance(int $driverId, Carbon $periodStart, DriverBalance $balance): float
    {
        $latestPreviousSettlement = DriverSettlement::query()
            ->where('driver_id', $driverId)
            ->whereDate('period_end', '<', $periodStart->toDateString())
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->first(['amount_due']);

        if ($latestPreviousSettlement) {
            return round((float) $latestPreviousSettlement->amount_due, 2);
        }

        return round((float) $balance->current_balance, 2);
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

    /**
     * @param  \Illuminate\Support\Collection<int, DriverAdjustment>  $adjustments
     */
    private function sumAdjustmentsForPeriod(\Illuminate\Support\Collection $adjustments, Carbon $start, Carbon $end): float
    {
        if ($adjustments->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($adjustments as $adjustment) {
            $startsAt = Carbon::parse($adjustment->starts_at)->startOfDay();
            $weeks = (int) ($adjustment->recurrence_weeks ?? 1);
            $weeks = max(1, $weeks);

            for ($i = 0; $i < $weeks; $i++) {
                $occurrence = $startsAt->copy()->addWeeks($i);

                if ($occurrence->lt($start) || $occurrence->gt($end)) {
                    continue;
                }

                $total += (float) $adjustment->amount;
            }
        }

        return round($total, 2);
    }
}
