<?php

namespace App\Services;

use App\Models\TeslaAccount;
use App\Models\TeslaChargingEvent;
use App\Models\TeslaVehicle;
use App\Models\TeslaVehicleError;
use App\Models\TeslaVehicleSnapshot;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TeslaService
{
    /**
     * @var list<string>
     */
    private const VEHICLE_DATA_MODULES = [
        'vehicle_state',
        'charge_state',
        'drive_state',
        'location_data',
        'climate_state',
        'vehicle_config',
        'gui_settings',
    ];

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
        array $modules = self::VEHICLE_DATA_MODULES
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
            $this->syncVehicleExtras($vehicle->refresh());
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

    /**
     * @return array<string, mixed>
     */
    public function getFleetTelemetryErrors(TeslaVehicle $vehicle): array
    {
        return $this->client($vehicle->account)
            ->get($this->url("/api/1/vehicles/{$vehicle->vin}/fleet_telemetry_errors"))
            ->throw()
            ->json();
    }

    public function createManualOdometerSnapshot(TeslaVehicle $vehicle): TeslaVehicleSnapshot
    {
        $snapshot = $this->hydrateVehicleData($vehicle, true);

        if (! $snapshot) {
            throw new RuntimeException('Nao foi possivel obter dados atuais da Tesla para esta viatura.');
        }

        $this->createWeeklyMileageFromManualSnapshot($snapshot);

        return $snapshot->refresh();
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

    protected function hydrateVehicleData(TeslaVehicle $vehicle, bool $isManual = false): ?TeslaVehicleSnapshot
    {
        try {
            $data = $this->getVehicleData($vehicle);
        } catch (RequestException) {
            return null;
        }

        $vehicleState = $data['vehicle_state'] ?? [];
        $chargeState = $data['charge_state'] ?? [];
        $driveState = $data['drive_state'] ?? [];
        $climateState = $data['climate_state'] ?? [];
        $vehicleConfig = $data['vehicle_config'] ?? [];

        $vehicle->forceFill([
            'model' => $vehicleConfig['car_type'] ?? $vehicle->model,
            'odometer' => $vehicleState['odometer'] ?? $vehicle->odometer,
            'battery_level' => $chargeState['battery_level'] ?? $vehicle->battery_level,
            'raw_payload' => array_merge($vehicle->raw_payload ?? [], ['vehicle_data' => $data]),
            'last_seen_at' => now(),
        ])->save();

        $location = $this->resolveGoogleLocation(
            $this->floatValue($driveState['latitude'] ?? $driveState['native_latitude'] ?? null),
            $this->floatValue($driveState['longitude'] ?? $driveState['native_longitude'] ?? null),
        );

        return TeslaVehicleSnapshot::query()->create([
            'tesla_vehicle_id' => $vehicle->getKey(),
            'recorded_at' => now(),
            'is_manual' => $isManual,
            'vehicle_state' => $vehicle->state,
            'charging_state' => $this->stringValue($chargeState['charging_state'] ?? null),
            'battery_level' => $this->integerValue($chargeState['battery_level'] ?? null),
            'usable_battery_level' => $this->integerValue($chargeState['usable_battery_level'] ?? null),
            'battery_range' => $this->floatValue($chargeState['battery_range'] ?? null),
            'est_battery_range' => $this->floatValue($chargeState['est_battery_range'] ?? null),
            'rated_battery_range' => $this->floatValue($chargeState['rated_battery_range'] ?? null),
            'odometer' => $this->floatValue($vehicleState['odometer'] ?? null),
            'speed' => $this->floatValue($driveState['speed'] ?? null),
            'latitude' => $this->floatValue($driveState['latitude'] ?? $driveState['native_latitude'] ?? null),
            'longitude' => $this->floatValue($driveState['longitude'] ?? $driveState['native_longitude'] ?? null),
            'locality' => $location['locality'],
            'formatted_address' => $location['formatted_address'],
            'google_place_id' => $location['google_place_id'],
            'heading' => $this->integerValue($driveState['heading'] ?? null),
            'shift_state' => $this->stringValue($driveState['shift_state'] ?? null),
            'charge_energy_added' => $this->floatValue($chargeState['charge_energy_added'] ?? null),
            'charger_power' => $this->floatValue($chargeState['charger_power'] ?? null),
            'charge_limit_soc' => $this->integerValue($chargeState['charge_limit_soc'] ?? null),
            'inside_temp' => $this->floatValue($climateState['inside_temp'] ?? null),
            'outside_temp' => $this->floatValue($climateState['outside_temp'] ?? null),
            'driver_temp_setting' => $this->floatValue($climateState['driver_temp_setting'] ?? null),
            'passenger_temp_setting' => $this->floatValue($climateState['passenger_temp_setting'] ?? null),
            'tpms_pressure_fl' => $this->floatValue($vehicleState['tpms_pressure_fl'] ?? null),
            'tpms_pressure_fr' => $this->floatValue($vehicleState['tpms_pressure_fr'] ?? null),
            'tpms_pressure_rl' => $this->floatValue($vehicleState['tpms_pressure_rl'] ?? null),
            'tpms_pressure_rr' => $this->floatValue($vehicleState['tpms_pressure_rr'] ?? null),
            'raw_payload' => $data,
        ]);
    }

    protected function createWeeklyMileageFromManualSnapshot(TeslaVehicleSnapshot $snapshot): void
    {
        if (! is_numeric($snapshot->odometer)) {
            return;
        }

        $vehicle = $snapshot->teslaVehicle()->first();

        if (! $vehicle) {
            return;
        }

        $internalVehicle = $this->resolveInternalVehicle($vehicle);

        if (! $internalVehicle) {
            return;
        }

        $previous = $vehicle->snapshots()
            ->where('is_manual', true)
            ->whereNotNull('odometer')
            ->whereKeyNot($snapshot->getKey())
            ->where('recorded_at', '<', $snapshot->recorded_at)
            ->latest('recorded_at')
            ->first();

        if (! $previous || ! is_numeric($previous->odometer)) {
            return;
        }

        $weeklyKm = round(max(0, (float) $snapshot->odometer - (float) $previous->odometer), 2);
        $periodStart = $previous->recorded_at->copy()->startOfDay();
        $periodEnd = $snapshot->recorded_at->isMonday()
            ? $snapshot->recorded_at->copy()->subDay()->endOfDay()
            : $snapshot->recorded_at->copy()->endOfDay();

        if ($periodEnd->lt($periodStart)) {
            $periodEnd = $snapshot->recorded_at->copy()->endOfDay();
        }

        $driverMatch = $this->resolveDriverForVehiclePeriod($internalVehicle, $periodStart, $periodEnd);

        $weeklyMileage = VehicleWeeklyMileage::query()->updateOrCreate(
            [
                'vehicle_id' => $internalVehicle->getKey(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'driver_id' => $driverMatch['driver_id'],
                'weekly_km' => $weeklyKm,
                'assignment_status' => $driverMatch['status'],
                'raw_row' => [
                    'source' => 'tesla_manual_snapshot',
                    'tesla_vehicle_id' => $vehicle->getKey(),
                    'previous_snapshot_id' => $previous->getKey(),
                    'current_snapshot_id' => $snapshot->getKey(),
                    'previous_odometer' => (float) $previous->odometer,
                    'current_odometer' => (float) $snapshot->odometer,
                ],
                'imported_at' => now(),
                'source_file' => 'tesla_manual_snapshot',
            ],
        );

        $snapshot->forceFill([
            'vehicle_weekly_mileage_id' => $weeklyMileage->getKey(),
        ])->save();
    }

    protected function resolveInternalVehicle(TeslaVehicle $vehicle): ?Vehicle
    {
        if ($vehicle->vehicle_id) {
            return $vehicle->vehicle;
        }

        $internalVehicle = Vehicle::query()
            ->where('vin', $vehicle->vin)
            ->first();

        if (! $internalVehicle && $vehicle->display_name) {
            $normalizedPlate = $this->normalizePlate($vehicle->display_name);

            if ($normalizedPlate !== '') {
                $internalVehicle = Vehicle::query()
                    ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(license_plate, '-', ''), ' ', ''), '.', '')) = ?", [$normalizedPlate])
                    ->first();
            }
        }

        if ($internalVehicle) {
            $vehicle->forceFill(['vehicle_id' => $internalVehicle->getKey()])->save();
        }

        return $internalVehicle;
    }

    /**
     * @return array{driver_id: int|null, status: string}
     */
    protected function resolveDriverForVehiclePeriod(Vehicle $vehicle, Carbon $periodStart, Carbon $periodEnd): array
    {
        $matches = VehicleAllocation::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('starts_at', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $periodStart);
            })
            ->pluck('driver_id')
            ->filter()
            ->unique()
            ->values();

        if ($matches->count() === 1) {
            return [
                'driver_id' => (int) $matches->first(),
                'status' => 'ok',
            ];
        }

        if ($matches->count() > 1) {
            return [
                'driver_id' => null,
                'status' => 'ambiguous_driver',
            ];
        }

        return [
            'driver_id' => null,
            'status' => 'unassigned_driver',
        ];
    }

    /**
     * @return array{locality: string|null, formatted_address: string|null, google_place_id: string|null}
     */
    protected function resolveGoogleLocation(?float $latitude, ?float $longitude): array
    {
        $fallback = [
            'locality' => null,
            'formatted_address' => null,
            'google_place_id' => null,
        ];

        $key = config('services.google.maps_api_key');

        if (! $latitude || ! $longitude || ! $key) {
            return $fallback;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => "{$latitude},{$longitude}",
                    'key' => $key,
                    'language' => 'pt',
                    'region' => 'pt',
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            Log::info('Google geocoding unavailable for Tesla location.', [
                'message' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        $result = $response['results'][0] ?? null;

        if (! is_array($result)) {
            return $fallback;
        }

        return [
            'locality' => $this->localityFromGoogleResult($result),
            'formatted_address' => $this->stringValue($result['formatted_address'] ?? null),
            'google_place_id' => $this->stringValue($result['place_id'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function localityFromGoogleResult(array $result): ?string
    {
        $components = $result['address_components'] ?? [];

        if (! is_array($components)) {
            return null;
        }

        foreach (['locality', 'postal_town', 'administrative_area_level_2', 'administrative_area_level_1'] as $type) {
            foreach ($components as $component) {
                if (
                    is_array($component)
                    && in_array($type, $component['types'] ?? [], true)
                    && isset($component['long_name'])
                ) {
                    return (string) $component['long_name'];
                }
            }
        }

        return null;
    }

    protected function normalizePlate(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', Str::ascii($value)) ?? '');
    }

    protected function syncVehicleExtras(TeslaVehicle $vehicle): void
    {
        $this->syncChargingData($vehicle);
        $this->syncTelemetryErrors($vehicle);
    }

    protected function syncChargingData(TeslaVehicle $vehicle): void
    {
        $start = now()->subDays(90)->toIso8601String();
        $end = now()->toIso8601String();

        try {
            $history = $this->getChargingHistory($vehicle, $start, $end);
            $this->storeChargingRows($vehicle, 'history', $this->rowsFromTeslaResponse($history));
        } catch (Throwable $exception) {
            Log::info('Tesla charging history unavailable.', [
                'tesla_vehicle_id' => $vehicle->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $sessions = $this->getChargingSessions($vehicle, now()->subDays(90)->toDateString(), now()->toDateString());
            $this->storeChargingRows($vehicle, 'session', $this->rowsFromTeslaResponse($sessions));
        } catch (Throwable $exception) {
            Log::info('Tesla charging sessions unavailable.', [
                'tesla_vehicle_id' => $vehicle->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function syncTelemetryErrors(TeslaVehicle $vehicle): void
    {
        try {
            $response = $this->getFleetTelemetryErrors($vehicle);
        } catch (Throwable $exception) {
            Log::info('Tesla fleet telemetry errors unavailable.', [
                'tesla_vehicle_id' => $vehicle->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($this->rowsFromTeslaResponse($response) as $row) {
            TeslaVehicleError::query()->create([
                'tesla_vehicle_id' => $vehicle->getKey(),
                'source' => 'fleet_telemetry',
                'code' => $this->stringValue($row['code'] ?? $row['error_code'] ?? $row['name'] ?? null),
                'message' => $this->stringValue($row['message'] ?? $row['error'] ?? $row['description'] ?? null),
                'occurred_at' => $this->dateValue($row['created_at'] ?? $row['timestamp'] ?? $row['time'] ?? null),
                'raw_payload' => $row,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function storeChargingRows(TeslaVehicle $vehicle, string $source, array $rows): void
    {
        foreach ($rows as $row) {
            $externalId = $this->stringValue($row['id'] ?? $row['session_id'] ?? $row['charging_session_id'] ?? null)
                ?? md5(json_encode($row, JSON_THROW_ON_ERROR));

            TeslaChargingEvent::query()->updateOrCreate(
                [
                    'tesla_vehicle_id' => $vehicle->getKey(),
                    'source' => $source,
                    'external_id' => $externalId,
                ],
                [
                    'started_at' => $this->dateValue($row['started_at'] ?? $row['start_time'] ?? $row['startTime'] ?? $row['date_from'] ?? null),
                    'ended_at' => $this->dateValue($row['ended_at'] ?? $row['end_time'] ?? $row['endTime'] ?? $row['date_to'] ?? null),
                    'energy_kwh' => $this->floatValue($row['energy_kwh'] ?? $row['energy_used'] ?? $row['kwh'] ?? $row['charge_energy_added'] ?? null),
                    'cost' => $this->floatValue($row['cost'] ?? $row['fee'] ?? $row['billed_amount'] ?? $row['total_cost'] ?? null),
                    'currency' => $this->stringValue($row['currency'] ?? $row['billing_currency'] ?? null),
                    'location_name' => $this->stringValue($row['location'] ?? $row['site_location_name'] ?? $row['charging_location'] ?? null),
                    'country' => $this->stringValue($row['country'] ?? null),
                    'raw_payload' => $row,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rowsFromTeslaResponse(array $response): array
    {
        $candidates = [
            $response['response'] ?? null,
            $response['data'] ?? null,
            $response['results'] ?? null,
            $response,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (array_is_list($candidate)) {
                return array_values(array_filter($candidate, fn (mixed $row): bool => is_array($row)));
            }

            foreach (['sessions', 'history', 'records', 'errors'] as $key) {
                if (isset($candidate[$key]) && is_array($candidate[$key]) && array_is_list($candidate[$key])) {
                    return array_values(array_filter($candidate[$key], fn (mixed $row): bool => is_array($row)));
                }
            }
        }

        return [];
    }

    protected function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    protected function dateValue(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
