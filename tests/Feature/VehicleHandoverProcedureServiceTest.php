<?php

use App\Mail\VehicleHandoverProceduresMail;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('uploads wheel photos and saves replaces and removes the general video through the draft api', function () {
    Storage::fake('public');
    $operator = User::factory()->create();
    $vehicle = Vehicle::factory()->create(['status' => 'available']);
    $driver = Driver::factory()->create();
    $draft = app(VehicleHandoverProcedureService::class)->createDraft([
        'type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
    ], $operator);
    $token = 'handover-extra-media-token';
    Cache::put('app_auth_token:'.$token, $operator->id, now()->addMinute());
    $endpoint = "/app/ops/vehicle-handovers/{$draft->id}/media";

    foreach (['wheel_front_left', 'wheel_front_right', 'wheel_rear_left', 'wheel_rear_right'] as $key) {
        $this->withToken($token)->post($endpoint, [
            'kind' => 'photo', 'key' => $key, 'media' => UploadedFile::fake()->image($key.'.jpg'),
        ])->assertSuccessful();
        Storage::disk('public')->assertExists($draft->refresh()->guided_photo_items[$key]['photo_path']);
    }

    foreach (['exterior', 'general', 'general'] as $key) {
        $this->withToken($token)->post($endpoint, [
            'kind' => 'video', 'key' => $key, 'media' => UploadedFile::fake()->create($key.'.mp4', 100, 'video/mp4'),
        ])->assertSuccessful();
        Storage::disk('public')->assertExists($draft->refresh()->video_items[$key]['video_path']);
    }
    expect($draft->video_items['general']['label'])->toBe('Video geral');
    $exteriorPath = $draft->video_items['exterior']['video_path'];
    $this->withToken($token)->post($endpoint, ['kind' => 'video', 'key' => 'general', 'media' => ''])->assertSuccessful();
    expect($draft->refresh()->video_items['general']['video_path'])->toBeNull()
        ->and($draft->video_items['exterior']['video_path'])->toBe($exteriorPath);
});

it('saves and reloads wheel photos multiple general photos and an extra video in the edit form', function () {
    Storage::fake('public');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    $operator = User::factory()->create();
    $this->actingAs($operator);
    $vehicle = Vehicle::factory()->create(['status' => 'available']);
    $driver = Driver::factory()->create();
    $draft = app(VehicleHandoverProcedureService::class)->createDraft([
        'type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
        'performed_at' => '2026-10-20 09:45:00',
    ], $operator);
    $draft->update(['driver_signature_data_url' => 'driver-signature']);

    $form = \Livewire\Livewire::test(\App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure::class, ['record' => $draft->id])
        ->assertSee('Fotos das jantes')
        ->assertSee('Video geral')
        ->assertFormFieldExists('video_items.general');

    $photos = [];
    foreach (['wheel_front_left', 'wheel_front_right', 'wheel_rear_left', 'wheel_rear_right'] as $key) {
        $form->assertFormFieldExists("guided_photo_items.{$key}.photo");
        $photos[$key] = ['photo' => UploadedFile::fake()->image($key.'.jpg', 640, 480)];
    }

    $form->fillForm([
        'guided_photo_items' => $photos,
        'general_photos' => [UploadedFile::fake()->image('general-one.jpg'), UploadedFile::fake()->image('general-two.jpg')],
        'video_items.general' => UploadedFile::fake()->create('general.mp4', 100, 'video/mp4'),
        'operator_signature_data_url' => 'operator-signature',
    ])->call('save')->assertHasNoFormErrors();

    $draft->refresh();
    expect($draft->general_photo_paths)->toHaveCount(2);
    Storage::disk('public')->assertExists($draft->general_photo_paths);
    $videoPath = $draft->video_items['general']['video_path'];
    expect($videoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($videoPath);

    $reloaded = \Livewire\Livewire::test(\App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure::class, ['record' => $draft->id]);
    foreach (array_keys($photos) as $key) {
        $path = $draft->guided_photo_items[$key]['photo_path'];
        expect($path)->not->toBeNull();
        Storage::disk('public')->assertExists($path);
        expect(array_values($reloaded->get("data.guided_photo_items.{$key}.photo")))->toBe([$path]);
    }
    expect(array_values($reloaded->get('data.general_photos')))->toBe($draft->general_photo_paths);
    expect(array_values($reloaded->get('data.video_items.general')))->toBe([$videoPath]);

    app(VehicleHandoverProcedureService::class)->generateArtifacts($draft);
    expect($draft->refresh()->performed_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 09:45:00');
    Storage::disk('public')->assertExists($draft->pdf_path);

    foreach (['vehicle-handovers.snippet', 'pdf.vehicle-handover-procedure'] as $template) {
        $html = view($template, [
            'procedure' => $draft,
            'typeLabels' => \App\Support\VehicleHandoverDefinition::typeLabels(),
            'logo' => null,
        ])->render();
        $wheelSectionStart = strpos($html, '>Fotos das jantes<');
        $generalSectionStart = strpos($html, '>Fotos gerais<');

        expect($wheelSectionStart)->not->toBeFalse();
        foreach (['Jante dianteira esquerda', 'Jante dianteira direita', 'Jante traseira esquerda', 'Jante traseira direita'] as $label) {
            expect(strpos($html, $label))->toBeGreaterThan($wheelSectionStart)
                ->toBeLessThan($generalSectionStart);
        }
    }

    $draft->update(['html_snapshot' => '<p>Old document layout</p>']);
    expect($draft->refresh()->performed_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 09:45:00');
    \Livewire\Livewire::test(\App\Filament\Resources\VehicleHandoverProcedures\Pages\ViewVehicleHandoverProcedure::class, ['record' => $draft->id])
        ->assertSee('Fotos das jantes')
        ->assertSee('Jante dianteira esquerda')
        ->assertDontSee('Old document layout');
});

it('stores handover videos before the procedure is completed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $token = 'handover-media-token';
    Cache::put('app_auth_token:'.$token, $user->id, now()->addMinute());

    $response = $this->withToken($token)->post('/app/ops/vehicle-handovers/media', [
        'video' => UploadedFile::fake()->create('exterior.webm', 1024, 'video/webm'),
    ]);

    $response
        ->assertCreated()
        ->assertJsonStructure(['path', 'url']);

    Storage::disk('public')->assertExists($response->json('path'));
});

it('records a delivery handover and associates the selected driver and vehicle', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $handoverVehicle = Vehicle::query()->create([
        'license_plate' => 'CA-20-AG',
        'make' => 'Hyundai',
        'model' => 'Ioniq',
        'status' => 'available',
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
        ->and($procedure->created_allocation_id)->not->toBeNull()
        ->and($procedure->fault_items)->toHaveCount(1)
        ->and($procedure->fault_items[0]['type'])->toBe('electrical');

    $this->assertDatabaseHas(VehicleHandoverProcedure::class, [
        'id' => $procedure->id,
        'type' => 'delivery',
        'vehicle_id' => $handoverVehicle->id,
        'driver_id' => $driver->id,
        'notes' => 'Aciedente.',
    ]);

    $this->assertDatabaseHas(VehicleAllocation::class, [
        'id' => $procedure->created_allocation_id,
        'vehicle_id' => $handoverVehicle->id,
        'driver_id' => $driver->id,
        'status' => 'active',
    ]);

    expect($handoverVehicle->refresh()->status)->toBe('allocated');

    Mail::assertSent(VehicleHandoverProceduresMail::class, function (VehicleHandoverProceduresMail $mail): bool {
        return $mail->hasTo('info@zentrum-tvde.com')
            && count($mail->to) === 1
            && $mail->cc === []
            && $mail->bcc === [];
    });
    Mail::assertSentCount(1);
    expect($procedure->refresh()->email_recipients)->toBe(['info@zentrum-tvde.com'])
        ->and($procedure->email_sent_at)->not->toBeNull();
});

it('rejects a delivery when the selected driver already has another vehicle', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $availableVehicle = Vehicle::query()->create(['license_plate' => 'AA-01-AA', 'make' => 'Test', 'model' => 'Available', 'status' => 'available']);
    $allocatedVehicle = Vehicle::query()->create(['license_plate' => 'BB-02-BB', 'make' => 'Test', 'model' => 'Allocated', 'status' => 'allocated']);

    VehicleAllocation::factory()->create([
        'vehicle_id' => $allocatedVehicle->id,
        'driver_id' => $driver->id,
        'ends_at' => null,
        'status' => 'active',
    ]);

    expect(fn () => app(VehicleHandoverProcedureService::class)->create([
        'type' => 'delivery',
        'vehicle_id' => $availableVehicle->id,
        'driver_id' => $driver->id,
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ], $operator))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects a delivery when the vehicle already has a driver', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'CC-03-CC', 'make' => 'Test', 'model' => 'Allocated', 'status' => 'allocated']);

    VehicleAllocation::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'ends_at' => null,
        'status' => 'active',
    ]);

    expect(fn () => app(VehicleHandoverProcedureService::class)->create([
        'type' => 'delivery',
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ], $operator))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects a return when the vehicle has no assigned driver', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'DD-04-DD', 'make' => 'Test', 'model' => 'Available', 'status' => 'available']);

    expect(fn () => app(VehicleHandoverProcedureService::class)->create([
        'type' => 'return',
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ], $operator))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects a return for a driver not assigned to the vehicle', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $assignedDriver = Driver::factory()->create();
    $otherDriver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'EE-05-EE', 'make' => 'Test', 'model' => 'Allocated', 'status' => 'allocated']);

    VehicleAllocation::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $assignedDriver->id,
        'ends_at' => null,
        'status' => 'active',
    ]);

    expect(fn () => app(VehicleHandoverProcedureService::class)->create([
        'type' => 'return',
        'vehicle_id' => $vehicle->id,
        'driver_id' => $otherDriver->id,
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ], $operator))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('records a return for the assigned driver and closes the allocation', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'FF-06-FF',
        'make' => 'Test',
        'model' => 'Allocated',
        'status' => 'allocated',
    ]);
    $allocation = VehicleAllocation::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'ends_at' => null,
        'status' => 'active',
    ]);

    $procedure = app(VehicleHandoverProcedureService::class)->create([
        'type' => 'return',
        'performed_at' => '2026-07-21 10:00:00',
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ], $operator);

    expect($procedure->closed_allocation_id)->toBe($allocation->id)
        ->and($allocation->refresh()->status)->toBe('completed')
        ->and($vehicle->refresh()->status)->toBe('available');

    Mail::assertSent(VehicleHandoverProceduresMail::class, function (VehicleHandoverProceduresMail $mail): bool {
        return $mail->hasTo('info@zentrum-tvde.com')
            && count($mail->to) === 1
            && $mail->cc === []
            && $mail->bcc === [];
    });
    Mail::assertSentCount(1);
    expect($procedure->refresh()->email_recipients)->toBe(['info@zentrum-tvde.com'])
        ->and($procedure->email_sent_at)->not->toBeNull();
});

it('creates a persistent draft without changing vehicle allocation or sending mail', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'GG-07-GG', 'make' => 'Test', 'model' => 'Draft', 'status' => 'available']);
    $service = app(VehicleHandoverProcedureService::class);

    $draft = $service->createDraft([
        'type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
    ], $operator);

    expect($draft->status)->toBe('draft')
        ->and($draft->draft_step)->toBe('photos')
        ->and($draft->created_allocation_id)->toBeNull()
        ->and($vehicle->refresh()->status)->toBe('available')
        ->and($service->activeDraft($operator)?->id)->toBe($draft->id);
    Mail::assertNothingSent();
});

it('persists draft fields and requires the driver signature before completion', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'HH-08-HH', 'make' => 'Test', 'model' => 'Draft', 'status' => 'available']);
    $service = app(VehicleHandoverProcedureService::class);
    $draft = $service->createDraft(['type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id], $operator);

    $service->updateDraft($draft, [
        'draft_step' => 'signatures', 'notes' => 'Guardado por fases',
        'operator_signature_data_url' => 'operator-signature',
    ]);

    expect($draft->refresh()->notes)->toBe('Guardado por fases')
        ->and($draft->draft_step)->toBe('signatures');
    expect(fn () => $service->completeDraft($draft, $operator))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($vehicle->refresh()->status)->toBe('available');
});

it('completes a signed draft exactly once and creates the allocation', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'II-09-II', 'make' => 'Test', 'model' => 'Draft', 'status' => 'available']);
    $service = app(VehicleHandoverProcedureService::class);
    $draft = $service->createDraft(['type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id], $operator);
    $service->updateDraft($draft, [
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ]);

    $completed = $service->completeDraft($draft, $operator);

    expect($completed->status)->toBe('completed')
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->created_allocation_id)->not->toBeNull()
        ->and($vehicle->refresh()->status)->toBe('allocated');
    expect(fn () => $service->completeDraft($completed, $operator))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    Mail::assertSent(VehicleHandoverProceduresMail::class, function (VehicleHandoverProceduresMail $mail): bool {
        return $mail->hasTo('info@zentrum-tvde.com')
            && count($mail->to) === 1
            && $mail->cc === []
            && $mail->bcc === [];
    });
    Mail::assertSentCount(1);
    expect($completed->refresh()->email_recipients)->toBe(['info@zentrum-tvde.com']);
});

it('creates resumes updates and completes a draft through the app api', function () {
    Mail::fake();
    Storage::fake('public');

    $operator = User::factory()->create();
    $token = 'handover-draft-token';
    Cache::put('app_auth_token:'.$token, $operator->id, now()->addMinute());
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create(['license_plate' => 'JJ-10-JJ', 'make' => 'Test', 'model' => 'Api', 'status' => 'available']);

    $created = $this->withToken($token)->postJson('/app/ops/vehicle-handovers/draft', [
        'type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
    ])->assertCreated()->assertJsonPath('procedure.status', 'draft');
    $draftId = $created->json('procedure.id');

    $this->withToken($token)->patchJson("/app/ops/vehicle-handovers/{$draftId}/draft", [
        'draft_step' => 'signatures',
        'operator_signature_data_url' => 'operator-signature',
        'driver_signature_data_url' => 'driver-signature',
    ])->assertSuccessful()->assertJsonPath('procedure.draft_step', 'signatures');

    $this->withToken($token)->getJson('/app/ops/vehicle-handovers')
        ->assertSuccessful()->assertJsonPath('active_draft.id', $draftId);

    $this->withToken($token)->postJson("/app/ops/vehicle-handovers/{$draftId}/complete")
        ->assertSuccessful()->assertJsonPath('procedure.status', 'completed');
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
