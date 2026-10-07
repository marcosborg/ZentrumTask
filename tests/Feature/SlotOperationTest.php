<?php

use App\Enums\TvdeOperation;
use App\Enums\VatRefundMode;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverParticipation;
use App\Models\PlatformDriverBalance;
use App\Models\SlotPack;
use App\Models\Vehicle;
use App\Models\VehicleCheckup;
use App\Services\ParticipationService;
use App\Services\SlotSettlementCalculator;
use App\Services\SlotSettlementPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {

    config(['slots.enabled' => true]);

    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00:00'));

});

function preparedSlot(string $start = '2026-10-12', float $price = 30, ?Driver $driver = null): DriverParticipation
{

    $driver ??= Driver::factory()->create(['registration_operation' => 'slot']);

    $vehicle = Vehicle::factory()->create(['operation' => 'slot', 'owner_driver_id' => $driver->id]);

    $participation = DriverParticipation::factory()->create([

        'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'starts_at' => $start,

        'contract_file' => 'contract.pdf', 'documents_approved_at' => now(),

    ]);

    DriverBillingProfile::factory()->create(['driver_id' => $driver->id, 'driver_participation_id' => $participation->id, 'operation' => 'slot', 'valid_from' => $start, 'vat_percent' => 0]);

    VehicleCheckup::factory()->create(['vehicle_id' => $vehicle->id, 'completed_at' => $start]);

    app(ParticipationService::class)->assignPack($participation, SlotPack::factory()->create(['weekly_price' => $price]), $start);

    return $participation;

}

it('requires every admission prerequisite before activation', function (string $field): void {

    $participation = preparedSlot();

    if ($field === 'checkup') {

        $participation->vehicle->checkups()->delete();

    } elseif ($field === 'profile') {

        $participation->billingProfiles()->delete();

    } else {

        $participation->update([$field => null]);

    }

    expect(fn () => app(ParticipationService::class)->activate($participation))->toThrow(ValidationException::class);

    expect($participation->fresh()->status)->toBe('preparing');

})->with(['checkup', 'profile', 'contract_file', 'documents_approved_at']);

it('charges each pack once even without trips and preserves its historical price', function (float $price): void {

    $participation = preparedSlot(price: $price);

    app(ParticipationService::class)->activate($participation);

    $calculator = app(SlotSettlementCalculator::class);

    expect($calculator->calculate('2026-10-12', '2026-10-18')['created'])->toBe(1);

    expect($calculator->calculate('2026-10-12', '2026-10-18')['skipped'])->toBe(1);

    $settlement = $participation->settlements()->sole();

    expect((float) $settlement->slot_fee)->toBe($price)

        ->and((float) $settlement->amount_payable)->toBe(-$price)

        ->and((float) $participation->balance->current_balance)->toBe(-$price)

        ->and($settlement->payment_due_at->toDateString())->toBe('2026-10-19');

})->with([30.0, 50.0]);

it('waives only completely suspended weeks', function (string $end, float $fee): void {

    $participation = preparedSlot();

    app(ParticipationService::class)->activate($participation);

    app(ParticipationService::class)->suspend($participation, '2026-10-12', $end, 'Ausência');

    app(SlotSettlementCalculator::class)->calculate('2026-10-12', '2026-10-18');

    expect((float) $participation->settlements()->sole()->slot_fee)->toBe($fee);

    expect(fn () => app(ParticipationService::class)->suspend($participation, '2026-10-18', '2026-10-18', 'Correção'))->toThrow(ValidationException::class);

})->with([['2026-10-18', 0.0], ['2026-10-17', 30.0]]);

it('keeps tax on earnings and never taxes the inclusive pack fee or carry', function (): void {

    $participation = preparedSlot();

    $participation->billingProfiles()->first()->update(['vat_refund_mode' => VatRefundMode::DriverDeliversVat, 'vat_percent' => 23, 'apply_withholding_tax' => true, 'withholding_tax_percent' => 10]);

    app(ParticipationService::class)->activate($participation);

    app(ParticipationService::class)->regularize($participation, 100, 'Saldo documentado');

    PlatformDriverBalance::query()->create(['driver_id' => $participation->driver_id, 'driver_participation_id' => $participation->id, 'operation' => 'slot', 'platform' => 'uber', 'driver_code' => 'slot-tax', 'period_start' => '2026-10-12', 'period_end' => '2026-10-18', 'net_amount' => 1000, 'tips_amount' => 100, 'source_file' => 'test.csv', 'imported_at' => now()]);

    app(SlotSettlementCalculator::class)->calculate('2026-10-12', '2026-10-18');

    $settlement = $participation->settlements()->sole();

    expect((float) $settlement->amount_payable)->toBe(1100.0)

        ->and((float) $settlement->amount_due)->toBe(1200.0)

        ->and((float) $settlement->rules_snapshot['vat_amount'])->toBe(230.0)

        ->and((float) $settlement->rules_snapshot['tips_total'])->toBe(100.0);

    expect(fn () => app(SlotSettlementPaymentService::class)->pay($settlement, 500))->toThrow(ValidationException::class);

    $settlement->update(['platform_received_at' => now(), 'reconciled_at' => now()]);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 12:00:00'));
    $reference = (string) \Illuminate\Support\Str::uuid();
    app(SlotSettlementPaymentService::class)->pay($settlement, 500, $reference);
    app(SlotSettlementPaymentService::class)->pay($settlement, 500, $reference);

    expect((float) $participation->balance()->first()->current_balance)->toBe(700.0)

        ->and((float) $settlement->fresh()->amount_transferred)->toBe(500.0);

    app(SlotSettlementPaymentService::class)->pay($settlement, 600);

    expect((float) $participation->balance()->first()->current_balance)->toBe(100.0);

    expect(fn () => app(SlotSettlementPaymentService::class)->pay($settlement, 600))->toThrow(ValidationException::class);

});

it('transitions both ways without moving balances or deposits', function (): void {

    $driver = Driver::factory()->create();

    $origin = $driver->participations()->sole();

    $origin->update(['deposit_amount' => 250]);

    app(ParticipationService::class)->regularize($origin, 80, 'Saldo aluguer');

    $slot = preparedSlot(driver: $driver);

    app(ParticipationService::class)->transition($origin, $slot);

    expect($origin->fresh()->ends_at->toDateString())->toBe('2026-10-12')

        ->and((float) $origin->balance()->first()->current_balance)->toBe(80.0)

        ->and((float) $slot->balance()->first()->current_balance)->toBe(0.0)

        ->and((float) $slot->deposit_amount)->toBe(0.0);

    $rental = DriverParticipation::factory()->create(['driver_id' => $driver->id, 'operation' => 'rental', 'starts_at' => '2026-10-19']);

    app(ParticipationService::class)->transition($slot, $rental);

    expect(app(ParticipationService::class)->resolve($driver->id, '2026-10-12', '2026-10-18')->id)->toBe($slot->id)

        ->and(app(ParticipationService::class)->resolve($driver->id, '2026-10-05', '2026-10-18'))->toBeNull()

        ->and($rental->fresh()->status)->toBe('active');

});

it('does not offer another free checkup before twelve months', function (): void {

    $participation = preparedSlot();

    expect($participation->vehicle->checkups()->sole()->nextDueAt()->toDateString())->toBe('2027-10-12');

    expect(fn () => VehicleCheckup::factory()->create(['vehicle_id' => $participation->vehicle_id, 'completed_at' => '2027-01-01']))->toThrow(ValidationException::class);

});

it('keeps SLOT vehicles outside rental resources and public catalogues', function (): void {

    $slot = preparedSlot();

    $rental = Vehicle::factory()->create();

    expect(\App\Filament\Resources\Vehicles\VehicleResource::getEloquentQuery()->pluck('id')->all())->toContain($rental->id)->not->toContain($slot->vehicle_id);

    expect(\App\Filament\Resources\SlotVehicles\SlotVehicleResource::getEloquentQuery()->pluck('id')->all())->toBe([$slot->vehicle_id]);

    expect(Vehicle::query()->websiteCatalog()->whereKey($slot->vehicle_id)->exists())->toBeFalse();

});

it('keeps the module disabled until configured', function (): void {

    $slot = preparedSlot();

    config(['slots.enabled' => false]);

    expect(fn () => app(ParticipationService::class)->activate($slot))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    expect(\App\Filament\Resources\SlotVehicles\SlotVehicleResource::canAccess())->toBeFalse();

});

it('renders separate management pages and blocks cross-operation actions', function (): void {

    $this->actingAs(\App\Models\User::factory()->create());

    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

    $slot = preparedSlot();

    app(ParticipationService::class)->activate($slot);

    app(SlotSettlementCalculator::class)->calculate('2026-10-12', '2026-10-18');

    $settlement = $slot->settlements()->sole();

    $rental = Driver::factory()->create()->participations()->sole();

    \Livewire\Livewire::test(\App\Filament\Resources\DriverParticipations\Pages\ManageDriverParticipations::class)

        ->assertSuccessful()->assertCanSeeTableRecords([$slot])->assertCanNotSeeTableRecords([$rental]);

    \Livewire\Livewire::test(\App\Filament\Resources\DriverSettlements\Pages\ManageDriverSettlements::class)

        ->assertSuccessful()->assertCanSeeTableRecords([$settlement]);

    \Livewire\Livewire::test(\App\Filament\Resources\SlotVehicles\Pages\ManageSlotVehicles::class)

        ->assertSuccessful()->assertCanSeeTableRecords([$slot->vehicle]);

    expect(fn () => (new \App\Filament\Pages\DriverSettlementsReport)->markSettlementPaid($settlement))

        ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

});

it('allocates late imports to the earning period and leaves conflicting periods pending', function (): void {

    $driver = Driver::factory()->create(['uber_driver_code' => 'late-slot']);

    $origin = $driver->participations()->sole();

    $slot = preparedSlot(driver: $driver);

    app(ParticipationService::class)->transition($origin, $slot);

    $rental = DriverParticipation::factory()->create(['driver_id' => $driver->id, 'operation' => 'rental', 'starts_at' => '2026-10-19']);

    app(ParticipationService::class)->transition($slot, $rental);

    foreach ([['2026-10-12', '2026-10-18'], ['2026-10-05', '2026-10-18']] as [$start, $end]) {

        PlatformDriverBalance::query()->create(['platform' => 'uber', 'driver_code' => 'late-slot', 'period_start' => $start, 'period_end' => $end, 'net_amount' => 100, 'tips_amount' => 0, 'source_file' => 'late.csv', 'imported_at' => now()]);

    }

    expect(app(\App\Services\PlatformDriverBalanceAllocator::class)->allocate())->toBe(['allocated' => 1, 'pending' => 1]);

    expect(PlatformDriverBalance::query()->whereDate('period_start', '2026-10-12')->sole()->driver_participation_id)->toBe($slot->id);

    expect(PlatformDriverBalance::query()->whereDate('period_start', '2026-10-05')->sole()->allocation_error)->not->toBeNull();

});

it('versions fiscal profiles for the same identity without sharing deposit history', function (): void {

    $driver = Driver::factory()->create(['deposit_amount' => 200]);

    $origin = $driver->participations()->sole();

    DriverBillingProfile::factory()->create(['driver_id' => $driver->id, 'driver_participation_id' => $origin->id]);

    $slot = preparedSlot(driver: $driver);

    app(ParticipationService::class)->transition($origin, $slot);

    $slot->update(['deposit_amount' => 40]);

    expect(DriverBillingProfile::query()->where('driver_id', $driver->id)->count())->toBe(2);

    expect(app(\App\Services\DriverDepositService::class)->summaryForDriver($driver, $slot->id)['current_balance'])->toBe(40.0)

        ->and(app(\App\Services\DriverDepositService::class)->summaryForDriver($driver, $origin->id)['current_balance'])->toBe(200.0);

});

it('preserves original values and ids when migrating existing rental records', function (): void {

    $migration = require database_path('migrations/2026_10_07_132428_add_tvde_operations.php');

    $migration->down();

    $driver = Driver::withoutEvents(fn () => Driver::factory()->create(['deposit_amount' => 200]));

    $profile = DriverBillingProfile::withoutEvents(fn () => DriverBillingProfile::factory()->create(['driver_id' => $driver->id, 'valid_from' => '2025-01-01']));

    $migration->up();

    $membership = $driver->participations()->sole();

    expect($membership->operation)->toBe(TvdeOperation::Rental)

        ->and($membership->starts_at->toDateString())->toBe('2025-01-01')

        ->and((float) $membership->deposit_amount)->toBe(200.0)

        ->and($profile->fresh()->driver_participation_id)->toBe($membership->id)

        ->and($profile->fresh()->percent_company)->toBe($profile->percent_company);

});

it('uses the same calculation for imported and manually entered SLOT statements', function (): void {
    $slot = preparedSlot(price: 50);
    app(ParticipationService::class)->activate($slot);
    $manual = app(\App\Services\DriverSettlementService::class)->createStatementFromInputs($slot->driver, $slot->billingProfiles()->sole(), ['week_start_date' => '2026-10-12', 'week_end_date' => '2026-10-18', 'net_total' => 200, 'tips_total' => 20, 'expenses_total' => 10]);
    expect((float) $manual->amount_payable_to_driver)->toBe(140.0)
        ->and((float) $manual->slot_fee)->toBe(50.0)
        ->and($manual->operation)->toBe('slot')
        ->and((float) $manual->items->sum('amount'))->toBe(140.0);
});

it('charges partial weeks and schedules pack changes on Monday only', function (): void {
    $slot = preparedSlot('2026-10-14');
    app(ParticipationService::class)->activate($slot);
    $premium = SlotPack::factory()->create(['name' => 'Premium', 'weekly_price' => 50]);
    expect(fn () => app(ParticipationService::class)->assignPack($slot, $premium, '2026-10-15'))->toThrow(ValidationException::class);
    app(ParticipationService::class)->assignPack($slot, $premium, '2026-10-19');
    app(SlotSettlementCalculator::class)->calculate('2026-10-12', '2026-10-18');
    app(SlotSettlementCalculator::class)->calculate('2026-10-19', '2026-10-25');
    expect($slot->settlements()->orderBy('period_start')->pluck('slot_fee')->all())->toBe(['30.00', '50.00']);
});

it('distributes a mixed CSV without duplicating reimported earnings', function (): void {
    $slot = preparedSlot();
    $slot->driver->update(['bolt_driver_code' => 'slot-mixed']);
    app(ParticipationService::class)->activate($slot);
    $rental = Driver::factory()->create(['bolt_driver_code' => 'rental-mixed']);
    $path = storage_path('framework/testing/slot-mixed.csv');
    file_put_contents($path, "Identificador do motorista,Ganhos liquidos|EUR,Gorjetas dos passageiros|EUR\nslot-mixed,100,10\nrental-mixed,200,20\nunknown,300,30\n");
    $service = app(\App\Services\BoltPlatformCsvImportService::class);
    $period = ['period_start' => '2026-10-12', 'period_end' => '2026-10-18'];
    $service->import($path, $period);
    app(\App\Services\PlatformDriverBalanceAllocator::class)->allocate();
    $service->import($path, $period);
    expect(PlatformDriverBalance::query()->count())->toBe(3)
        ->and(PlatformDriverBalance::query()->forOperation(TvdeOperation::Slot)->sole()->driver_participation_id)->toBe($slot->id)
        ->and(PlatformDriverBalance::query()->where('driver_id', $rental->id)->sole()->operation)->toBe('rental')
        ->and(PlatformDriverBalance::query()->whereNull('driver_participation_id')->count())->toBe(1);
    unlink($path);
});

it('keeps accident requests linked to their original vehicle and participation', function (): void {
    $slot = preparedSlot(price: 50);
    app(ParticipationService::class)->activate($slot);
    $accident = \App\Models\SlotAccident::factory()->create(['driver_participation_id' => $slot->id, 'vehicle_id' => $slot->vehicle_id, 'occurred_at' => '2026-10-13']);
    foreach (['immobilization', 'replacement_vehicle'] as $type) {
        \App\Models\SlotAccidentRequest::factory()->create(['slot_accident_id' => $accident->id, 'type' => $type, 'status' => 'requested']);
    }
    expect($accident->requests()->count())->toBe(2)
        ->and($accident->participation->id)->toBe($slot->id);
    $other = preparedSlot();
    expect(fn () => $accident->update(['vehicle_id' => $other->vehicle_id]))->toThrow(ValidationException::class);
});

it('renders operational SLOT pages with scoped records', function (string $page): void {
    $this->actingAs(\App\Models\User::factory()->create());
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    preparedSlot();
    \Livewire\Livewire::test($page)->assertSuccessful();
})->with([
    \App\Filament\Resources\SlotBalances\Pages\ManageSlotBalances::class,
    \App\Filament\Resources\SlotDeposits\Pages\ManageSlotDeposits::class,
    \App\Filament\Resources\RentalBalances\Pages\ManageRentalBalances::class,
    \App\Filament\Resources\RentalDeposits\Pages\ManageRentalDeposits::class,
    \App\Filament\Resources\SlotWeekStatements\Pages\ManageSlotWeekStatements::class,
    \App\Filament\Resources\VehicleCheckups\Pages\ManageVehicleCheckups::class,
    \App\Filament\Resources\SlotAccidents\Pages\ManageSlotAccidents::class,
    \App\Filament\Resources\SlotAccidentRequests\Pages\ManageSlotAccidentRequests::class,
]);

it('opens vehicle documents only through the correct operation', function (): void {
    $this->actingAs(\App\Models\User::factory()->create());
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    $slot = preparedSlot();
    \Livewire\Livewire::test(\App\Filament\Resources\SlotVehicles\Pages\EditSlotVehicle::class, ['record' => $slot->vehicle_id])->assertSuccessful();
    $rental = Vehicle::factory()->create();
    $this->get(\App\Filament\Resources\SlotVehicles\SlotVehicleResource::getUrl('edit', ['record' => $rental->id]))->assertNotFound();
});

it('keeps the current allocation until a scheduled Monday transition', function (): void {
    $driver = Driver::factory()->create();
    $origin = $driver->participations()->sole();
    $vehicle = Vehicle::factory()->create();
    \App\Models\VehicleAllocation::factory()->create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'starts_at' => '2026-10-07 00:00:00', 'ends_at' => null, 'status' => 'active']);
    $slot = preparedSlot(driver: $driver);
    app(ParticipationService::class)->transition($origin, $slot);
    expect($driver->fresh()->currentAllocation?->vehicle_id)->toBe($vehicle->id);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-12 12:00:00'));
    expect($driver->fresh()->currentAllocation)->toBeNull()
        ->and(Driver::query()->currentlyInOperation(TvdeOperation::Rental)->whereKey($driver->id)->exists())->toBeFalse();
});

it('exports and emails only SLOT settlements through the SLOT page', function (): void {
    \Illuminate\Support\Facades\Mail::fake();
    $this->actingAs(\App\Models\User::factory()->create());
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    $slot = preparedSlot();
    app(ParticipationService::class)->activate($slot);
    app(SlotSettlementCalculator::class)->calculate('2026-10-12', '2026-10-18');
    $settlement = $slot->settlements()->sole();
    \Livewire\Livewire::test(\App\Filament\Resources\DriverSettlements\Pages\ManageDriverSettlements::class)
        ->callTableAction('email', $settlement)->assertHasNoTableActionErrors();
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SlotSettlementSummaryMail::class, fn ($mail) => $mail->settlement->id === $settlement->id);
    expect($settlement->emailLogs()->sole()->recipient)->toBe($slot->driver->email);
    $mail = new \App\Mail\SlotSettlementSummaryMail($settlement);
    $mail->assertSeeInHtml('SLOT');
    \Livewire\Livewire::test(\App\Filament\Resources\DriverSettlements\Pages\ManageDriverSettlements::class)
        ->callTableAction('export')->assertFileDownloaded('settlements-slot.csv');
});
