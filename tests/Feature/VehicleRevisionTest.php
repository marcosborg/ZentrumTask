<?php

use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\RevisionsRelationManager;
use App\Mail\VehicleDocumentAlertsSummaryMail;
use App\Models\TeslaVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleRevision;
use App\Models\VehicleRevisionReminder;
use App\Models\VehicleWeeklyMileage;
use App\Services\VehicleRevisionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-21 08:00:00');
    Mail::fake();
    $this->service = app(VehicleRevisionService::class);
    $this->vehicle = Vehicle::factory()->create(['current_odometer' => 90000, 'source' => 'tvde']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('warns at either inclusive threshold and includes overdue revisions', function (?string $completed, ?int $years, ?int $nextKm, int $currentKm, bool $pending, bool $overdue): void {
    $this->vehicle->update(['current_odometer' => $currentKm]);
    VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => $completed, 'interval_years' => $years, 'next_odometer' => $nextKm]);
    $summary = $this->service->summary($this->vehicle->fresh());
    expect($summary['reasons'] !== [])->toBe($pending)->and($summary['overdue'])->toBe($overdue);
})->with([
    '30 days' => ['2025-11-20', 1, null, 90000, true, false],
    '31 days' => ['2025-11-21', 1, null, 90000, false, false],
    '5000 km' => ['2026-09-01', null, 100000, 95000, true, false],
    '5001 km' => ['2026-09-01', null, 100000, 94999, false, false],
    'date due today' => ['2025-10-21', 1, null, 90000, true, true],
    'date overdue' => ['2025-10-20', 1, null, 90000, true, true],
    'km overdue' => ['2026-09-01', 1, 100000, 100001, true, true],
    'km reached' => ['2026-09-01', 1, 100000, 100000, true, true],
]);

it('uses the latest completion and keeps its limits when a future revision is scheduled', function (): void {
    $latest = VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2025-10-20', 'next_odometer' => null]);
    VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2024-01-01', 'next_odometer' => null]);
    $scheduled = VehicleRevision::factory()->for($this->vehicle)->scheduled()->create(['scheduled_at' => '2026-12-01']);
    $summary = $this->service->summary($this->vehicle->fresh());
    expect($summary['revision_id'])->toBe($latest->id)->and($summary['overdue'])->toBeTrue()
        ->and($summary['scheduled_at']->toDateString())->toBe('2026-12-01');
    $scheduled->update(['completed_at' => today(), 'odometer' => 90000, 'next_odometer' => 120000]);
    $summary = $this->service->summary($this->vehicle->fresh());
    expect($summary['revision_id'])->toBe($scheduled->id)->and($summary['reasons'])->toBe([])
        ->and($this->vehicle->revisions()->count())->toBe(3);
});

it('warns for a first scheduled revision and ignores vehicles with no revisions', function (): void {
    expect($this->service->summary($this->vehicle))->toBeNull();
    VehicleRevision::factory()->for($this->vehicle)->scheduled()->create();
    expect($this->service->summary($this->vehicle->fresh())['reasons'])->not->toBeEmpty();
});

it('handles leap years without rolling the deadline into March', function (): void {
    $revision = VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2024-02-29']);
    expect($this->service->nextDate($revision)->toDateString())->toBe('2025-02-28');
});

it('uses dates when no odometer is available', function (): void {
    $this->vehicle->update(['current_odometer' => null]);
    VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2025-10-20']);
    $summary = $this->service->summary($this->vehicle->fresh());
    expect($summary['reading']['value'])->toBeNull()->and($summary['reasons'])->toHaveCount(1);
    $recipient = User::factory()->create();
    $html = (new VehicleDocumentAlertsSummaryMail($recipient, new Illuminate\Database\Eloquent\Collection, today(), collect([$summary])))->render();
    expect($html)->toContain('Quilometragem indisponível', 'Revisões próximas e vencidas', $this->vehicle->license_plate);
});

it('selects the newest odometer and prefers Tesla on a tie without using weekly distance', function (string $snapshotDate, string $expectedSource, float $expectedValue): void {
    VehicleWeeklyMileage::factory()->for($this->vehicle)->create([
        'period_start' => '2026-10-12', 'period_end' => '2026-10-18', 'weekly_km' => 50,
        'raw_row' => ['current_odometer' => 100000],
    ]);
    $tesla = TeslaVehicle::factory()->create(['vehicle_id' => $this->vehicle->id, 'odometer' => null]);
    $tesla->snapshots()->create(['recorded_at' => $snapshotDate, 'odometer' => 100100, 'raw_payload' => []]);
    $tesla->snapshots()->create(['recorded_at' => '2026-10-20', 'odometer' => null, 'raw_payload' => []]);
    $reading = $this->service->odometer($this->vehicle->fresh());
    expect($reading['source'])->toBe($expectedSource)->and($reading['value'])->toBe($expectedValue);
})->with([
    ['2026-10-17 00:00:00', 'Leitura semanal', 100000.0],
    ['2026-10-18 00:00:00', 'Tesla', 100100.0],
    ['2026-10-19 00:00:00', 'Tesla', 100100.0],
]);

it('falls back to the vehicle and skips invalid weekly readings', function (): void {
    VehicleWeeklyMileage::factory()->for($this->vehicle)->create(['period_start' => '2026-10-12', 'period_end' => '2026-10-18', 'weekly_km' => 9999, 'raw_row' => ['current_odometer' => 'invalid']]);
    expect($this->service->odometer($this->vehicle->fresh())['value'])->toBe(90000.0);
});

it('rejects invalid revisions', function (array $attributes): void {
    expect(fn () => VehicleRevision::factory()->for($this->vehicle)->create($attributes))->toThrow(ValidationException::class);
})->with([
    'future completion' => [['completed_at' => '2026-10-22']],
    'lower next odometer' => [['next_odometer' => 79999]],
    'equal next odometer' => [['next_odometer' => 80000]],
    'zero years' => [['interval_years' => 0]],
    'fractional years' => [['interval_years' => 1.5]],
]);

it('emails revisions alone daily with recipient filtering and same-day deduplication', function (): void {
    $adriano = User::factory()->create(['name' => 'Adriano Silva']);
    $marcos = User::factory()->create(['name' => 'Marcos Borges']);
    VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2025-10-20']);
    $fleet = Vehicle::factory()->create(['source' => 'fleet']);
    VehicleRevision::factory()->for($fleet)->scheduled()->create();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, fn ($mail): bool => $mail->hasTo($adriano->email) && $mail->alerts->isEmpty() && $mail->revisions->count() === 2);
    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, fn ($mail): bool => $mail->hasTo($marcos->email) && $mail->revisions->count() === 1);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSentCount(2);
    expect(VehicleRevisionReminder::count())->toBe(3);
    Carbon::setTestNow('2026-10-22 08:00:00');
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSentCount(4);
});

it('combines documents and revisions and removes completed or corrected revision cycles', function (): void {
    User::factory()->create(['name' => 'Adriano Silva']);
    $revision = VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2025-10-20']);
    VehicleDocument::factory()->for($this->vehicle)->create(['expires_at' => today()->addDays(4)]);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, fn ($mail): bool => $mail->alerts->count() === 1 && $mail->revisions->count() === 1);
    $revision->update(['completed_at' => today(), 'next_odometer' => 120000]);
    Carbon::setTestNow('2026-10-22 08:00:00');
    Mail::fake();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, fn ($mail): bool => $mail->alerts->count() === 1 && $mail->revisions->isEmpty());
});

it('allows a corrected pending cycle on the same day but does not resend after a note edit', function (): void {
    User::factory()->create(['name' => 'Adriano Silva']);
    $revision = VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2025-10-20']);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $revision->update(['notes' => 'Nota corrigida']);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSentCount(1);
    $revision->update(['completed_at' => '2025-10-19']);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSentCount(2);
});

it('manages revisions in Filament and isolates other vehicles', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $other = VehicleRevision::factory()->create();
    $component = Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class]);
    $component->callTableAction('create', data: ['completed_at' => '2025-10-20', 'scheduled_at' => null, 'odometer' => 80000, 'next_odometer' => 100000, 'interval_years' => 1])
        ->assertHasNoTableActionErrors();
    $revision = $this->vehicle->revisions()->sole();
    $component->assertCanSeeTableRecords([$revision])->assertCanNotSeeTableRecords([$other])
        ->callTableAction('edit', $revision, data: ['notes' => 'Revisão confirmada'])->assertHasNoTableActionErrors();
    expect($revision->fresh()->notes)->toBe('Revisão confirmada');
    $component->callTableAction('schedule', data: ['scheduled_at' => '2026-10-25', 'notes' => 'Oficina'])->assertHasNoTableActionErrors();
    $scheduled = $this->vehicle->revisions()->whereNull('completed_at')->sole();
    $component->callTableAction('complete', $scheduled, data: ['completed_at' => '2026-10-21', 'odometer' => 90000, 'next_odometer' => 120000, 'interval_years' => 1])->assertHasNoTableActionErrors();
    expect($scheduled->fresh()->completed_at->toDateString())->toBe('2026-10-21')
        ->and($this->vehicle->revisions()->count())->toBe(2);
    $component->callTableAction('delete', $revision)->assertHasNoTableActionErrors();
    expect($revision->fresh())->toBeNull()->and($other->fresh())->not->toBeNull();
});

it('denies revision mutations when the vehicle cannot be edited', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Gate::before(fn ($user, string $ability): ?bool => $ability === 'update' ? false : null);
    $revision = VehicleRevision::factory()->for($this->vehicle)->scheduled()->create();
    Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
        ->assertTableActionHidden('create')->assertTableActionHidden('schedule')
        ->assertTableActionHidden('edit', $revision)->assertTableActionHidden('delete', $revision)->assertTableActionHidden('complete', $revision);
});

it('validates completion data in the Filament form', function (array $data, array $errors): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $scheduled = VehicleRevision::factory()->for($this->vehicle)->scheduled()->create();
    Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
        ->callTableAction('complete', $scheduled, data: array_replace([
            'completed_at' => '2026-10-21', 'odometer' => 90000, 'next_odometer' => 120000, 'interval_years' => 1,
        ], $data))->assertHasTableActionErrors($errors);
    expect($scheduled->fresh()->completed_at)->toBeNull();
})->with([
    'future date' => [['completed_at' => '2026-10-22'], ['completed_at']],
    'lower kilometres' => [['next_odometer' => 80000], ['next_odometer']],
]);

it('supports revisions for SLOT vehicles using the same relation manager', function (): void {
    config(['slots.enabled' => true]);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $vehicle = Vehicle::factory()->create(['operation' => 'slot', 'owner_driver_id' => App\Models\Driver::factory()->create()->id]);
    $page = App\Filament\Resources\SlotVehicles\Pages\EditSlotVehicle::class;
    expect(RevisionsRelationManager::canViewForRecord($vehicle, $page))->toBeTrue();
    Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $vehicle, 'pageClass' => $page])
        ->callTableAction('schedule', data: ['scheduled_at' => '2026-10-25'])->assertHasNoTableActionErrors();
    expect($vehicle->revisions()->count())->toBe(1)->and($this->vehicle->revisions()->count())->toBe(0);
    config(['slots.enabled' => false]);
    expect(RevisionsRelationManager::canViewForRecord($vehicle, $page))->toBeFalse();
});

it('does not send an empty summary or keep deleted revisions pending', function (): void {
    User::factory()->create(['name' => 'Adriano Silva']);
    $revision = VehicleRevision::factory()->for($this->vehicle)->scheduled()->create();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $revision->delete();
    Carbon::setTestNow('2026-10-22 08:00:00');
    Mail::fake();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertNothingSent();
});

it('retries a revision email after a delivery failure without storing it as sent', function (): void {
    User::factory()->create(['name' => 'Adriano Silva']);
    VehicleRevision::factory()->for($this->vehicle)->scheduled()->create();
    $mailFake = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));
    expect(fn () => Illuminate\Support\Facades\Artisan::call('app:check-vehicle-documents-expiry'))->toThrow(RuntimeException::class, 'Mail unavailable');
    expect(VehicleRevisionReminder::count())->toBe(0);
    Mail::swap($mailFake);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSentCount(1);
});

it('uses a dated Tesla vehicle reading and ignores future snapshots', function (): void {
    $tesla = TeslaVehicle::factory()->create(['vehicle_id' => $this->vehicle->id, 'odometer' => 101000, 'last_seen_at' => '2026-10-21 07:00:00']);
    $tesla->snapshots()->create(['recorded_at' => '2026-10-20 00:00:00', 'odometer' => 100000, 'raw_payload' => []]);
    $tesla->snapshots()->create(['recorded_at' => '2026-10-22 00:00:00', 'odometer' => 102000, 'raw_payload' => []]);
    expect($this->service->odometer($this->vehicle->fresh())['value'])->toBe(101000.0);
});

it('accepts optional revision fields through creation and editing', function (array $values): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $data = array_replace(['scheduled_at' => null, 'completed_at' => null, 'odometer' => null, 'next_odometer' => null, 'interval_years' => null, 'notes' => null], $values);
    $component = Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class]);
    $component->callTableAction('create', data: $data)->assertHasNoTableActionErrors();
    $revision = $this->vehicle->revisions()->sole();
    expect($revision->completed_at?->toDateString())->toBe($data['completed_at'])->and($revision->odometer)->toBe($data['odometer']);
    $component->callTableAction('edit', $revision, data: $data)->assertHasNoTableActionErrors();
})->with([
    'kilometres only' => [['odometer' => 80000]],
    'date only' => [['completed_at' => '2026-10-01']],
    'next kilometres only' => [['next_odometer' => 95000]],
    'empty' => [[]],
]);

it('allows completing a scheduled revision with only kilometres', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $revision = VehicleRevision::factory()->for($this->vehicle)->scheduled()->create();
    Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
        ->callTableAction('complete', $revision, data: ['completed_at' => null, 'odometer' => 90000, 'next_odometer' => null, 'interval_years' => null])
        ->assertHasNoTableActionErrors();
    expect($revision->fresh()->completed_at)->toBeNull()->and($revision->fresh()->isRecorded())->toBeTrue()
        ->and($this->service->summary($this->vehicle->fresh())['reasons'])->toBe([]);
});

it('calculates alerts only from available revision information', function (array $values, ?string $reason): void {
    $revision = $this->vehicle->revisions()->create($values);
    $summary = $this->service->summary($this->vehicle->fresh());
    expect($summary['reasons'] ?? [])->toBe($reason ? [$reason] : []);
    if ($revision->completed_at === null) {
        expect($summary['due_at'] ?? null)->toBeNull();
    }
})->with([
    'km alert without date' => [['odometer' => 80000, 'next_odometer' => 95000, 'interval_years' => 1], 'Faltam até 5.000 km'],
    'date alert without km' => [['completed_at' => '2025-10-20', 'interval_years' => 1], 'Prazo de revisão atingido'],
    'last km without next limit' => [['odometer' => 80000], null],
    'date without interval' => [['completed_at' => '2025-10-20'], null],
    'empty record' => [[], null],
]);

it('uses an undated revision as the current cycle without reviving an older dated alert', function (): void {
    VehicleRevision::factory()->for($this->vehicle)->create(['completed_at' => '2025-10-20']);
    $latest = $this->vehicle->revisions()->create(['odometer' => 90000, 'next_odometer' => 120000]);
    $summary = $this->service->summary($this->vehicle->fresh());
    expect($summary['revision_id'])->toBe($latest->id)->and($summary['reasons'])->toBe([]);
});
