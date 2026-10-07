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

afterEach(function () {
    Carbon::setTestNow();
});

it('can resume the migration without overwriting an existing expiry cycle', function () {
    Carbon::setTestNow('2026-10-07 08:00:00');
    $document = VehicleDocument::factory()->create(['expires_at' => now()->addYear()]);
    $alert = VehicleDocumentAlert::factory()->for($document, 'document')->create([
        'document_expires_at' => '2026-10-06',
    ]);
    $legacyAlert = VehicleDocumentAlert::factory()->for($document, 'document')->create([
        'document_expires_at' => null,
    ]);

    $migration = require database_path('migrations/2026_10_07_092213_add_document_expires_at_to_vehicle_document_alerts_table.php');
    $migration->up();
    $migration->up();

    expect($alert->fresh()->document_expires_at->toDateString())->toBe('2026-10-06')
        ->and($legacyAlert->fresh()->document_expires_at->toDateString())->toBe('2027-10-07');
});

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

it('does not recreate manually resolved alerts on subsequent days or after editing the title', function () {
    Carbon::setTestNow('2026-10-07 08:00:00');
    Mail::fake();
    User::factory()->create(['name' => 'Adriano Silva']);
    $document = VehicleDocument::factory()->create(['expires_at' => now()->addDays(10)]);

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $alert = $document->alerts()->sole();
    $alert->update(['is_resolved' => true, 'resolved_at' => now()]);
    $document->update(['title' => 'Titulo corrigido']);
    Carbon::setTestNow('2026-10-12 08:00:00');
    Mail::fake();

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    expect($document->alerts()->count())->toBe(1);
    Mail::assertNothingSent();
});

it('resolves old alerts when the expiry is corrected and sends only the remaining pending document', function () {
    Carbon::setTestNow('2026-10-07 08:00:00');
    Mail::fake();
    User::factory()->create(['name' => 'Adriano Silva']);
    $renewed = VehicleDocument::factory()->create(['expires_at' => now()->subDay()]);
    $pending = VehicleDocument::factory()->create(['expires_at' => now()->addDays(5)]);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    $renewed->update(['expires_at' => now()->addYear()]);
    expect($renewed->alerts()->sole()->is_resolved)->toBeTrue();
    Carbon::setTestNow('2026-10-08 08:00:00');
    Mail::fake();

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    Mail::assertSent(VehicleDocumentAlertsSummaryMail::class, function ($mail) use ($pending): bool {
        return $mail->alerts->count() === 1 && $mail->alerts->first()->vehicle_document_id === $pending->id;
    });
    Mail::assertSentCount(1);
});

it('resolves the entire expiry cycle and resumes reminders when it is reopened', function () {
    Carbon::setTestNow('2026-10-07 08:00:00');
    Mail::fake();
    User::factory()->create(['name' => 'Adriano Silva']);
    $document = VehicleDocument::factory()->create(['expires_at' => now()->subDay()]);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Carbon::setTestNow('2026-10-08 08:00:00');
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    $alert = $document->alerts()->latest('id')->firstOrFail();
    $alert->update(['is_resolved' => true, 'resolved_at' => now()]);
    expect($document->alerts()->where('is_resolved', false)->count())->toBe(0);

    $alert->update(['is_resolved' => false, 'resolved_at' => null]);
    expect($document->alerts()->where('is_resolved', false)->count())->toBe(2);
    Carbon::setTestNow('2026-10-09 08:00:00');
    Mail::fake();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    Mail::assertSentCount(1);
});

it('reconciles existing stale alerts even when the expiry was changed without model events', function () {
    Carbon::setTestNow('2026-10-07 08:00:00');
    Mail::fake();
    User::factory()->create(['name' => 'Adriano Silva']);
    $document = VehicleDocument::factory()->create(['expires_at' => now()->subDay()]);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $document->updateQuietly(['expires_at' => now()->addYear()]);
    Carbon::setTestNow('2026-10-08 08:00:00');
    Mail::fake();

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    expect($document->alerts()->sole()->is_resolved)->toBeTrue();
    Mail::assertNothingSent();
});

it('allows alerts for a new expiry even if the previous expiry was resolved on the same day', function () {
    Carbon::setTestNow('2026-10-07 08:00:00');
    Mail::fake();
    User::factory()->create(['name' => 'Adriano Silva']);
    $document = VehicleDocument::factory()->create(['expires_at' => now()->addDays(3)]);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $document->alerts()->sole()->update(['is_resolved' => true, 'resolved_at' => now()]);
    $document->update(['expires_at' => now()->addDays(6)]);
    Mail::fake();

    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    expect($document->alerts()->count())->toBe(2)
        ->and($document->alerts()->where('is_resolved', false)->sole()->document_expires_at->toDateString())
        ->toBe('2026-10-13');
    Mail::assertSentCount(1);
});

it('resolves alerts and stops sending when a document is removed or no longer has an expiry', function (string $correction) {
    Carbon::setTestNow('2026-10-07 08:00:00');
    Mail::fake();
    User::factory()->create(['name' => 'Adriano Silva']);
    $document = VehicleDocument::factory()->create(['expires_at' => now()->subDay()]);
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();
    $alert = $document->alerts()->sole();

    if ($correction === 'delete') {
        $document->delete();
    } else {
        $document->update(['expires_at' => null]);
    }

    Carbon::setTestNow('2026-10-08 08:00:00');
    Mail::fake();
    $this->artisan('app:check-vehicle-documents-expiry')->assertSuccessful();

    expect($alert->fresh()->is_resolved)->toBeTrue()
        ->and($alert->fresh()->resolved_at)->not->toBeNull();
    Mail::assertNothingSent();
})->with(['delete', 'no_expiry']);
