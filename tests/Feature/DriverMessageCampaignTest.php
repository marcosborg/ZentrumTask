<?php

use App\Filament\Resources\DriverMessageCampaigns\Pages\CreateDriverMessageCampaign;
use App\Jobs\SendDriverCampaignEmail;
use App\Mail\DriverCampaignMail;
use App\Models\Driver;
use App\Models\DriverMessageCampaign;
use App\Models\DriverMessageDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates individual history records and queues email for every selected driver with email', function () {
    Queue::fake();

    $user = User::factory()->create();
    $withContacts = Driver::factory()->create([
        'email' => 'motorista@example.com',
        'phone' => '912345678',
    ]);
    $withoutContacts = Driver::factory()->create([
        'email' => null,
        'phone' => null,
    ]);

    $this->actingAs($user);

    Livewire::test(CreateDriverMessageCampaign::class)
        ->fillForm([
            'subject' => 'Aviso importante',
            'body' => 'Esta é a mensagem.',
            'driver_ids' => [$withContacts->id, $withoutContacts->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $campaign = DriverMessageCampaign::query()->firstOrFail();

    expect($campaign->created_by_user_id)->toBe($user->id)
        ->and($campaign->deliveries)->toHaveCount(2)
        ->and($campaign->deliveries->firstWhere('driver_id', $withContacts->id)->email_status)->toBe('pending')
        ->and($campaign->deliveries->firstWhere('driver_id', $withoutContacts->id)->email_status)->toBe('unavailable')
        ->and($campaign->deliveries->firstWhere('driver_id', $withoutContacts->id)->whatsapp_status)->toBe('unavailable');

    Queue::assertPushed(SendDriverCampaignEmail::class, 1);
});

it('sends an individual email and updates its history', function () {
    Mail::fake();

    $campaign = DriverMessageCampaign::factory()->create([
        'subject' => 'Assunto do teste',
        'body' => 'Mensagem do teste',
    ]);
    $delivery = DriverMessageDelivery::factory()->create([
        'driver_message_campaign_id' => $campaign->id,
        'email_address' => 'destino@example.com',
        'email_status' => 'pending',
    ]);

    (new SendDriverCampaignEmail($delivery->id))->handle();

    Mail::assertSent(DriverCampaignMail::class, 'destino@example.com');

    expect($delivery->refresh()->email_status)->toBe('sent')
        ->and($delivery->email_sent_at)->not->toBeNull();
});

it('marks whatsapp as sent and opens the app with the campaign message', function () {
    $user = User::factory()->create();
    $campaign = DriverMessageCampaign::factory()->create(['body' => 'Olá motorista']);
    $delivery = DriverMessageDelivery::factory()->create([
        'driver_message_campaign_id' => $campaign->id,
        'phone_number' => '912 345 678',
        'email_status' => 'sent',
        'whatsapp_status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('driver-messages.whatsapp', $delivery));

    $response->assertRedirect('https://wa.me/351912345678?text=Ol%C3%A1%20motorista');

    expect($delivery->refresh()->whatsapp_status)->toBe('sent')
        ->and($delivery->whatsapp_sent_by_user_id)->toBe($user->id)
        ->and($delivery->whatsapp_sent_at)->not->toBeNull();
});

it('does not expose the whatsapp action without authentication', function () {
    $delivery = DriverMessageDelivery::factory()->create(['phone_number' => '912345678']);

    $this->get(route('driver-messages.whatsapp', $delivery))
        ->assertRedirect(route('login'));

    expect($delivery->refresh()->whatsapp_status)->toBe('pending');
});
