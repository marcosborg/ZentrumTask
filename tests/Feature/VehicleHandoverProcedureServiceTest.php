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
        'fault_items' => [[
            'type' => 'electrical',
            'severity' => 'high',
            'description' => 'Aviso eletrico no painel.',
        ]],
        'general_photos' => [],
    ], $operator);

    expect($procedure)->toBeInstanceOf(VehicleHandoverProcedure::class)
        ->and($procedure->created_allocation_id)->toBeNull()
        ->and($procedure->fault_items)->toHaveCount(1)
        ->and($procedure->fault_items[0]['type'])->toBe('electrical');

    $this->assertDatabaseHas(VehicleHandoverProcedure::class, [
        'id' => $procedure->id,
        'type' => 'delivery',
        'vehicle_id' => $handoverVehicle->id,
        'driver_id' => $driver->id,
        'notes' => 'Aciedente.',
    ]);

    expect(VehicleAllocation::query()->active()->where('driver_id', $driver->id)->count())->toBe(1);
});

it('generates a workshop repair pdf when damages are registered', function () {
    Storage::fake('public');

    Storage::disk('public')->put(
        'vehicle-handovers/damage-photos/vidro.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
    );

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'CA-20-AG',
        'make' => 'Hyundai',
        'model' => 'Ioniq',
        'status' => 'allocated',
    ]);

    $procedure = VehicleHandoverProcedure::query()->create([
        'type' => 'return',
        'status' => 'completed',
        'performed_at' => now(),
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'operator_user_id' => $operator->id,
        'vehicle_snapshot' => [
            'license_plate' => 'CA-20-AG',
            'make' => 'Hyundai',
            'model' => 'Ioniq',
        ],
        'driver_snapshot' => [
            'name' => $driver->name,
            'phone' => $driver->phone,
        ],
        'checklist_payload' => [],
        'damage_items' => [[
            'type' => 'partido',
            'zone' => 'frente',
            'description' => 'Vidro quebrado',
            'photo_path' => 'vehicle-handovers/damage-photos/vidro.png',
        ]],
        'fault_items' => [[
            'type' => 'mechanical',
            'severity' => 'immobilized',
            'description' => 'Motor nao arranca.',
        ]],
        'general_photo_paths' => [],
        'guided_photo_items' => [],
        'video_items' => [[
            'label' => 'Video exterior',
            'url' => 'https://zentrum-tvde.com/storage/vehicle-handovers/videos/exterior.mp4',
            'qr_path' => null,
        ]],
        'operator_signature_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
        'driver_signature_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
    ]);

    $output = app(VehicleHandoverProcedureService::class)
        ->generateWorkshopRepairPdf($procedure)
        ->output();

    expect($output)->toStartWith('%PDF')
        ->and(strlen($output))->toBeGreaterThan(1000);
});
