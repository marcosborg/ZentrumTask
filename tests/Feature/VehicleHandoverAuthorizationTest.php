<?php

use App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\ListVehicleHandoverProcedures;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\ViewVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\VehicleHandoverProcedureResource;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('restricts handover deletion to the verified Marcos account', function (int $id, string $email, bool $allowed) {
    Filament::setCurrentPanel('admin');
    $user = User::factory()->create(['id' => $id, 'name' => 'Marcos Borges', 'email' => $email]);
    $this->actingAs($user);
    $vehicle = Vehicle::factory()->create(['status' => 'available']);
    $driver = Driver::factory()->create();
    $procedure = app(VehicleHandoverProcedureService::class)->createDraft([
        'type' => 'delivery', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
    ], $user);
    expect(VehicleHandoverProcedureResource::canDelete($procedure))->toBe($allowed)
        ->and(VehicleHandoverProcedureResource::canDeleteAny())->toBeFalse();

    foreach ([EditVehicleHandoverProcedure::class, ViewVehicleHandoverProcedure::class] as $page) {
        $component = Livewire::test($page, ['record' => $procedure->id]);
        if ($allowed) {
            $component->assertActionVisible('delete');
        } else {
            $component->assertActionHidden('delete')->mountAction('delete')->call('callMountedAction');
            expect(VehicleHandoverProcedure::query()->whereKey($procedure->id)->exists())->toBeTrue();
        }
    }

    $list = Livewire::test(ListVehicleHandoverProcedures::class);
    $action = TestAction::make('delete')->table($procedure);
    if ($allowed) {
        $list->assertActionVisible($action)->callAction($action);
        expect(VehicleHandoverProcedure::query()->whereKey($procedure->id)->exists())->toBeFalse();
    } else {
        $list->assertActionHidden($action)->mountAction($action)->call('callMountedAction');
        expect(VehicleHandoverProcedure::query()->whereKey($procedure->id)->exists())->toBeTrue();
    }
})->with([
    'Marcos' => [1, 'marcosborges@netlook.pt', true],
    'same name different account' => [2, 'other@example.com', false],
    'same id different email' => [1, 'other@example.com', false],
    'same email different id' => [2, 'marcosborges@netlook.pt', false],
]);

it('restores the previous allocation when Marcos deletes a return and blocks conflicting changes', function (string $conflict) {
    Filament::setCurrentPanel('admin');
    \Illuminate\Support\Facades\Mail::fake();
    \Illuminate\Support\Facades\Storage::fake('public');
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 16:45:00'));
    $user = User::factory()->create(['id' => 1, 'email' => 'marcosborges@netlook.pt']);
    $this->actingAs($user);
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create(['status' => 'allocated']);
    $allocation = \App\Models\VehicleAllocation::factory()->create([
        'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
        'starts_at' => '2026-10-20 00:00:00', 'ends_at' => null, 'status' => 'active',
    ]);
    $procedure = app(VehicleHandoverProcedureService::class)->create([
        'type' => 'return', 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
        'performed_at' => '2026-10-28 16:45:00',
        'operator_signature_data_url' => 'operator-signature', 'driver_signature_data_url' => 'driver-signature',
    ], $user);
    if ($conflict === 'missing allocation') {
        $procedure->update(['closed_allocation_id' => null]);
    } elseif ($conflict === 'allocation') {
        \App\Models\VehicleAllocation::factory()->create([
            'driver_id' => $driver->id, 'vehicle_id' => Vehicle::factory()->create()->id,
            'starts_at' => '2026-10-29 00:00:00', 'ends_at' => null, 'status' => 'active',
        ]);
    } elseif ($conflict === 'later procedure') {
        $later = $procedure->replicate();
        $later->performed_at = '2026-10-29 10:00:00';
        $later->type = 'inspection';
        $later->closed_allocation_id = null;
        $later->save();
    } elseif ($conflict === 'edited end date') {
        $allocation->refresh()->update(['ends_at' => '2026-10-26 23:59:59']);
    }
    $before = $allocation->refresh()->getAttributes();
    $component = Livewire::test(ViewVehicleHandoverProcedure::class, ['record' => $procedure->id])
        ->callAction('delete');
    if ($conflict === 'none') {
        expect(VehicleHandoverProcedure::query()->whereKey($procedure->id)->exists())->toBeFalse()
            ->and($allocation->refresh()->status)->toBe('active')
            ->and($allocation->ends_at)->toBeNull()
            ->and($vehicle->refresh()->status)->toBe('allocated');
    } else {
        $component->assertNotified('Eliminação bloqueada');
        expect(VehicleHandoverProcedure::query()->whereKey($procedure->id)->exists())->toBeTrue()
            ->and($allocation->refresh()->getAttributes())->toBe($before)
            ->and($vehicle->refresh()->status)->toBe('available');
    }
})->with(['none', 'allocation', 'later procedure', 'edited end date', 'missing allocation']);
