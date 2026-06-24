<?php

namespace App\Filament\Pages;

use App\Models\TeslaChargingEvent;
use App\Models\TeslaVehicle;
use App\Models\TeslaVehicleError;
use App\Models\TeslaVehicleSnapshot;
use App\Services\TeslaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class TeslaVehicleDetails extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $title = 'Detalhe Tesla';

    protected static ?string $slug = 'tesla/{vehicle}';

    protected string $view = 'filament.pages.tesla-vehicle-details';

    public TeslaVehicle $vehicle;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(TeslaVehicle $vehicle): void
    {
        $this->vehicle = $vehicle->load(['account']);
    }

    public function getTitle(): string
    {
        return $this->vehicle->display_name
            ? "Tesla {$this->vehicle->display_name}"
            : "Tesla {$this->vehicle->vin}";
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('setChargeLimit')
                ->label('Definir limite SOC')
                ->icon('heroicon-m-bolt')
                ->color('warning')
                ->modalHeading('Definir limite de carga')
                ->modalDescription('Envia um comando para a Tesla para alterar o limite de carga desta viatura.')
                ->form([
                    TextInput::make('percent')
                        ->label('Limite SOC')
                        ->suffix('%')
                        ->numeric()
                        ->integer()
                        ->minValue(50)
                        ->maxValue(100)
                        ->default($this->latestSnapshot()?->charge_limit_soc ?? 80)
                        ->required(),
                ])
                ->action(function (array $data, TeslaService $teslaService): void {
                    $percent = (int) ($data['percent'] ?? 0);

                    try {
                        $response = $teslaService->setChargeLimit($this->vehicle->loadMissing('account'), $percent);
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Falha ao definir limite SOC')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    $result = data_get($response, 'response.result');
                    $reason = (string) (data_get($response, 'response.reason') ?? data_get($response, 'error') ?? '');
                    $alreadySet = $reason === 'already_set';

                    if ($result === true || $alreadySet) {
                        Notification::make()
                            ->success()
                            ->title($alreadySet ? 'Limite SOC ja estava definido' : 'Limite SOC enviado')
                            ->body("Limite solicitado: {$percent}%.")
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Tesla recusou o comando')
                        ->body($reason !== '' ? $reason : 'Resposta inesperada da Tesla.')
                        ->send();
                }),
        ];
    }

    public function latestSnapshot(): ?TeslaVehicleSnapshot
    {
        return $this->vehicle->snapshots()
            ->latest('recorded_at')
            ->first();
    }

    /**
     * @return Collection<int, TeslaVehicleSnapshot>
     */
    public function chartSnapshots(): Collection
    {
        return $this->vehicle->snapshots()
            ->latest('recorded_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @return Collection<int, TeslaChargingEvent>
     */
    public function recentChargingEvents(): Collection
    {
        return $this->vehicle->chargingEvents()
            ->latest('started_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, TeslaVehicleError>
     */
    public function recentErrors(): Collection
    {
        return $this->vehicle->errors()
            ->latest('occurred_at')
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    public function summaryCards(): array
    {
        $snapshot = $this->latestSnapshot();

        return [
            [
                'label' => 'Estado',
                'value' => $this->vehicleStateLabel($this->vehicle->state),
                'tone' => $this->vehicle->state === 'online' ? 'success' : 'warning',
            ],
            [
                'label' => 'Bateria',
                'value' => $this->percentValue($snapshot?->battery_level ?? $this->vehicle->battery_level),
                'tone' => ((int) ($snapshot?->battery_level ?? $this->vehicle->battery_level ?? 0)) <= 20 ? 'danger' : 'success',
            ],
            [
                'label' => 'Autonomia estimada',
                'value' => $this->distanceValue($snapshot?->est_battery_range ?? $snapshot?->battery_range),
                'tone' => 'info',
            ],
            [
                'label' => 'Odometro',
                'value' => $this->distanceValue($snapshot?->odometer ?? $this->vehicle->odometer),
                'tone' => 'neutral',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function tirePressureCards(): array
    {
        $snapshot = $this->latestSnapshot();

        return [
            ['label' => 'Frente esquerda', 'value' => $this->pressureValue($snapshot?->tpms_pressure_fl)],
            ['label' => 'Frente direita', 'value' => $this->pressureValue($snapshot?->tpms_pressure_fr)],
            ['label' => 'Tras esquerda', 'value' => $this->pressureValue($snapshot?->tpms_pressure_rl)],
            ['label' => 'Tras direita', 'value' => $this->pressureValue($snapshot?->tpms_pressure_rr)],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    public function batteryBars(): array
    {
        return $this->chartSnapshots()
            ->map(fn (TeslaVehicleSnapshot $snapshot): array => [
                'label' => $snapshot->recorded_at?->format('H:i') ?? '-',
                'value' => (int) ($snapshot->battery_level ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: float, height: int}>
     */
    public function chargingBars(): array
    {
        $events = $this->vehicle->chargingEvents()
            ->whereNotNull('energy_kwh')
            ->latest('started_at')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $max = max((float) $events->max('energy_kwh'), 1);

        return $events
            ->map(fn (TeslaChargingEvent $event): array => [
                'label' => $event->started_at?->format('d/m') ?? '-',
                'value' => (float) $event->energy_kwh,
                'height' => max(6, (int) (((float) $event->energy_kwh / $max) * 100)),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function latestRawPayload(): array
    {
        return $this->latestSnapshot()?->raw_payload ?? $this->vehicle->raw_payload ?? [];
    }

    /**
     * @return Collection<int, TeslaVehicleSnapshot>
     */
    public function manualOdometerSnapshots(): Collection
    {
        return $this->vehicle->snapshots()
            ->with('weeklyMileage')
            ->where('is_manual', true)
            ->whereNotNull('odometer')
            ->latest('recorded_at')
            ->limit(5)
            ->get();
    }

    public function percentValue(mixed $value): string
    {
        return is_numeric($value) ? ((int) $value).'%' : '-';
    }

    public function distanceValue(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1, ',', ' ').' km' : '-';
    }

    public function temperatureValue(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1, ',', ' ').' C' : '-';
    }

    public function pressureValue(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, ',', ' ').' bar' : '-';
    }

    public function moneyValue(?TeslaChargingEvent $event): string
    {
        if (! $event || ! is_numeric($event->cost)) {
            return '-';
        }

        return number_format((float) $event->cost, 2, ',', ' ').' '.($event->currency ?: 'EUR');
    }

    protected function vehicleStateLabel(?string $state): string
    {
        return match ($state) {
            'online' => 'Online',
            'asleep' => 'Sleep',
            'offline' => 'Offline',
            default => $state ? ucfirst($state) : '-',
        };
    }
}
