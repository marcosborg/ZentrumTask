<?php

use App\Filament\Pages\DriverSettlementsReport;
use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\DriverBalance;
use App\Models\DriverBillingProfile;
use App\Models\DriverParticipation;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Models\Vehicle;
use App\Models\VehicleWeeklyMileage;
use App\Services\DriverSettlementCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createReportSettlement(array $attributes = []): DriverSettlement
{
    $driver = $attributes['driver'] ?? Driver::factory()->create();

    unset($attributes['driver']);

    return DriverSettlement::query()->create(array_merge([
        'driver_id' => $driver->id,
        'period_start' => '2026-05-04',
        'period_end' => '2026-05-10',
        'net_total' => 1000,
        'tips_total' => 0,
        'expenses_total' => 0,
        'carry_over_balance' => 0,
        'company_share' => 400,
        'driver_share' => 600,
        'amount_payable' => 600,
        'amount_due' => 600,
        'amount_transferred' => 0,
        'is_paid' => false,
        'rules_snapshot' => [],
    ], $attributes));
}

it('shows original file amounts only in the two specified platform columns', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    $camilo = Driver::factory()->create(['id' => 20, 'name' => 'Camilo Ricardo Correia Tristão']);
    $zacaria = Driver::factory()->create(['id' => 30, 'name' => 'Zacaria Isabel Fernandes Vasconcelos']);

    $camiloParticipation = DriverParticipation::factory()->create([
        'id' => 14,
        'driver_id' => $camilo->id,
        'operation' => 'rental',
        'starts_at' => '2026-10-01',
    ]);
    $zacariaParticipation = DriverParticipation::factory()->create([
        'id' => 24,
        'driver_id' => $zacaria->id,
        'operation' => 'rental',
        'starts_at' => '2026-10-01',
    ]);

    $camiloSettlement = createReportSettlement([
        'driver' => $camilo,
        'driver_participation_id' => $camiloParticipation->id,
        'operation' => 'rental',
        'period_start' => '2026-10-05',
        'period_end' => '2026-10-11',
        'net_total' => 444.23,
        'amount_payable' => 116.56,
    ]);
    $zacariaSettlement = createReportSettlement([
        'driver' => $zacaria,
        'driver_participation_id' => $zacariaParticipation->id,
        'operation' => 'rental',
        'period_start' => '2026-10-05',
        'period_end' => '2026-10-11',
        'net_total' => 860.49,
        'amount_payable' => 321.58,
    ]);

    foreach ([
        [$camilo, $camiloParticipation, 'uber', 544.23],
        [$zacaria, $zacariaParticipation, 'bolt', 469.88],
    ] as [$driver, $participation, $platform, $amount]) {
        PlatformDriverBalance::query()->create([
            'driver_id' => $driver->id,
            'driver_participation_id' => $participation->id,
            'operation' => 'rental',
            'platform' => $platform,
            'driver_code' => 'report-display-test-'.$driver->id,
            'period_start' => '2026-10-05',
            'period_end' => '2026-10-11',
            'net_amount' => $amount,
            'tips_amount' => 0,
            'source_file' => 'test.csv',
            'imported_at' => now(),
        ]);
    }

    Livewire::test(DriverSettlementsReport::class, [
        'filtersForm' => ['period_start' => '2026-10-05', 'period_end' => '2026-10-11'],
    ])
        ->assertTableColumnStateSet('uber_net', 544.23, $camiloSettlement->id)
        ->assertTableColumnFormattedStateSet('uber_net', '444,23 €', $camiloSettlement->id)
        ->assertTableColumnFormattedStateSet('bolt_net', '0,00 €', $camiloSettlement->id)
        ->assertTableColumnStateSet('bolt_net', 469.88, $zacariaSettlement->id)
        ->assertTableColumnFormattedStateSet('bolt_net', '366,31 €', $zacariaSettlement->id)
        ->assertTableColumnFormattedStateSet('uber_net', '0,00 €', $zacariaSettlement->id)
        ->assertTableColumnStateSet('total_operadores', 544.23, $camiloSettlement->id)
        ->assertTableColumnStateSet('total_operadores', 469.88, $zacariaSettlement->id);

    expect($camiloSettlement->refresh()->amount_payable)->toBe('116.56')
        ->and($zacariaSettlement->refresh()->amount_payable)->toBe('321.58')
        ->and(PlatformDriverBalance::query()->where('driver_id', $camilo->id)->value('net_amount'))->toBe('544.23')
        ->and(PlatformDriverBalance::query()->where('driver_id', $zacaria->id)->value('net_amount'))->toBe('469.88');
});

it('deletes a manual adjustment directly from the settlements report modal', function () {
    $driver = Driver::factory()->create();

    $adjustment = DriverAdjustment::query()->create([
        'driver_id' => $driver->id,
        'starts_at' => '2026-03-02',
        'recurrence_weeks' => null,
        'category' => 'acerto',
        'description' => 'Ajuste para eliminar',
        'amount' => 42.50,
        'external_ref' => 'manual-test-delete',
        'raw_row' => ['origin' => 'test'],
        'source_file' => 'manual',
        'imported_at' => now(),
    ]);

    $page = new DriverSettlementsReport;
    $page->deleteManualAdjustment($driver->id, $adjustment->id);

    expect(DriverAdjustment::query()->find($adjustment->id))->toBeNull();
});

it('marks a settlement as paid without a green receipt', function () {
    $settlement = createReportSettlement();
    DriverBalance::query()->create([
        'driver_id' => $settlement->driver_id,
        'current_balance' => 600,
        'is_settled' => false,
        'last_settlement_id' => $settlement->id,
    ]);

    $page = new DriverSettlementsReport;

    expect($page->markSettlementPaid($settlement))->toBeTrue();

    $settlement->refresh();

    expect($settlement->is_paid)->toBeTrue()
        ->and($settlement->amount_due)->toBe('0.00')
        ->and($settlement->amount_transferred)->toBe('600.00')
        ->and($settlement->paid_at)->not->toBeNull();
});

it('marks a settlement as paid when a green receipt exists', function () {
    Storage::fake('local');

    $settlement = createReportSettlement();
    DriverBalance::query()->create([
        'driver_id' => $settlement->driver_id,
        'current_balance' => 600,
        'is_settled' => false,
        'last_settlement_id' => $settlement->id,
    ]);

    Storage::disk('local')->put('driver-settlement-receipts/'.$settlement->id.'/recibo.pdf', 'receipt');

    $page = new DriverSettlementsReport;
    $page->saveGreenReceipt($settlement, 'driver-settlement-receipts/'.$settlement->id.'/recibo.pdf');

    expect($page->markSettlementPaid($settlement))->toBeTrue();

    $settlement->refresh();

    expect($settlement->is_paid)->toBeTrue()
        ->and($settlement->amount_due)->toBe('0.00')
        ->and($settlement->amount_transferred)->toBe('600.00')
        ->and($settlement->paid_at)->not->toBeNull();
});

it('replaces the previous green receipt file on upload', function () {
    Storage::fake('local');

    $settlement = createReportSettlement([
        'green_receipt_path' => 'driver-settlement-receipts/1/old.pdf',
    ]);

    Storage::disk('local')->put('driver-settlement-receipts/1/old.pdf', 'old');
    Storage::disk('local')->put('driver-settlement-receipts/1/new.pdf', 'new');

    $page = new DriverSettlementsReport;
    $page->saveGreenReceipt($settlement, 'driver-settlement-receipts/1/new.pdf');

    Storage::disk('local')->assertMissing('driver-settlement-receipts/1/old.pdf');
    Storage::disk('local')->assertExists('driver-settlement-receipts/1/new.pdf');

    expect($settlement->refresh()->green_receipt_path)->toBe('driver-settlement-receipts/1/new.pdf')
        ->and($settlement->green_receipt_uploaded_at)->not->toBeNull();
});

it('uploads a green receipt through the table action', function () {
    Storage::fake('local');
    $this->actingAs(\App\Models\User::factory()->create());

    $settlement = createReportSettlement();

    Livewire::test(DriverSettlementsReport::class)
        ->assertTableActionExists(
            'manageGreenReceipt',
            fn (\Filament\Actions\Action $action): bool => $action->getExtraAttributes()['class'] === 'hidden',
            $settlement,
        )
        ->callTableAction('manageGreenReceipt', $settlement, [
            'green_receipt_file' => UploadedFile::fake()->create('recibo-verde.pdf', 64, 'application/pdf'),
        ]);

    $settlement->refresh();

    expect($settlement->green_receipt_path)->not->toBeNull()
        ->and($settlement->green_receipt_uploaded_at)->not->toBeNull();

    Storage::disk('local')->assertExists($settlement->green_receipt_path);
});

it('filters settlements by receipt and payment status', function (bool $withReceipt, bool $withoutReceipt, bool $unpaid, bool $paid) {
    $this->actingAs(\App\Models\User::factory()->create());
    $settlements = collect([
        createReportSettlement(['green_receipt_path' => 'receipts/one.pdf', 'is_paid' => false]),
        createReportSettlement(['green_receipt_path' => 'receipts/two.pdf', 'is_paid' => true]),
        createReportSettlement(['green_receipt_path' => null, 'is_paid' => false]),
        createReportSettlement(['green_receipt_path' => '', 'is_paid' => true]),
    ]);
    $expected = $settlements->filter(fn (DriverSettlement $settlement): bool => (! $withReceipt || filled($settlement->green_receipt_path))
        && (! $withoutReceipt || blank($settlement->green_receipt_path))
        && (! $unpaid || ! $settlement->is_paid)
        && (! $paid || $settlement->is_paid)
    );

    Livewire::test(DriverSettlementsReport::class, [
        'filtersForm' => ['period_start' => '2026-05-04', 'period_end' => '2026-05-10'],
    ])
        ->set('filtersForm.with_receipt', $withReceipt)
        ->set('filtersForm.without_receipt', $withoutReceipt)
        ->set('filtersForm.unpaid', $unpaid)
        ->set('filtersForm.paid', $paid)
        ->call('applyFilters')
        ->assertCanSeeTableRecords($expected)
        ->assertCanNotSeeTableRecords($settlements->diff($expected))
        ->set('filtersForm.with_receipt', false)
        ->set('filtersForm.without_receipt', false)
        ->set('filtersForm.unpaid', false)
        ->set('filtersForm.paid', false)
        ->call('applyFilters')
        ->assertCanSeeTableRecords($settlements);
})->with([
    'no filters' => [false, false, false, false],
    'with receipt' => [true, false, false, false],
    'without receipt' => [false, true, false, false],
    'unpaid' => [false, false, true, false],
    'paid' => [false, false, false, true],
    'with receipt and unpaid' => [true, false, true, false],
    'without receipt and paid' => [false, true, false, true],
]);

it('shows upload and open receipt controls in the settlement column', function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $settlement = createReportSettlement();

    $page = Livewire::test(DriverSettlementsReport::class, [
        'filtersForm' => ['period_start' => '2026-05-04', 'period_end' => '2026-05-10'],
    ])->assertSee('Carregar PDF')->assertDontSee('Abrir recibo');

    $settlement->update(['green_receipt_path' => 'driver-settlement-receipts/'.$settlement->id.'/receipt.pdf']);

    $page->call('$refresh')->assertSee('Abrir recibo')->assertSee('Substituir PDF');
});

it('opens a green receipt inline while keeping downloads available', function () {
    Storage::fake('local');
    $this->actingAs(\App\Models\User::factory()->create());
    $settlement = createReportSettlement(['green_receipt_path' => 'driver-settlement-receipts/receipt.pdf']);
    Storage::disk('local')->put($settlement->green_receipt_path, "%PDF-1.4\n%%EOF");

    $this->get(route('driver-settlements.green-receipt.download', [$settlement, 'preview' => 1]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('driver-settlements.green-receipt.download', $settlement))
        ->assertDownload('receipt.pdf');
});

it('stores and serves green receipts from the configured private disk', function () {
    config()->set('filesystems.settlement_receipts_disk', 's3');
    Storage::fake('s3');
    $this->actingAs(\App\Models\User::factory()->create());

    $settlement = createReportSettlement([
        'green_receipt_path' => 'driver-settlement-receipts/1/old.pdf',
    ]);
    Storage::disk('s3')->put('driver-settlement-receipts/1/old.pdf', 'old');
    Storage::disk('s3')->put('driver-settlement-receipts/1/new.pdf', "%PDF-1.4\n%%EOF");

    $page = new DriverSettlementsReport;
    $page->saveGreenReceipt($settlement, 'driver-settlement-receipts/1/new.pdf');

    Storage::disk('s3')->assertMissing('driver-settlement-receipts/1/old.pdf');
    Storage::disk('s3')->assertExists('driver-settlement-receipts/1/new.pdf');

    $this->get(route('driver-settlements.green-receipt.download', [$settlement, 'preview' => 1]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('driver-settlements.green-receipt.download', $settlement))
        ->assertDownload('new.pdf');
});

it('protects receipt previews and handles missing files', function () {
    Storage::fake('local');
    $settlement = createReportSettlement(['green_receipt_path' => 'driver-settlement-receipts/missing.pdf']);
    $url = route('driver-settlements.green-receipt.download', [$settlement, 'preview' => 1]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(\App\Models\User::factory()->create())->get($url)->assertNotFound();
});

it('derives the weekly workflow checklist from email receipt and payment state', function () {
    $settlement = createReportSettlement([
        'email_sent_count' => 1,
        'green_receipt_path' => 'driver-settlement-receipts/1/recibo.pdf',
        'is_paid' => true,
    ]);

    $page = new DriverSettlementsReport;

    expect($page->settlementChecklist($settlement))->toBe([
        'Email',
        'Recibo',
        'Pago',
    ]);
});

it('hides weekly mileage while keeping the extra km monetary value', function () {
    $settlement = createReportSettlement();
    $vehicle = Vehicle::factory()->create();

    VehicleWeeklyMileage::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $settlement->driver_id,
        'period_start' => '2026-05-04',
        'period_end' => '2026-05-10',
        'weekly_km' => 2350,
        'assignment_status' => 'ok',
        'raw_row' => [
            'previous_odometer' => 101250.4,
            'current_odometer' => 103600.4,
        ],
    ]);

    Livewire::test(DriverSettlementsReport::class)
        ->assertDontSee('Km semana')
        ->assertDontSee('2 350,0 km')
        ->assertSee('Km extra');
});

it('shows only the extra km monetary value in the client settlement email', function () {
    $html = view('emails.driver-settlement-summary', [
        'payload' => [
            'expenses' => [
                'extra_km_total' => '64,40 €',
            ],
        ],
    ])->render();

    expect($html)
        ->toContain('<strong>Km extra</strong><br>64,40 €')
        ->not->toContain('Km semana');
});

it('allows the extra km charge to be overridden directly on a settlement', function () {
    $settlement = createReportSettlement([
        'expenses_total' => 200,
        'amount_payable' => 400,
        'amount_due' => 400,
        'rules_snapshot' => [
            'amount_payable_base' => 400,
            'vat_multiplier' => 1,
            'extra_km_total' => 120,
        ],
    ]);

    DriverBalance::query()->create([
        'driver_id' => $settlement->driver_id,
        'current_balance' => 400,
        'last_settlement_id' => $settlement->id,
        'is_settled' => false,
    ]);

    (new DriverSettlementsReport)->setExtraKmOverride($settlement, 0);

    $settlement->refresh();

    expect((float) $settlement->expenses_total)->toBe(80.0)
        ->and((float) $settlement->amount_payable)->toBe(520.0)
        ->and((float) $settlement->amount_due)->toBe(520.0)
        ->and((float) data_get($settlement->rules_snapshot, 'extra_km_calculated_total'))->toBe(120.0)
        ->and((float) data_get($settlement->rules_snapshot, 'extra_km_override'))->toBe(0.0)
        ->and((float) DriverBalance::query()->where('driver_id', $settlement->driver_id)->value('current_balance'))->toBe(520.0);
});

it('recalculates only one driver settlement without changing the others', function () {
    $driverOne = Driver::factory()->create();
    $driverTwo = Driver::factory()->create();

    DriverBillingProfile::factory()->create([
        'driver_id' => $driverOne->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
    ]);

    DriverBillingProfile::factory()->create([
        'driver_id' => $driverTwo->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 40,
        'percent_driver' => 60,
        'vat_percent' => 23,
    ]);

    DB::table('platform_driver_balances')->insert([
        [
            'platform' => 'bolt',
            'driver_code' => 'driver-one',
            'driver_id' => $driverOne->id,
            'period_start' => '2026-02-02',
            'period_end' => '2026-02-08',
            'net_amount' => 100.00,
            'tips_amount' => 0.00,
            'source_file' => 'test.csv',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'platform' => 'bolt',
            'driver_code' => 'driver-two',
            'driver_id' => $driverTwo->id,
            'period_start' => '2026-02-02',
            'period_end' => '2026-02-08',
            'net_amount' => 200.00,
            'tips_amount' => 0.00,
            'source_file' => 'test.csv',
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    app(\App\Services\DriverSettlementCalculator::class)->calculate('2026-02-02', '2026-02-08');

    $driverOneSettlement = DriverSettlement::query()
        ->where('driver_id', $driverOne->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    $driverTwoSettlement = DriverSettlement::query()
        ->where('driver_id', $driverTwo->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    expect($driverOneSettlement->amount_due)->toBe('60.00')
        ->and($driverTwoSettlement->amount_due)->toBe('120.00');

    DB::table('platform_driver_balances')
        ->where('driver_id', $driverOne->id)
        ->update([
            'net_amount' => 300.00,
            'updated_at' => now(),
        ]);

    $page = new DriverSettlementsReport;
    $result = $page->recalculateSettlement($driverOneSettlement);

    $recalculatedDriverOneSettlement = DriverSettlement::query()
        ->where('driver_id', $driverOne->id)
        ->whereDate('period_start', '2026-02-02')
        ->whereDate('period_end', '2026-02-08')
        ->firstOrFail();

    $unchangedDriverTwoSettlement = DriverSettlement::query()
        ->whereKey($driverTwoSettlement->id)
        ->firstOrFail();

    expect($result)->toMatchArray([
        'deleted' => 1,
        'created' => 1,
        'skipped' => 0,
        'missing_profiles' => 0,
    ])
        ->and($recalculatedDriverOneSettlement->id)->not->toBe($driverOneSettlement->id)
        ->and($recalculatedDriverOneSettlement->amount_due)->toBe('180.00')
        ->and($unchangedDriverTwoSettlement->id)->toBe($driverTwoSettlement->id)
        ->and($unchangedDriverTwoSettlement->amount_due)->toBe('120.00');
});

it('shows the protected extra km expense in the report', function (bool $hasPreviousWeek, float $weeklyKm, float $expected): void {
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'AA-11-BB',
        'make' => 'Toyota',
        'model' => 'Corolla',
        'status' => 'available',
    ]);

    DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id,
        'active' => true,
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'percent_company' => 0,
        'percent_driver' => 100,
        'vehicle_rent_value' => null,
        'extra_km_limit' => 2000,
        'extra_km_rate' => 0.12,
    ]);

    DB::table('platform_driver_balances')->insert([
        'platform' => 'uber',
        'driver_code' => 'driver-report-extra-km',
        'driver_id' => $driver->id,
        'period_start' => '2026-04-13',
        'period_end' => '2026-04-19',
        'net_amount' => 1000.00,
        'tips_amount' => 0.00,
        'source_file' => 'test.csv',
        'imported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($hasPreviousWeek) {
        VehicleWeeklyMileage::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'period_start' => '2026-04-06',
            'period_end' => '2026-04-12',
        ]);
    }

    VehicleWeeklyMileage::query()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'period_start' => '2026-04-13',
        'period_end' => '2026-04-19',
        'weekly_km' => $weeklyKm,
        'assignment_status' => 'ok',
        'source_file' => 'kms.csv',
        'imported_at' => now(),
    ]);

    app(DriverSettlementCalculator::class)->calculate('2026-04-13', '2026-04-19', $driver->id);

    $settlement = DriverSettlement::query()
        ->where('driver_id', $driver->id)
        ->whereDate('period_start', '2026-04-13')
        ->whereDate('period_end', '2026-04-19')
        ->firstOrFail();

    $page = new DriverSettlementsReport;

    (function () use ($settlement): void {
        $this->billingCache = [
            $settlement->id => [
                'billing_profile_id' => data_get($settlement->rules_snapshot, 'billing_profile_id'),
            ],
        ];
        $this->billingCacheBuilt = true;
    })->call($page);

    $expenses = (function (DriverSettlement $record): array {
        return $this->extraKmExpensesForSettlement($record);
    })->call($page, $settlement);

    expect((float) data_get($settlement->rules_snapshot, 'extra_km_total'))->toBe($expected)
        ->and($expenses['total'])->toBe($expected)
        ->and($expenses['count'])->toBe($expected > 0 ? 1 : 0);

    if ($expected > 0) {
        expect($expenses['rows'][0])->toMatchArray([
            'total_km' => $weeklyKm,
            'weekly_km' => $weeklyKm,
            'extra_km' => $weeklyKm - 2000,
            'amount' => $expected,
            'vehicle_id' => $vehicle->id,
        ]);
    } else {
        expect($expenses['rows'])->toBe([]);
    }
})->with([
    'without prior week' => [false, 2250.0, 0.0],
    'above sixty euros' => [true, 4159.0, 0.0],
    'valid charge' => [true, 2250.0, 30.0],
    'exactly sixty euros' => [true, 2500.0, 60.0],
]);
