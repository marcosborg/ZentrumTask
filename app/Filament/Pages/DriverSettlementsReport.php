<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Models\VehicleAllocation;
use App\Services\DriverSettlementCalculator;
use App\Services\SettlementBillingResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class DriverSettlementsReport extends Page implements HasTable
{
    use HasFiltersForm {
        HasFiltersForm::normalizeTableFilterValuesFromQueryString insteadof InteractsWithTable;
    }
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Settlements';

    protected static ?string $title = 'Relatorio de settlements';

    protected string $view = 'filament.pages.driver-settlements-report';

    public ?array $filtersForm = [];

    /** @var array<int, array<string, mixed>> */
    private array $billingCache = [];

    private bool $billingCacheBuilt = false;

    public function mount(): void {}

    public function mountHasFilters(): void
    {
        $filtersSessionKey = $this->getFiltersSessionKey();

        if (! count($this->filtersForm ?? [])) {
            $this->filtersForm = null;
        }

        if (
            ($this->filtersForm === null) &&
            $this->persistsFiltersInSession() &&
            session()->has($filtersSessionKey)
        ) {
            $this->filtersForm = session()->get($filtersSessionKey);
        }

        if ($this->filtersForm) {
            $this->normalizeTableFilterValuesFromQueryString($this->filtersForm);
        }

        if (
            ! ($this->filtersForm['period_start'] ?? null) ||
            ! ($this->filtersForm['period_end'] ?? null)
        ) {
            $period = $this->resolveDefaultPeriod();

            $this->filtersForm = array_merge($this->filtersForm ?? [], [
                'platform' => $this->filtersForm['platform'] ?? null,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'driver_id' => $this->filtersForm['driver_id'] ?? null,
            ]);
        }

        $this->getFiltersForm()->fill($this->filtersForm);

        if ($this->persistsFiltersInSession()) {
            session()->put($filtersSessionKey, $this->filtersForm);
        }
    }

    public function updatedFiltersForm(): void
    {
        $this->resetBillingCache();

        if ($this->persistsFiltersInSession()) {
            session()->put(
                $this->getFiltersSessionKey(),
                $this->filtersForm,
            );
        }
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->schema($this->getFiltersFormSchema());
    }

    public function getFiltersForm(): Schema
    {
        if ((! $this->isCachingSchemas) && $this->hasCachedSchema('filtersForm')) {
            return $this->getSchema('filtersForm');
        }

        $schema = $this->makeSchema()
            ->columns([
                'md' => 2,
                'xl' => 3,
                '2xl' => 4,
            ])
            ->extraAttributes(['wire:partial' => 'table-filters-form'])
            ->live()
            ->statePath('filtersForm');

        return $this->filtersForm($schema);
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function getFiltersFormSchema(): array
    {
        return [
            Select::make('platform')
                ->label('Plataforma')
                ->options([
                    'bolt' => 'Bolt',
                    'uber' => 'Uber',
                ])
                ->placeholder('Todas')
                ->native(false),
            DatePicker::make('period_start')
                ->label('Periodo inicio')
                ->required()
                ->native(false),
            DatePicker::make('period_end')
                ->label('Periodo fim')
                ->required()
                ->native(false),
            Select::make('driver_id')
                ->label('Motorista')
                ->placeholder('Todos')
                ->options($this->driverOptions())
                ->searchable()
                ->native(false)
                ->nullable(),
        ];
    }

    public function applyFilters(): void
    {
        $this->getFiltersForm()->validate();
        $this->resetBillingCache();
        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Filtros aplicados')
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildSettlementsQuery())
            ->columns([
                TextColumn::make('driver_name')
                    ->label('Motorista')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('drivers.name', 'like', "%{$search}%");
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('drivers.name', $direction);
                    }),
                TextColumn::make('driver_email')
                    ->label('Email')
                    ->state(fn (DriverSettlement $record): string => (string) ($record->driver_email ?? '—'))
                    ->copyable()
                    ->copyableState(fn (DriverSettlement $record): string => (string) ($record->driver_email ?? ''))
                    ->copyMessage('Email copiado')
                    ->copyMessageDuration(1500),
                TextColumn::make('period_range')
                    ->label('Periodo')
                    ->state(function (DriverSettlement $record): string {
                        $start = $record->period_start?->format('d/m/Y') ?? '-';
                        $end = $record->period_end?->format('d/m/Y') ?? '-';

                        return "{$start} - {$end}";
                    }),
                TextColumn::make('uber_net')
                    ->label('Uber')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('bolt_net')
                    ->label('Bolt')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('total_operadores')
                    ->label('Total operadores')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => (float) $record->uber_net + (float) $record->bolt_net)
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('tips_total_balance')
                    ->label('Tips')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('prio_expenses')
                    ->label('PRIO')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->prioExpensesForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('via_verde_expenses')
                    ->label('Via Verde')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->viaVerdeExpensesForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('adjustments_expenses')
                    ->label('Ajustes')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->adjustmentsForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('expenses_total')
                    ->label('Despesas')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => (float) $record->expenses_total)
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('rental_days')
                    ->label('Dias')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): int => $this->billingFor($record)['rental_days'])
                    ->sortable(),
                TextColumn::make('rent_total')
                    ->label('Aluguer')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->billingFor($record)['rent_total'])
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('total_liquido_sem_tips')
                    ->label('Valor da semana')
                    ->alignRight()
                    ->state(function (DriverSettlement $record): float {
                        $totalOperadores = (float) $record->uber_net + (float) $record->bolt_net;

                        return $totalOperadores
                            + (float) $record->tips_total_balance
                            - (float) $record->expenses_total
                            - (float) $this->billingFor($record)['rent_total'];
                    })
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('percent_company')
                    ->label('Empresa %')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): ?float => $this->billingFor($record)['percent_company'])
                    ->formatStateUsing(fn ($state): string => $this->formatPercent($state)),
                TextColumn::make('percent_driver')
                    ->label('Motorista %')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): ?float => $this->billingFor($record)['percent_driver'])
                    ->formatStateUsing(fn ($state): string => $this->formatPercent($state)),
                TextColumn::make('withholding_label')
                    ->label('Retencao')
                    ->badge()
                    ->state(fn (DriverSettlement $record): string => $this->billingFor($record)['withholding_label'])
                    ->color(fn (DriverSettlement $record): string => $this->billingBadgeColor($record, 'withholding')),
                TextColumn::make('vat_23_label')
                    ->label('IVA')
                    ->badge()
                    ->state(fn (DriverSettlement $record): string => $this->billingFor($record)['vat_label'])
                    ->color(fn (DriverSettlement $record): string => $this->billingBadgeColor($record, 'vat'))
                    ->tooltip(function (DriverSettlement $record): ?string {
                        $data = $this->billingFor($record);
                        $mode = $data['vat_refund_mode'] ?? null;
                        $label = $data['vat_label'] ?? null;

                        if ($mode && $label) {
                            return "Modo: {$mode} · {$label}";
                        }

                        return $mode ? "Modo: {$mode}" : $label;
                    }),
                TextColumn::make('profile_status')
                    ->label('Perfil')
                    ->badge()
                    ->state(fn (DriverSettlement $record): string => $this->billingProfileLabel($record))
                    ->color(fn (DriverSettlement $record): string => $this->billingProfileColor($record)),
                TextColumn::make('amount_payable')
                    ->label('A pagar')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                $this->viewDetailsAction(),
                $this->deleteSettlementAction(),
            ])
            ->defaultSort('period_start', 'desc');
    }

    public function generateSettlementsAction(): Action
    {
        return Action::make('generateSettlements')
            ->label('Gerar settlements')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Cria settlements para o periodo selecionado. Settlements existentes nao sao alterados.')
            ->action(function (): void {
                $filters = $this->filtersForActions();
                if (! $filters) {
                    return;
                }

                $result = DB::transaction(fn (): array => app(DriverSettlementCalculator::class)
                    ->calculate(
                        $filters['period_start'],
                        $filters['period_end'],
                        $filters['driver_id']
                    ));

                $this->resetBillingCache();
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Settlements gerados')
                    ->body("Criados: {$result['created']} | Skips: {$result['skipped']} | Sem perfil: {$result['missing_profiles']}")
                    ->send();
            });
    }

    public function deletePeriodSettlementsAction(): Action
    {
        return Action::make('deletePeriodSettlements')
            ->label('Apagar settlements do periodo')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Remove apenas settlements totalmente dentro do periodo selecionado.')
            ->action(function (): void {
                $filters = $this->filtersForActions();
                if (! $filters) {
                    return;
                }

                $deleted = DB::transaction(fn (): int => $this->deleteSettlementsForPeriod(
                    $filters['period_start'],
                    $filters['period_end'],
                    $filters['driver_id']
                ));

                $this->resetBillingCache();
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Settlements removidos')
                    ->body("Total removido: {$deleted}")
                    ->send();
            });
    }

    public function regenerateSettlementsAction(): Action
    {
        return Action::make('regenerateSettlements')
            ->label('Recalcular (regenerar)')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Apaga settlements do periodo e recalcula a partir dos balances existentes.')
            ->action(function (): void {
                $filters = $this->filtersForActions();
                if (! $filters) {
                    return;
                }

                $result = DB::transaction(function () use ($filters): array {
                    $deleted = $this->deleteSettlementsForPeriod(
                        $filters['period_start'],
                        $filters['period_end'],
                        $filters['driver_id']
                    );

                    $calculated = app(DriverSettlementCalculator::class)
                        ->calculate(
                            $filters['period_start'],
                            $filters['period_end'],
                            $filters['driver_id']
                        );

                    return [
                        'deleted' => $deleted,
                        'created' => $calculated['created'] ?? 0,
                        'skipped' => $calculated['skipped'] ?? 0,
                        'missing_profiles' => $calculated['missing_profiles'] ?? 0,
                    ];
                });

                $this->resetBillingCache();
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Settlements recalculados')
                    ->body("Removidos: {$result['deleted']} | Criados: {$result['created']} | Skips: {$result['skipped']} | Sem perfil: {$result['missing_profiles']}")
                    ->send();
            });
    }

    /**
     * @return array{period_start: string|null, period_end: string|null, driver_id: int|null, platform: string|null}
     */
    private function filtersState(): array
    {
        $state = $this->getFiltersForm()->getRawState() ?? [];

        return [
            'platform' => $state['platform'] ?? null,
            'period_start' => $this->normalizeDate($state['period_start'] ?? null),
            'period_end' => $this->normalizeDate($state['period_end'] ?? null),
            'driver_id' => $state['driver_id'] ?? null,
        ];
    }

    /**
     * @return array{period_start: string, period_end: string, driver_id: int|null, platform: string|null}|null
     */
    private function filtersForActions(): ?array
    {
        $state = $this->filtersState();

        if (! $state['period_start'] || ! $state['period_end']) {
            Notification::make()
                ->danger()
                ->title('Selecione um periodo valido')
                ->send();

            return null;
        }

        return [
            'platform' => $state['platform'],
            'period_start' => (string) $state['period_start'],
            'period_end' => (string) $state['period_end'],
            'driver_id' => $state['driver_id'] ? (int) $state['driver_id'] : null,
        ];
    }

    private function buildSettlementsQuery(): Builder
    {
        $filters = $this->filtersState();
        $periodStart = $filters['period_start'];
        $periodEnd = $filters['period_end'];
        $driverId = $filters['driver_id'];
        $platform = $filters['platform'];

        $query = DriverSettlement::query()
            ->leftJoin('drivers', 'drivers.id', '=', 'driver_settlements.driver_id')
            ->select([
                'driver_settlements.*',
                'drivers.name as driver_name',
                'drivers.email as driver_email',
            ]);

        $query->selectSub(
            PlatformDriverBalance::query()
                ->selectRaw('COALESCE(SUM(net_amount), 0)')
                ->where('platform', 'uber')
                ->whereColumn('platform_driver_balances.driver_id', 'driver_settlements.driver_id')
                ->whereColumn('platform_driver_balances.period_start', '>=', 'driver_settlements.period_start')
                ->whereColumn('platform_driver_balances.period_end', '<=', 'driver_settlements.period_end'),
            'uber_net'
        );

        $query->selectSub(
            PlatformDriverBalance::query()
                ->selectRaw('COALESCE(SUM(net_amount), 0)')
                ->where('platform', 'bolt')
                ->whereColumn('platform_driver_balances.driver_id', 'driver_settlements.driver_id')
                ->whereColumn('platform_driver_balances.period_start', '>=', 'driver_settlements.period_start')
                ->whereColumn('platform_driver_balances.period_end', '<=', 'driver_settlements.period_end'),
            'bolt_net'
        );

        $query->selectSub(
            PlatformDriverBalance::query()
                ->selectRaw('COALESCE(SUM(tips_amount), 0)')
                ->whereColumn('platform_driver_balances.driver_id', 'driver_settlements.driver_id')
                ->whereColumn('platform_driver_balances.period_start', '>=', 'driver_settlements.period_start')
                ->whereColumn('platform_driver_balances.period_end', '<=', 'driver_settlements.period_end'),
            'tips_total_balance'
        );

        if ($periodStart && $periodEnd) {
            $query->whereDate('driver_settlements.period_start', '>=', $periodStart)
                ->whereDate('driver_settlements.period_end', '<=', $periodEnd);
        }

        if ($driverId) {
            $query->where('driver_settlements.driver_id', $driverId);
        }

        if ($platform) {
            $query->whereExists(function (QueryBuilder $sub) use ($platform): void {
                $sub->selectRaw('1')
                    ->from('platform_driver_balances')
                    ->where('platform_driver_balances.platform', $platform)
                    ->whereColumn('platform_driver_balances.driver_id', 'driver_settlements.driver_id')
                    ->whereColumn('platform_driver_balances.period_start', '>=', 'driver_settlements.period_start')
                    ->whereColumn('platform_driver_balances.period_end', '<=', 'driver_settlements.period_end');
            });
        }

        return $query;
    }

    private function viewDetailsAction(): Action
    {
        return Action::make('viewDetails')
            ->label('Ver detalhes')
            ->icon(Heroicon::OutlinedEye)
            ->modalHeading('Detalhes do settlement')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function (DriverSettlement $record) {
                $balances = $this->balancesForSettlement($record);
                $billing = $this->billingFor($record);
                $prioExpenses = $this->prioExpensesForSettlement($record);
                $viaVerdeExpenses = $this->viaVerdeExpensesForSettlement($record);
                $adjustments = $this->adjustmentsForSettlement($record);

                return view('filament.pages.driver-settlements-report-details', [
                    'settlement' => $record,
                    'balances' => $balances,
                    'billing' => $billing,
                    'prioExpenses' => $prioExpenses,
                    'viaVerdeExpenses' => $viaVerdeExpenses,
                    'adjustments' => $adjustments,
                ]);
            })
            ->action(function (): void {});
    }

    private function deleteSettlementAction(): Action
    {
        return Action::make('deleteSettlement')
            ->label('Apagar')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->modalDescription('Remove apenas este settlement.')
            ->action(function (DriverSettlement $record): void {
                DB::transaction(fn (): bool => (bool) $record->delete());

                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Settlement removido')
                    ->send();
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function balancesForSettlement(DriverSettlement $settlement): array
    {
        return PlatformDriverBalance::query()
            ->where('driver_id', $settlement->driver_id)
            ->whereDate('period_start', '>=', $settlement->period_start)
            ->whereDate('period_end', '<=', $settlement->period_end)
            ->orderBy('platform')
            ->orderBy('period_start')
            ->get([
                'platform',
                'period_start',
                'period_end',
                'net_amount',
                'tips_amount',
                'source_file',
            ])
            ->map(fn (PlatformDriverBalance $row): array => [
                'platform' => $row->platform,
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
                'net_amount' => (float) $row->net_amount,
                'tips_amount' => (float) $row->tips_amount,
                'source_file' => $row->source_file,
            ])
            ->all();
    }

    /**
     * @return array{total: float, count: int, rows: array<int, array<string, mixed>>}
     */
    private function prioExpensesForSettlement(DriverSettlement $settlement): array
    {
        $rows = \App\Models\PrioTransaction::query()
            ->where('driver_id', $settlement->driver_id)
            ->where('assignment_status', 'ok')
            ->whereDate('occurred_at', '>=', $settlement->period_start)
            ->whereDate('occurred_at', '<=', $settlement->period_end)
            ->orderBy('occurred_at')
            ->get([
                'occurred_at',
                'vehicle_plate',
                'card_code',
                'net_amount',
            ])
            ->map(fn (\App\Models\PrioTransaction $row): array => [
                'occurred_at' => $row->occurred_at,
                'vehicle_plate' => $row->vehicle_plate,
                'card_code' => $row->card_code,
                'net_amount' => (float) $row->net_amount,
            ])
            ->all();

        $total = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['net_amount'], 0.0);

        return [
            'total' => round($total, 2),
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{total: float, count: int, rows: array<int, array<string, mixed>>}
     */
    private function viaVerdeExpensesForSettlement(DriverSettlement $settlement): array
    {
        $rows = \App\Models\ViaVerdeTransaction::query()
            ->where('driver_id', $settlement->driver_id)
            ->where('assignment_status', 'ok')
            ->whereDate('occurred_at', '>=', $settlement->period_start)
            ->whereDate('occurred_at', '<=', $settlement->period_end)
            ->orderBy('occurred_at')
            ->get([
                'occurred_at',
                'vehicle_plate',
                'location',
                'amount',
            ])
            ->map(fn (\App\Models\ViaVerdeTransaction $row): array => [
                'occurred_at' => $row->occurred_at,
                'vehicle_plate' => $row->vehicle_plate,
                'location' => $row->location,
                'amount' => (float) $row->amount,
            ])
            ->all();

        $total = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['amount'], 0.0);

        return [
            'total' => round($total, 2),
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{total: float, count: int, rows: array<int, array<string, mixed>>}
     */
    private function adjustmentsForSettlement(DriverSettlement $settlement): array
    {
        $adjustments = \App\Models\DriverAdjustment::query()
            ->where('driver_id', $settlement->driver_id)
            ->whereDate('starts_at', '<=', $settlement->period_end)
            ->orderBy('starts_at')
            ->get([
                'starts_at',
                'recurrence_weeks',
                'category',
                'description',
                'amount',
            ]);

        if ($adjustments->isEmpty()) {
            return [
                'total' => 0.0,
                'count' => 0,
                'rows' => [],
            ];
        }

        $start = Carbon::parse($settlement->period_start)->startOfDay();
        $end = Carbon::parse($settlement->period_end)->endOfDay();

        $rows = [];
        $total = 0.0;

        foreach ($adjustments as $adjustment) {
            $startsAt = Carbon::parse($adjustment->starts_at)->startOfDay();
            $weeks = (int) ($adjustment->recurrence_weeks ?? 1);
            $weeks = max(1, $weeks);

            for ($i = 0; $i < $weeks; $i++) {
                $occurrence = $startsAt->copy()->addWeeks($i);

                if ($occurrence->lt($start) || $occurrence->gt($end)) {
                    continue;
                }

                $rows[] = [
                    'occurred_at' => $occurrence,
                    'category' => $adjustment->category,
                    'description' => $adjustment->description,
                    'amount' => (float) $adjustment->amount,
                ];

                $total += (float) $adjustment->amount;
            }
        }

        return [
            'total' => round($total, 2),
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    private function deleteSettlementsForPeriod(string $periodStart, string $periodEnd, ?int $driverId = null): int
    {
        return DriverSettlement::query()
            ->when($driverId, fn (Builder $query) => $query->where('driver_id', $driverId))
            ->whereDate('period_start', '>=', $periodStart)
            ->whereDate('period_end', '<=', $periodEnd)
            ->delete();
    }

    /**
     * @return array<int, string>
     */
    private function driverOptions(): array
    {
        return cache()->remember('driver_options_select', 60, fn (): array => Driver::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all());
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolveDefaultPeriod(): array
    {
        $latestBalance = PlatformDriverBalance::query()
            ->select(['period_start', 'period_end'])
            ->orderByDesc('period_end')
            ->first();

        $latestSettlement = DriverSettlement::query()
            ->select(['period_start', 'period_end'])
            ->orderByDesc('period_end')
            ->first();

        $latest = collect([$latestBalance, $latestSettlement])
            ->filter()
            ->sortByDesc(fn ($row) => Carbon::parse($row->period_end))
            ->first();

        if ($latest) {
            return [
                'start' => Carbon::parse($latest->period_start)->toDateString(),
                'end' => Carbon::parse($latest->period_end)->toDateString(),
            ];
        }

        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function formatMoney(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 2, ',', ' ')." \u{20AC}";
    }

    private function formatPercent(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 2, ',', ' ').'%';
    }

    private function resetBillingCache(): void
    {
        $this->billingCache = [];
        $this->billingCacheBuilt = false;
    }

    /**
     * @return array<string, mixed>
     */
    private function billingFor(DriverSettlement $record): array
    {
        if (! $this->billingCacheBuilt) {
            $this->buildBillingCache();
        }

        return $this->billingCache[$record->id] ?? $this->emptyBillingData();
    }

    private function buildBillingCache(): void
    {
        if ($this->billingCacheBuilt) {
            return;
        }

        $records = $this->getTableRecords();

        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            $records = $records->getCollection();
        }

        $records = collect($records)->values();

        if ($records->isEmpty()) {
            $this->billingCacheBuilt = true;

            return;
        }

        $driverIds = $records->pluck('driver_id')->filter()->unique()->values();

        if ($driverIds->isEmpty()) {
            $this->billingCacheBuilt = true;

            return;
        }

        $minStart = Carbon::parse($records->min('period_start'))->startOfDay();
        $maxEnd = Carbon::parse($records->max('period_end'))->endOfDay();

        $drivers = Driver::query()
            ->whereIn('id', $driverIds)
            ->get()
            ->keyBy('id');

        $profilesByDriver = DriverBillingProfile::query()
            ->whereIn('driver_id', $driverIds)
            ->where('active', true)
            ->where(function ($query) use ($maxEnd): void {
                $query->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $maxEnd);
            })
            ->where(function ($query) use ($minStart): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $minStart);
            })
            ->orderBy('valid_from')
            ->get()
            ->groupBy('driver_id');

        $allocationsByDriver = VehicleAllocation::query()
            ->whereIn('driver_id', $driverIds)
            ->where('starts_at', '<=', $maxEnd)
            ->where(function ($query) use ($minStart): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $minStart);
            })
            ->get()
            ->groupBy('driver_id');

        $resolver = app(SettlementBillingResolver::class);

        foreach ($records as $record) {
            $driver = $drivers->get($record->driver_id);

            if (! $driver) {
                $this->billingCache[$record->id] = $this->emptyBillingData();

                continue;
            }

            $start = Carbon::parse($record->period_start)->startOfDay();
            $end = Carbon::parse($record->period_end)->endOfDay();

            $profiles = $profilesByDriver->get($record->driver_id, collect());
            $allocations = $allocationsByDriver->get($record->driver_id, collect());

            $this->billingCache[$record->id] = $resolver->resolveSettlementBilling(
                $driver,
                $start,
                $end,
                $profiles,
                $allocations
            );
        }

        $this->billingCacheBuilt = true;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBillingData(): array
    {
        return [
            'profile_status' => 'missing',
            'billing_profile_id' => null,
            'billing_profile_label' => null,
            'rental_days' => 0,
            'rent_total' => 0.0,
            'percent_company' => null,
            'percent_driver' => null,
            'withholding_label' => '—',
            'vat_label' => '—',
            'vat_refund_mode' => null,
        ];
    }

    private function billingProfileLabel(DriverSettlement $record): string
    {
        return match ($this->billingFor($record)['profile_status']) {
            'ok' => 'OK',
            'ambiguous' => 'Perfil multiplo',
            default => 'Sem perfil',
        };
    }

    private function billingProfileColor(DriverSettlement $record): string
    {
        return match ($this->billingFor($record)['profile_status']) {
            'ok' => 'success',
            'ambiguous' => 'warning',
            default => 'danger',
        };
    }

    private function billingBadgeColor(DriverSettlement $record, string $type): string
    {
        $data = $this->billingFor($record);

        if ($type === 'withholding') {
            return str_starts_with($data['withholding_label'], 'Sim') ? 'warning' : ($data['withholding_label'] === 'Nao' ? 'gray' : 'gray');
        }

        if ($type === 'vat') {
            return str_starts_with($data['vat_label'], 'Sim') ? 'success' : 'gray';
        }

        return 'gray';
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'd/m/Y',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
