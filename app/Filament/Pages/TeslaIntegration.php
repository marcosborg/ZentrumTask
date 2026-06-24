<?php

namespace App\Filament\Pages;

use App\Models\TeslaAccount;
use App\Models\TeslaChargingEvent;
use App\Models\TeslaVehicle;
use App\Models\VehicleAllocation;
use App\Services\TeslaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;
use UnitEnum;

class TeslaIntegration extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Tesla';

    protected static ?string $title = 'Integracao Tesla';

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'tesla';

    protected string $view = 'filament.pages.tesla-integration';

    public bool $isConfigured = false;

    public Collection $accounts;

    public Collection $vehicles;

    public function mount(): void
    {
        $this->isConfigured = filled(config('services.tesla.client_id'))
            && filled(config('services.tesla.client_secret'))
            && filled(config('services.tesla.redirect_uri'));

        $this->accounts = TeslaAccount::query()
            ->withCount('vehicles')
            ->latest()
            ->get();

        $this->vehicles = TeslaVehicle::query()
            ->with(['account', 'vehicle.currentAllocation.driver'])
            ->withCount(['snapshots', 'chargingEvents', 'errors'])
            ->latest('last_seen_at')
            ->latest()
            ->get();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFleetOdometerSnapshots')
                ->label('Criar snapshots km')
                ->icon('heroicon-m-camera')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Criar snapshots manuais para toda a frota Tesla')
                ->modalDescription('Vai buscar a leitura atual de cada viatura à Tesla. Quando existir uma leitura manual anterior e a viatura estiver ligada ao cadastro interno, grava os km no periodo escolhido para os settlements.')
                ->form([
                    DatePicker::make('period_start')
                        ->label('Periodo inicio')
                        ->default(now()->startOfWeek(Carbon::MONDAY)->subWeek()->toDateString())
                        ->required()
                        ->native(false),
                    DatePicker::make('period_end')
                        ->label('Periodo fim')
                        ->default(now()->startOfWeek(Carbon::MONDAY)->subDay()->toDateString())
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data, TeslaService $teslaService): void {
                    $periodStart = (string) ($data['period_start'] ?? '');
                    $periodEnd = (string) ($data['period_end'] ?? '');
                    $created = 0;
                    $weeklyMileageCreated = 0;
                    $failed = 0;

                    TeslaVehicle::query()
                        ->with('account')
                        ->orderBy('display_name')
                        ->get()
                        ->each(function (TeslaVehicle $vehicle) use ($teslaService, $periodStart, $periodEnd, &$created, &$weeklyMileageCreated, &$failed): void {
                            try {
                                $snapshot = $teslaService->createManualOdometerSnapshot($vehicle, $periodStart, $periodEnd);
                            } catch (Throwable) {
                                $failed++;

                                return;
                            }

                            $created++;

                            if ($snapshot->vehicle_weekly_mileage_id) {
                                $weeklyMileageCreated++;
                            }
                        });

                    $this->mount();
                    $this->resetTable();

                    Notification::make()
                        ->success()
                        ->title('Snapshots da frota concluídos')
                        ->body("Criados: {$created}. Sem resposta/erro: {$failed}. Semanas calculadas: {$weeklyMileageCreated}.")
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TeslaVehicle::query()
                    ->with(['account', 'vehicle.currentAllocation.driver'])
                    ->withCount(['snapshots', 'chargingEvents', 'errors'])
                    ->latest('last_seen_at')
            )
            ->columns([
                TextColumn::make('display_name')
                    ->label('Viatura')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->weight('semibold'),
                TextColumn::make('vehicle.currentAllocation.driver.name')
                    ->label('Motorista')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            VehicleAllocation::query()
                                ->select('drivers.name')
                                ->join('drivers', 'drivers.id', '=', 'vehicle_allocations.driver_id')
                                ->whereColumn('vehicle_allocations.vehicle_id', 'tesla_vehicles.vehicle_id')
                                ->where('vehicle_allocations.status', 'active')
                                ->whereNull('vehicle_allocations.ends_at')
                                ->orderByDesc('vehicle_allocations.starts_at')
                                ->limit(1),
                            $direction,
                        );
                    }),
                TextColumn::make('vin')
                    ->label('VIN')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('state')
                    ->label('Estado')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'online' => 'success',
                        'asleep' => 'warning',
                        'offline' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'online' => 'Online',
                        'asleep' => 'Sleep',
                        'offline' => 'Offline',
                        default => $state ? ucfirst($state) : '-',
                    }),
                TextColumn::make('battery_level')
                    ->label('Bateria')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : "{$state}%")
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state <= 15 => 'danger',
                        $state <= 35 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('odometer')
                    ->label('Odometro')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '-' : number_format($state, 1, ',', ' ')),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label('Ultima atualizacao')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('snapshots_count')
                    ->label('Snapshots')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('charging_events_count')
                    ->label('Carreg.')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('errors_count')
                    ->label('Erros')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Estado')
                    ->options([
                        'online' => 'Online',
                        'asleep' => 'Sleep',
                        'offline' => 'Offline',
                    ]),
            ])
            ->recordUrl(fn (TeslaVehicle $record): string => TeslaVehicleDetails::getUrl(['vehicle' => $record]))
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<int, array{week_start: string, week_end: string, charges_count: int, vehicles_count: int, energy_kwh: float, cost: float, currency: string}>
     */
    public function weeklyChargingCosts(): array
    {
        return TeslaChargingEvent::query()
            ->with('teslaVehicle:id,display_name,vin')
            ->where('source', 'history')
            ->latest('started_at')
            ->get()
            ->map(function (TeslaChargingEvent $event): ?array {
                $startedAt = $this->chargingStartedAt($event);
                $cost = $this->chargingCost($event);

                if (! $startedAt || $startedAt->lt(now()->subWeeks(12)->startOfWeek(Carbon::MONDAY)) || $cost === null) {
                    return null;
                }

                return [
                    'event' => $event,
                    'started_at' => $startedAt,
                    'energy_kwh' => $this->chargingEnergy($event),
                    'cost' => $cost,
                    'currency' => $this->chargingCurrency($event),
                ];
            })
            ->filter()
            ->groupBy(function (array $row): string {
                $weekStart = $row['started_at']->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

                return $weekStart.'|'.$row['currency'];
            })
            ->map(function (\Illuminate\Support\Collection $rows): array {
                $first = $rows->first();
                $weekStart = $first['started_at']->copy()->startOfWeek(Carbon::MONDAY);
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

                return [
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'charges_count' => $rows->count(),
                    'vehicles_count' => $rows->pluck('event.tesla_vehicle_id')->unique()->count(),
                    'energy_kwh' => (float) $rows->sum(fn (array $row): float => (float) $row['energy_kwh']),
                    'cost' => (float) $rows->sum(fn (array $row): float => (float) $row['cost']),
                    'currency' => $first['currency'],
                ];
            })
            ->sortByDesc('week_start')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{started_at: Carbon, vehicle_name: string, vin: string, location: string, energy_kwh: float|null, cost: float|null, currency: string}>
     */
    public function superchargerCharges(): array
    {
        return TeslaChargingEvent::query()
            ->with('teslaVehicle:id,display_name,vin')
            ->where('source', 'history')
            ->latest('started_at')
            ->get()
            ->map(function (TeslaChargingEvent $event): ?array {
                $startedAt = $this->chargingStartedAt($event);
                $cost = $this->chargingCost($event);

                if (! $startedAt || $cost === null) {
                    return null;
                }

                return [
                    'started_at' => $startedAt,
                    'vehicle_name' => $event->teslaVehicle?->display_name ?: '-',
                    'vin' => $event->teslaVehicle?->vin ?: '-',
                    'location' => $this->chargingLocation($event),
                    'energy_kwh' => $this->chargingEnergy($event),
                    'cost' => $cost,
                    'currency' => $this->chargingCurrency($event),
                ];
            })
            ->filter()
            ->sortByDesc('started_at')
            ->take(50)
            ->values()
            ->all();
    }

    protected function chargingStartedAt(TeslaChargingEvent $event): ?Carbon
    {
        $value = $event->started_at ?? ($event->raw_payload['chargeStartDateTime'] ?? null);

        return $value ? Carbon::parse($value) : null;
    }

    protected function chargingEnergy(TeslaChargingEvent $event): ?float
    {
        if (is_numeric($event->energy_kwh)) {
            return (float) $event->energy_kwh;
        }

        return $this->chargingFeeValue($event, 'usageBase');
    }

    protected function chargingCost(TeslaChargingEvent $event): ?float
    {
        if (is_numeric($event->cost)) {
            return (float) $event->cost;
        }

        return $this->chargingFeeValue($event, 'totalDue')
            ?? $this->chargingFeeValue($event, 'totalBase');
    }

    protected function chargingCurrency(TeslaChargingEvent $event): string
    {
        if (filled($event->currency)) {
            return $event->currency;
        }

        $fees = $event->raw_payload['fees'] ?? [];

        if (! is_array($fees)) {
            return 'EUR';
        }

        foreach ($fees as $fee) {
            if (is_array($fee) && ($fee['feeType'] ?? null) === 'CHARGING' && filled($fee['currencyCode'] ?? null)) {
                return (string) $fee['currencyCode'];
            }
        }

        return 'EUR';
    }

    protected function chargingLocation(TeslaChargingEvent $event): string
    {
        if (filled($event->location_name)) {
            return $event->location_name;
        }

        return (string) ($event->raw_payload['siteLocationName'] ?? '-');
    }

    protected function chargingFeeValue(TeslaChargingEvent $event, string $key): ?float
    {
        $fees = $event->raw_payload['fees'] ?? [];

        if (! is_array($fees)) {
            return null;
        }

        foreach ($fees as $fee) {
            if (! is_array($fee) || ($fee['feeType'] ?? null) !== 'CHARGING' || ! is_numeric($fee[$key] ?? null)) {
                continue;
            }

            return (float) $fee[$key];
        }

        return null;
    }
}
