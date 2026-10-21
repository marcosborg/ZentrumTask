<?php

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    $this->operator = User::factory()->create();
    $this->driver = Driver::factory()->create();
    $this->vehicle = Vehicle::factory()->create(['status' => 'allocated']);
    $this->allocation = VehicleAllocation::factory()->create([
        'vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id,
        'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => null,
    ]);
    $this->payload = [
        'type' => 'inspection', 'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'operator_signature_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
        'driver_signature_data_url' => 'data:image/png;base64,iVBORw0KGgo=',
        'damage_items' => [['type' => 'risco', 'zone' => 'frente', 'description' => 'Risco observado']],
    ];
});

it('creates an inspection and its document without changing any allocation', function () {
    $before = $this->allocation->refresh()->getAttributes();
    $vehicleBefore = $this->vehicle->refresh()->getAttributes();
    $procedure = app(VehicleHandoverProcedureService::class)->create($this->payload, $this->operator);

    expect($procedure->type)->toBe('inspection')
        ->and($procedure->status)->toBe('completed')
        ->and($procedure->created_allocation_id)->toBeNull()
        ->and($procedure->closed_allocation_id)->toBeNull()
        ->and($procedure->allocation_effective_start_date)->toBeNull()
        ->and($procedure->allocation_effective_end_date)->toBeNull()
        ->and($procedure->damage_items[0]['description'])->toBe('Risco observado')
        ->and($this->allocation->refresh()->getAttributes())->toBe($before)
        ->and($this->vehicle->refresh()->getAttributes())->toBe($vehicleBefore)
        ->and(VehicleAllocation::count())->toBe(1)
        ->and($procedure->html_snapshot)->toContain('Inspeção de viatura');
    Storage::disk('public')->assertExists($procedure->pdf_path);
    expect(view('pdf.vehicle-handover-procedure', [
        'procedure' => $procedure, 'typeLabels' => App\Support\VehicleHandoverDefinition::typeLabels(), 'logo' => null,
    ])->render())->toContain('Inspeção');
});

it('completes and resumes an inspection through the app api preserving the assignment', function () {
    $before = $this->allocation->refresh()->getAttributes();
    Cache::put('app_auth_token:inspection-token', $this->operator->id, now()->addMinute());
    $response = $this->withToken('inspection-token')->postJson('/app/ops/vehicle-handovers/draft', $this->payload)->assertCreated();
    $id = $response->json('procedure.id');
    $this->withToken('inspection-token')->getJson('/app/ops/vehicle-handovers')->assertSuccessful()
        ->assertJsonPath('active_draft.type', 'inspection');
    $this->withToken('inspection-token')->patchJson("/app/ops/vehicle-handovers/{$id}/draft", $this->payload)->assertSuccessful();
    $this->withToken('inspection-token')->postJson("/app/ops/vehicle-handovers/{$id}/complete")->assertSuccessful()
        ->assertJsonPath('procedure.type', 'inspection')->assertJsonPath('procedure.type_label', 'Inspeção');
    expect($this->allocation->refresh()->getAttributes())->toBe($before)
        ->and($this->vehicle->refresh()->status)->toBe('allocated')
        ->and(VehicleAllocation::count())->toBe(1)
        ->and(VehicleHandoverProcedure::findOrFail($id)->status)->toBe('completed');
});

it('rejects inspections without the matching current assignment', function (string $scenario) {
    if ($scenario === 'unassigned') {
        $this->allocation->update(['status' => 'completed', 'ends_at' => now()]);
    } else {
        $this->payload['driver_id'] = Driver::factory()->create()->id;
    }
    expect(fn () => app(VehicleHandoverProcedureService::class)->createDraft($this->payload, $this->operator))
        ->toThrow(ValidationException::class);
    expect(VehicleHandoverProcedure::count())->toBe(0);
})->with(['unassigned', 'wrong driver']);

it('rechecks the assignment before completing an inspection', function () {
    $service = app(VehicleHandoverProcedureService::class);
    $draft = $service->createDraft($this->payload, $this->operator);
    $service->updateDraft($draft, $this->payload);
    $this->allocation->update(['driver_id' => Driver::factory()->create()->id, 'driver_participation_id' => null]);
    $before = $this->allocation->refresh()->getAttributes();
    expect(fn () => $service->completeDraft($draft, $this->operator))->toThrow(ValidationException::class);
    expect($draft->refresh()->status)->toBe('draft')
        ->and($this->allocation->refresh()->getAttributes())->toBe($before);
});

it('lists inspections in their own backoffice section', function () {
    Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs($this->operator);
    $inspection = app(VehicleHandoverProcedureService::class)->create($this->payload, $this->operator);
    $handover = $inspection->replicate();
    $handover->type = 'delivery';
    $handover->save();
    Livewire\Livewire::test(App\Filament\Resources\VehicleHandoverProcedures\Pages\ListVehicleInspections::class)
        ->assertCanSeeTableRecords([$inspection])->assertCanNotSeeTableRecords([$handover]);
    Livewire\Livewire::test(App\Filament\Resources\VehicleHandoverProcedures\Pages\ListVehicleHandoverProcedures::class)
        ->assertCanSeeTableRecords([$handover])->assertCanNotSeeTableRecords([$inspection]);
});

it('creates an inspection from the backoffice form with both signatures', function () {
    Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs($this->operator);
    $before = $this->allocation->refresh()->getAttributes();
    Livewire\Livewire::test(App\Filament\Resources\VehicleHandoverProcedures\Pages\CreateVehicleHandoverProcedure::class)
        ->fillForm($this->payload)
        ->call('create')->assertHasNoFormErrors();
    expect(VehicleHandoverProcedure::query()->where('type', 'inspection')->count())->toBe(1)
        ->and($this->allocation->refresh()->getAttributes())->toBe($before);
});

it('compares with the latest completed delivery before the inspection for the same vehicle', function () {
    $service = app(VehicleHandoverProcedureService::class);
    $inspection = $service->createDraft($this->payload + ['performed_at' => '2026-10-21 10:00:00'], $this->operator);
    $delivery = $inspection->replicate();
    $delivery->fill([
        'type' => 'delivery', 'status' => 'completed', 'performed_at' => '2026-10-20 09:00:00',
        'guided_photo_items' => ['front_full' => ['label' => 'Frente', 'photo_path' => 'front.jpg'], 'wheel_front_left' => ['label' => 'Jante dianteira esquerda', 'photo_path' => 'wheel.jpg']],
        'general_photo_paths' => ['general.jpg'],
        'damage_items' => [['type' => 'risco', 'zone' => 'frente', 'description' => 'Dano anterior', 'photo_path' => 'damage.jpg']],
    ])->save();
    foreach ([
        ['performed_at' => '2026-10-19 09:00:00'],
        ['performed_at' => '2026-10-22 09:00:00'],
        ['performed_at' => '2026-10-21 09:00:00', 'status' => 'draft'],
        ['performed_at' => '2026-10-21 09:00:00', 'type' => 'return'],
        ['performed_at' => '2026-10-21 09:00:00', 'vehicle_id' => Vehicle::factory()->create()->id],
    ] as $attributes) {
        $other = $delivery->replicate();
        $other->fill($attributes)->save();
    }

    Cache::put('app_auth_token:comparison-token', $this->operator->id, now()->addMinute());
    $this->withToken('comparison-token')->getJson("/app/ops/vehicle-handovers/{$inspection->id}")
        ->assertSuccessful()->assertJsonPath('procedure.inspection_reference.id', $delivery->id)
        ->assertJsonCount(3, 'procedure.inspection_reference.photos')
        ->assertJsonPath('procedure.inspection_reference.damage_items.0.description', 'Dano anterior')
        ->assertJsonPath('procedure.inspection_reference.damage_items.0.photo_url', Storage::disk('public')->url('damage.jpg'));
    expect($inspection->refresh()->damage_items)->toBe([]);

    Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs($this->operator);
    Livewire\Livewire::test(App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure::class, ['record' => $inspection->id])
        ->assertSee('Comparar com a última entrega')->assertSee('Dano anterior')->assertSee('Jante dianteira esquerda');
    Livewire\Livewire::test(App\Filament\Resources\VehicleHandoverProcedures\Pages\ViewVehicleHandoverProcedure::class, ['record' => $inspection->id])
        ->assertSee('Dano anterior');

    expect($service->inspectionReference($this->vehicle->id, '2026-10-18 10:00:00'))->toBeNull()
        ->and($service->inspectionReference(null))->toBeNull();
});

it('shows an explicit empty reference when the vehicle has no earlier delivery', function () {
    $draft = app(VehicleHandoverProcedureService::class)->createDraft($this->payload, $this->operator);
    Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs($this->operator);
    Livewire\Livewire::test(App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure::class, ['record' => $draft->id])
        ->assertSee('Não existe uma entrega concluída anterior');
});
