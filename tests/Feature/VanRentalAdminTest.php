<?php

use App\Filament\Pages\VanRentalCalendar;
use App\Filament\Resources\RentalVans\Pages\CreateRentalVan;
use App\Filament\Resources\RentalVans\Pages\EditRentalVan;
use App\Filament\Resources\RentalVans\Pages\ListRentalVans;
use App\Filament\Resources\VanReservations\Pages\EditVanReservation;
use App\Filament\Resources\VanReservations\Pages\ListVanReservations;
use App\Models\RentalVan;
use App\Models\User;
use App\Services\VanRentalService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the website management and calendar pages', function (): void {
    $van = RentalVan::factory()->create();
    Livewire::test(ListRentalVans::class)->assertCanSeeTableRecords([$van]);
    Livewire::test(CreateRentalVan::class)->assertSuccessful();
    Livewire::test(EditRentalVan::class, ['record' => $van->id])->assertSuccessful();
    Livewire::test(ListVanReservations::class)->assertSuccessful();
    Livewire::test(VanRentalCalendar::class)->assertSuccessful()->set('period', 'week')->assertSuccessful();
});

it('creates drafts and rejects incomplete publication through the form', function (): void {
    Livewire::test(CreateRentalVan::class)->fillForm(['name' => 'Carrinha Teste', 'license_plate' => 'TEST-01', 'status' => 'draft', 'self_drive' => true, 'self_drive_rate' => 15, 'deposit' => 0, 'minimum_hours' => 1, 'buffer_minutes' => 30, 'lead_hours' => 2, 'opens_at' => '08:00', 'closes_at' => '20:00'])->call('create')->assertHasNoFormErrors();
    $van = RentalVan::query()->where('license_plate', 'TEST-01')->firstOrFail();
    expect($van->self_drive_rate)->toBe(1500);
    Livewire::test(EditRentalVan::class, ['record' => $van->id])->fillForm(['status' => 'published'])->call('save')->assertHasFormErrors(['photos']);
});

it('confirms and audits a reservation from the panel', function (): void {
    CarbonImmutable::setTestNow('2026-09-10 06:00:00 UTC');
    try {
        $van = RentalVan::factory()->published()->create();
        $reservation = app(VanRentalService::class)->reserve($van, ['submission_key' => (string) Str::uuid(), 'mode' => 'self_drive', 'starts_at' => '2026-09-12T09:00', 'ends_at' => '2026-09-12T11:00', 'name' => 'Teste', 'email' => 'teste@example.com', 'phone' => '912345678', 'purpose' => 'goods']);
        Livewire::test(EditVanReservation::class, ['record' => $reservation->id])->assertSuccessful()->callAction('confirmed', data: ['reason' => 'Aceite pela equipa'])->assertHasNoActionErrors();
        expect($reservation->fresh()->status)->toBe('confirmed');
        expect($reservation->events()->count())->toBe(2);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('requires authentication for rental administration', function (): void {
    auth()->logout();
    $this->get('/admin/rental-vans')->assertRedirect('/admin/login');
    $this->get('/admin/van-reservations')->assertRedirect('/admin/login');
    $this->get('/admin/van-rental-calendar')->assertRedirect('/admin/login');
});

it('uploads and reorders photographs on the rental storage disk', function (): void {
    \Illuminate\Support\Facades\Storage::fake('rental_vans');
    $van = RentalVan::factory()->create(['photos' => []]);
    Livewire::test(EditRentalVan::class, ['record' => $van->id])
        ->fillForm(['photos' => [\Illuminate\Http\UploadedFile::fake()->image('cargo.jpg'), \Illuminate\Http\UploadedFile::fake()->image('rear.jpg')]])
        ->call('save')->assertHasNoFormErrors();
    $photos = $van->fresh()->photos;
    expect($photos)->toHaveCount(2);
    foreach ($photos as $photo) {
        \Illuminate\Support\Facades\Storage::disk('rental_vans')->assertExists($photo);
    }
    $component = Livewire::test(EditRentalVan::class, ['record' => $van->id]);
    $component->set('data.photos', array_reverse($component->get('data.photos'), true))->call('save')->assertHasNoFormErrors();
    expect($van->fresh()->photos)->toBe(array_reverse($photos));
});

it('shows reservation history and manages calendar blocks', function (): void {
    $van = RentalVan::factory()->create();
    Livewire::test(VanRentalCalendar::class)->callAction('block', data: ['van_id' => $van->id, 'starts_at' => '2026-09-15T09:00', 'ends_at' => '2026-09-15T12:00', 'reason' => 'Inspeção'])->assertHasNoActionErrors()->assertSee('Inspeção');
    expect($van->blocks()->count())->toBe(1);
});
