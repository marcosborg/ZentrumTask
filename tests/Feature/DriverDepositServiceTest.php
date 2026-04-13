<?php

use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\DriverDepositDebit;
use App\Models\DriverSettlement;
use App\Services\DriverDepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates the accumulated deposit balance from initial value, caucao adjustments and debits', function () {
    $driver = Driver::factory()->create([
        'deposit_amount' => 250,
        'deposit_initial_amount' => 500,
    ]);

    DriverAdjustment::query()->create([
        'driver_id' => $driver->id,
        'starts_at' => '2026-04-06',
        'recurrence_weeks' => 2,
        'category' => 'caucao',
        'description' => 'Reforco caucao',
        'amount' => 50,
        'external_ref' => 'dep-adj-1',
        'raw_row' => ['origin' => 'test'],
        'source_file' => 'manual',
        'imported_at' => now(),
    ]);

    DriverSettlement::query()->create([
        'driver_id' => $driver->id,
        'period_start' => '2026-04-06',
        'period_end' => '2026-04-12',
        'net_total' => 0,
        'tips_total' => 0,
        'expenses_total' => 0,
        'carry_over_balance' => 0,
        'company_share' => 0,
        'driver_share' => 0,
        'amount_payable' => 0,
        'amount_due' => 0,
        'is_paid' => false,
        'rules_snapshot' => [],
    ]);

    DriverDepositDebit::query()->create([
        'driver_id' => $driver->id,
        'occurred_at' => '2026-04-10',
        'amount' => 35,
        'description' => 'Pintura dano lateral',
        'source_file' => 'manual',
    ]);

    $summary = app(DriverDepositService::class)->summaryForDriver($driver);

    expect($summary['agreed_amount'])->toBe(500.0)
        ->and($summary['paid_amount'])->toBe(250.0)
        ->and($summary['adjustments_total'])->toBe(50.0)
        ->and($summary['debits_total'])->toBe(35.0)
        ->and($summary['current_balance'])->toBe(265.0);
});

it('records deposit debits linked to a settlement and exposes them in the history', function () {
    $driver = Driver::factory()->create([
        'deposit_initial_amount' => 250,
        'deposit_amount' => 250,
    ]);

    $settlement = DriverSettlement::query()->create([
        'driver_id' => $driver->id,
        'period_start' => '2026-04-06',
        'period_end' => '2026-04-12',
        'net_total' => 0,
        'tips_total' => 0,
        'expenses_total' => 0,
        'carry_over_balance' => 0,
        'company_share' => 0,
        'driver_share' => 0,
        'amount_payable' => 0,
        'amount_due' => 0,
        'is_paid' => false,
        'rules_snapshot' => [],
    ]);

    $debit = app(DriverDepositService::class)->createDebitFromSettlement($settlement, [
        'occurred_at' => '2026-04-12',
        'amount' => 80,
        'description' => 'Reparacao pintura',
        'notes' => 'Dano porta traseira',
    ]);

    $history = app(DriverDepositService::class)->historyForDriver($driver);

    expect($debit->driver_settlement_id)->toBe($settlement->id)
        ->and($debit->amount)->toBe('80.00')
        ->and($history[0]['type'])->toBe('Debito')
        ->and($history[0]['description'])->toBe('Reparacao pintura')
        ->and($history[0]['settlement_label'])->toBe('06/04/2026 - 12/04/2026')
        ->and($history[0]['amount'])->toBe(-80.0)
        ->and($history[0]['balance_after'])->toBe(170.0);
});
