<?php

use App\Filament\Pages\TeslaIntegration;
use App\Mail\TeslaTirePressureAlertMail;
use App\Models\Driver;
use App\Models\TeslaAccount;
use App\Models\TeslaVehicle;
use App\Models\TeslaVehicleSnapshot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Services\TeslaService;
use App\Support\TeslaTirePressureEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.tesla.base_url', 'https://tesla.test');
    config()->set('services.tesla.token_url', 'https://auth.tesla.test/oauth2/v3/token');
});

it('converts bar to psi and evaluates compliant pressures', function (): void {
    $snapshot = new TeslaVehicleSnapshot([
        'tpms_pressure_fl' => 2.90,
        'tpms_pressure_fr' => 2.92,
        'tpms_pressure_rl' => 2.94,
        'tpms_pressure_rr' => 2.96,
    ]);

    $assessment = app(TeslaTirePressureEvaluator::class)->evaluate($snapshot);

    expect($assessment['status'])->toBe('compliant')
        ->and($assessment['pressures'])->toBe([
            'fl' => 42.1,
            'fr' => 42.4,
            'rl' => 42.6,
            'rr' => 42.9,
        ])
        ->and($assessment['difference'])->toBe(0.9);
});

it('treats 42 and 43 psi as inclusive limits', function (): void {
    $evaluator = app(TeslaTirePressureEvaluator::class);
    $snapshot = new TeslaVehicleSnapshot([
        'tpms_pressure_fl' => 42 / 14.5038,
        'tpms_pressure_fr' => 42 / 14.5038,
        'tpms_pressure_rl' => 43 / 14.5038,
        'tpms_pressure_rr' => 43 / 14.5038,
    ]);

    expect($evaluator->evaluate($snapshot)['status'])->toBe('compliant');
});

it('does not accept pressure above the maximum', function (): void {
    $snapshot = new TeslaVehicleSnapshot([
        'tpms_pressure_fl' => 43.04 / 14.5038,
        'tpms_pressure_fr' => 42.5 / 14.5038,
        'tpms_pressure_rl' => 42.5 / 14.5038,
        'tpms_pressure_rr' => 42.5 / 14.5038,
    ]);

    $assessment = app(TeslaTirePressureEvaluator::class)->evaluate($snapshot);

    expect($assessment['pressures']['fl'])->toBe(43.1)
        ->and($assessment['status'])->toBe('abnormal');
});

it('reports out of range and unbalanced pressures', function (): void {
    $snapshot = new TeslaVehicleSnapshot([
        'tpms_pressure_fl' => 2.80,
        'tpms_pressure_fr' => 2.90,
        'tpms_pressure_rl' => 2.94,
        'tpms_pressure_rr' => 3.05,
    ]);

    $assessment = app(TeslaTirePressureEvaluator::class)->evaluate($snapshot);

    expect($assessment['status'])->toBe('abnormal')
        ->and($assessment['problems'])->toHaveCount(3)
        ->and(implode(' ', $assessment['problems']))->toContain('Diferenca');
});

it('returns no data when one or more readings are missing', function (): void {
    $snapshot = new TeslaVehicleSnapshot([
        'tpms_pressure_fl' => 2.90,
        'tpms_pressure_fr' => 2.90,
        'tpms_pressure_rl' => null,
        'tpms_pressure_rr' => 2.90,
    ]);

    expect(app(TeslaTirePressureEvaluator::class)->evaluate($snapshot)['status'])->toBe('no_data');
});

it('captures current tire pressures in a new snapshot', function (): void {
    Http::fake([
        'tesla.test/api/1/vehicles/*/vehicle_data*' => Http::response([
            'response' => [
                'vehicle_state' => [
                    'tpms_pressure_fl' => 2.90,
                    'tpms_pressure_fr' => 2.91,
                    'tpms_pressure_rl' => 2.92,
                    'tpms_pressure_rr' => 2.93,
                ],
            ],
        ]),
    ]);

    $account = TeslaAccount::factory()->create([
        'access_token' => encrypt('access-token'),
        'expires_at' => now()->addHour(),
    ]);
    $vehicle = TeslaVehicle::factory()->create([
        'tesla_account_id' => $account->id,
        'vin' => '5YJ3E1EA7JF000001',
    ]);

    $snapshot = app(TeslaService::class)->captureTirePressureSnapshot($vehicle);

    expect($snapshot)->not->toBeNull()
        ->and((float) $snapshot->tpms_pressure_fl)->toBe(2.90)
        ->and((float) $snapshot->tpms_pressure_rr)->toBe(2.93)
        ->and($vehicle->snapshots()->count())->toBe(1);
});

it('sends an alert only to the currently allocated driver', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    $driver = Driver::factory()->create(['email' => 'motorista@example.com']);
    $internalVehicle = Vehicle::query()->create([
        'license_plate' => 'AA-00-AA',
        'vin' => '5YJ3E1EA7JF000002',
        'make' => 'Tesla',
        'model' => 'Model 3',
        'status' => 'allocated',
    ]);
    VehicleAllocation::factory()->create([
        'vehicle_id' => $internalVehicle->id,
        'driver_id' => $driver->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => null,
    ]);
    $vehicle = TeslaVehicle::factory()->create([
        'vehicle_id' => $internalVehicle->id,
        'display_name' => 'AA-00-AA',
        'state' => 'online',
    ]);
    TeslaVehicleSnapshot::query()->create([
        'tesla_vehicle_id' => $vehicle->id,
        'recorded_at' => now(),
        'tpms_pressure_fl' => 2.80,
        'tpms_pressure_fr' => 2.90,
        'tpms_pressure_rl' => 2.90,
        'tpms_pressure_rr' => 2.90,
        'raw_payload' => [],
    ]);

    Livewire::actingAs($user)
        ->test(TeslaIntegration::class)
        ->assertTableActionVisible('emailTirePressureAlert', $vehicle)
        ->assertTableActionEnabled('emailTirePressureAlert', $vehicle)
        ->callTableAction('emailTirePressureAlert', $vehicle)
        ->assertNotified('Aviso enviado');

    Mail::assertSent(TeslaTirePressureAlertMail::class, 'motorista@example.com');
});

it('sends all alerts only to eligible drivers', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    $sequence = 0;
    $createVehicle = function (string $email, float $pressure) use (&$sequence): TeslaVehicle {
        $sequence++;
        $plate = sprintf('AA-%02d-BB', $sequence);
        $driver = Driver::factory()->create(['email' => $email]);
        $internalVehicle = Vehicle::query()->create([
            'license_plate' => $plate,
            'vin' => '5YJ3E1EA7JF'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'make' => 'Tesla',
            'model' => 'Model 3',
            'status' => 'allocated',
        ]);
        VehicleAllocation::factory()->create([
            'vehicle_id' => $internalVehicle->id,
            'driver_id' => $driver->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);
        $vehicle = TeslaVehicle::factory()->create([
            'vehicle_id' => $internalVehicle->id,
            'display_name' => $plate,
            'state' => 'online',
        ]);
        TeslaVehicleSnapshot::query()->create([
            'tesla_vehicle_id' => $vehicle->id,
            'recorded_at' => now(),
            'tpms_pressure_fl' => $pressure,
            'tpms_pressure_fr' => 2.90,
            'tpms_pressure_rl' => 2.90,
            'tpms_pressure_rr' => 2.90,
            'raw_payload' => [],
        ]);

        return $vehicle;
    };

    $createVehicle('primeiro@example.com', 2.80);
    $createVehicle('segundo@example.com', 2.80);
    $createVehicle('conforme@example.com', 2.90);

    Livewire::actingAs($user)
        ->test(TeslaIntegration::class)
        ->mountAction('emailAllTirePressureAlerts')
        ->assertActionMounted('emailAllTirePressureAlerts');

    Livewire::actingAs($user)
        ->test(TeslaIntegration::class)
        ->callAction('emailAllTirePressureAlerts')
        ->assertNotified('Envio de avisos concluido');

    Mail::assertSent(TeslaTirePressureAlertMail::class, 2);
    Mail::assertSent(TeslaTirePressureAlertMail::class, 'primeiro@example.com');
    Mail::assertSent(TeslaTirePressureAlertMail::class, 'segundo@example.com');
    Mail::assertNotSent(TeslaTirePressureAlertMail::class, 'conforme@example.com');
});

it('renders the pressure readings risks and manufacturer disclaimer in the email', function (): void {
    $driver = Driver::factory()->make(['name' => 'Maria', 'email' => 'maria@example.com']);
    $vehicle = TeslaVehicle::factory()->make(['display_name' => 'AA-00-AA']);
    $assessment = [
        'status' => 'abnormal',
        'pressures' => ['fl' => 40.6, 'fr' => 42.1, 'rl' => 42.1, 'rr' => 42.1],
        'difference' => 1.5,
        'problems' => ['Pneu dianteiro esquerdo fora do intervalo.'],
    ];
    $mail = new TeslaTirePressureAlertMail($driver, $vehicle, $assessment);

    $mail->assertHasSubject('Aviso de pressao dos pneus - AA-00-AA')
        ->assertSeeInHtml('40,6 PSI')
        ->assertSeeInHtml('reduzir a aderencia')
        ->assertSeeInHtml('desgaste irregular')
        ->assertSeeInHtml('nao substitui as especificacoes do fabricante');
});

it('disables the alert action without a current driver email', function (): void {
    $user = User::factory()->create();
    $vehicle = TeslaVehicle::factory()->create(['state' => 'online']);
    TeslaVehicleSnapshot::query()->create([
        'tesla_vehicle_id' => $vehicle->id,
        'recorded_at' => now(),
        'tpms_pressure_fl' => 2.80,
        'tpms_pressure_fr' => 2.90,
        'tpms_pressure_rl' => 2.90,
        'tpms_pressure_rr' => 2.90,
        'raw_payload' => [],
    ]);

    Livewire::actingAs($user)
        ->test(TeslaIntegration::class)
        ->assertTableActionVisible('emailTirePressureAlert', $vehicle)
        ->assertTableActionDisabled('emailTirePressureAlert', $vehicle);
});

it('treats an offline vehicle as having no current pressure data', function (): void {
    $user = User::factory()->create();
    $vehicle = TeslaVehicle::factory()->create(['state' => 'offline']);
    TeslaVehicleSnapshot::query()->create([
        'tesla_vehicle_id' => $vehicle->id,
        'recorded_at' => now()->subHour(),
        'tpms_pressure_fl' => 2.80,
        'tpms_pressure_fr' => 2.90,
        'tpms_pressure_rl' => 2.90,
        'tpms_pressure_rr' => 2.90,
        'raw_payload' => [],
    ]);

    Livewire::actingAs($user)
        ->test(TeslaIntegration::class)
        ->assertTableActionHidden('emailTirePressureAlert', $vehicle);
});
