<?php

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
use App\Services\ViaVerdeCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('imports via verde xlsx files', function () {
    $driver = Driver::factory()->create();

    $vehicle = Vehicle::query()->create([
        'license_plate' => 'AA-11-BB',
        'make' => 'Test',
        'model' => 'Vehicle',
        'status' => 'available',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => Carbon::parse('2026-01-01 00:00:00'),
        'ends_at' => Carbon::parse('2026-01-31 23:59:59'),
        'status' => 'active',
    ]);

    $service = app(ViaVerdeCsvImportService::class);
    $result = $service->import(base_path('tests/Fixtures/via_verde_sample.xlsx'));

    expect($result['total'])->toBe(1)
        ->and($result['inserted'])->toBe(1)
        ->and($result['updated'])->toBe(0)
        ->and($result['invalid_rows'])->toBe(0)
        ->and($result['period_start'])->toBe('2026-01-05')
        ->and($result['period_end'])->toBe('2026-01-05');

    $transaction = ViaVerdeTransaction::query()->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->vehicle_id)->toBe($vehicle->id)
        ->and($transaction->driver_id)->toBe($driver->id)
        ->and($transaction->assignment_status)->toBe('ok')
        ->and($transaction->amount)->toBe('1.23')
        ->and($transaction->type)->toBe('toll')
        ->and($transaction->location)->toBe('A1 Norte -> Porto');
});
