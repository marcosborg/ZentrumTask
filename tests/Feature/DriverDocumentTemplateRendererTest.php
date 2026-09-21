<?php

use App\Enums\VehicleRentType;
use App\Models\DocumentTemplate;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleDocument;
use App\Services\DriverDocumentTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('renders automatic contract values from the current billing profile and allocated vehicle', function (): void {
    Carbon::setTestNow('2026-09-21 10:00:00');

    $driver = Driver::factory()->create(['name' => 'Motorista Teste']);
    $profile = DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id,
        'active' => true,
        'valid_from' => '2026-09-01',
        'valid_to' => null,
        'vehicle_rent_type' => VehicleRentType::Weekly,
        'vehicle_rent_value' => 375,
        'extra_km_limit' => 2500,
        'extra_km_rate' => 0.12,
    ]);
    $vehicle = Vehicle::factory()->create();

    VehicleAllocation::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'starts_at' => '2026-09-01 09:00:00',
        'ends_at' => null,
        'status' => 'active',
    ]);

    VehicleDocument::factory()->create([
        'vehicle_id' => $vehicle->id,
        'type' => 'INSURANCE',
        'expires_at' => '2026-10-31',
    ]);
    VehicleDocument::factory()->create([
        'vehicle_id' => $vehicle->id,
        'type' => 'INSURANCE',
        'expires_at' => '2027-10-31',
    ]);
    VehicleDocument::factory()->create([
        'vehicle_id' => $vehicle->id,
        'type' => 'INSPECTION',
        'expires_at' => '2027-06-30',
    ]);

    $template = DocumentTemplate::query()->create([
        'internal_name' => 'automatic-contract-test',
        'name' => 'Contrato automático',
        'content' => <<<'HTML'
<p>{{ date }}</p>
<p>{{billing_profile.vehicle_rent_value}} €</p>
<p>{{ billing_profile.vehicle_rent_value_in_words }}</p>
<p>{{billing_profile.extra_km_limit}} km</p>
<p>{{billing_profile.extra_km_rate}} €/km</p>
<p>{{vehicle.insurance_expires_at}}</p>
<p>{{vehicle.inspection_expires_at}}</p>
<p>{{vehicle.ipo_expires_at}}</p>
HTML,
    ]);

    $html = app(DriverDocumentTemplateRenderer::class)->render($template, $driver);

    expect($html)
        ->toContain('21 de setembro de 2026')
        ->toContain('375,00 €')
        ->toContain('trezentos e setenta e cinco euros')
        ->toContain('2.500 km')
        ->toContain('0,12 €/km')
        ->toContain('31-10-2027')
        ->toContain('30-06-2027')
        ->not->toContain('31-10-2026');

    expect($profile->vehicle_rent_value)->toBe('375.00');
});

it('ignores billing profiles that are not active on the document date', function (): void {
    Carbon::setTestNow('2026-09-21 10:00:00');

    $inactiveDriver = Driver::factory()->create();
    DriverBillingProfile::factory()->create([
        'driver_id' => $inactiveDriver->id,
        'active' => false,
        'valid_from' => '2026-01-01',
        'vehicle_rent_value' => 111,
    ]);

    $futureDriver = Driver::factory()->create();
    DriverBillingProfile::factory()->create([
        'driver_id' => $futureDriver->id,
        'active' => true,
        'valid_from' => '2026-10-01',
        'vehicle_rent_value' => 222,
    ]);

    $currentDriver = Driver::factory()->create();
    DriverBillingProfile::factory()->create([
        'driver_id' => $currentDriver->id,
        'active' => true,
        'valid_from' => '2026-09-01',
        'valid_to' => '2026-09-30',
        'vehicle_rent_value' => 333,
    ]);

    $template = DocumentTemplate::query()->create([
        'internal_name' => 'current-profile-test',
        'name' => 'Perfil atual',
        'content' => '<p>{{billing_profile.vehicle_rent_value}}</p>',
    ]);

    $renderer = app(DriverDocumentTemplateRenderer::class);
    $inactiveHtml = $renderer->render($template, $inactiveDriver);
    $futureHtml = $renderer->render($template, $futureDriver);
    $currentHtml = $renderer->render($template, $currentDriver);

    expect($inactiveHtml)
        ->toContain('<p></p>')
        ->not->toContain('111,00')
        ->and($futureHtml)
        ->toContain('<p></p>')
        ->not->toContain('222,00')
        ->and($currentHtml)
        ->toContain('<p>333,00</p>')
        ->not->toContain('111,00')
        ->not->toContain('222,00');
});
