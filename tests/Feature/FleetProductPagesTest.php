<?php

use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use App\Models\Vehicle;
use App\Models\VehicleWebsitePhoto;
use App\Services\AndroidPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createWebsiteLeadStage(): Stage
{
    Board::query()->create([
        'name' => 'Arquivo',
        'slug' => 'arquivo',
        'is_active' => false,
        'position' => 0,
    ]);

    $board = Board::query()->create([
        'name' => 'Website',
        'slug' => 'website',
        'is_active' => true,
        'position' => 1,
    ]);

    return Stage::query()->create([
        'board_id' => $board->id,
        'name' => 'Novos contactos',
        'slug' => 'novos-contactos',
        'position' => 1,
        'is_initial' => true,
        'is_final' => false,
        'freeze_sla' => false,
    ]);
}

it('shows tvde vehicles on the homepage and links to the product page', function () {
    $vehicle = Vehicle::query()->create([
        'license_plate' => 'AA-11-BB',
        'vin' => '5YJ3E1EA7JF000001',
        'make' => 'Tesla',
        'model' => 'Model 3',
        'trim' => 'Long Range',
        'source' => 'tvde',
        'status' => 'available',
        'weekly_rental_price' => 325.50,
        'notes' => 'Autonomia alargada e conforto para TVDE.',
    ]);

    Vehicle::query()->create([
        'license_plate' => '55-EE-66',
        'vin' => '5YJ3E1EA7JF000011',
        'make' => 'BMW',
        'model' => 'i4',
        'source' => 'private',
        'status' => 'maintenance',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Tesla Model 3 Long Range')
        ->assertSee('Tesla')
        ->assertSee('Disponivel')
        ->assertSee('Aluguer semanal')
        ->assertSee('325,50&euro;', false)
        ->assertSee(route('vehicle.index'), false)
        ->assertDontSee('BMW i4')
        ->assertSee(route('vehicle.show', ['vehicle' => $vehicle, 'slug' => $vehicle->publicSlug()]), false);

    $this->get(route('vehicle.show', ['vehicle' => $vehicle, 'slug' => $vehicle->publicSlug()]))
        ->assertSuccessful()
        ->assertSee('Pedir contacto')
        ->assertSee('Aluguer semanal')
        ->assertSee('325,50&euro;', false)
        ->assertSee('Autonomia alargada e conforto para TVDE.')
        ->assertSee('application/ld+json', false);
});

it('does not show non tvde vehicles publicly', function () {
    $vehicle = Vehicle::query()->create([
        'license_plate' => '44-DD-55',
        'vin' => '5YJ3E1EA7JF000002',
        'make' => 'BYD',
        'model' => 'Seal',
        'source' => 'private',
        'status' => 'available',
    ]);

    $this->get(route('vehicle.show', ['vehicle' => $vehicle, 'slug' => $vehicle->publicSlug()]))->assertNotFound();
    $this->get('/')->assertDontSee('BYD Seal');
});

it('shows all tvde vehicles on the full fleet page including unavailable ones', function () {
    $availableVehicle = Vehicle::query()->create([
        'license_plate' => '66-FF-77',
        'vin' => '5YJ3E1EA7JF000021',
        'make' => 'Tesla',
        'model' => 'Model Y',
        'source' => 'tvde',
        'status' => 'available',
        'weekly_rental_price' => 410,
    ]);

    $unavailableVehicle = Vehicle::query()->create([
        'license_plate' => '77-GG-88',
        'vin' => '5YJ3E1EA7JF000022',
        'make' => 'Mercedes',
        'model' => 'EQA',
        'source' => 'tvde',
        'status' => 'allocated',
    ]);

    Vehicle::query()->create([
        'license_plate' => '88-HH-99',
        'vin' => '5YJ3E1EA7JF000023',
        'make' => 'Audi',
        'model' => 'Q4',
        'source' => 'private',
        'status' => 'available',
    ]);

    $this->get(route('vehicle.index'))
        ->assertSuccessful()
        ->assertSee($availableVehicle->displayName())
        ->assertSee($unavailableVehicle->displayName())
        ->assertSee('Disponivel')
        ->assertSee('Indisponivel')
        ->assertSee('410,00&euro;', false)
        ->assertDontSee('Audi Q4');
});

it('prefers website gallery photos over operational vehicle photos on public pages', function () {
    $vehicle = Vehicle::query()->create([
        'license_plate' => '99-II-00',
        'vin' => '5YJ3E1EA7JF000024',
        'make' => 'Nissan',
        'model' => 'Leaf',
        'source' => 'tvde',
        'status' => 'available',
    ]);

    VehicleWebsitePhoto::query()->create([
        'vehicle_id' => $vehicle->id,
        'photo_path' => 'vehicle-website-photos/site-leaf.jpg',
        'sort_order' => 1,
    ]);

    $this->get(route('vehicle.show', ['vehicle' => $vehicle, 'slug' => $vehicle->publicSlug()]))
        ->assertSuccessful()
        ->assertSee(Storage::disk('public')->url('vehicle-website-photos/site-leaf.jpg'), false);
});

it('creates a kanban task with the vehicle name and contact name when submitting the vehicle form', function () {
    createWebsiteLeadStage();

    $vehicle = Vehicle::query()->create([
        'license_plate' => '22-CC-33',
        'vin' => '5YJ3E1EA7JF000003',
        'make' => 'Hyundai',
        'model' => 'Ioniq 5',
        'trim' => null,
        'source' => 'tvde',
        'status' => 'maintenance',
    ]);

    $this->mock(AndroidPushNotificationService::class, function ($mock): void {
        $mock->shouldReceive('sendNewContactTask')->once();
    });

    $response = $this->post(route('contact.submit'), [
        'name' => 'Joao Silva',
        'email' => 'joao@example.com',
        'phone' => '912345678',
        'message' => 'Quero saber quando posso levantar a viatura.',
        'vehicle_id' => $vehicle->id,
        'page_url' => route('vehicle.show', ['vehicle' => $vehicle, 'slug' => $vehicle->publicSlug()]),
        'source' => 'website_vehicle_product',
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('contact_success');

    $task = Task::query()->firstOrFail();

    expect($task->title)->toBe('Lead viatura: Hyundai Ioniq 5 - Joao Silva')
        ->and($task->meta['vehicle_id'])->toBe($vehicle->id)
        ->and($task->meta['vehicle_name'])->toBe('Hyundai Ioniq 5')
        ->and($task->meta['contact_name'])->toBe('Joao Silva')
        ->and($task->meta['source'])->toBe('website_vehicle_product')
        ->and($task->description)->toContain('Viatura: Hyundai Ioniq 5')
        ->and($task->description)->toContain('Estado: Manutencao')
        ->and($task->description)->toContain(route('vehicle.show', ['vehicle' => $vehicle, 'slug' => $vehicle->publicSlug()]));
});
