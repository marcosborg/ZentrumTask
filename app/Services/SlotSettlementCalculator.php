<?php

namespace App\Services;

class SlotSettlementCalculator
{
    /** @return array{created:int, skipped:int, missing_profiles:int} */
    public function calculate(string $start, string $end, ?int $driverId = null): array
    {
        abort_unless(config('slots.enabled'), 403);
        $result = ['created' => 0, 'skipped' => 0, 'missing_profiles' => 0];
        $participations = \App\Models\DriverParticipation::query()->forOperation(\App\Enums\TvdeOperation::Slot)->where('status', '!=', 'preparing')->whereDate('starts_at', '<=', $end)->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>', $start))->when($driverId, fn ($q) => $q->where('driver_id', $driverId))->get();
        foreach ($participations as $participation) {
            $status = \Illuminate\Support\Facades\DB::transaction(function () use ($participation, $start, $end): string {
                \App\Models\Driver::query()->whereKey($participation->driver_id)->lockForUpdate()->firstOrFail();
                \App\Models\DriverParticipation::query()->whereKey($participation->id)->lockForUpdate()->firstOrFail();
                if ($participation->settlements()->whereDate('period_start', $start)->whereDate('period_end', $end)->exists()) {
                    return 'skipped';
                }
                $profile = $participation->billingProfiles()->active()->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $end))->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $start))->latest('valid_from')->get();
                if ($profile->count() !== 1) {
                    return 'missing_profiles';
                }
                $profile = $profile->sole();
                $rows = \App\Models\PlatformDriverBalance::query()->where('driver_participation_id', $participation->id)->whereDate('period_start', $start)->whereDate('period_end', $end)->get();
                $expenses = 0;
                foreach (\App\Models\DriverAdjustment::query()->where('driver_participation_id', $participation->id)->whereDate('starts_at', '<=', $end)->get() as $adjustment) {
                    for ($i = 0; $i < max(1, (int) $adjustment->recurrence_weeks); $i++) {
                        $date = $adjustment->starts_at->copy()->addWeeks($i)->toDateString();
                        if ($date >= $start && $date <= $end) {
                            $expenses += (float) $adjustment->amount;
                        }
                    }
                }
                $parts = app(SlotStatementCalculator::class)->components($participation, $profile, $start, $end, (float) $rows->sum('net_amount'), (float) $rows->sum('tips_amount'), $expenses);
                $balance = $participation->balance()->firstOrCreate(['driver_id' => $participation->driver_id], ['operation' => 'slot', 'current_balance' => 0, 'is_settled' => false]);
                $carry = (float) $balance->current_balance;
                $parts['carry_over_balance'] = $carry;
                $parts['amount_due'] = round($carry + $parts['amount_payable'], 2);
                $settlement = $participation->settlements()->create([
                    'driver_id' => $participation->driver_id, 'operation' => 'slot', 'period_start' => $start, 'period_end' => $end,
                    'net_total' => $parts['net_total'], 'tips_total' => $parts['tips_total'], 'expenses_total' => $parts['expenses_total'],
                    'company_share' => 0, 'driver_share' => $parts['driver_share'], 'slot_fee' => $parts['slot_fee'],
                    'amount_payable' => $parts['amount_payable'], 'carry_over_balance' => $carry, 'amount_due' => $parts['amount_due'],
                    'rules_snapshot' => $parts, 'is_paid' => false, 'payment_due_at' => \Illuminate\Support\Carbon::parse($end)->addDay()->toDateString(),
                ]);
                $balance->update(['current_balance' => $parts['amount_due'], 'last_settlement_id' => $settlement->id, 'is_settled' => false]);
                \App\Models\DriverBalanceMovement::query()->create(['driver_id' => $participation->driver_id, 'driver_participation_id' => $participation->id, 'operation' => 'slot', 'driver_balance_id' => $balance->id, 'driver_settlement_id' => $settlement->id, 'type' => 'settlement', 'amount' => $parts['amount_payable'], 'description' => "SLOT {$start} - {$end}"]);

                return 'created';
            });
            $result[$status]++;
        }

        return $result;
    }
}
