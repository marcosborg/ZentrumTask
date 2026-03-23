<?php

use App\Filament\Pages\DriverSettlementsReport;
use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('deletes a manual adjustment directly from the settlements report modal', function () {
    $driver = Driver::factory()->create();

    $adjustment = DriverAdjustment::query()->create([
        'driver_id' => $driver->id,
        'starts_at' => '2026-03-02',
        'recurrence_weeks' => null,
        'category' => 'acerto',
        'description' => 'Ajuste para eliminar',
        'amount' => 42.50,
        'external_ref' => 'manual-test-delete',
        'raw_row' => ['origin' => 'test'],
        'source_file' => 'manual',
        'imported_at' => now(),
    ]);

    $page = new DriverSettlementsReport;
    $page->deleteManualAdjustment($driver->id, $adjustment->id);

    expect(DriverAdjustment::query()->find($adjustment->id))->toBeNull();
});

it('recalculates only one driver settlement without changing the others', function () {
    $driverOne = Driver::factory()->create();
    $driverTwo = Driver::factory()->create();

    DriverBillingProfile::factory()->create([
        'driver_id' => $driverOne->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
    ]);

    DriverBillingProfile::factory()->create([
        'driver_id' => $driverTwo->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
    ]);

    DB::table('platform_driver_balances')->insert([
        [
            'platform' => 'bolt',
            'driver_code' => 'driver-one',
            'driver_id' => $driverOne->id,
            'period_start' => '2026-02-02',
            'period_end' => '2026-02-08',
            'net_amount' => 100.00,
            'tips_amount' => 0.00,
            'source_file' => 'test.csv',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'platform' => 'bolt',
            'driver_code' => 'driver-two',
            'driver_id' => $driverTwo->id,
            'period_start' => '2026-02-02',
            'period_end' => '2026-02-08',
            'net_amount' => 200.00,
            'tips_amount' => 0.00,
            'source_file' => 'test.csv',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    app(\App\Services\DriverSettlementCalculator::class)->calculate('2026-02-02', '2026-02-08');

    $driverOneSettlement = DriverSettlement::query()
        ->where('driver_id', $driverOne->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    $driverTwoSettlement = DriverSettlement::query()
        ->where('driver_id', $driverTwo->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    expect($driverOneSettlement->amount_due)->toBe('60.00')
        ->and($driverTwoSettlement->amount_due)->toBe('120.00');

    DB::table('platform_driver_balances')
        ->where('driver_id', $driverOne->id)
        ->update([
            'net_amount' => 300.00,
            'updated_at' => now(),
        ]);

    $page = new DriverSettlementsReport;
    $result = $page->recalculateSettlement($driverOneSettlement);

    $recalculatedDriverOneSettlement = DriverSettlement::query()
        ->where('driver_id', $driverOne->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    $unchangedDriverTwoSettlement = DriverSettlement::query()
        ->whereKey($driverTwoSettlement->id)
        ->firstOrFail();

    expect($result)->toMatchArray([
        'deleted' => 1,
        'created' => 1,
        'skipped' => 0,
        'missing_profiles' => 0,
    ])
        ->and($recalculatedDriverOneSettlement->id)->not->toBe($driverOneSettlement->id)
        ->and($recalculatedDriverOneSettlement->amount_due)->toBe('180.00')
        ->and($unchangedDriverTwoSettlement->id)->toBe($driverTwoSettlement->id)
        ->and($unchangedDriverTwoSettlement->amount_due)->toBe('120.00');
});
