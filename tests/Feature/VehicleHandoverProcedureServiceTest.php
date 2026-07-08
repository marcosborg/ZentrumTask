<?php

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('records a delivery handover even when allocation state is conflicting', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $handoverVehicle = Vehicle::query()->create([
        'license_plate' => 'CA-20-AG',
        'make' => 'Hyundai',
        'model' => 'Ioniq',
        'status' => 'allocated',
    ]);
    $otherVehicle = Vehicle::query()->create([
        'license_plate' => 'BV-10-BS',
        'make' => 'Tesla',
        'model' => 'Model 3',
        'status' => 'allocated',
    ]);

    VehicleAllocation::factory()->create([
        'vehicle_id' => $otherVehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'status' => 'active',
    ]);

    $procedure = app(VehicleHandoverProcedureService::class)->create([
        'type' => 'delivery',
        'performed_at' => '2026-07-08 05:57:00',
        'vehicle_id' => $handoverVehicle->id,
        'driver_id' => $driver->id,
        'notes' => 'Aciedente.',
        'operator_signature_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
        'driver_signature_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
        'checklist_payload' => [],
        'guided_photo_items' => [],
        'video_items' => [],
        'damage_items' => [],
        'general_photos' => [],
    ], $operator);

    expect($procedure)->toBeInstanceOf(VehicleHandoverProcedure::class)
        ->and($procedure->created_allocation_id)->toBeNull();

    $this->assertDatabaseHas(VehicleHandoverProcedure::class, [
        'id' => $procedure->id,
        'type' => 'delivery',
        'vehicle_id' => $handoverVehicle->id,
        'driver_id' => $driver->id,
        'notes' => 'Aciedente.',
    ]);

    expect(VehicleAllocation::query()->active()->where('driver_id', $driver->id)->count())->toBe(1);
});
