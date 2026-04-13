<?php

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use App\Services\VehicleWeeklyMileageImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports headerless weekly mileage csv files and assigns the driver for the period', function () {
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'BT-24-AZ',
        'make' => 'Toyota',
        'model' => 'Corolla',
        'status' => 'available',
    ]);

    VehicleAllocation::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => '2026-04-01 00:00:00',
        'ends_at' => null,
        'status' => 'active',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'weekly-km-');
    file_put_contents($path, "BT-24-AZ;1405\n");

    $result = app(VehicleWeeklyMileageImportService::class)->import($path, '2026-04-06', '2026-04-12');

    $row = VehicleWeeklyMileage::query()->firstOrFail();

    expect($result['total'])->toBe(1)
        ->and($result['inserted'])->toBe(1)
        ->and($result['updated'])->toBe(0)
        ->and($result['invalid_rows'])->toBe(0)
        ->and($result['missing_plates'])->toBe([])
        ->and($result['unassigned_driver'])->toBe(0)
        ->and($row->vehicle_id)->toBe($vehicle->id)
        ->and($row->driver_id)->toBe($driver->id)
        ->and($row->assignment_status)->toBe('ok')
        ->and((float) $row->weekly_km)->toBe(1405.0)
        ->and($row->raw_row)->toMatchArray([
            'MATRICULA' => 'BT-24-AZ',
            'KM_TOTAL' => '1405',
        ]);

    @unlink($path);
});
