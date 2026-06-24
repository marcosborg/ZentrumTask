<?php

use App\Models\TeslaAccount;
use App\Models\TeslaVehicle;
use App\Models\User;
use App\Services\TeslaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.tesla.base_url', 'https://tesla.test');
    config()->set('services.tesla.auth_url', 'https://auth.tesla.test/oauth2/v3/authorize');
    config()->set('services.tesla.token_url', 'https://auth.tesla.test/oauth2/v3/token');
    config()->set('services.tesla.client_id', 'client-id');
    config()->set('services.tesla.client_secret', 'client-secret');
    config()->set('services.tesla.redirect_uri', 'https://zentrum.test/tesla/callback');
    config()->set('services.tesla.scopes', 'openid offline_access vehicle_device_data vehicle_location vehicle_charging_cmds');
});

it('lists vehicles for a tesla account', function (): void {
    Http::fake([
        'tesla.test/api/1/vehicles' => Http::response([
            'response' => [
                [
                    'id' => 123,
                    'vin' => '5YJ3E1EA7JF000001',
                    'display_name' => 'Model 3',
                ],
            ],
        ]),
    ]);

    $account = TeslaAccount::factory()->create([
        'access_token' => encrypt('valid-token'),
        'refresh_token' => encrypt('refresh-token'),
        'expires_at' => now()->addHour(),
    ]);

    $vehicles = app(TeslaService::class)->listVehicles($account);

    expect($vehicles)->toHaveCount(1)
        ->and($vehicles[0]['vin'])->toBe('5YJ3E1EA7JF000001');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer valid-token'));
});

it('refreshes an expired token before fetching vehicle data', function (): void {
    Http::fake([
        'auth.tesla.test/oauth2/v3/token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]),
        'tesla.test/api/1/vehicles/5YJ3E1EA7JF000001/vehicle_data*' => Http::response([
            'response' => [
                'vehicle_state' => [
                    'odometer' => 12345,
                ],
            ],
        ]),
    ]);

    $account = TeslaAccount::factory()->create([
        'access_token' => encrypt('expired-token'),
        'refresh_token' => encrypt('refresh-token'),
        'expires_at' => now()->subMinute(),
    ]);

    $vehicle = TeslaVehicle::factory()->create([
        'tesla_account_id' => $account->id,
        'vin' => '5YJ3E1EA7JF000001',
    ]);

    $data = app(TeslaService::class)->getVehicleData($vehicle);

    expect($data['vehicle_state']['odometer'])->toBe(12345)
        ->and(decrypt($account->refresh()->access_token))->toBe('new-access-token')
        ->and(decrypt($account->refresh_token))->toBe('new-refresh-token');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.tesla.test/oauth2/v3/token');
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://tesla.test/api/1/vehicles/5YJ3E1EA7JF000001/vehicle_data')
        && $request->hasHeader('Authorization', 'Bearer new-access-token'));
});

it('shows a configuration warning when tesla credentials are missing', function (): void {
    config()->set('services.tesla.client_id', null);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.tesla.index'))
        ->assertOk()
        ->assertSee('Configuracao Tesla incompleta');
});

it('blocks callbacks with an invalid oauth state', function (): void {
    $this->actingAs(User::factory()->create())
        ->withSession(['tesla_oauth_state' => 'expected-state'])
        ->get(route('tesla.callback', [
            'state' => 'wrong-state',
            'code' => 'valid-code',
        ]))
        ->assertRedirect(route('admin.tesla.index'))
        ->assertSessionHas('error');
});

it('rejects callbacks without an authorization code', function (): void {
    $this->actingAs(User::factory()->create())
        ->withSession(['tesla_oauth_state' => 'expected-state'])
        ->get(route('tesla.callback', [
            'state' => 'expected-state',
        ]))
        ->assertRedirect(route('admin.tesla.index'))
        ->assertSessionHas('error');
});

it('shows a readable error when tesla token exchange fails', function (): void {
    Http::fake([
        'auth.tesla.test/oauth2/v3/token' => Http::response([
            'error_description' => 'Invalid authorization code',
        ], 400),
    ]);

    $this->actingAs(User::factory()->create())
        ->withSession(['tesla_oauth_state' => 'expected-state'])
        ->get(route('tesla.callback', [
            'state' => 'expected-state',
            'code' => 'bad-code',
        ]))
        ->assertRedirect(route('admin.tesla.index'))
        ->assertSessionHas('error', 'Invalid authorization code');
});

it('stores a tesla account after a successful oauth callback', function (): void {
    $idToken = 'header.'.rtrim(strtr(base64_encode(json_encode([
        'sub' => '89d88970-4869-46b9-9c60-44b87c1f6c9f',
    ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=').'.signature';

    Http::fake([
        'auth.tesla.test/oauth2/v3/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'scope' => 'openid offline_access vehicle_device_data',
            'id_token' => $idToken,
        ]),
        'tesla.test/api/1/users/me' => Http::response([
            'response' => [
                'email' => 'owner@example.com',
            ],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'tesla_oauth_state' => 'expected-state',
            'tesla_oauth_user_id' => $user->id,
        ])
        ->get(route('tesla.callback', [
            'state' => 'expected-state',
            'code' => 'valid-code',
        ]))
        ->assertRedirect(route('admin.tesla.index'))
        ->assertSessionHas('success');

    $account = TeslaAccount::query()->firstOrFail();

    expect($account->user_id)->toBe($user->id)
        ->and($account->tesla_user_id)->toBe('89d88970-4869-46b9-9c60-44b87c1f6c9f')
        ->and($account->owner_email)->toBe('owner@example.com')
        ->and(decrypt($account->access_token))->toBe('access-token')
        ->and(decrypt($account->refresh_token))->toBe('refresh-token');
});

it('stores a tesla account when the oauth state is validated from cache', function (): void {
    Http::fake([
        'auth.tesla.test/oauth2/v3/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
        ]),
        'tesla.test/api/1/users/me' => Http::response([
            'response' => [
                'email' => 'cached-owner@example.com',
            ],
        ]),
    ]);

    $user = User::factory()->create();

    Cache::put('tesla:oauth-state:cached-state', [
        'user_id' => $user->id,
    ], now()->addMinutes(15));

    $this->get(route('tesla.callback', [
        'state' => 'cached-state',
        'code' => 'valid-code',
    ]))
        ->assertRedirect(route('admin.tesla.index'))
        ->assertSessionHas('success');

    $account = TeslaAccount::query()->firstOrFail();

    expect($account->user_id)->toBe($user->id)
        ->and($account->owner_email)->toBe('cached-owner@example.com');
});

it('syncs tesla vehicles into the database', function (): void {
    Http::fake([
        'tesla.test/api/1/vehicles' => Http::response([
            'response' => [
                [
                    'id_s' => '123456789',
                    'vin' => '5YJ3E1EA7JF000001',
                    'display_name' => 'Zentrum Tesla',
                    'state' => 'online',
                ],
            ],
        ]),
        'tesla.test/api/1/vehicles/5YJ3E1EA7JF000001/vehicle_data*' => Http::response([
            'response' => [
                'vehicle_state' => [
                    'odometer' => 43210.5,
                ],
                'charge_state' => [
                    'battery_level' => 78,
                ],
                'vehicle_config' => [
                    'car_type' => 'model3',
                ],
            ],
        ]),
    ]);

    TeslaAccount::factory()->create([
        'access_token' => encrypt('valid-token'),
        'refresh_token' => encrypt('refresh-token'),
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('admin.tesla.syncVehicles'))
        ->assertRedirect(route('admin.tesla.index'))
        ->assertSessionHas('success');

    $vehicle = TeslaVehicle::query()->firstOrFail();

    expect($vehicle->vin)->toBe('5YJ3E1EA7JF000001')
        ->and($vehicle->display_name)->toBe('Zentrum Tesla')
        ->and((float) $vehicle->odometer)->toBe(43210.5)
        ->and($vehicle->battery_level)->toBe(78)
        ->and($vehicle->model)->toBe('model3');
});
