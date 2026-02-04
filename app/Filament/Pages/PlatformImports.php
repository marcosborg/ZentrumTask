<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\DriverAdjustment;
use App\Models\PlatformDriverBalance;
use App\Models\PrioTransaction;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
use App\Services\BoltPlatformCsvImportService;
use App\Services\DriverAdjustmentsCsvImportService;
use App\Services\PrioFuelCsvImportService;
use App\Services\UberPlatformCsvImportService;
use App\Services\ViaVerdeCsvImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use UnitEnum;

class PlatformImports extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Imports';

    protected static ?string $title = 'Imports de plataformas';

    protected string $view = 'filament.pages.platform-imports';

    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    /** @var list<string> */
    public array $missingDriverCodes = [];

    /** @var list<string> */
    public array $missingPrioCards = [];

    /** @var list<string> */
    public array $missingViaVerdePlates = [];

    /** @var list<string> */
    public array $missingAdjustmentDrivers = [];

    /** @var array<string, mixed>|null */
    public ?array $adjustmentResult = null;

    public ?string $adjustmentError = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill([
            'platform' => null,
            'file' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                Select::make('platform')
                    ->label('Plataforma')
                    ->options([
                        'bolt' => 'Bolt',
                        'uber' => 'Uber',
                        'prio' => 'PRIO Abastecimento',
                        'via_verde' => 'Via Verde',
                    ])
                    ->required()
                    ->native(false),
                FileUpload::make('file')
                    ->label('CSV')
                    ->disk('local')
                    ->directory('platform-imports')
                    ->preserveFilenames()
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                    ])
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('period_start')
                    ->label('Periodo inicio (opcional)')
                    ->native(false),
                DatePicker::make('period_end')
                    ->label('Periodo fim (opcional)')
                    ->native(false),
            ])
            ->statePath('data');
    }

    public function runImport(): void
    {
        $this->errorMessage = null;
        $this->result = null;
        $this->missingDriverCodes = [];
        $this->missingPrioCards = [];
        $this->missingViaVerdePlates = [];

        $data = $this->form->getState();
        $platform = $data['platform'] ?? null;
        $file = $data['file'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;

        if (! $platform || ! $file) {
            Notification::make()
                ->danger()
                ->title('Selecione a plataforma e o ficheiro CSV')
                ->send();

            return;
        }

        if (! in_array($platform, ['prio', 'via_verde'], true) && (($periodStart && ! $periodEnd) || ($periodEnd && ! $periodStart))) {
            Notification::make()
                ->danger()
                ->title('Indique inicio e fim do periodo')
                ->send();

            return;
        }

        $path = Storage::disk('local')->path($file);
        $options = [];

        if (! in_array($platform, ['prio', 'via_verde'], true) && $periodStart && $periodEnd) {
            $options = [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        }

        try {
            if ($platform === 'prio') {
                $result = app(PrioFuelCsvImportService::class)->import($path);
            } elseif ($platform === 'via_verde') {
                $result = app(ViaVerdeCsvImportService::class)->import($path);
            } else {
                $result = $platform === 'bolt'
                    ? app(BoltPlatformCsvImportService::class)->import($path, $options)
                    : app(UberPlatformCsvImportService::class)->import($path, $options);
            }

            if (! in_array($platform, ['prio', 'via_verde'], true)) {
                $driverCodes = array_values(array_filter(array_map(
                    fn (string $code): string => strtolower(trim($code)),
                    $result['driver_codes'] ?? []
                )));

                if ($driverCodes !== []) {
                    $column = $platform === 'bolt' ? 'bolt_driver_code' : 'uber_driver_code';
                    $normalizedColumn = DB::raw("LOWER(TRIM({$column}))");

                    $missing = Driver::query()
                        ->whereNotNull($column)
                        ->whereIn($normalizedColumn, $driverCodes)
                        ->selectRaw("LOWER(TRIM({$column})) as code")
                        ->pluck('code')
                        ->filter()
                        ->all();

                    $this->missingDriverCodes = array_values(array_diff($driverCodes, $missing));
                }
            } elseif ($platform === 'prio') {
                $sourceFile = basename($path);
                $this->missingPrioCards = PrioTransaction::query()
                    ->where('source_file', $sourceFile)
                    ->where('assignment_status', 'unassigned_vehicle')
                    ->pluck('card_code')
                    ->filter()
                    ->map(fn (string $code): string => strtoupper(trim($code)))
                    ->unique()
                    ->values()
                    ->all();
            } elseif ($platform === 'via_verde') {
                $this->missingViaVerdePlates = [];
            }

            $this->result = [
                'total' => $result['total'] ?? 0,
                'inserted' => $result['inserted'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'duplicates' => $result['duplicates'] ?? 0,
                'invalid_rows' => $result['invalid_rows'] ?? 0,
                'period_start' => $result['period_start'] ?? null,
                'period_end' => $result['period_end'] ?? null,
                'platform' => $platform,
                'import_type' => in_array($platform, ['prio', 'via_verde'], true) ? $platform : 'platform',
                'unassigned_vehicle' => $result['unassigned_vehicle'] ?? 0,
                'unassigned_driver' => $result['unassigned_driver'] ?? 0,
                'ambiguous_driver' => $result['ambiguous_driver'] ?? 0,
            ];

            Notification::make()
                ->success()
                ->title('Import concluido')
                ->send();
        } catch (RuntimeException $exception) {
            $this->errorMessage = $exception->getMessage();
            if ($platform === 'via_verde') {
                if (preg_match('/matricula\s+([A-Z0-9-]+)/i', $exception->getMessage(), $matches)) {
                    $this->missingViaVerdePlates = [strtoupper($matches[1])];
                }
            }

            Notification::make()
                ->danger()
                ->title('Falha no import')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function importAdjustmentsAction(): Action
    {
        return Action::make('importAdjustments')
            ->label('Importar ajustes')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Importar ajustes (caucao e acertos)')
            ->form([
                FileUpload::make('file')
                    ->label('CSV')
                    ->disk('local')
                    ->directory('settlement-adjustments')
                    ->preserveFilenames()
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                    ])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->adjustmentError = null;
                $this->adjustmentResult = null;
                $this->missingAdjustmentDrivers = [];

                $file = $data['file'] ?? null;

                if (! $file) {
                    Notification::make()
                        ->danger()
                        ->title('Selecione um ficheiro CSV')
                        ->send();

                    return;
                }

                try {
                    $path = Storage::disk('local')->path($file);
                    $result = app(DriverAdjustmentsCsvImportService::class)->import($path);

                    $this->missingAdjustmentDrivers = $result['missing_drivers'] ?? [];
                    $this->adjustmentResult = $result;

                    Notification::make()
                        ->success()
                        ->title('Ajustes importados')
                        ->send();
                } catch (RuntimeException $exception) {
                    $this->adjustmentError = $exception->getMessage();

                    Notification::make()
                        ->danger()
                        ->title('Falha ao importar ajustes')
                        ->body($exception->getMessage())
                        ->send();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PrioTransaction::query()
                ->whereIn('assignment_status', [
                    'unassigned_vehicle',
                    'unassigned_driver',
                    'ambiguous_driver',
                ])
                ->with(['vehicle', 'driver'])
                ->orderByDesc('occurred_at'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('card_code')
                    ->label('Cartao PRIO'),
                TextColumn::make('vehicle_plate')
                    ->label('Matricula CSV')
                    ->toggleable(),
                TextColumn::make('vehicle.license_plate')
                    ->label('Viatura')
                    ->state(fn (PrioTransaction $record): string => $record->vehicle?->license_plate ?? '—'),
                TextColumn::make('driver.name')
                    ->label('Motorista')
                    ->state(fn (PrioTransaction $record): string => $record->driver?->name ?? '—'),
                TextColumn::make('assignment_status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (PrioTransaction $record): string => $this->resolveAssignmentLabel($record->assignment_status))
                    ->color(fn (PrioTransaction $record): string => $this->resolveAssignmentColor($record->assignment_status)),
            ])
            ->recordActions([
                Action::make('associateCard')
                    ->label('Associar cartao')
                    ->icon(Heroicon::OutlinedLink)
                    ->modalHeading('Associar cartao PRIO a viatura')
                    ->form([
                        TextInput::make('card_code')
                            ->label('Cartao PRIO')
                            ->disabled(),
                        Select::make('vehicle_id')
                            ->label('Viatura')
                            ->options(fn (): array => Vehicle::query()
                                ->orderBy('license_plate')
                                ->pluck('license_plate', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false),
                    ])
                    ->fillForm(fn (PrioTransaction $record): array => [
                        'card_code' => $record->card_code,
                        'vehicle_id' => $record->vehicle_id,
                    ])
                    ->action(function (PrioTransaction $record, array $data): void {
                        $updatedCount = DB::transaction(function () use ($record, $data): int {
                            $vehicleId = (int) $data['vehicle_id'];
                            $cardCode = (string) $record->card_code;

                            Vehicle::query()
                                ->whereKey($vehicleId)
                                ->update([
                                    'prio_card_code' => $cardCode,
                                ]);

                            return $this->reprocessPrioTransactions($cardCode, $vehicleId);
                        });

                        $vehiclePlate = Vehicle::query()
                            ->whereKey($data['vehicle_id'])
                            ->value('license_plate');

                        Notification::make()
                            ->success()
                            ->title('Cartao associado')
                            ->body("Cartao PRIO {$record->card_code} associado a viatura {$vehiclePlate}. {$updatedCount} transacoes atualizadas.")
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->emptyStateHeading('Sem transacoes por associar')
            ->defaultSort('occurred_at', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $imports = PlatformDriverBalance::query()
            ->select([
                'platform',
                'source_file',
                'period_start',
                'period_end',
            ])
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('SUM(CASE WHEN driver_id IS NOT NULL THEN 1 ELSE 0 END) as allocated_count')
            ->selectRaw('SUM(CASE WHEN driver_id IS NULL THEN 1 ELSE 0 END) as pending_count')
            ->groupBy('platform', 'source_file', 'period_start', 'period_end')
            ->orderByDesc('period_start')
            ->get()
            ->map(function ($row): array {
                $allocatedCount = (int) $row->allocated_count;
                $pendingCount = (int) $row->pending_count;

                if ($pendingCount === 0) {
                    $statusLabel = 'Completo';
                    $statusColor = 'success';
                } elseif ($allocatedCount > 0) {
                    $statusLabel = 'Parcial';
                    $statusColor = 'warning';
                } else {
                    $statusLabel = 'Nao alocado';
                    $statusColor = 'danger';
                }

                return [
                    'platform' => $row->platform,
                    'platform_label' => strtoupper($row->platform),
                    'source_file' => $row->source_file,
                    'period_start' => $row->period_start,
                    'period_end' => $row->period_end,
                    'total_records' => (int) $row->total_records,
                    'allocated_count' => $allocatedCount,
                    'pending_count' => $pendingCount,
                    'status_label' => $statusLabel,
                    'status_color' => $statusColor,
                    'import_type' => 'platform',
                ];
            })
            ->all();

        $prioImports = PrioTransaction::query()
            ->selectRaw('source_file, MIN(occurred_at) as period_start, MAX(occurred_at) as period_end')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw("SUM(CASE WHEN assignment_status = 'ok' THEN 1 ELSE 0 END) as allocated_count")
            ->selectRaw("SUM(CASE WHEN assignment_status != 'ok' THEN 1 ELSE 0 END) as pending_count")
            ->groupBy('source_file')
            ->orderByDesc('period_start')
            ->get()
            ->map(function ($row): array {
                $allocatedCount = (int) $row->allocated_count;
                $pendingCount = (int) $row->pending_count;

                if ($pendingCount === 0) {
                    $statusLabel = 'Completo';
                    $statusColor = 'success';
                } elseif ($allocatedCount > 0) {
                    $statusLabel = 'Parcial';
                    $statusColor = 'warning';
                } else {
                    $statusLabel = 'Nao alocado';
                    $statusColor = 'danger';
                }

                return [
                    'platform' => 'prio',
                    'platform_label' => 'PRIO',
                    'source_file' => $row->source_file,
                    'period_start' => $row->period_start,
                    'period_end' => $row->period_end,
                    'total_records' => (int) $row->total_records,
                    'allocated_count' => $allocatedCount,
                    'pending_count' => $pendingCount,
                    'status_label' => $statusLabel,
                    'status_color' => $statusColor,
                    'import_type' => 'prio',
                ];
            })
            ->all();

        $viaVerdeImports = [];
        $viaVerdePending = [];

        if (DB::getSchemaBuilder()->hasTable('via_verde_transactions')) {
            $viaVerdeImports = ViaVerdeTransaction::query()
                ->selectRaw('source_file, MIN(occurred_at) as period_start, MAX(occurred_at) as period_end')
                ->selectRaw('COUNT(*) as total_records')
                ->selectRaw("SUM(CASE WHEN assignment_status = 'ok' THEN 1 ELSE 0 END) as allocated_count")
                ->selectRaw("SUM(CASE WHEN assignment_status != 'ok' THEN 1 ELSE 0 END) as pending_count")
                ->groupBy('source_file')
                ->orderByDesc('period_start')
                ->get()
                ->map(function ($row): array {
                    $allocatedCount = (int) $row->allocated_count;
                    $pendingCount = (int) $row->pending_count;

                    if ($pendingCount === 0) {
                        $statusLabel = 'Completo';
                        $statusColor = 'success';
                    } elseif ($allocatedCount > 0) {
                        $statusLabel = 'Parcial';
                        $statusColor = 'warning';
                    } else {
                        $statusLabel = 'Nao alocado';
                        $statusColor = 'danger';
                    }

                    return [
                        'platform' => 'via_verde',
                        'platform_label' => 'Via Verde',
                        'source_file' => $row->source_file,
                        'period_start' => $row->period_start,
                        'period_end' => $row->period_end,
                        'total_records' => (int) $row->total_records,
                        'allocated_count' => $allocatedCount,
                        'pending_count' => $pendingCount,
                        'status_label' => $statusLabel,
                        'status_color' => $statusColor,
                        'import_type' => 'via_verde',
                    ];
                })
                ->all();

            $viaVerdePending = ViaVerdeTransaction::query()
                ->whereIn('assignment_status', ['unassigned_driver', 'ambiguous_driver'])
                ->with(['driver'])
                ->orderByDesc('occurred_at')
                ->get()
                ->map(fn (ViaVerdeTransaction $row): array => [
                    'occurred_at' => $row->occurred_at,
                    'vehicle_plate' => $row->vehicle_plate,
                    'location' => $row->location,
                    'amount' => (float) $row->amount,
                    'driver_name' => $row->driver?->name,
                    'status' => $row->assignment_status,
                ])
                ->all();
        }

        $adjustmentImports = [];

        if (DB::getSchemaBuilder()->hasTable('driver_adjustments')) {
            $adjustmentImports = DriverAdjustment::query()
                ->selectRaw('source_file, MIN(starts_at) as period_start, MAX(starts_at) as period_end')
                ->selectRaw('COUNT(*) as total_records')
                ->groupBy('source_file')
                ->orderByDesc('period_start')
                ->get()
                ->map(fn ($row): array => [
                    'source_file' => $row->source_file,
                    'period_start' => $row->period_start,
                    'period_end' => $row->period_end,
                    'total_records' => (int) $row->total_records,
                ])
                ->all();
        }

        return [
            'importsHistory' => array_merge($prioImports, $viaVerdeImports, $imports),
            'viaVerdePending' => $viaVerdePending,
            'adjustmentImports' => $adjustmentImports,
        ];
    }

    private function resolveAssignmentLabel(string $status): string
    {
        return match ($status) {
            'unassigned_vehicle' => 'Sem viatura',
            'unassigned_driver' => 'Sem motorista',
            'ambiguous_driver' => 'Ambiguo',
            default => 'OK',
        };
    }

    private function resolveAssignmentColor(string $status): string
    {
        return match ($status) {
            'unassigned_vehicle' => 'danger',
            'unassigned_driver' => 'warning',
            'ambiguous_driver' => 'warning',
            default => 'success',
        };
    }

    private function reprocessPrioTransactions(string $cardCode, int $vehicleId): int
    {
        $transactions = PrioTransaction::query()
            ->where('card_code', $cardCode)
            ->orderBy('occurred_at')
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $min = Carbon::parse($transactions->min('occurred_at'))->startOfDay();
        $max = Carbon::parse($transactions->max('occurred_at'))->endOfDay();

        $allocations = VehicleAllocation::query()
            ->where('vehicle_id', $vehicleId)
            ->where('starts_at', '<=', $max)
            ->where(function ($query) use ($min): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $min);
            })
            ->orderBy('starts_at')
            ->get();

        $updated = 0;

        foreach ($transactions as $transaction) {
            $match = $this->resolveDriverForTransaction($allocations, Carbon::parse($transaction->occurred_at));

            $transaction->update([
                'vehicle_id' => $vehicleId,
                'driver_id' => $match['driver_id'],
                'assignment_status' => $match['status'],
            ]);

            $updated++;
        }

        return $updated;
    }

    /**
     * @param  Collection<int, VehicleAllocation>  $allocations
     * @return array{driver_id: int|null, status: string}
     */
    private function resolveDriverForTransaction(Collection $allocations, Carbon $occurredAt): array
    {
        $matches = $allocations->filter(function (VehicleAllocation $allocation) use ($occurredAt): bool {
            $start = $allocation->starts_at instanceof Carbon ? $allocation->starts_at : Carbon::parse($allocation->starts_at);
            $end = $allocation->ends_at
                ? ($allocation->ends_at instanceof Carbon ? $allocation->ends_at : Carbon::parse($allocation->ends_at))
                : null;

            if ($start->gt($occurredAt)) {
                return false;
            }

            if ($end && $end->lt($occurredAt)) {
                return false;
            }

            return true;
        })->values();

        if ($matches->count() === 1) {
            return [
                'driver_id' => $matches->first()->driver_id,
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

    public function deleteImportAction(): Action
    {
        return Action::make('deleteImport')
            ->label('Eliminar import')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalDescription('Esta acao ira eliminar todos os balances importados deste ficheiro e todas as alocacoes associadas. Esta acao e irreversivel.')
            ->action(function (array $arguments): void {
                if (($arguments['import_type'] ?? null) === 'prio') {
                    PrioTransaction::query()
                        ->where('source_file', $arguments['source_file'] ?? null)
                        ->delete();

                    return;
                }

                if (($arguments['import_type'] ?? null) === 'via_verde') {
                    ViaVerdeTransaction::query()
                        ->where('source_file', $arguments['source_file'] ?? null)
                        ->delete();

                    return;
                }

                PlatformDriverBalance::query()
                    ->where('platform', $arguments['platform'] ?? null)
                    ->where('source_file', $arguments['source_file'] ?? null)
                    ->whereDate('period_start', $arguments['period_start'] ?? null)
                    ->whereDate('period_end', $arguments['period_end'] ?? null)
                    ->delete();
            })
            ->livewire($this);
    }

    public function deleteAdjustmentImportAction(): Action
    {
        return Action::make('deleteAdjustmentImport')
            ->label('Eliminar import')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalDescription('Remove todos os ajustes importados deste ficheiro.')
            ->action(function (array $arguments): void {
                DriverAdjustment::query()
                    ->where('source_file', $arguments['source_file'] ?? null)
                    ->delete();
            })
            ->livewire($this);
    }
}
