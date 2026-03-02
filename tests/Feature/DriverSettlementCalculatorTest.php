<?php

use App\Enums\VatRefundMode;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PrioTransaction;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use App\Models\ViaVerdeTransaction;
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

it('applies vat after carry over when driver delivers vat', function () {
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
        'driver_code' => 'driver-test-4',
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
        ->and($settlement->amount_payable)->toBe('787.20')
        ->and($settlement->carry_over_balance)->toBe('200.00')
        ->and($settlement->amount_due)->toBe('1033.20');
});

it('calculates prio and via verde by allocation day window excluding only paused day', function () {
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'AA-11-BB',
        'make' => 'Toyota',
        'model' => 'Corolla',
        'status' => 'available',
    ]);

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

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => '2026-02-20 00:00:00',
        'ends_at' => '2026-02-23 00:00:00',
        'status' => 'ended',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => '2026-02-25 00:00:00',
        'ends_at' => null,
        'status' => 'active',
    ]);

    DB::table('platform_driver_balances')->insert([
        'platform' => 'bolt',
        'driver_code' => 'driver-test-3',
        'driver_id' => $driver->id,
        'period_start' => '2026-02-23',
        'period_end' => '2026-03-01',
        'net_amount' => 100.00,
        'tips_amount' => 0.00,
        'source_file' => 'test.csv',
        'imported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    PrioTransaction::query()->create([
        'occurred_at' => '2026-02-23 12:00:00',
        'card_code' => 'CARD-1',
        'id_usage' => 'U-1',
        'net_amount' => 10.00,
        'assignment_status' => 'unassigned_driver',
        'vehicle_id' => $vehicle->id,
        'driver_id' => null,
        'imported_at' => now(),
    ]);

    PrioTransaction::query()->create([
        'occurred_at' => '2026-02-24 12:00:00',
        'card_code' => 'CARD-2',
        'id_usage' => 'U-2',
        'net_amount' => 20.00,
        'assignment_status' => 'unassigned_driver',
        'vehicle_id' => $vehicle->id,
        'driver_id' => null,
        'imported_at' => now(),
    ]);

    PrioTransaction::query()->create([
        'occurred_at' => '2026-02-25 12:00:00',
        'card_code' => 'CARD-3',
        'id_usage' => 'U-3',
        'net_amount' => 30.00,
        'assignment_status' => 'unassigned_driver',
        'vehicle_id' => $vehicle->id,
        'driver_id' => null,
        'imported_at' => now(),
    ]);

    ViaVerdeTransaction::query()->create([
        'occurred_at' => '2026-02-23 12:00:00',
        'amount' => 1.00,
        'external_ref' => 'V-1',
        'assignment_status' => 'unassigned_driver',
        'vehicle_id' => $vehicle->id,
        'driver_id' => null,
        'imported_at' => now(),
    ]);

    ViaVerdeTransaction::query()->create([
        'occurred_at' => '2026-02-24 12:00:00',
        'amount' => 2.00,
        'external_ref' => 'V-2',
        'assignment_status' => 'unassigned_driver',
        'vehicle_id' => $vehicle->id,
        'driver_id' => null,
        'imported_at' => now(),
    ]);

    ViaVerdeTransaction::query()->create([
        'occurred_at' => '2026-02-25 12:00:00',
        'amount' => 3.00,
        'external_ref' => 'V-3',
        'assignment_status' => 'unassigned_driver',
        'vehicle_id' => $vehicle->id,
        'driver_id' => null,
        'imported_at' => now(),
    ]);

    $result = app(DriverSettlementCalculator::class)->calculate('2026-02-23', '2026-03-01', $driver->id);

    $settlement = DriverSettlement::query()
        ->where('driver_id', $driver->id)
        ->whereDate('period_start', '2026-02-23')
        ->whereDate('period_end', '2026-03-01')
        ->firstOrFail();

    expect($result['created'])->toBe(1)
        ->and($settlement->expenses_total)->toBe('44.00')
        ->and($settlement->amount_payable)->toBe('16.00')
        ->and($settlement->amount_due)->toBe('16.00');
});

it('adds extra km charges based on billing profile limit and rate', function () {
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'ZZ-22-YY',
        'make' => 'Renault',
        'model' => 'Megane',
        'status' => 'available',
    ]);

    DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
        'vat_refund_mode' => VatRefundMode::None,
        'extra_km_limit' => 2000,
        'extra_km_rate' => 0.12,
    ]);

    DB::table('platform_driver_balances')->insert([
        'platform' => 'uber',
        'driver_code' => 'driver-test-5',
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

    VehicleWeeklyMileage::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'period_start' => '2026-01-26',
        'period_end' => '2026-02-01',
        'weekly_km' => 0,
        'assignment_status' => 'ok',
        'source_file' => 'km-week.csv',
        'imported_at' => now(),
    ]);

    VehicleWeeklyMileage::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'period_start' => '2026-02-02',
        'period_end' => '2026-02-08',
        'weekly_km' => 2250,
        'assignment_status' => 'ok',
        'source_file' => 'km-week.csv',
        'imported_at' => now(),
    ]);

    $result = app(DriverSettlementCalculator::class)->calculate('2026-02-02', '2026-02-08', $driver->id);

    $settlement = DriverSettlement::query()
        ->where('driver_id', $driver->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    expect($result['created'])->toBe(1)
        ->and($settlement->expenses_total)->toBe('30.00')
        ->and($settlement->amount_payable)->toBe('610.00')
        ->and($settlement->amount_due)->toBe('610.00');
});
