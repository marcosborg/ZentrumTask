<?php

use App\Mail\VehicleDocumentAlertsSummaryMail;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('emails all daily alerts to Adriano and only TVDE alerts to Marcos', function () {
    Carbon::setTestNow('2026-07-29 08:00:00');
    Mail::fake();

    $adriano = User::factory()->create([
        'name' => 'Adriano Silva',
        'email' => 'adriano@example.com',
    ]);
    $marcos = User::factory()->create([
        'name' => 'Marcos Borges',
        'email' => 'marcos@example.com',
    ]);

    $tvdeVehicle = Vehicle::factory()->create(['source' => 'tvde']);
    $otherVehicle = Vehicle::factory()->create(['source' => 'fleet']);

    VehicleDocument::factory()->for($tvdeVehicle)->create([
        'title' => 'Seguro TVDE',
        'expires_at' => now()->addDays(5),
    ]);
    VehicleDocument::factory()->for($otherVehicle)->create([
        'title' => 'Seguro interno',
        'expires_at' => now()->addDays(20),
    ]);

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    expect(VehicleDocumentAlert::query()->count())->toBe(2);

    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, function (VehicleDocumentAlertsSummaryMail $mail) use ($adriano): bool {
        return $mail->hasTo($adriano->email) && $mail->alerts->count() === 2;
    });
    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, function (VehicleDocumentAlertsSummaryMail $mail) use ($marcos): bool {
        return $mail->hasTo($marcos->email)
            && $mail->alerts->count() === 1
            && $mail->alerts->first()->document->vehicle->source === 'tvde';
    });

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, 2);
});

it('creates expiry alerts up to 60 days before expiry', function () {
    Carbon::setTestNow('2026-07-29 08:00:00');
    Mail::fake();

    $vehicle = Vehicle::factory()->create();

    $expiringIn60Days = VehicleDocument::factory()->for($vehicle)->create([
        'title' => 'Documento dentro do prazo de alerta',
        'expires_at' => now()->addDays(60),
    ]);
    $expiringIn61Days = VehicleDocument::factory()->for($vehicle)->create([
        'title' => 'Documento fora do prazo de alerta',
        'expires_at' => now()->addDays(61),
    ]);

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    $alert = VehicleDocumentAlert::query()
        ->whereBelongsTo($expiringIn60Days, 'document')
        ->sole();

    expect($alert->level)->toBe('expiring_60')
        ->and($alert->message)->toBe('Documento a expirar em 60 dias: Documento dentro do prazo de alerta')
        ->and(VehicleDocumentAlert::query()->whereBelongsTo($expiringIn61Days, 'document')->exists())->toBeFalse();
});
