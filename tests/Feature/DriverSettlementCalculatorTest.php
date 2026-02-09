<?php

use App\Enums\VatRefundMode;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Services\DriverSettlementCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('calculates settlement with tips excluded from percentage and added at the end', function () {
    $driver = Driver::factory()->create();

    DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
        'vat_refund_mode' => VatRefundMode::DriverDeliversVat,
    ]);

    DB::table('platform_driver_balances')->insert([
        'platform' => 'uber',
        'driver_code' => 'driver-test-1',
        'driver_id' => $driver->id,
        'period_start' => '2026-02-02',
        'period_end' => '2026-02-08',
        'net_amount' => 1000.00,
        'tips_amount' => 100.00,
        'source_file' => 'test.csv',
        'imported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('driver_adjustments')->insert([
        'driver_id' => $driver->id,
        'starts_at' => '2026-02-03',
        'recurrence_weeks' => null,
        'category' => 'acerto',
        'description' => 'Teste ajuste',
        'amount' => 50.00,
        'external_ref' => 'adj-test-1',
        'raw_row' => json_encode(['origin' => 'test'], JSON_THROW_ON_ERROR),
        'source_file' => 'manual',
        'imported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(DriverSettlementCalculator::class)->calculate('2026-02-02', '2026-02-08', $driver->id);
    $settlement = DriverSettlement::query()->where('driver_id', $driver->id)->firstOrFail();

    expect($result['created'])->toBe(1)
        ->and($settlement->company_share)->toBe('360.00')
        ->and($settlement->driver_share)->toBe('540.00')
        ->and($settlement->expenses_total)->toBe('50.00')
        ->and($settlement->amount_payable)->toBe('725.70')
        ->and($settlement->amount_due)->toBe('725.70');
});

it('applies carry over before computing amount due', function () {
    $driver = Driver::factory()->create();

    DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
        'vat_refund_mode' => VatRefundMode::None,
    ]);

    DriverSettlement::query()->create([
        'driver_id' => $driver->id,
        'period_start' => '2026-01-26',
        'period_end' => '2026-02-01',
        'net_total' => 0,
        'tips_total' => 0,
        'expenses_total' => 0,
        'carry_over_balance' => 0,
        'company_share' => 0,
        'driver_share' => 0,
        'amount_payable' => 200,
        'amount_due' => 200,
        'is_paid' => false,
        'rules_snapshot' => [],
    ]);

    DB::table('platform_driver_balances')->insert([
        'platform' => 'bolt',
        'driver_code' => 'driver-test-2',
        'driver_id' => $driver->id,
        'period_start' => '2026-02-02',
        'period_end' => '2026-02-08',
        'net_amount' => 1000.00,
        'tips_amount' => 100.00,
        'source_file' => 'test.csv',
        'imported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(DriverSettlementCalculator::class)->calculate('2026-02-02', '2026-02-08', $driver->id);

    $settlement = DriverSettlement::query()
        ->where('driver_id', $driver->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    expect($result['created'])->toBe(1)
        ->and($settlement->driver_share)->toBe('540.00')
        ->and($settlement->amount_payable)->toBe('640.00')
        ->and($settlement->carry_over_balance)->toBe('200.00')
        ->and($settlement->amount_due)->toBe('840.00');
});
