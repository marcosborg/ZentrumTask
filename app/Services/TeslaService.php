<?php

namespace App\Services;

use App\Models\TeslaAccount;
use App\Models\TeslaVehicle;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeslaService
{
    public function getAuthorizationUrl(string $state): string
    {
        return (string) Str::of((string) config('services.tesla.auth_url'))
            ->append('?'.http_build_query([
                'response_type' => 'code',
                'client_id' => config('services.tesla.client_id'),
                'redirect_uri' => config('services.tesla.redirect_uri'),
                'scope' => config('services.tesla.scopes'),
                'state' => $state,
                'prompt' => 'login',
            ]));
    }

    /**
     * Troca o authorization code pelo access token e refresh token.
     *
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code): array
    {
        try {
            return $this->client()
                ->asForm()
                ->post((string) config('services.tesla.token_url'), [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('services.tesla.client_id'),
                    'client_secret' => config('services.tesla.client_secret'),
                    'redirect_uri' => config('services.tesla.redirect_uri'),
                    'code' => $code,
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::warning('Tesla OAuth token exchange failed.', [
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array
    {
        return $this->exchangeCodeForToken($code);
    }

    /**
     * Atualiza o token quando expirado.
     */
    public function refreshToken(TeslaAccount $account): TeslaAccount
    {
        try {
            $response = $this->client()
                ->asForm()
                ->post((string) config('services.tesla.token_url'), [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('services.tesla.client_id'),
                    'client_secret' => config('services.tesla.client_secret'),
                    'refresh_token' => decrypt($account->refresh_token),
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::warning('Tesla OAuth token refresh failed.', [
                'tesla_account_id' => $account->getKey(),
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
            ]);

            throw $exception;
        }

        $account->forceFill([
            'access_token' => encrypt((string) $response['access_token']),
            'refresh_token' => encrypt((string) ($response['refresh_token'] ?? decrypt($account->refresh_token))),
            'expires_at' => Carbon::now()->addSeconds((int) $response['expires_in']),
            'scopes' => $this->scopesFrom($response['scope'] ?? config('services.tesla.scopes')),
        ])->save();

        return $account->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserMe(TeslaAccount $account): array
    {
        try {
            $response = $this->client($account)
                ->get($this->url('/api/1/users/me'))
                ->throw()
                ->json();

            return $response['response'] ?? $response;
        } catch (RequestException $exception) {
            Log::warning('Tesla user profile request failed.', [
                'tesla_account_id' => $account->getKey(),
                'status' => $exception->response?->status(),
            ]);

            throw $exception;
        }
    }

    /**
     * Lista os veículos associados à conta.
     *
     * @return list<array<string, mixed>>
     */
    public function listVehicles(TeslaAccount $account): array
    {
        try {
            $response = $this->client($account)
                ->get($this->url('/api/1/vehicles'))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::warning('Tesla vehicles request failed.', [
                'tesla_account_id' => $account->getKey(),
                'status' => $exception->response?->status(),
            ]);

            throw $exception;
        }

        return $response['response'] ?? [];
    }

    /**
     * Obtém dados do veículo via /vehicle_data endpoint.
     *
     * @param  list<string>  $modules
     * @return array<string, mixed>
     */
    public function getVehicleData(
        TeslaVehicle $vehicle,
        array $modules = ['vehicle_state', 'charge_state', 'drive_state', 'location_data']
    ): array {
        try {
            $response = $this->client($vehicle->account)
                ->get($this->url("/api/1/vehicles/{$vehicle->vin}/vehicle_data"), [
                    'endpoints' => implode(';', $modules),
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            Log::warning('Tesla vehicle data request failed.', [
                'tesla_vehicle_id' => $vehicle->getKey(),
                'status' => $exception->response?->status(),
            ]);

            throw $exception;
        }

        return $response['response'] ?? [];
    }

    public function syncVehicles(TeslaAccount $account): int
    {
        $vehicles = $this->listVehicles($account);
        $synced = 0;

        foreach ($vehicles as $vehicleData) {
            $vin = (string) ($vehicleData['vin'] ?? '');

            if ($vin === '') {
                continue;
            }

            $vehicle = TeslaVehicle::query()->updateOrCreate(
                ['vin' => $vin],
                [
                    'tesla_account_id' => $account->getKey(),
                    'tesla_vehicle_id' => (string) ($vehicleData['id_s'] ?? $vehicleData['id'] ?? $vehicleData['vehicle_id'] ?? $vin),
                    'display_name' => $vehicleData['display_name'] ?? null,
                    'state' => $vehicleData['state'] ?? null,
                    'raw_payload' => $vehicleData,
                    'last_seen_at' => now(),
                ],
            );

            $this->hydrateVehicleData($vehicle);
            $synced++;
        }

        $account->forceFill(['last_synced_at' => now()])->save();

        return $synced;
    }

    /**
     * Obtém histórico de carregamento para determinado período.
     *
     * @return array<string, mixed>
     */
    public function getChargingHistory(
        TeslaVehicle $vehicle,
        string $start,
        string $end,
        int $page = 1,
        int $size = 50
    ): array {
        return $this->client($vehicle->account)
            ->get($this->url('/api/1/dx/charging/history'), [
                'vin' => $vehicle->vin,
                'startTime' => $start,
                'endTime' => $end,
                'pageNo' => $page,
                'pageSize' => $size,
            ])
            ->throw()
            ->json();
    }

    /**
     * Obtém sessões de carregamento.
     *
     * @return array<string, mixed>
     */
    public function getChargingSessions(
        TeslaVehicle $vehicle,
        string $startDate,
        string $endDate,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->client($vehicle->account)
            ->get($this->url('/api/1/dx/charging/sessions'), [
                'vin' => $vehicle->vin,
                'date_from' => $startDate,
                'date_to' => $endDate,
                'limit' => $limit,
                'offset' => $offset,
            ])
            ->throw()
            ->json();
    }

    protected function client(?TeslaAccount $account = null): PendingRequest
    {
        $request = Http::acceptJson()->timeout(20);

        if (! $account) {
            return $request;
        }

        return $request->withToken($this->accessToken($account));
    }

    protected function accessToken(TeslaAccount $account): string
    {
        if ($this->tokenIsExpired($account->expires_at)) {
            $account = $this->refreshToken($account);
        }

        return decrypt($account->access_token);
    }

    protected function tokenIsExpired(?CarbonInterface $expiresAt): bool
    {
        return ! $expiresAt || $expiresAt->copy()->subMinutes(5)->isPast();
    }

    protected function url(string $path): string
    {
        return Str::of((string) config('services.tesla.base_url'))
            ->rtrim('/')
            ->append('/'.ltrim($path, '/'))
            ->toString();
    }

    /**
     * @return list<string>
     */
    public function scopesFrom(mixed $scopes): array
    {
        if (is_array($scopes)) {
            return array_values(array_filter($scopes, fn (mixed $scope): bool => is_string($scope) && $scope !== ''));
        }

        return str($scopes ?: config('services.tesla.scopes'))
            ->explode(' ')
            ->filter()
            ->values()
            ->all();
    }

    protected function hydrateVehicleData(TeslaVehicle $vehicle): void
    {
        try {
            $data = $this->getVehicleData($vehicle);
        } catch (RequestException) {
            return;
        }

        $vehicleState = $data['vehicle_state'] ?? [];
        $chargeState = $data['charge_state'] ?? [];
        $vehicleConfig = $data['vehicle_config'] ?? [];

        $vehicle->forceFill([
            'model' => $vehicleConfig['car_type'] ?? $vehicle->model,
            'odometer' => $vehicleState['odometer'] ?? $vehicle->odometer,
            'battery_level' => $chargeState['battery_level'] ?? $vehicle->battery_level,
            'raw_payload' => array_merge($vehicle->raw_payload ?? [], ['vehicle_data' => $data]),
            'last_seen_at' => now(),
        ])->save();
    }
}
