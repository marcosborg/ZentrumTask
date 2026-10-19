<?php

use App\Filament\Pages\TeslaIntegration;
use App\Models\Driver;
use App\Models\TeslaVehicle;
use App\Models\TeslaVehicleSnapshot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use App\Services\TeslaService;
use App\Services\VehicleOdometerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('asks for failed TVDE readings and the IONIQ but excludes private and SLOT vehicles', function (): void {
    $driver = Driver::factory()->create(['name' => 'Condutor IONIQ']);
    $ioniq = Vehicle::factory()->create(['source' => 'tvde', 'operation' => 'rental', 'model' => 'IONIQ', 'status' => 'allocated']);
    VehicleAllocation::factory()->create(['vehicle_id' => $ioniq->id, 'driver_id' => $driver->id, 'starts_at' => now()->subMonth(), 'ends_at' => null, 'status' => 'active']);
    $failed = Vehicle::factory()->create(['source' => 'tvde', 'operation' => 'rental']);
    $success = Vehicle::factory()->create(['source' => 'tvde', 'operation' => 'rental']);
    $private = Vehicle::factory()->create(['source' => 'private']);
    $slot = Vehicle::factory()->create(['source' => 'tvde', 'operation' => 'slot', 'owner_driver_id' => $driver->id]);
    $sold = Vehicle::factory()->create(['source' => 'tvde', 'status' => 'sold']);
    $failedTesla = TeslaVehicle::factory()->create(['vehicle_id' => $failed->id]);
    $successTesla = TeslaVehicle::factory()->create(['vehicle_id' => $success->id]);
    TeslaVehicle::factory()->create(['vehicle_id' => $private->id]);
    TeslaVehicle::factory()->create(['vehicle_id' => $slot->id]);
    TeslaVehicle::factory()->create(['vehicle_id' => $sold->id]);
    $this->mock(TeslaService::class, function ($mock) use ($failedTesla, $successTesla): void {
        $mock->shouldReceive('createManualOdometerSnapshot')->once()->withArgs(fn ($vehicle): bool => $vehicle->id === $failedTesla->id)->andThrow(new RuntimeException('Offline'));
        $mock->shouldReceive('createManualOdometerSnapshot')->once()->withArgs(fn ($vehicle): bool => $vehicle->id === $successTesla->id)->andReturn(new TeslaVehicleSnapshot(['odometer' => 123000]));
    });

    Livewire::actingAs(User::factory()->create())->test(TeslaIntegration::class)
        ->callAction('createFleetOdometerSnapshots', data: ['period_start' => '2026-10-12', 'period_end' => '2026-10-18'])
        ->assertActionMounted('enterManualOdometers')
        ->assertSet('pendingOdometerVehicleIds', fn (array $ids): bool => count($ids) === 2 && in_array($ioniq->id, $ids) && in_array($failed->id, $ids))
        ->setActionData(['odometers' => [$ioniq->id => 120000, $failed->id => 140000]])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Leituras manuais guardadas');

    expect(VehicleWeeklyMileage::query()->count())->toBe(2)
        ->and(VehicleWeeklyMileage::query()->where('vehicle_id', $ioniq->id)->firstOrFail()->driver_id)->toBe($driver->id);
});

it('stores an initial baseline and calculates only against the immediately preceding week', function (): void {
    $vehicle = Vehicle::factory()->create();
    $service = app(VehicleOdometerService::class);
    $first = $service->record($vehicle, 100000, '2026-10-05', '2026-10-11');
    $next = $service->record($vehicle, 102300, '2026-10-12', '2026-10-18');
    $gap = $service->record($vehicle, 109000, '2026-10-26', '2026-11-01');

    expect((float) $first->weekly_km)->toBe(0.0)
        ->and($first->raw_row['has_previous_week_reading'])->toBeFalse()
        ->and((float) $next->weekly_km)->toBe(2300.0)
        ->and($next->raw_row['has_previous_week_reading'])->toBeTrue()
        ->and((float) $gap->weekly_km)->toBe(0.0);

    $service->record($vehicle, 102400, '2026-10-12', '2026-10-18');
    expect(VehicleWeeklyMileage::query()->count())->toBe(3)
        ->and((float) $next->refresh()->weekly_km)->toBe(2400.0);
});

it('rejects a decreasing odometer instead of charging an invalid difference', function (): void {
    $vehicle = Vehicle::factory()->create();
    $service = app(VehicleOdometerService::class);
    $service->record($vehicle, 100000, '2026-10-05', '2026-10-11');

    expect(fn () => $service->record($vehicle, 99999, '2026-10-12', '2026-10-18'))->toThrow(ValidationException::class);
});

it('uses a legacy Tesla baseline from the previous week but not an older snapshot', function (): void {
    $vehicle = Vehicle::factory()->create();
    $tesla = TeslaVehicle::factory()->create(['vehicle_id' => $vehicle->id]);
    TeslaVehicleSnapshot::query()->create(['tesla_vehicle_id' => $tesla->id, 'is_manual' => true, 'recorded_at' => '2026-10-12 10:00:00', 'odometer' => 100000, 'raw_payload' => []]);
    $service = app(VehicleOdometerService::class);
    $reading = $service->record($vehicle, 102200, '2026-10-12', '2026-10-18');
    $gap = $service->record($vehicle, 109000, '2026-10-26', '2026-11-01');

    expect((float) $reading->weekly_km)->toBe(2200.0)
        ->and((float) $gap->weekly_km)->toBe(0.0);
});

it('opens the manual popup only when a TVDE reading is missing', function (?float $odometer, bool $expectsPopup): void {
    $vehicle = Vehicle::factory()->create(['source' => 'tvde', 'operation' => 'rental']);
    TeslaVehicle::factory()->create(['vehicle_id' => $vehicle->id]);
    $this->mock(TeslaService::class, function ($mock) use ($odometer): void {
        $mock->shouldReceive('createManualOdometerSnapshot')->once()->andReturn(new TeslaVehicleSnapshot(['odometer' => $odometer]));
    });
    $page = Livewire::actingAs(User::factory()->create())->test(TeslaIntegration::class)
        ->callAction('createFleetOdometerSnapshots', data: ['period_start' => '2026-10-12', 'period_end' => '2026-10-18']);

    if ($expectsPopup) {
        $page->assertActionMounted('enterManualOdometers');
    } else {
        $page->assertSet('mountedActions', []);
    }
})->with([[null, true], [123000.0, false]]);

it('validates manual odometers and shows the vehicle and driver in the input label', function (): void {
    $vehicle = Vehicle::factory()->create(['source' => 'tvde', 'operation' => 'rental', 'model' => 'IONIQ']);
    $driver = Driver::factory()->create(['name' => 'Condutor de teste']);
    VehicleAllocation::factory()->create(['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'starts_at' => now()->subMonth(), 'ends_at' => null, 'status' => 'active']);
    app(VehicleOdometerService::class)->record($vehicle, 100000, '2026-10-05', '2026-10-11');
    $page = Livewire::actingAs(User::factory()->create())->test(TeslaIntegration::class)
        ->callAction('createFleetOdometerSnapshots', data: ['period_start' => '2026-10-12', 'period_end' => '2026-10-18'])
        ->assertActionMounted('enterManualOdometers');
    $fields = $page->instance()->getSchema('mountedActionSchema0')->getComponents();
    expect($fields[0]->getLabel())->toBe($vehicle->license_plate.' — Condutor de teste');
    $page->setActionData(['odometers' => [$vehicle->id => -1]])
        ->callMountedAction()->assertHasActionErrors(['odometers.'.$vehicle->id => 'min'])
        ->setActionData(['odometers' => [$vehicle->id => 99999]])
        ->callMountedAction()->assertHasActionErrors(['odometers.'.$vehicle->id]);

    expect(VehicleWeeklyMileage::query()->count())->toBe(1);
});
