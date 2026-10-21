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
