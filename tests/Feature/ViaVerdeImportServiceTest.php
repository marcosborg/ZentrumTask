<?php

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
use App\Services\ViaVerdeCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

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

it('assigns driver by row plate when csv contains multiple plates', function () {
    $driverOne = Driver::factory()->create();
    $driverTwo = Driver::factory()->create();

    $vehicleOne = Vehicle::query()->create([
        'license_plate' => 'AA-11-BB',
        'make' => 'Test',
        'model' => 'Vehicle',
        'status' => 'available',
    ]);

    $vehicleTwo = Vehicle::query()->create([
        'license_plate' => 'CC-22-DD',
        'make' => 'Test',
        'model' => 'Vehicle',
        'status' => 'available',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicleOne->id,
        'driver_id' => $driverOne->id,
        'starts_at' => Carbon::parse('2026-02-01 00:00:00'),
        'ends_at' => Carbon::parse('2026-02-28 23:59:59'),
        'status' => 'active',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicleTwo->id,
        'driver_id' => $driverTwo->id,
        'starts_at' => Carbon::parse('2026-02-01 00:00:00'),
        'ends_at' => Carbon::parse('2026-02-28 23:59:59'),
        'status' => 'active',
    ]);

    $csvPath = storage_path('framework/testing/via-verde-'.Str::random(8).'.csv');

    file_put_contents($csvPath, implode("\n", [
        'Entry Date;Entry Point;Exit Point;Service Description;Liquid Value;License Plate',
        '2026-02-16 08:00:00;A1 Norte;Porto;Autoestradas;2,50;AA-11-BB',
        '2026-02-16 09:00:00;A2 Sul;Setubal;Autoestradas;3,75;CC-22-DD',
    ]));

    $result = app(ViaVerdeCsvImportService::class)->import($csvPath);

    expect($result['inserted'])->toBe(2)
        ->and($result['updated'])->toBe(0)
        ->and($result['invalid_rows'])->toBe(0)
        ->and($result['unassigned_driver'])->toBe(0);

    $rows = ViaVerdeTransaction::query()
        ->orderBy('occurred_at')
        ->get(['vehicle_plate', 'driver_id', 'assignment_status', 'amount']);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->vehicle_plate)->toBe('AA-11-BB')
        ->and($rows[0]->driver_id)->toBe($driverOne->id)
        ->and($rows[0]->assignment_status)->toBe('ok')
        ->and($rows[1]->vehicle_plate)->toBe('CC-22-DD')
        ->and($rows[1]->driver_id)->toBe($driverTwo->id)
        ->and($rows[1]->assignment_status)->toBe('ok');
});

it('imports via verde csv with separate date and time columns including seconds', function () {
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
        'starts_at' => Carbon::parse('2026-03-01 00:00:00'),
        'ends_at' => Carbon::parse('2026-03-31 23:59:59'),
        'status' => 'active',
    ]);

    $csvPath = storage_path('framework/testing/via-verde-'.Str::random(8).'.csv');

    file_put_contents($csvPath, implode("\n", [
        'Data;Hora;Entrada;Saida;Valor;Matricula',
        '16/03/2026;10:30:47;Pontinha;Belas PV;1,00;AA-11-BB',
    ]));

    $result = app(ViaVerdeCsvImportService::class)->import($csvPath);
    $transaction = ViaVerdeTransaction::query()->firstOrFail();

    expect($result['inserted'])->toBe(1)
        ->and($transaction->occurred_at?->format('Y-m-d H:i:s'))->toBe('2026-03-16 10:30:47')
        ->and($transaction->driver_id)->toBe($driver->id)
        ->and($transaction->assignment_status)->toBe('ok');
});

it('repairs via verde occurred_at and assignment from raw row', function () {
    $wrongDriver = Driver::factory()->create();
    $rightDriver = Driver::factory()->create();

    $vehicle = Vehicle::query()->create([
        'license_plate' => 'AA-11-BB',
        'make' => 'Test',
        'model' => 'Vehicle',
        'status' => 'available',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $wrongDriver->id,
        'starts_at' => Carbon::parse('2026-03-16 00:00:00'),
        'ends_at' => Carbon::parse('2026-03-16 23:59:59'),
        'status' => 'ended',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $rightDriver->id,
        'starts_at' => Carbon::parse('2026-03-05 00:00:00'),
        'ends_at' => Carbon::parse('2026-03-05 23:59:59'),
        'status' => 'ended',
    ]);

    $transaction = ViaVerdeTransaction::query()->create([
        'occurred_at' => '2026-03-16 10:02:49',
        'vehicle_plate' => 'AA-11-BB',
        'location' => 'PQ Aeroporto P2 -> PQ Aeroporto P2',
        'type' => 'parking',
        'amount' => 1.30,
        'external_ref' => sha1('bad-ref'),
        'vehicle_id' => $vehicle->id,
        'driver_id' => $wrongDriver->id,
        'assignment_status' => 'ok',
        'raw_row' => [
            'Entry Date' => '2026-03-05 11:20:52',
            'Entry Point' => 'PQ Aeroporto P2',
            'Exit Point' => 'PQ Aeroporto P2',
            'Liquid Value' => '1,3',
            'License Plate' => 'AA-11-BB',
        ],
        'imported_at' => now(),
        'source_file' => 'via-verde.csv',
    ]);

    Artisan::call('via-verde:repair-dates', [
        '--source-file' => 'via-verde.csv',
    ]);

    $transaction->refresh();

    expect($transaction->occurred_at?->format('Y-m-d H:i:s'))->toBe('2026-03-05 11:20:52')
        ->and($transaction->driver_id)->toBe($rightDriver->id)
        ->and($transaction->assignment_status)->toBe('ok')
        ->and($transaction->external_ref)->toBe(
            sha1(Carbon::parse('2026-03-05 11:20:52')->toIso8601String().'|PQ Aeroporto P2 -> PQ Aeroporto P2|1.3|parking')
        );
});
