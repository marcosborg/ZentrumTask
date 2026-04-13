<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\DriverDepositDebit;
use App\Models\DriverSettlement;
use Illuminate\Support\Carbon;

class DriverDepositService
{
    /**
     * @return array{
     *     agreed_amount: float,
     *     paid_amount: float,
     *     adjustments_total: float,
     *     debits_total: float,
     *     current_balance: float,
     *     payment_method: string|null
     * }
     */
    public function summaryForDriver(Driver|int $driver): array
    {
        $driver = $driver instanceof Driver ? $driver : Driver::query()->findOrFail($driver);

        $agreedAmount = round((float) ($driver->deposit_initial_amount ?? 0), 2);
        $paidAmount = round((float) ($driver->deposit_amount ?? 0), 2);
        $adjustmentsTotal = round((float) collect($this->chargedDepositAdjustmentsForDriver($driver))
            ->sum('amount'), 2);
        $debitsTotal = round((float) DriverDepositDebit::query()
            ->where('driver_id', $driver->id)
            ->sum('amount'), 2);

        return [
            'agreed_amount' => $agreedAmount,
            'paid_amount' => $paidAmount,
            'adjustments_total' => $adjustmentsTotal,
            'debits_total' => $debitsTotal,
            'current_balance' => round($paidAmount + $adjustmentsTotal - $debitsTotal, 2),
            'payment_method' => $driver->deposit_payment_method,
        ];
    }

    /**
     * @return array<int, array{
     *     occurred_at: Carbon|null,
     *     type: string,
     *     description: string,
     *     amount: float,
     *     balance_after: float,
     *     settlement_label: string|null,
     *     notes: string|null,
     *     source_file: string|null
     * }>
     */
    public function historyForDriver(Driver|int $driver): array
    {
        $driver = $driver instanceof Driver ? $driver : Driver::query()->findOrFail($driver);
        $entries = collect();

        $paidAmount = round((float) ($driver->deposit_amount ?? 0), 2);

        if ($paidAmount !== 0.0) {
            $entries->push([
                'occurred_at' => $driver->deposit_paid_at ? Carbon::parse($driver->deposit_paid_at) : null,
                'type' => 'Pago inicial',
                'description' => 'Valor pago no ato inicial',
                'amount' => $paidAmount,
                'settlement_label' => null,
                'notes' => null,
                'source_file' => null,
            ]);
        }

        collect($this->chargedDepositAdjustmentsForDriver($driver))
            ->each(function (array $row) use ($entries): void {
                $entries->push($row);
            });

        DriverDepositDebit::query()
            ->with('settlement')
            ->where('driver_id', $driver->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'driver_id', 'driver_settlement_id', 'occurred_at', 'amount', 'description', 'notes', 'source_file'])
            ->each(function (DriverDepositDebit $debit) use ($entries): void {
                $settlement = $debit->settlement;

                $entries->push([
                    'occurred_at' => $debit->occurred_at ? Carbon::parse($debit->occurred_at) : null,
                    'type' => 'Debito',
                    'description' => $debit->description,
                    'amount' => -1 * round((float) $debit->amount, 2),
                    'settlement_label' => $settlement
                        ? (($settlement->period_start?->format('d/m/Y') ?? '-').' - '.($settlement->period_end?->format('d/m/Y') ?? '-'))
                        : null,
                    'notes' => $debit->notes,
                    'source_file' => $debit->source_file,
                ]);
            });

        $runningBalance = 0.0;

        return $entries
            ->sortBy([
                fn (array $entry) => $entry['occurred_at']?->timestamp ?? 0,
                fn (array $entry) => $entry['type'],
            ])
            ->values()
            ->map(function (array $entry) use (&$runningBalance): array {
                $runningBalance = round($runningBalance + (float) $entry['amount'], 2);
                $entry['balance_after'] = $runningBalance;

                return $entry;
            })
            ->reverse()
            ->values()
            ->all();
    }

    public function createDebitFromSettlement(DriverSettlement $settlement, array $data): DriverDepositDebit
    {
        return DriverDepositDebit::query()->create([
            'driver_id' => $settlement->driver_id,
            'driver_settlement_id' => $settlement->id,
            'created_by_user_id' => auth()->id(),
            'occurred_at' => Carbon::parse((string) $data['occurred_at'])->toDateString(),
            'amount' => round((float) $data['amount'], 2),
            'description' => trim((string) $data['description']),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'source_file' => 'manual',
        ]);
    }

    public function createDebitForDriver(Driver|int $driver, array $data): DriverDepositDebit
    {
        $driver = $driver instanceof Driver ? $driver : Driver::query()->findOrFail($driver);

        return DriverDepositDebit::query()->create([
            'driver_id' => $driver->id,
            'driver_settlement_id' => null,
            'created_by_user_id' => auth()->id(),
            'occurred_at' => Carbon::parse((string) $data['occurred_at'])->toDateString(),
            'amount' => round((float) $data['amount'], 2),
            'description' => trim((string) $data['description']),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'source_file' => 'manual',
        ]);
    }

    /**
     * @return array<int, array{
     *     occurred_at: Carbon|null,
     *     type: string,
     *     description: string,
     *     amount: float,
     *     settlement_label: string|null,
     *     notes: string|null,
     *     source_file: string|null
     * }>
     */
    private function chargedDepositAdjustmentsForDriver(Driver $driver): array
    {
        $adjustments = DriverAdjustment::query()
            ->where('driver_id', $driver->id)
            ->where('category', 'caucao')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get(['id', 'starts_at', 'recurrence_weeks', 'description', 'amount', 'source_file']);

        if ($adjustments->isEmpty()) {
            return [];
        }

        $settlements = DriverSettlement::query()
            ->where('driver_id', $driver->id)
            ->orderBy('period_start')
            ->orderBy('id')
            ->get(['period_start', 'period_end']);

        if ($settlements->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($adjustments as $adjustment) {
            $startsAt = Carbon::parse($adjustment->starts_at)->startOfDay();
            $weeks = max(1, (int) ($adjustment->recurrence_weeks ?? 1));

            for ($i = 0; $i < $weeks; $i++) {
                $occurrence = $startsAt->copy()->addWeeks($i);

                $settlement = $settlements->first(function (DriverSettlement $settlement) use ($occurrence): bool {
                    $periodStart = Carbon::parse($settlement->period_start)->startOfDay();
                    $periodEnd = Carbon::parse($settlement->period_end)->endOfDay();

                    return $occurrence->betweenIncluded($periodStart, $periodEnd);
                });

                if (! $settlement) {
                    continue;
                }

                $rows[] = [
                    'occurred_at' => $occurrence,
                    'type' => 'Ajuste caucao',
                    'description' => (string) ($adjustment->description ?: 'Cobranca de caucao'),
                    'amount' => round((float) $adjustment->amount, 2),
                    'settlement_label' => ($settlement->period_start?->format('d/m/Y') ?? '-').' - '.($settlement->period_end?->format('d/m/Y') ?? '-'),
                    'notes' => null,
                    'source_file' => $adjustment->source_file,
                ];
            }
        }

        return $rows;
    }
}
