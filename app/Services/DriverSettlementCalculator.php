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
use App\Models\TeslaChargingEvent;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use App\Models\ViaVerdeTransaction;
use Illuminate\Database\Eloquent\Builder;
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

        $adjustmentExpenses = DriverAdjustment::query()
            ->whereDate('starts_at', '<=', $end->toDateString())
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->get()
            ->groupBy('driver_id');

        $balanceDriverIds = $grouped->keys()->map(fn ($id): int => (int) $id)->values();
        $allocationDriverIds = VehicleAllocation::query()
            ->whereNotNull('driver_id')
            ->where('starts_at', '<=', $end)
            ->where(function ($query) use ($start): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $start);
            })
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->pluck('driver_id')
            ->map(fn ($id): int => (int) $id);

        $created = 0;
        $skipped = 0;
        $missingProfiles = 0;
        $driverIds = $balanceDriverIds
            ->merge($allocationDriverIds)
            ->unique()
            ->values();
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

        foreach ($driverIds as $driverId) {
            $driverBalances = $grouped->get($driverId, collect());

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
            $prioTotal = $this->sumPrioExpensesForDriver((int) $driverId, $start, $end);
            $viaVerdeTotal = $this->sumViaVerdeExpensesForDriver((int) $driverId, $start, $end);
            $extraKmTotal = $this->sumExtraKmChargesForDriver((int) $driverId, $profile, $start, $end);
            $teslaChargingTotal = $this->sumTeslaChargingExpensesForDriver((int) $driverId, $start, $end);
            $adjustmentsTotal = $this->sumAdjustmentsForPeriod(
                $adjustmentExpenses[$driverId] ?? collect(),
                $start,
                $end
            );
            $expensesTotal = round($prioTotal + $viaVerdeTotal + $extraKmTotal + $teslaChargingTotal + $adjustmentsTotal, 2);

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
            $amountDue = round(($carryOverBalance + $amountPayableBase) * $vatMultiplier, 2);

            $settlement = DriverSettlement::query()->create([
                'driver_id' => $driverId,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'net_total' => $netTotal,
                'tips_total' => $tipsTotal,
                'expenses_total' => $expensesTotal,
                'tesla_charging_total' => $teslaChargingTotal,
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
                    'vat_percent' => $vatPercent,
                    'vat_refund_mode' => $profile->vat_refund_mode?->value ?? null,
                    'vat_multiplier' => $vatMultiplier,
                    'extra_km_total' => $extraKmTotal,
                    'tesla_charging_total' => $teslaChargingTotal,
                    'extra_km_limit' => (float) ($profile->extra_km_limit ?? 0),
                    'extra_km_rate' => (float) ($profile->extra_km_rate ?? 0),
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

    private function sumPrioExpensesForDriver(int $driverId, Carbon $start, Carbon $end): float
    {
        $total = PrioTransaction::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->where(function (Builder $query) use ($driverId): void {
                $query
                    ->where(function (Builder $query) use ($driverId): void {
                        $query
                            ->whereNotNull('vehicle_id')
                            ->whereExists(function ($allocationQuery) use ($driverId): void {
                                $allocationQuery
                                    ->selectRaw('1')
                                    ->from('vehicle_allocations')
                                    ->where('vehicle_allocations.driver_id', $driverId)
                                    ->whereColumn('vehicle_allocations.vehicle_id', 'prio_transactions.vehicle_id')
                                    ->whereRaw('DATE(prio_transactions.occurred_at) >= DATE(vehicle_allocations.starts_at)')
                                    ->where(function ($dateOverlapQuery): void {
                                        $dateOverlapQuery
                                            ->whereNull('vehicle_allocations.ends_at')
                                            ->orWhereRaw('DATE(prio_transactions.occurred_at) <= DATE(vehicle_allocations.ends_at)');
                                    });
                            });
                    })
                    ->orWhere(function (Builder $query) use ($driverId): void {
                        $query
                            ->whereNull('vehicle_id')
                            ->where('driver_id', $driverId)
                            ->where('assignment_status', 'ok');
                    });
            })
            ->sum('net_amount');

        return round((float) $total, 2);
    }

    private function sumViaVerdeExpensesForDriver(int $driverId, Carbon $start, Carbon $end): float
    {
        $total = ViaVerdeTransaction::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->where(function (Builder $query) use ($driverId): void {
                $query
                    ->where(function (Builder $query) use ($driverId): void {
                        $query
                            ->whereNotNull('vehicle_id')
                            ->whereExists(function ($allocationQuery) use ($driverId): void {
                                $allocationQuery
                                    ->selectRaw('1')
                                    ->from('vehicle_allocations')
                                    ->where('vehicle_allocations.driver_id', $driverId)
                                    ->whereColumn('vehicle_allocations.vehicle_id', 'via_verde_transactions.vehicle_id')
                                    ->whereRaw('DATE(via_verde_transactions.occurred_at) >= DATE(vehicle_allocations.starts_at)')
                                    ->where(function ($dateOverlapQuery): void {
                                        $dateOverlapQuery
                                            ->whereNull('vehicle_allocations.ends_at')
                                            ->orWhereRaw('DATE(via_verde_transactions.occurred_at) <= DATE(vehicle_allocations.ends_at)');
                                    });
                            });
                    })
                    ->orWhere(function (Builder $query) use ($driverId): void {
                        $query
                            ->whereNull('vehicle_id')
                            ->where('driver_id', $driverId)
                            ->where('assignment_status', 'ok');
                    });
            })
            ->sum('amount');

        return round((float) $total, 2);
    }

    private function sumTeslaChargingExpensesForDriver(int $driverId, Carbon $start, Carbon $end): float
    {
        $chargingStartsAt = Carbon::parse((string) config('services.tesla.settlement_charging_starts_at', '2026-09-07'))->startOfDay();

        if ($end->lt($chargingStartsAt)) {
            return 0.0;
        }

        $effectiveStart = $start->greaterThan($chargingStartsAt) ? $start : $chargingStartsAt;

        $total = TeslaChargingEvent::query()
            ->join('tesla_vehicles', 'tesla_vehicles.id', '=', 'tesla_charging_events.tesla_vehicle_id')
            ->whereNotNull('tesla_vehicles.vehicle_id')
            ->whereNotNull('tesla_charging_events.cost')
            ->whereBetween('tesla_charging_events.started_at', [$effectiveStart, $end])
            ->whereExists(function ($allocationQuery) use ($driverId): void {
                $allocationQuery
                    ->selectRaw('1')
                    ->from('vehicle_allocations')
                    ->where('vehicle_allocations.driver_id', $driverId)
                    ->whereColumn('vehicle_allocations.vehicle_id', 'tesla_vehicles.vehicle_id')
                    ->whereRaw('DATE(tesla_charging_events.started_at) >= DATE(vehicle_allocations.starts_at)')
                    ->where(function ($dateOverlapQuery): void {
                        $dateOverlapQuery
                            ->whereNull('vehicle_allocations.ends_at')
                            ->orWhereRaw('DATE(tesla_charging_events.started_at) <= DATE(vehicle_allocations.ends_at)');
                    });
            })
            ->sum('tesla_charging_events.cost');

        return round((float) $total, 2);
    }

    private function sumExtraKmChargesForDriver(int $driverId, DriverBillingProfile $profile, Carbon $start, Carbon $end): float
    {
        $limit = (float) ($profile->extra_km_limit ?? 0);
        $rate = (float) ($profile->extra_km_rate ?? 0);

        if ($limit <= 0 || $rate <= 0) {
            return 0.0;
        }

        $rows = VehicleWeeklyMileage::query()
            ->where('driver_id', $driverId)
            ->where('assignment_status', 'ok')
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_end', '<=', $end->toDateString())
            ->orderBy('vehicle_id')
            ->orderBy('period_start')
            ->get(['vehicle_id', 'period_start', 'period_end', 'weekly_km']);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($rows as $row) {
            $weeklyKm = (float) $row->weekly_km;

            $extraKm = max(0.0, $weeklyKm - $limit);
            $total += $extraKm * $rate;
        }

        return round($total, 2);
    }
}
