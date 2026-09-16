<?php

use App\Models\RentalVan;
use App\Models\User;
use App\Models\VanReservation;
use App\Models\WebsiteMenuItem;
use App\Services\VanRentalKanbanService;
use App\Services\VanRentalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-09-10 06:00:00 UTC');
    $this->service = app(VanRentalService::class);
    $this->van = RentalVan::factory()->published()->create();
    $this->data = ['submission_key' => (string) Str::uuid(), 'mode' => 'self_drive', 'starts_at' => '2026-09-12T09:00', 'ends_at' => '2026-09-12T11:30', 'name' => 'Cliente Teste', 'email' => 'teste@example.com', 'phone' => '912345678', 'purpose' => 'moving'];
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('isolates the van catalogue and hides unpublished vehicles and private details', function (): void {
    RentalVan::factory()->create(['name' => 'Carrinha rascunho']);
    $this->get(route('van-rentals.index'))->assertSuccessful()->assertSee($this->van->name)->assertDontSee('Carrinha rascunho')->assertDontSee($this->van->license_plate);
    $this->get($this->van->publicUrl())->assertSuccessful()->assertSee('Planeie o aluguer')->assertDontSee($this->van->license_plate)->assertDontSee('Reserva imediata');
    $this->get('/frota')->assertDontSee($this->van->name);
    $this->van->update(['status' => 'archived']);
    $this->get($this->van->publicUrl())->assertNotFound();
    $this->get(route('van-rentals.quote', $this->van))->assertNotFound();
});

it('presents the availability calendar as a pickup and return date selector', function (): void {
    $this->get($this->van->publicUrl())
        ->assertSuccessful()
        ->assertSee('Selecione primeiro o dia de levantamento e depois o dia de entrega.')
        ->assertSee('data-closes=', false);
});

it('shows up to four featured vans and does not duplicate a configured menu entry', function (): void {
    $this->van->update(['featured' => true]);
    WebsiteMenuItem::factory()->create(['label' => 'Aluguer de carrinhas', 'url' => '/aluguer-carrinhas']);
    $response = $this->get('/')->assertSuccessful()->assertSee($this->van->name);
    expect(substr_count($response->getContent(), 'class="nav-link nav-link-custom" href="/aluguer-carrinhas"'))->toBe(1);
});

it('requires complete publication data', function (): void {
    $this->van->photos = [];
    expect(fn () => $this->van->save())->toThrow(ValidationException::class);
});

it('calculates hours begun minimum hours driver tariff and separate deposit', function (): void {
    $quote = $this->service->quote($this->van, $this->data);
    expect($quote)->hourly_rate->toBe(1500)->billable_hours->toBe(3)->estimated_total->toBe(4500)->deposit->toBe(25000);
    $this->van->update(['minimum_hours' => 4]);
    $quote = $this->service->quote($this->van, [...$this->data, 'mode' => 'with_driver']);
    expect($quote['estimated_total'])->toBe(12000);
});

it('validates dates and operating windows', function (string $start, string $end): void {
    expect(fn () => $this->service->quote($this->van, [...$this->data, 'starts_at' => $start, 'ends_at' => $end]))->toThrow(ValidationException::class);
})->with([
    ['2026-09-09T09:00', '2026-09-09T10:00'],
    ['2026-09-10T08:00', '2026-09-10T10:00'],
    ['2026-09-12T09:15', '2026-09-12T10:00'],
    ['2026-09-12T11:00', '2026-09-12T10:00'],
    ['2026-09-12T06:00', '2026-09-12T10:00'],
    ['2026-09-12T09:00', '2026-10-13T10:00'],
]);

it('rejects nonexistent or ambiguous Lisbon daylight saving times and stores UTC', function (): void {
    expect(fn () => $this->service->date('2026-03-29T01:30'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->date('2026-10-25T01:30'))->toThrow(ValidationException::class);
    expect($this->service->date('2026-09-12T09:00')->format('Y-m-d H:i'))->toBe('2026-09-12 08:00');
});

it('preserves snapshots across price changes and prevents duplicate requests', function (): void {
    $reservation = $this->service->reserve($this->van, $this->data);
    $this->van->update(['self_drive_rate' => 9900, 'deposit' => 90000]);
    $again = $this->service->reserve($this->van, $this->data);
    expect($again->id)->toBe($reservation->id);
    expect($again->estimated_total)->toBe(4500);
    expect($again->deposit)->toBe(25000);
    expect(VanReservation::query()->count())->toBe(1);
    $this->service->reschedule($again, '2026-09-13T09:00', '2026-09-13T11:00', User::factory()->create(), 'Novo horário');
    expect($again->fresh()->estimated_total)->toBe(3000);
    expect($again->events()->count())->toBe(2);
});

it('keeps pending requests available but blocks competing confirmations including preparation time', function (): void {
    $first = $this->service->reserve($this->van, $this->data);
    $second = $this->service->reserve($this->van, [...$this->data, 'submission_key' => (string) Str::uuid()]);
    $user = User::factory()->create();
    $this->service->transition($first, 'confirmed', $user, 'Confirmado');
    expect(fn () => $this->service->transition($second, 'confirmed', $user, 'Confirmado'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->quote($this->van, [...$this->data, 'starts_at' => '2026-09-12T11:30', 'ends_at' => '2026-09-12T12:30']))->toThrow(ValidationException::class);
    expect($this->service->quote($this->van, [...$this->data, 'starts_at' => '2026-09-12T12:00', 'ends_at' => '2026-09-12T13:00'])['billable_hours'])->toBe(1);
});

it('requires a verified driver and rejects invalid status transitions', function (): void {
    $reservation = $this->service->reserve($this->van, [...$this->data, 'mode' => 'with_driver', 'origin' => 'Porto', 'destination' => 'Gaia']);
    $user = User::factory()->create();
    expect(fn () => $this->service->transition($reservation, 'confirmed', $user, 'Confirmado'))->toThrow(ValidationException::class);
    $this->service->transition($reservation, 'confirmed', $user, 'Confirmado', ['driver_name' => 'Responsável', 'driver_verified' => true]);
    expect($reservation->fresh()->driver_name)->toBe('Responsável');
    expect(fn () => $this->service->transition($reservation, 'completed', $user, 'Concluir'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->transition($reservation, 'in_progress', $user, 'Iniciar'))->toThrow(ValidationException::class);
    $this->service->transition($reservation, 'cancelled', $user, 'Cliente cancelou');
    expect($reservation->fresh()->status)->toBe('cancelled');
});

it('exposes only occupied intervals and excludes blocked vans from date filters', function (): void {
    $this->service->block($this->van, '2026-09-12T09:00', '2026-09-12T11:30', 'Razão confidencial', User::factory()->create());
    $response = $this->getJson(route('van-rentals.availability', ['van' => $this->van, 'month' => '2026-09']))->assertSuccessful()->assertJsonCount(1, 'periods')->assertDontSee('Razão confidencial');
    expect(array_keys($response->json('periods.0')))->toBe(['start', 'end']);
    $this->get(route('van-rentals.index', ['starts_at' => $this->data['starts_at'], 'ends_at' => $this->data['ends_at']]))->assertSuccessful()->assertDontSee($this->van->name);
});

it('submits safely with session bound idempotency and retains requests when kanban fails', function (): void {
    $response = $this->get($this->van->publicUrl());
    $key = $response->viewData('submissionKey');
    $payload = [...$this->data, 'submission_key' => $key, 'accept_terms' => '1'];
    $this->post(route('van-rentals.store', $this->van), $payload)->assertRedirect()->assertSessionHas('van_success');
    $this->post(route('van-rentals.store', $this->van), $payload)->assertRedirect();
    expect(VanReservation::query()->count())->toBe(1);
    expect(VanReservation::query()->first()->kanban_error)->not->toBeNull();
    $this->post(route('van-rentals.store', $this->van), [...$payload, 'submission_key' => (string) Str::uuid()])->assertStatus(419);
    $this->post(route('van-rentals.store', $this->van), [...$payload, 'email' => 'invalid'])->assertSessionHasErrors('email');
});

it('retries kanban without duplicate tasks', function (): void {
    $reservation = $this->service->reserve($this->van, $this->data);
    $board = \App\Models\Board::query()->create(['name' => 'Website', 'slug' => 'van-website', 'is_active' => true, 'position' => 1]);
    \App\Models\Stage::query()->create(['board_id' => $board->id, 'name' => 'Pedidos', 'slug' => 'van-pedidos', 'is_initial' => true, 'position' => 1]);
    app(VanRentalKanbanService::class)->sync($reservation);
    app(VanRentalKanbanService::class)->sync($reservation);
    expect($reservation->fresh()->task_id)->not->toBeNull();
    expect(\App\Models\Task::query()->count())->toBe(1);
});

it('filters by modality and minimum volume without exposing drafts', function (): void {
    $this->van->update(['with_driver' => false]);
    $this->get(route('van-rentals.index', ['mode' => 'with_driver']))->assertDontSee($this->van->name);
    $this->get(route('van-rentals.index', ['capacity' => 14]))->assertDontSee($this->van->name);
    $this->getJson(route('van-rentals.quote', ['van' => $this->van, ...$this->data, 'mode' => 'with_driver']))->assertUnprocessable()->assertJsonValidationErrors('mode');
});

it('rejects driver requests without the journey or terms and ignores client supplied prices', function (): void {
    $key = $this->get($this->van->publicUrl())->viewData('submissionKey');
    $payload = [...$this->data, 'submission_key' => $key, 'mode' => 'with_driver'];
    $this->postJson(route('van-rentals.store', $this->van), $payload)->assertUnprocessable()->assertJsonValidationErrors(['origin', 'destination', 'accept_terms']);
    $this->post(route('van-rentals.store', $this->van), [...$payload, 'origin' => 'Porto', 'destination' => 'Gaia', 'accept_terms' => 1, 'estimated_total' => 1, 'hourly_rate' => 1, 'status' => 'confirmed'])->assertRedirect();
    $reservation = VanReservation::query()->firstOrFail();
    expect($reservation->estimated_total)->toBe(9000)->and($reservation->status)->toBe('pending');
});

it('requires rechecking the driver when rescheduling a confirmed journey', function (): void {
    $user = User::factory()->create();
    $reservation = $this->service->reserve($this->van, [...$this->data, 'mode' => 'with_driver']);
    $this->service->transition($reservation, 'confirmed', $user, 'Aceite', ['driver_name' => 'Motorista', 'driver_verified' => true]);
    expect(fn () => $this->service->reschedule($reservation, '2026-09-13T09:00', '2026-09-13T11:00', $user, 'Alterado'))->toThrow(ValidationException::class);
});

it('starts and completes confirmed rentals and rejects terminal changes', function (): void {
    $user = User::factory()->create();
    $reservation = $this->service->reserve($this->van, $this->data);
    $this->service->transition($reservation, 'confirmed', $user, 'Aceite');
    CarbonImmutable::setTestNow('2026-09-12 08:00:00 UTC');
    $this->service->transition($reservation, 'in_progress', $user, 'Viatura entregue');
    $this->service->transition($reservation, 'completed', $user, 'Viatura devolvida');
    expect($reservation->fresh()->status)->toBe('completed');
    expect(fn () => $this->service->transition($reservation, 'confirmed', $user, 'Reabrir'))->toThrow(ValidationException::class);
});

it('rejects blocks and rescheduling that collide with confirmed rentals', function (): void {
    $user = User::factory()->create();
    $reservation = $this->service->reserve($this->van, $this->data);
    $this->service->transition($reservation, 'confirmed', $user, 'Aceite');
    expect(fn () => $this->service->block($this->van, '2026-09-12T09:00', '2026-09-12T12:00', 'Manutenção', $user))->toThrow(ValidationException::class);
    $later = $this->service->reserve($this->van, [...$this->data, 'submission_key' => (string) Str::uuid(), 'starts_at' => '2026-09-13T09:00', 'ends_at' => '2026-09-13T11:00']);
    expect(fn () => $this->service->reschedule($later, $this->data['starts_at'], $this->data['ends_at'], $user, 'Alteração'))->toThrow(ValidationException::class);
    $this->service->transition($reservation, 'cancelled', $user, 'Cancelamento');
    expect($this->service->quote($this->van, $this->data)['estimated_total'])->toBe(4500);
});

it('rejects photo paths outside rental uploads', function (): void {
    $this->van->photos = ['../private/document.pdf'];
    expect(fn () => $this->van->save())->toThrow(ValidationException::class);
});
