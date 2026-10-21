<?php

use App\Models\AppDeviceToken;
use App\Models\Task;
use App\Models\User;
use App\Services\AndroidPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('registers and refreshes native notification tokens', function (string $platform) {
    $user = User::factory()->create();
    Cache::put('app_auth_token:test-session', $user->id);
    $payload = ['token' => 'test-fcm-token', 'platform' => $platform, 'device_name' => 'Test device'];

    $this->withToken('test-session')->postJson('/app/devices/register', $payload)->assertCreated();
    $this->withToken('test-session')->postJson('/app/devices/register', $payload)->assertCreated();

    expect(AppDeviceToken::query()->count())->toBe(1);
    $this->assertDatabaseHas('app_device_tokens', [
        'user_id' => $user->id,
        'platform' => $platform,
        'token_hash' => hash('sha256', 'test-fcm-token'),
    ]);
})->with(['android', 'ios']);

it('rejects unsupported platforms and missing tokens', function (array $payload, string $field) {
    $user = User::factory()->create();
    Cache::put('app_auth_token:test-session', $user->id);
    $this->withToken('test-session')->postJson('/app/devices/register', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(AppDeviceToken::query()->count())->toBe(0);
})->with([
    [['platform' => 'web', 'token' => 'fcm'], 'platform'],
    [['platform' => 'ios'], 'token'],
]);

it('requires authentication before registering a device', function () {
    $this->postJson('/app/devices/register', ['platform' => 'ios', 'token' => 'fcm'])->assertUnauthorized();
});

it('only unregisters the authenticated users device', function () {
    $users = User::factory()->count(2)->create();
    Cache::put('app_auth_token:test-session', $users[0]->id);
    AppDeviceToken::query()->create([
        'user_id' => $users[1]->id, 'token' => 'other-token',
        'token_hash' => hash('sha256', 'other-token'), 'platform' => 'ios',
    ]);
    $this->withToken('test-session')->postJson('/app/devices/unregister', ['token' => 'other-token'])->assertOk();
    expect(AppDeviceToken::query()->count())->toBe(1);
});

it('sends FCM alerts to both platforms for the assigned user', function () {
    Http::preventStrayRequests();
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'test-message'])]);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    foreach (['android', 'ios'] as $platform) {
        AppDeviceToken::query()->create([
            'user_id' => $user->id, 'token' => $platform.'-token',
            'token_hash' => hash('sha256', $platform.'-token'), 'platform' => $platform,
        ]);
    }
    AppDeviceToken::query()->create([
        'user_id' => $otherUser->id, 'token' => 'other-token',
        'token_hash' => hash('sha256', 'other-token'), 'platform' => 'ios',
    ]);
    $service = new class extends AndroidPushNotificationService
    {
        protected function loadCredentials(): ?array
        {
            return ['project_id' => 'test-project'];
        }

        protected function issueAccessToken(array $credentials): string
        {
            return 'test-access-token';
        }
    };
    $task = new Task(['title' => 'Test push', 'assigned_to_id' => $user->id]);
    $task->id = 42;
    $service->sendNewContactTask($task);

    Http::assertSentCount(2);
    foreach (['android', 'ios'] as $platform) {
        Http::assertSent(fn (Request $request): bool => $request['message']['token'] === $platform.'-token'
            && $request['message']['apns']['payload']['aps']['sound'] === 'default'
            && $request['message']['apns']['headers']['apns-push-type'] === 'alert'
            && $request['message']['data']['route'] === '/reserved/tasks?task=42');
    }
});
