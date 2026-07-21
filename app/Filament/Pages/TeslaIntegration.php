<?php

namespace App\Filament\Pages;

use App\Mail\TeslaTirePressureAlertMail;
use App\Models\TeslaAccount;
use App\Models\TeslaChargingEvent;
use App\Models\TeslaVehicle;
use App\Models\VehicleAllocation;
use App\Services\TeslaService;
use App\Support\TeslaTirePressureEvaluator;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
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
            ->with(['account', 'latestSnapshot', 'vehicle.currentAllocation.driver'])
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
            Action::make('checkFleetTirePressures')
                ->label('Verificar pressao dos pneus')
                ->icon('heroicon-m-shield-check')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Verificar pressao dos pneus de toda a frota')
                ->modalDescription('Vai consultar as quatro leituras TPMS atuais de cada viatura Tesla. A operacao pode demorar alguns instantes.')
                ->action(function (TeslaService $teslaService, TeslaTirePressureEvaluator $evaluator): void {
                    $counts = [
                        'compliant' => 0,
                        'abnormal' => 0,
                        'no_data' => 0,
                        'failed' => 0,
                    ];

                    TeslaVehicle::query()
                        ->with('account')
                        ->orderBy('display_name')
                        ->get()
                        ->each(function (TeslaVehicle $vehicle) use ($teslaService, $evaluator, &$counts): void {
                            try {
                                $snapshot = $teslaService->captureTirePressureSnapshot($vehicle);
                            } catch (Throwable) {
                                $counts['failed']++;

                                return;
                            }

                            if (! $snapshot) {
                                $counts[$vehicle->state === 'offline' ? 'no_data' : 'failed']++;

                                return;
                            }

                            $counts[$evaluator->evaluate($snapshot)['status']]++;
                        });

                    $this->mount();
                    $this->resetTable();

                    $notification = Notification::make()
                        ->title('Verificacao da pressao concluida')
                        ->body("Conformes: {$counts['compliant']}. Anomalas: {$counts['abnormal']}. Sem dados: {$counts['no_data']}. Falhas: {$counts['failed']}.");

                    if ($counts['abnormal'] > 0 || $counts['failed'] > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            Action::make('emailAllTirePressureAlerts')
                ->label(fn (): string => 'Enviar todos ('.$this->eligibleTirePressureAlertVehicles()->count().')')
                ->icon('heroicon-m-paper-airplane')
                ->color('danger')
                ->disabled(fn (): bool => $this->eligibleTirePressureAlertVehicles()->isEmpty())
                ->modalHeading('Enviar todos os avisos de pressao')
                ->modalSubmitActionLabel('Enviar todos os emails')
                ->modalDescription('Sera enviado um email individual a cada motorista listado abaixo.')
                ->modalContent(fn (): View => view(
                    'filament.pages.actions.tesla-tire-pressure-bulk-email-preview',
                    ['vehicles' => $this->eligibleTirePressureAlertVehicles()],
                ))
                ->action(function (): void {
                    $vehicles = $this->eligibleTirePressureAlertVehicles();
                    $sent = 0;
                    $failed = 0;

                    foreach ($vehicles as $vehicle) {
                        $driver = $vehicle->vehicle?->currentAllocation?->driver;

                        if (! $driver || ! filter_var($driver->email, FILTER_VALIDATE_EMAIL)) {
                            $failed++;

                            continue;
                        }

                        try {
                            Mail::to($driver->email)->send(new TeslaTirePressureAlertMail(
                                driver: $driver,
                                vehicle: $vehicle,
                                assessment: $this->tirePressureAssessment($vehicle),
                            ));
                            $sent++;
                        } catch (Throwable $exception) {
                            report($exception);
                            $failed++;
                        }
                    }

                    $notification = Notification::make()
                        ->title('Envio de avisos concluido')
                        ->body("Enviados: {$sent}. Falhas: {$failed}.");

                    if ($failed > 0) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
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
                    ->with(['account', 'latestSnapshot', 'vehicle.currentAllocation.driver'])
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
                    ->limit(28)
                    ->tooltip(fn (TeslaVehicle $record): ?string => $record->vehicle?->currentAllocation?->driver?->name)
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
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('tire_pressure_status')
                    ->label('Pressao')
                    ->state(fn (TeslaVehicle $record): string => $this->tirePressureAssessment($record)['status'])
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'compliant' => 'Conforme',
                        'abnormal' => 'Anomala',
                        default => 'Sem dados',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'compliant' => 'success',
                        'abnormal' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('tire_pressures')
                    ->label('Pneus (DE / DD / TE / TD)')
                    ->state(fn (TeslaVehicle $record): string => $this->formatTirePressures($record))
                    ->placeholder('-')
                    ->fontFamily('mono'),
                TextColumn::make('latestSnapshot.recorded_at')
                    ->label('Leitura pneus')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-'),
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
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('odometer')
                    ->label('Odometro')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '-' : number_format($state, 1, ',', ' '))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_seen_at')
                    ->label('Ultima atualizacao')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('snapshots_count')
                    ->label('Snapshots')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('charging_events_count')
                    ->label('Carreg.')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('errors_count')
                    ->label('Erros')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->recordActions([
                Action::make('emailTirePressureAlert')
                    ->label(fn (TeslaVehicle $record): string => $this->driverCanReceiveTirePressureAlert($record)
                        ? 'Enviar email'
                        : 'Sem email')
                    ->icon('heroicon-m-envelope')
                    ->color('danger')
                    ->button()
                    ->hidden(fn (TeslaVehicle $record): bool => $this->tirePressureAssessment($record)['status'] !== 'abnormal')
                    ->disabled(fn (TeslaVehicle $record): bool => ! $this->driverCanReceiveTirePressureAlert($record))
                    ->tooltip(fn (TeslaVehicle $record): ?string => $this->driverCanReceiveTirePressureAlert($record)
                        ? null
                        : 'A viatura nao tem um motorista ativo com email valido.')
                    ->modalHeading('Rever aviso de pressao dos pneus')
                    ->modalSubmitActionLabel('Enviar email')
                    ->modalContent(fn (TeslaVehicle $record): View => view(
                        'filament.pages.actions.tesla-tire-pressure-email-preview',
                        [
                            'vehicle' => $record,
                            'driver' => $record->vehicle->currentAllocation->driver,
                            'assessment' => $this->tirePressureAssessment($record),
                        ],
                    ))
                    ->action(function (TeslaVehicle $record): void {
                        $driver = $record->vehicle?->currentAllocation?->driver;

                        if (! $driver || ! filter_var($driver->email, FILTER_VALIDATE_EMAIL)) {
                            Notification::make()
                                ->danger()
                                ->title('Nao foi possivel enviar o aviso')
                                ->body('A viatura nao tem um motorista ativo com email valido.')
                                ->send();

                            return;
                        }

                        try {
                            Mail::to($driver->email)->send(new TeslaTirePressureAlertMail(
                                driver: $driver,
                                vehicle: $record,
                                assessment: $this->tirePressureAssessment($record),
                            ));
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->danger()
                                ->title('Falha no envio do email')
                                ->body('O aviso nao foi enviado. Tenta novamente.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Aviso enviado')
                            ->body("Email enviado para {$driver->email}.")
                            ->send();
                    }),
            ])
            ->recordUrl(fn (TeslaVehicle $record): string => TeslaVehicleDetails::getUrl(['vehicle' => $record]))
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array{status: 'compliant'|'abnormal'|'no_data', pressures: array{fl: float|null, fr: float|null, rl: float|null, rr: float|null}, difference: float|null, problems: list<string>}
     */
    protected function tirePressureAssessment(TeslaVehicle $vehicle): array
    {
        return app(TeslaTirePressureEvaluator::class)->evaluate(
            $vehicle->state === 'offline' ? null : $vehicle->latestSnapshot,
        );
    }

    protected function formatPressure(?float $pressure): string
    {
        return $pressure === null ? '-' : number_format($pressure, 1, ',', ' ').' PSI';
    }

    protected function formatTirePressures(TeslaVehicle $vehicle): string
    {
        $pressures = $this->tirePressureAssessment($vehicle)['pressures'];

        if (in_array(null, $pressures, true)) {
            return '-';
        }

        return collect($pressures)
            ->map(fn (float $pressure, string $position): string => match ($position) {
                'fl' => 'DE',
                'fr' => 'DD',
                'rl' => 'TE',
                'rr' => 'TD',
            }.' '.number_format($pressure, 1, ',', ' '))
            ->implode('  |  ');
    }

    protected function driverCanReceiveTirePressureAlert(TeslaVehicle $vehicle): bool
    {
        $email = $vehicle->vehicle?->currentAllocation?->driver?->email;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return Collection<int, TeslaVehicle>
     */
    protected function eligibleTirePressureAlertVehicles(): Collection
    {
        return TeslaVehicle::query()
            ->with(['latestSnapshot', 'vehicle.currentAllocation.driver'])
            ->where('state', '!=', 'offline')
            ->orderBy('display_name')
            ->get()
            ->filter(fn (TeslaVehicle $vehicle): bool => $this->tirePressureAssessment($vehicle)['status'] === 'abnormal'
                && $this->driverCanReceiveTirePressureAlert($vehicle));
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
