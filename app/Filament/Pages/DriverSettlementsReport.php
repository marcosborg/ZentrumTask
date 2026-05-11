<?php

namespace App\Filament\Pages;

use App\Mail\DriverSettlementSummaryMail;
use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\DriverBalance;
use App\Models\DriverBalanceMovement;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Models\SettlementEmailLog;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use App\Services\DriverDepositService;
use App\Services\DriverSettlementCalculator;
use App\Services\SettlementBillingResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
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

    /** @var array<int, array{name: string, email: string}> */
    private array $driverIdentityCache = [];

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

    public function deleteManualAdjustment(int $driverId, int $adjustmentId): void
    {
        $adjustment = DriverAdjustment::query()
            ->where('driver_id', $driverId)
            ->find($adjustmentId);

        if (! $adjustment) {
            Notification::make()
                ->danger()
                ->title('Ajuste nao encontrado')
                ->send();

            return;
        }

        $adjustment->delete();

        $this->resetBillingCache();
        $this->resetTable();

        Notification::make()
            ->success()
            ->title('Ajuste removido')
            ->send();
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

    /**
     * @return list<string>
     */
    public function settlementChecklist(DriverSettlement $record): array
    {
        $items = [];

        if ((int) ($record->email_sent_count ?? 0) > 0) {
            $items[] = 'Email';
        }

        if ($this->hasGreenReceipt($record)) {
            $items[] = 'Recibo';
        }

        if ((bool) $record->is_paid) {
            $items[] = 'Pago';
        }

        return $items === [] ? ['Pendente'] : $items;
    }

    public function hasGreenReceipt(DriverSettlement $record): bool
    {
        return filled($record->green_receipt_path);
    }

    public function hasGreenReceiptFile(DriverSettlement $record): bool
    {
        return $this->hasGreenReceipt($record)
            && Storage::disk('local')->exists((string) $record->green_receipt_path);
    }

    public function saveGreenReceipt(DriverSettlement $record, string $path): void
    {
        $previousPath = $record->green_receipt_path;

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        $record->forceFill([
            'green_receipt_path' => $path,
            'green_receipt_uploaded_at' => now(),
            'green_receipt_uploaded_by_user_id' => auth()->id(),
        ])->save();
    }

    public function downloadGreenReceipt(DriverSettlement $record): ?StreamedResponse
    {
        $record->refresh();
        $path = $record->green_receipt_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            Notification::make()
                ->danger()
                ->title('Recibo verde indisponivel')
                ->body('O ficheiro nao foi encontrado no storage.')
                ->send();

            return null;
        }

        return Storage::disk('local')->download($path, basename($path));
    }

    public function markSettlementPaid(DriverSettlement $record): bool
    {
        $record->refresh();

        if (! $this->hasGreenReceiptFile($record)) {
            Notification::make()
                ->danger()
                ->title('Anexe o recibo verde antes de marcar como pago.')
                ->send();

            return false;
        }

        DB::transaction(function () use ($record): void {
            $balance = $this->resolveBalance((int) $record->driver_id);
            $current = round((float) $balance->current_balance, 2);
            $transferredAmount = round((float) ($record->amount_due ?? 0), 2);

            if ($current !== 0.0) {
                DriverBalanceMovement::query()->create([
                    'driver_id' => $record->driver_id,
                    'driver_balance_id' => $balance->id,
                    'driver_settlement_id' => $record->id,
                    'amount' => -$current,
                    'type' => 'payment',
                    'description' => 'Pagamento settlement '.$record->period_start?->format('d/m/Y').' - '.$record->period_end?->format('d/m/Y'),
                ]);
            }

            $balance->forceFill([
                'current_balance' => 0,
                'is_settled' => true,
                'settled_at' => now(),
                'last_settlement_id' => $record->id,
            ])->save();

            $record->forceFill([
                'amount_due' => 0,
                'amount_transferred' => $transferredAmount,
                'is_paid' => true,
                'paid_at' => now(),
            ])->save();
        });

        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildSettlementsQuery())
            ->columns([
                TextColumn::make('driver_name')
                    ->label('Motorista')
                    ->state(fn (DriverSettlement $record): string => $this->driverIdentity((int) $record->driver_id)['name'])
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('drivers.name', 'like', "%{$search}%");
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('drivers.name', $direction);
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
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tips_total_balance')
                    ->label('Tips')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney($state, 'add')),
                TextColumn::make('prio_expenses')
                    ->label('PRIO')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->prioExpensesForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney($state, 'subtract')),
                TextColumn::make('via_verde_expenses')
                    ->label('Via Verde')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->viaVerdeExpensesForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney($state, 'subtract')),
                TextColumn::make('adjustments_expenses')
                    ->label('Ajustes')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->adjustmentsForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney(-1 * (float) $state, 'signed')),
                TextColumn::make('extra_km_expenses')
                    ->label('Km extra')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->extraKmExpensesForSettlement($record)['total'])
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney($state, 'subtract')),
                TextColumn::make('expenses_total')
                    ->label('Despesas')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => (float) $record->expenses_total)
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney(-1 * (float) $state, 'signed'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rental_days')
                    ->label('Dias')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): int => $this->billingFor($record)['rental_days'])
                    ->sortable(),
                TextColumn::make('rent_total')
                    ->label('Aluguer')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => $this->billingFor($record)['rent_total'])
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney($state, 'subtract')),
                TextColumn::make('total_liquido_sem_tips')
                    ->label('Valor da semana')
                    ->alignRight()
                    ->state(function (DriverSettlement $record): float {
                        return (float) $record->driver_share
                            + (float) $record->tips_total
                            - (float) $record->expenses_total
                            - (float) $this->billingFor($record)['rent_total'];
                    })
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('carry_over_balance')
                    ->label('Saldo transitado')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => (float) $record->carry_over_balance)
                    ->formatStateUsing(fn ($state): string => $this->formatEffectMoney($state, 'signed')),
                TextColumn::make('percent_company')
                    ->label('Empresa %')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): ?float => $this->billingFor($record)['percent_company'])
                    ->formatStateUsing(fn ($state): string => $this->formatPercent($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('percent_driver')
                    ->label('Motorista %')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): ?float => $this->billingFor($record)['percent_driver'])
                    ->formatStateUsing(fn ($state): string => $this->formatPercent($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('withholding_label')
                    ->label('Retencao')
                    ->badge()
                    ->state(fn (DriverSettlement $record): string => $this->billingFor($record)['withholding_label'])
                    ->color(fn (DriverSettlement $record): string => $this->billingBadgeColor($record, 'withholding'))
                    ->toggleable(isToggledHiddenByDefault: true),
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
                            return "Modo: {$mode} - {$label}";
                        }

                        return $mode ? "Modo: {$mode}" : $label;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('profile_status')
                    ->label('Perfil')
                    ->badge()
                    ->state(fn (DriverSettlement $record): string => $this->billingProfileLabel($record))
                    ->color(fn (DriverSettlement $record): string => $this->billingProfileColor($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('workflow_checklist')
                    ->label('Checklist')
                    ->badge()
                    ->state(fn (DriverSettlement $record): array => $this->settlementChecklist($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Email' => 'info',
                        'Recibo' => 'success',
                        'Pago' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('amount_payable')
                    ->label('Valor a transferir')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => (float) $record->amount_due)
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('amount_transferred')
                    ->label('Valor transferido')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): float => (float) ($record->amount_transferred ?? 0))
                    ->formatStateUsing(fn ($state): string => $this->formatMoney($state)),
                TextColumn::make('is_paid')
                    ->label('Pago')
                    ->badge()
                    ->state(fn (DriverSettlement $record): string => $record->is_paid ? 'Pago' : 'Pendente')
                    ->color(fn (DriverSettlement $record): string => $record->is_paid ? 'success' : 'warning'),
                TextColumn::make('email_sent_count')
                    ->label('Emails')
                    ->alignRight()
                    ->state(fn (DriverSettlement $record): int => (int) ($record->email_sent_count ?? 0))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('last_emailed_at')
                    ->label('Ultimo envio')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                $this->sendSettlementEmailAction(),
                $this->manageGreenReceiptAction(),
                $this->downloadGreenReceiptAction(),
                $this->manageAdjustmentsAction(),
                $this->adjustBalanceAction(),
                $this->markPaidAction(),
                $this->recalculateSettlementAction(),
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

    public function sendPeriodSettlementEmailsAction(): Action
    {
        return Action::make('sendPeriodSettlementEmails')
            ->label('Enviar emails do periodo')
            ->color('info')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalDescription('Envia email do settlement conforme o modo configurado (test/production).')
            ->action(function (): void {
                $filters = $this->filtersForActions();

                if (! $filters) {
                    return;
                }

                $query = DriverSettlement::query()
                    ->whereDate('period_start', '>=', $filters['period_start'])
                    ->whereDate('period_end', '<=', $filters['period_end']);

                if ($filters['driver_id']) {
                    $query->where('driver_id', $filters['driver_id']);
                }

                $sent = 0;
                $failed = 0;
                $skipped = 0;

                $query->orderBy('id')->chunkById(50, function ($rows) use (&$sent, &$failed, &$skipped): void {
                    foreach ($rows as $row) {
                        $recipient = $this->settlementRecipientForRecord($row);

                        if (! $recipient) {
                            $skipped++;

                            continue;
                        }

                        try {
                            $this->sendSettlementEmail($row, $recipient);
                            $sent++;
                        } catch (Throwable) {
                            $failed++;
                        }
                    }
                });

                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Envio concluido')
                    ->body("Enviados: {$sent} | Falhas: {$failed} | Ignorados: {$skipped} | Modo: ".$this->settlementDeliveryMode())
                    ->send();
            });
    }

    public function recalculateSettlement(DriverSettlement $record): array
    {
        return DB::transaction(function () use ($record): array {
            $deleted = $this->deleteSettlementsForPeriod(
                $record->period_start?->toDateString() ?? '',
                $record->period_end?->toDateString() ?? '',
                (int) $record->driver_id
            );

            $calculated = app(DriverSettlementCalculator::class)
                ->calculate(
                    $record->period_start?->toDateString() ?? '',
                    $record->period_end?->toDateString() ?? '',
                    (int) $record->driver_id
                );

            return [
                'deleted' => $deleted,
                'created' => $calculated['created'] ?? 0,
                'skipped' => $calculated['skipped'] ?? 0,
                'missing_profiles' => $calculated['missing_profiles'] ?? 0,
            ];
        });
    }

    private function sendSettlementEmailAction(): Action
    {
        return Action::make('sendSettlementEmail')
            ->label('Enviar email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('info')
            ->requiresConfirmation()
            ->modalDescription('Envia email do settlement conforme o modo configurado (test/production).')
            ->action(function (DriverSettlement $record): void {
                $recipient = $this->settlementRecipientForRecord($record);

                if (! $recipient) {
                    Notification::make()
                        ->danger()
                        ->title('Destinatario de email invalido')
                        ->body($this->settlementDeliveryMode() === 'test'
                            ? 'Defina SETTLEMENT_TEST_EMAIL no .env'
                            : 'Motorista sem email valido')
                        ->send();

                    return;
                }

                try {
                    $this->sendSettlementEmail($record, $recipient);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Falha ao enviar email')
                        ->body($this->sanitizeUtf8($exception->getMessage()) ?? 'Erro desconhecido')
                        ->send();

                    return;
                }

                $record->refresh();
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Email enviado')
                    ->body("Destino: {$recipient} | Modo: ".$this->settlementDeliveryMode().' | Total enviados neste settlement: '.((int) $record->email_sent_count))
                    ->send();
            });
    }

    private function recalculateSettlementAction(): Action
    {
        return Action::make('recalculateSettlement')
            ->label('Recalcular')
            ->color('primary')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription('Apaga este settlement e recalcula apenas este motorista neste periodo.')
            ->action(function (?DriverSettlement $record): void {
                if (! $record) {
                    Notification::make()
                        ->danger()
                        ->title('Settlement indisponivel')
                        ->body('O registo original ja nao existe. Atualize a tabela e tente novamente.')
                        ->send();

                    return;
                }

                $result = $this->recalculateSettlement($record);

                $this->resetBillingCache();
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Settlement recalculado')
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
                $record->loadMissing('greenReceiptUploadedBy');

                $balances = $this->balancesForSettlement($record);
                $billing = $this->billingFor($record);
                $driverIdentity = $this->driverIdentity((int) $record->driver_id);
                $prioExpenses = $this->prioExpensesForSettlement($record);
                $viaVerdeExpenses = $this->viaVerdeExpensesForSettlement($record);
                $adjustments = $this->adjustmentsForSettlement($record);
                $depositSummary = $this->depositSummaryForDriver((int) $record->driver_id);
                $depositHistory = $this->depositHistoryForDriver((int) $record->driver_id);
                $balance = $this->resolveBalance((int) $record->driver_id);
                $balanceMovements = $this->balanceMovementsForSettlement($record);
                $emailLogs = $this->emailLogsForSettlement($record);

                return view('filament.pages.driver-settlements-report-details', [
                    'settlement' => $record,
                    'driverIdentity' => $driverIdentity,
                    'balances' => $balances,
                    'billing' => $billing,
                    'prioExpenses' => $prioExpenses,
                    'viaVerdeExpenses' => $viaVerdeExpenses,
                    'adjustments' => $adjustments,
                    'depositSummary' => $depositSummary,
                    'depositHistory' => $depositHistory,
                    'balance' => $balance,
                    'balanceMovements' => $balanceMovements,
                    'emailLogs' => $emailLogs,
                    'greenReceiptDownloadUrl' => $this->hasGreenReceipt($record)
                        ? route('driver-settlements.green-receipt.download', $record)
                        : null,
                ]);
            })
            ->action(function (): void {});
    }

    private function sendSettlementEmail(DriverSettlement $record, string $recipient): void
    {
        DB::transaction(function () use ($record, $recipient): void {
            $record->refresh();
            $payload = $this->settlementEmailPayload($record);

            try {
                $mail = Mail::to($recipient);
                $copyRecipient = $this->settlementCopyRecipient();

                if ($copyRecipient !== null) {
                    $mail->bcc($copyRecipient);
                }

                $sentMessage = $mail->send(new DriverSettlementSummaryMail($payload));

                $record->forceFill([
                    'email_sent_count' => ((int) $record->email_sent_count) + 1,
                    'last_emailed_at' => now(),
                    'last_emailed_to' => $recipient,
                ])->save();

                SettlementEmailLog::query()->create([
                    'driver_settlement_id' => $record->id,
                    'driver_id' => $record->driver_id,
                    'triggered_by_user_id' => auth()->id(),
                    'recipient' => $recipient,
                    'status' => 'sent',
                    'message_id' => is_object($sentMessage) && method_exists($sentMessage, 'getMessageId')
                        ? $sentMessage->getMessageId()
                        : null,
                ]);
            } catch (Throwable $exception) {
                SettlementEmailLog::query()->create([
                    'driver_settlement_id' => $record->id,
                    'driver_id' => $record->driver_id,
                    'triggered_by_user_id' => auth()->id(),
                    'recipient' => $recipient,
                    'status' => 'failed',
                    'error_message' => $this->sanitizeUtf8($exception->getMessage()) ?? 'Erro desconhecido',
                ]);

                throw $exception;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementEmailPayload(DriverSettlement $record): array
    {
        $driver = $this->driverIdentity((int) $record->driver_id);
        $balances = $this->balancesForSettlement($record);
        $billing = $this->billingFor($record);
        $balanceMovements = $this->balanceMovementsForSettlement($record);
        $prioExpenses = $this->prioExpensesForSettlement($record);
        $viaVerdeExpenses = $this->viaVerdeExpensesForSettlement($record);
        $adjustments = $this->adjustmentsForSettlement($record);
        $uberNet = collect($balances)->where('platform', 'uber')->sum('net_amount');
        $boltNet = collect($balances)->where('platform', 'bolt')->sum('net_amount');
        $tipsTotal = (float) ($record->tips_total ?? 0);
        $expensesTotal = (float) ($record->expenses_total ?? 0);
        $carryOverBalance = (float) ($record->carry_over_balance ?? 0);
        $driverShare = (float) ($record->driver_share ?? 0);
        $rentTotal = (float) ($billing['rent_total'] ?? 0);
        $weekValue = $driverShare + $tipsTotal - $expensesTotal - $rentTotal;
        $amountDue = (float) ($record->amount_due ?? 0);
        $amountPayable = (float) ($record->amount_payable ?? $amountDue);
        $vatMultiplier = $this->resolveVatMultiplierFromSettlement($record);
        $expectedAmountDue = round(($carryOverBalance + $weekValue) * $vatMultiplier, 2);
        $calculationDifference = round($amountDue - $expectedAmountDue, 2);

        return [
            'driver' => [
                'name' => $driver['name'],
                'email' => $driver['email'],
            ],
            'period_label' => ($record->period_start?->format('d/m/Y') ?? '-').' - '.($record->period_end?->format('d/m/Y') ?? '-'),
            'totals' => [
                'uber_net' => $this->formatMoney($uberNet),
                'bolt_net' => $this->formatMoney($boltNet),
                'tips_total' => $this->formatMoney($tipsTotal),
                'expenses_total' => $this->formatMoney($expensesTotal),
                'rent_total' => $this->formatMoney($rentTotal),
                'carry_over_balance' => $this->formatMoney($carryOverBalance),
                'amount_payable' => $this->formatMoney($amountPayable),
                'amount_due' => $this->formatMoney($amountDue),
            ],
            'calculation' => [
                'driver_share' => $this->formatMoney($driverShare),
                'tips_total' => $this->formatMoney($tipsTotal),
                'expenses_total' => $this->formatMoney($expensesTotal),
                'rent_total' => $this->formatMoney($rentTotal),
                'week_value' => $this->formatMoney($weekValue),
                'carry_over_balance' => $this->formatMoney($carryOverBalance),
                'amount_due' => $this->formatMoney($amountDue),
                'calculation_difference' => $this->formatMoney($calculationDifference),
                'is_consistent' => abs($calculationDifference) < 0.01,
                'tips_are_informative' => (float) ($billing['percent_driver'] ?? 0) === 100.0
                    && (float) ($billing['percent_company'] ?? 0) === 0.0,
            ],
            'billing' => [
                'profile' => $billing['billing_profile_label'] ?? '-',
                'profile_status' => $billing['profile_status'] ?? 'missing',
                'rental_days' => (int) ($billing['rental_days'] ?? 0),
                'rent_total' => $this->formatMoney($rentTotal),
                'percent_company' => $this->formatPercent($billing['percent_company'] ?? null),
                'percent_driver' => $this->formatPercent($billing['percent_driver'] ?? null),
                'withholding_label' => (string) ($billing['withholding_label'] ?? '-'),
                'vat_label' => (string) ($billing['vat_label'] ?? '-'),
                'vat_refund_mode' => (string) ($billing['vat_refund_mode'] ?? '-'),
            ],
            'expenses' => [
                'prio_total' => $this->formatMoney($prioExpenses['total'] ?? 0),
                'via_verde_total' => $this->formatMoney($viaVerdeExpenses['total'] ?? 0),
                'adjustments_total' => $this->formatMoney($adjustments['total'] ?? 0),
                'total' => $this->formatMoney($expensesTotal),
                'prio_rows' => collect($prioExpenses['rows'] ?? [])->map(fn (array $row): array => [
                    'occurred_at' => $row['occurred_at'] instanceof Carbon ? $row['occurred_at']->format('d/m/Y H:i') : '-',
                    'card_code' => (string) ($row['card_code'] ?? '-'),
                    'vehicle_plate' => (string) ($row['vehicle_plate'] ?? '-'),
                    'amount' => $this->formatMoney($row['net_amount'] ?? 0),
                ])->values()->all(),
                'via_verde_rows' => collect($viaVerdeExpenses['rows'] ?? [])->map(fn (array $row): array => [
                    'occurred_at' => $row['occurred_at'] instanceof Carbon ? $row['occurred_at']->format('d/m/Y H:i') : '-',
                    'vehicle_plate' => (string) ($row['vehicle_plate'] ?? '-'),
                    'location' => (string) ($row['location'] ?? '-'),
                    'amount' => $this->formatMoney($row['amount'] ?? 0),
                ])->values()->all(),
                'adjustment_rows' => collect($adjustments['rows'] ?? [])->map(fn (array $row): array => [
                    'occurred_at' => $row['occurred_at'] instanceof Carbon ? $row['occurred_at']->format('d/m/Y') : '-',
                    'category' => (string) ($row['category'] ?? '-'),
                    'description' => (string) ($row['description'] ?? '-'),
                    'amount' => $this->formatMoney($row['amount'] ?? 0),
                ])->values()->all(),
            ],
            'balances' => collect($balances)->map(fn (array $row): array => [
                'platform' => strtoupper((string) ($row['platform'] ?? '-')),
                'period' => Carbon::parse((string) $row['period_start'])->format('d/m/Y').' - '.Carbon::parse((string) $row['period_end'])->format('d/m/Y'),
                'net_amount' => $this->formatMoney($row['net_amount'] ?? 0),
                'tips_amount' => $this->formatMoney($row['tips_amount'] ?? 0),
                'source_file' => $row['source_file'] ?? '-',
            ])->values()->all(),
            'balance_movements' => collect($balanceMovements)->map(fn (array $row): array => [
                'created_at' => $row['created_at'] instanceof Carbon ? $row['created_at']->format('d/m/Y H:i') : '-',
                'type' => (string) ($row['type'] ?? '-'),
                'description' => (string) ($row['description'] ?? '-'),
                'amount' => $this->formatMoney($row['amount'] ?? 0),
            ])->values()->all(),
        ];
    }

    private function settlementRecipientForRecord(DriverSettlement $record): ?string
    {
        if ($this->settlementDeliveryMode() === 'test') {
            return $this->settlementTestRecipient();
        }

        $driverEmail = trim((string) ($this->driverIdentity((int) $record->driver_id)['email'] ?? ''));

        if ($driverEmail === '' || filter_var($driverEmail, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $driverEmail;
    }

    private function settlementDeliveryMode(): string
    {
        $mode = strtolower(trim((string) config('mail.settlement_delivery_mode', 'test')));

        return in_array($mode, ['test', 'production'], true) ? $mode : 'test';
    }

    private function settlementTestRecipient(): ?string
    {
        $recipient = trim((string) config('mail.settlement_test_recipient'));

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $recipient;
    }

    private function settlementCopyRecipient(): ?string
    {
        $recipient = trim((string) config('mail.settlement_copy_recipient'));

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $recipient;
    }

    private function adjustBalanceAction(): Action
    {
        return Action::make('adjustBalance')
            ->label('Ajustar saldo')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->modalHeading('Ajustar saldo transitado')
            ->modalDescription('Defina o valor exato do saldo transitado para este settlement.')
            ->form([
                TextInput::make('amount')
                    ->label('Saldo transitado')
                    ->required()
                    ->placeholder('Ex.: -25,63')
                    ->helperText('Este valor substitui o saldo transitado deste settlement e recalcula os seguintes.'),
                Textarea::make('description')
                    ->label('Descricao')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (DriverSettlement $record, array $data): void {
                $targetCarryValue = $this->parseLocalizedDecimal($data['amount'] ?? null);

                if ($targetCarryValue === null) {
                    Notification::make()
                        ->danger()
                        ->title('Saldo transitado invalido')
                        ->send();

                    return;
                }

                DB::transaction(function () use ($record, $data, $targetCarryValue): void {
                    $balance = $this->resolveBalance((int) $record->driver_id);
                    $targetCarry = round($targetCarryValue, 2);
                    $currentCarry = round((float) $record->carry_over_balance, 2);
                    $delta = round($targetCarry - $currentCarry, 2);

                    $this->applyAdjustmentForward($record, $targetCarry);

                    $latestSettlement = DriverSettlement::query()
                        ->where('driver_id', $record->driver_id)
                        ->orderByDesc('period_end')
                        ->orderByDesc('id')
                        ->first(['id', 'amount_due']);

                    $currentBalance = $latestSettlement
                        ? round((float) $latestSettlement->amount_due, 2)
                        : round((float) $balance->current_balance + $delta, 2);

                    $balance->forceFill([
                        'current_balance' => $currentBalance,
                        'is_settled' => false,
                        'settled_at' => null,
                        'last_settlement_id' => $latestSettlement?->id,
                    ])->save();

                    DriverBalanceMovement::query()->create([
                        'driver_id' => $record->driver_id,
                        'driver_balance_id' => $balance->id,
                        'driver_settlement_id' => $record->id,
                        'amount' => $delta,
                        'type' => 'manual_adjustment',
                        'description' => (string) $data['description'].' (carry '.$currentCarry.' -> '.$targetCarry.')',
                    ]);
                });

                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Saldo atualizado')
                    ->send();
            });
    }

    private function manageGreenReceiptAction(): Action
    {
        return Action::make('manageGreenReceipt')
            ->label('Recibo verde')
            ->icon(Heroicon::OutlinedDocumentArrowUp)
            ->color(fn (DriverSettlement $record): string => $this->hasGreenReceipt($record) ? 'success' : 'warning')
            ->modalHeading('Recibo verde')
            ->modalDescription(fn (DriverSettlement $record): string => $this->hasGreenReceipt($record)
                ? 'Substitua o recibo verde anexado a este settlement.'
                : 'Anexe o recibo verde antes de marcar o settlement como pago.')
            ->form([
                FileUpload::make('green_receipt_file')
                    ->label('Ficheiro')
                    ->disk('local')
                    ->directory(fn (DriverSettlement $record): string => "driver-settlement-receipts/{$record->id}")
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ])
                    ->maxFiles(1)
                    ->preserveFilenames()
                    ->required(),
            ])
            ->action(function (DriverSettlement $record, array $data): void {
                $path = $this->uploadedGreenReceiptPath($data['green_receipt_file'] ?? null);

                if (! $path) {
                    Notification::make()
                        ->danger()
                        ->title('Carregue um ficheiro valido')
                        ->send();

                    return;
                }

                $this->saveGreenReceipt($record, $path);
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Recibo verde anexado')
                    ->send();
            });
    }

    private function downloadGreenReceiptAction(): Action
    {
        return Action::make('downloadGreenReceipt')
            ->label('Descarregar recibo')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (DriverSettlement $record): bool => $this->hasGreenReceipt($record))
            ->action(fn (DriverSettlement $record): ?StreamedResponse => $this->downloadGreenReceipt($record));
    }

    private function uploadedGreenReceiptPath(mixed $state): ?string
    {
        if (is_string($state) && $state !== '') {
            return $state;
        }

        if (is_array($state)) {
            $first = collect($state)->first();

            return is_string($first) && $first !== '' ? $first : null;
        }

        return null;
    }

    private function manageAdjustmentsAction(): Action
    {
        return Action::make('manageAdjustments')
            ->label('Ajustes')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('info')
            ->modalHeading('Ajustes do motorista')
            ->modalSubmitActionLabel('Executar')
            ->modalDescription('Crie ajustes manuais sem CSV. Os valores de settlement sao atualizados ao recalcular.')
            ->modalContent(fn (DriverSettlement $record) => view('filament.pages.partials.driver-adjustments-list', [
                'driverId' => (int) $record->driver_id,
                'adjustments' => $this->manualAdjustmentsForDriver((int) $record->driver_id),
            ]))
            ->fillForm(fn (DriverSettlement $record): array => [
                'operation' => 'create',
                'adjustment_id' => null,
                'starts_at' => $record->period_start?->toDateString(),
                'recurrence_weeks' => null,
                'category' => 'acerto',
                'description' => null,
                'amount' => null,
            ])
            ->form([
                Select::make('operation')
                    ->label('Operacao')
                    ->options([
                        'create' => 'Criar',
                        'update' => 'Editar',
                        'delete' => 'Apagar',
                    ])
                    ->default('create')
                    ->required()
                    ->native(false),
                Select::make('adjustment_id')
                    ->label('Ajuste existente')
                    ->options(fn (DriverSettlement $record): array => collect($this->manualAdjustmentsForDriver((int) $record->driver_id))
                        ->mapWithKeys(fn (array $row): array => [
                            (int) $row['id'] => ($row['starts_at']?->format('d/m/Y') ?? '-').' | '.($row['category'] ?? '-').' | '.number_format((float) ($row['amount'] ?? 0), 2, ',', ' ').' EUR',
                        ])
                        ->all())
                    ->searchable()
                    ->native(false),
                DatePicker::make('starts_at')
                    ->label('Inicio')
                    ->native(false)
                    ->default(fn (DriverSettlement $record): ?string => $record->period_start?->toDateString()),
                TextInput::make('recurrence_weeks')
                    ->label('Semanas (opcional)')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('1'),
                Select::make('category')
                    ->label('Categoria')
                    ->options([
                        'acerto' => 'Acerto',
                        'caucao' => 'Caucao',
                        'outro' => 'Outro',
                    ])
                    ->default('acerto')
                    ->required()
                    ->native(false),
                Textarea::make('description')
                    ->label('Descricao')
                    ->rows(3),
                TextInput::make('amount')
                    ->label('Valor')
                    ->required()
                    ->placeholder('Ex.: 25,63'),
            ])
            ->action(function (DriverSettlement $record, array $data): void {
                $operation = (string) ($data['operation'] ?? 'create');
                $adjustmentId = isset($data['adjustment_id']) && $data['adjustment_id'] !== '' ? (int) $data['adjustment_id'] : null;

                if (in_array($operation, ['update', 'delete'], true) && ! $adjustmentId) {
                    Notification::make()
                        ->danger()
                        ->title('Selecione um ajuste existente')
                        ->send();

                    return;
                }

                if ($operation === 'delete') {
                    $this->deleteManualAdjustment((int) $record->driver_id, $adjustmentId);

                    return;
                }

                $amountValue = $this->parseLocalizedDecimal($data['amount'] ?? null);

                if ($amountValue === null) {
                    Notification::make()
                        ->danger()
                        ->title('Preencha um valor valido')
                        ->send();

                    return;
                }

                $startsAtInput = $data['starts_at'] ?? $record->period_start?->toDateString();

                if (empty($startsAtInput)) {
                    Notification::make()
                        ->danger()
                        ->title('Preencha uma data de inicio valida')
                        ->send();

                    return;
                }

                $startsAt = Carbon::parse((string) $startsAtInput)->toDateString();
                $description = trim((string) ($data['description'] ?? '')) ?: 'Ajuste manual';
                $weeks = isset($data['recurrence_weeks']) && $data['recurrence_weeks'] !== ''
                    ? max(1, (int) $data['recurrence_weeks'])
                    : null;

                if ($weeks === 1) {
                    $weeks = null;
                }

                if ($operation === 'update') {
                    $adjustment = DriverAdjustment::query()
                        ->where('driver_id', $record->driver_id)
                        ->find($adjustmentId);

                    if (! $adjustment) {
                        Notification::make()
                            ->danger()
                            ->title('Ajuste nao encontrado')
                            ->send();

                        return;
                    }

                    $adjustment->forceFill([
                        'starts_at' => $startsAt,
                        'recurrence_weeks' => $weeks,
                        'category' => (string) ($data['category'] ?? 'acerto'),
                        'description' => $description,
                        'amount' => round($amountValue, 2),
                        'source_file' => $adjustment->source_file ?? 'manual',
                    ])->save();

                    $this->resetBillingCache();
                    $this->resetTable();

                    Notification::make()
                        ->success()
                        ->title('Ajuste atualizado')
                        ->send();

                    return;
                }

                DriverAdjustment::query()->create([
                    'driver_id' => $record->driver_id,
                    'starts_at' => $startsAt,
                    'recurrence_weeks' => $weeks,
                    'category' => (string) ($data['category'] ?? 'acerto'),
                    'description' => $description,
                    'amount' => round($amountValue, 2),
                    'external_ref' => (string) Str::uuid(),
                    'raw_row' => ['origin' => 'manual'],
                    'source_file' => 'manual',
                    'imported_at' => now(),
                ]);

                $this->resetBillingCache();
                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Ajuste criado')
                    ->body('Recalcule os settlements do periodo para refletir o novo ajuste.')
                    ->send();
            });
    }

    private function applyAdjustmentForward(DriverSettlement $record, float $targetCarry): void
    {
        $settlements = DriverSettlement::query()
            ->where('driver_id', $record->driver_id)
            ->where(function (Builder $query) use ($record): void {
                $query->whereDate('period_start', '>', $record->period_start)
                    ->orWhere(function (Builder $nested) use ($record): void {
                        $nested->whereDate('period_start', $record->period_start)
                            ->where('id', '>=', $record->id);
                    });
            })
            ->orderBy('period_start')
            ->orderBy('id')
            ->get(['id', 'carry_over_balance', 'amount_payable', 'amount_due', 'rules_snapshot', 'is_paid', 'paid_at']);

        if ($settlements->isEmpty()) {
            return;
        }

        $currentCarry = round($targetCarry, 2);

        foreach ($settlements as $settlement) {
            $rules = is_array($settlement->rules_snapshot) ? $settlement->rules_snapshot : [];
            $amountPayableBase = round((float) ($rules['amount_payable_base'] ?? $settlement->amount_payable ?? 0), 2);
            $vatMultiplier = $this->resolveVatMultiplierFromRulesSnapshot($rules, $amountPayableBase, (float) $settlement->amount_payable);
            $amountPayable = round($amountPayableBase * $vatMultiplier, 2);
            $amountDue = round(($currentCarry + $amountPayableBase) * $vatMultiplier, 2);

            $rules['amount_payable_base'] = $amountPayableBase;
            $rules['vat_multiplier'] = $vatMultiplier;
            $rules['carry_over_balance'] = $currentCarry;
            $rules['amount_due'] = $amountDue;

            $settlement->forceFill([
                'carry_over_balance' => $currentCarry,
                'amount_payable' => $amountPayable,
                'amount_due' => $amountDue,
                'rules_snapshot' => $rules,
                'is_paid' => false,
                'paid_at' => null,
            ])->save();

            $currentCarry = $amountDue;
        }
    }

    private function resolveVatMultiplierFromSettlement(DriverSettlement $settlement): float
    {
        $rules = is_array($settlement->rules_snapshot) ? $settlement->rules_snapshot : [];
        $amountPayableBase = round((float) ($rules['amount_payable_base'] ?? $settlement->amount_payable ?? 0), 2);

        return $this->resolveVatMultiplierFromRulesSnapshot($rules, $amountPayableBase, (float) $settlement->amount_payable);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function resolveVatMultiplierFromRulesSnapshot(array $rules, float $amountPayableBase, float $amountPayable): float
    {
        $mode = (string) ($rules['vat_refund_mode'] ?? '');
        $percent = (float) ($rules['vat_percent'] ?? 0);

        if (in_array($mode, ['driver_delivers_vat', 'driver', 'refund_to_driver'], true) && $percent > 0) {
            return round(1 + ($percent / 100), 6);
        }

        if (isset($rules['vat_multiplier']) && is_numeric($rules['vat_multiplier']) && (float) $rules['vat_multiplier'] > 0) {
            return round((float) $rules['vat_multiplier'], 6);
        }

        if ($amountPayableBase !== 0.0) {
            return round(max(0.0, $amountPayable / $amountPayableBase), 6);
        }

        return 1.0;
    }

    private function markPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label('Dar como pago')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (DriverSettlement $record): bool => ! $record->is_paid)
            ->modalDescription('Define o settlement como pago e zera o saldo transitado.')
            ->action(function (DriverSettlement $record): void {
                if (! $this->markSettlementPaid($record)) {
                    return;
                }

                $this->resetTable();

                Notification::make()
                    ->success()
                    ->title('Settlement marcado como pago')
                    ->send();
            });
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
                DB::transaction(function () use ($record): void {
                    $this->deleteSettlementsAndRebuildBalances(
                        DriverSettlement::query()->whereKey($record->id)
                    );
                });

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
                'platform' => $this->sanitizeUtf8((string) $row->platform),
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
                'net_amount' => (float) $row->net_amount,
                'tips_amount' => (float) $row->tips_amount,
                'source_file' => $this->sanitizeUtf8($row->source_file),
            ])
            ->all();
    }

    /**
     * @return array{total: float, count: int, rows: array<int, array<string, mixed>>}
     */
    private function prioExpensesForSettlement(DriverSettlement $settlement): array
    {
        $rows = \App\Models\PrioTransaction::query()
            ->whereDate('occurred_at', '>=', $settlement->period_start)
            ->whereDate('occurred_at', '<=', $settlement->period_end)
            ->where(function (Builder $query) use ($settlement): void {
                $query
                    ->where(function (Builder $query) use ($settlement): void {
                        $query
                            ->whereNotNull('vehicle_id')
                            ->whereExists(function (QueryBuilder $allocationQuery) use ($settlement): void {
                                $allocationQuery
                                    ->selectRaw('1')
                                    ->from('vehicle_allocations')
                                    ->where('vehicle_allocations.driver_id', $settlement->driver_id)
                                    ->whereColumn('vehicle_allocations.vehicle_id', 'prio_transactions.vehicle_id')
                                    ->whereRaw('DATE(prio_transactions.occurred_at) >= DATE(vehicle_allocations.starts_at)')
                                    ->where(function (QueryBuilder $dateOverlapQuery): void {
                                        $dateOverlapQuery
                                            ->whereNull('vehicle_allocations.ends_at')
                                            ->orWhereRaw('DATE(prio_transactions.occurred_at) <= DATE(vehicle_allocations.ends_at)');
                                    });
                            });
                    })
                    ->orWhere(function (Builder $query) use ($settlement): void {
                        $query
                            ->whereNull('vehicle_id')
                            ->where('driver_id', $settlement->driver_id)
                            ->where('assignment_status', 'ok');
                    });
            })
            ->orderBy('occurred_at')
            ->get([
                'occurred_at',
                'vehicle_plate',
                'card_code',
                'net_amount',
            ])
            ->map(fn (\App\Models\PrioTransaction $row): array => [
                'occurred_at' => $row->occurred_at,
                'vehicle_plate' => $this->sanitizeUtf8($row->vehicle_plate),
                'card_code' => $this->sanitizeUtf8($row->card_code),
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
            ->whereDate('occurred_at', '>=', $settlement->period_start)
            ->whereDate('occurred_at', '<=', $settlement->period_end)
            ->where(function (Builder $query) use ($settlement): void {
                $query
                    ->where(function (Builder $query) use ($settlement): void {
                        $query
                            ->whereNotNull('vehicle_id')
                            ->whereExists(function (QueryBuilder $allocationQuery) use ($settlement): void {
                                $allocationQuery
                                    ->selectRaw('1')
                                    ->from('vehicle_allocations')
                                    ->where('vehicle_allocations.driver_id', $settlement->driver_id)
                                    ->whereColumn('vehicle_allocations.vehicle_id', 'via_verde_transactions.vehicle_id')
                                    ->whereRaw('DATE(via_verde_transactions.occurred_at) >= DATE(vehicle_allocations.starts_at)')
                                    ->where(function (QueryBuilder $dateOverlapQuery): void {
                                        $dateOverlapQuery
                                            ->whereNull('vehicle_allocations.ends_at')
                                            ->orWhereRaw('DATE(via_verde_transactions.occurred_at) <= DATE(vehicle_allocations.ends_at)');
                                    });
                            });
                    })
                    ->orWhere(function (Builder $query) use ($settlement): void {
                        $query
                            ->whereNull('vehicle_id')
                            ->where('driver_id', $settlement->driver_id)
                            ->where('assignment_status', 'ok');
                    });
            })
            ->orderBy('occurred_at')
            ->get([
                'occurred_at',
                'vehicle_plate',
                'location',
                'amount',
            ])
            ->map(fn (\App\Models\ViaVerdeTransaction $row): array => [
                'occurred_at' => $row->occurred_at,
                'vehicle_plate' => $this->sanitizeUtf8($row->vehicle_plate),
                'location' => $this->sanitizeUtf8($row->location),
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
        $adjustments = DriverAdjustment::query()
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
                    'category' => $this->sanitizeUtf8($adjustment->category),
                    'description' => $this->sanitizeUtf8($adjustment->description),
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

    /**
     * @return array{total: float, count: int, rows: array<int, array<string, mixed>>}
     */
    private function extraKmExpensesForSettlement(DriverSettlement $settlement): array
    {
        $billing = $this->billingFor($settlement);
        $profileId = (int) ($billing['billing_profile_id'] ?? 0);

        if ($profileId <= 0) {
            return [
                'total' => 0.0,
                'count' => 0,
                'rows' => [],
            ];
        }

        $profile = DriverBillingProfile::query()->find($profileId);

        if (! $profile) {
            return [
                'total' => 0.0,
                'count' => 0,
                'rows' => [],
            ];
        }

        $limit = (float) ($profile->extra_km_limit ?? 0);
        $rate = (float) ($profile->extra_km_rate ?? 0);

        if ($limit <= 0 || $rate <= 0) {
            return [
                'total' => 0.0,
                'count' => 0,
                'rows' => [],
            ];
        }

        $rows = VehicleWeeklyMileage::query()
            ->where('driver_id', $settlement->driver_id)
            ->where('assignment_status', 'ok')
            ->whereDate('period_start', '>=', $settlement->period_start)
            ->whereDate('period_end', '<=', $settlement->period_end)
            ->orderBy('period_start')
            ->get(['period_start', 'period_end', 'weekly_km', 'vehicle_id'])
            ->map(function (VehicleWeeklyMileage $row) use ($limit, $rate): array {
                $weeklyKm = (float) $row->weekly_km;
                $extraKm = max(0.0, $weeklyKm - $limit);
                $amount = round($extraKm * $rate, 2);

                return [
                    'period_start' => $row->period_start,
                    'period_end' => $row->period_end,
                    'total_km' => (float) $row->weekly_km,
                    'weekly_km' => $weeklyKm,
                    'extra_km' => $extraKm,
                    'amount' => $amount,
                    'vehicle_id' => $row->vehicle_id,
                ];
            })
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->values()
            ->all();

        $total = array_reduce($rows, fn (float $carry, array $row): float => $carry + (float) $row['amount'], 0.0);

        return [
            'total' => round($total, 2),
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manualAdjustmentsForDriver(int $driverId): array
    {
        return DriverAdjustment::query()
            ->where('driver_id', $driverId)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'driver_id', 'starts_at', 'recurrence_weeks', 'category', 'description', 'amount', 'source_file'])
            ->map(fn (DriverAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'driver_id' => $adjustment->driver_id,
                'starts_at' => $adjustment->starts_at,
                'recurrence_weeks' => $adjustment->recurrence_weeks,
                'category' => $this->sanitizeUtf8($adjustment->category),
                'description' => $this->sanitizeUtf8($adjustment->description),
                'amount' => (float) $adjustment->amount,
                'source_file' => $this->sanitizeUtf8($adjustment->source_file),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function depositSummaryForDriver(int $driverId): array
    {
        return app(DriverDepositService::class)->summaryForDriver($driverId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function depositHistoryForDriver(int $driverId): array
    {
        return app(DriverDepositService::class)->historyForDriver($driverId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function balanceMovementsForSettlement(DriverSettlement $settlement): array
    {
        return DriverBalanceMovement::query()
            ->where('driver_id', $settlement->driver_id)
            ->where('driver_settlement_id', $settlement->id)
            ->orderByDesc('created_at')
            ->get([
                'amount',
                'type',
                'description',
                'created_at',
            ])
            ->map(fn (DriverBalanceMovement $movement): array => [
                'amount' => (float) $movement->amount,
                'type' => $this->sanitizeUtf8($movement->type),
                'description' => $this->sanitizeUtf8($movement->description),
                'created_at' => $movement->created_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emailLogsForSettlement(DriverSettlement $settlement): array
    {
        return SettlementEmailLog::query()
            ->where('driver_settlement_id', $settlement->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get([
                'recipient',
                'status',
                'message_id',
                'error_message',
                'triggered_by_user_id',
                'created_at',
            ])
            ->map(fn (SettlementEmailLog $row): array => [
                'recipient' => $this->sanitizeUtf8($row->recipient),
                'status' => $this->sanitizeUtf8($row->status),
                'message_id' => $this->sanitizeUtf8($row->message_id),
                'error_message' => $this->sanitizeUtf8($row->error_message),
                'triggered_by_user_id' => $row->triggered_by_user_id,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    private function resolveBalance(int $driverId): DriverBalance
    {
        return DriverBalance::query()->firstOrCreate(
            ['driver_id' => $driverId],
            [
                'current_balance' => 0,
                'is_settled' => false,
            ]
        );
    }

    private function deleteSettlementsForPeriod(string $periodStart, string $periodEnd, ?int $driverId = null): int
    {
        return $this->deleteSettlementsAndRebuildBalances(
            DriverSettlement::query()
                ->when($driverId, fn (Builder $query) => $query->where('driver_id', $driverId))
                ->whereDate('period_start', '>=', $periodStart)
                ->whereDate('period_end', '<=', $periodEnd)
        );
    }

    private function deleteSettlementsAndRebuildBalances(Builder $query): int
    {
        $rows = (clone $query)
            ->get(['id', 'driver_id']);

        if ($rows->isEmpty()) {
            return 0;
        }

        $settlementIds = $rows->pluck('id')->all();
        $driverIds = $rows->pluck('driver_id')->filter()->unique()->values()->all();

        DriverBalanceMovement::query()
            ->whereIn('driver_settlement_id', $settlementIds)
            ->delete();

        $deleted = (clone $query)->delete();

        DriverBalanceMovement::query()
            ->whereIn('driver_id', $driverIds)
            ->where('type', 'settlement')
            ->whereNull('driver_settlement_id')
            ->delete();

        foreach ($driverIds as $driverId) {
            $balance = $this->resolveBalance((int) $driverId);

            $latestSettlement = DriverSettlement::query()
                ->where('driver_id', $driverId)
                ->orderByDesc('period_end')
                ->orderByDesc('id')
                ->first(['id', 'amount_due', 'is_paid', 'paid_at']);

            if (! $latestSettlement) {
                $balance->forceFill([
                    'current_balance' => 0,
                    'last_settlement_id' => null,
                    'is_settled' => false,
                    'settled_at' => null,
                ])->save();

                continue;
            }

            $balance->forceFill([
                'current_balance' => round((float) $latestSettlement->amount_due, 2),
                'last_settlement_id' => $latestSettlement->id,
                'is_settled' => (bool) $latestSettlement->is_paid,
                'settled_at' => $latestSettlement->paid_at,
            ])->save();
        }

        return $deleted;
    }

    /**
     * @return array<int, string>
     */
    private function driverOptions(): array
    {
        return cache()->remember('driver_options_select', 60, function (): array {
            return Driver::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Driver $driver): array => [
                    $driver->id => $this->sanitizeUtf8($driver->name) ?? ('Motorista #'.$driver->id),
                ])
                ->all();
        });
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
            return '-';
        }

        return number_format((float) $value, 2, ',', ' ')." \u{20AC}";
    }

    private function formatEffectMoney(mixed $value, string $mode = 'signed'): string
    {
        if ($value === null) {
            return '-';
        }

        $amount = match ($mode) {
            'add' => abs((float) $value),
            'subtract' => -1 * abs((float) $value),
            default => (float) $value,
        };

        $sign = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');

        return $sign.number_format(abs($amount), 2, ',', ' ')." \u{20AC}";
    }

    private function formatPercent(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format((float) $value, 2, ',', ' ').'%';
    }

    private function parseLocalizedDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(' ', '', $normalized);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function sanitizeUtf8(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        return utf8_encode($value);
    }

    /**
     * @return array{name: string, email: string}
     */
    private function driverIdentity(int $driverId): array
    {
        if (array_key_exists($driverId, $this->driverIdentityCache)) {
            return $this->driverIdentityCache[$driverId];
        }

        $driver = Driver::query()->find($driverId, ['name', 'email']);

        $identity = [
            'name' => $this->sanitizeUtf8($driver?->name) ?? ('Motorista #'.$driverId),
            'email' => $this->sanitizeUtf8($driver?->email) ?? '-',
        ];

        $this->driverIdentityCache[$driverId] = $identity;

        return $identity;
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
            'withholding_label' => '-',
            'vat_label' => '-',
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
