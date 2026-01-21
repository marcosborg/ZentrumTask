<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Services\DriverSettlementCalculator;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use UnitEnum;

class DriverSettlementsReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Settlements';

    protected static ?string $title = 'Relatorio de settlements';

    protected string $view = 'filament.pages.driver-settlements-report';

    public ?array $data = [];

    /** @var array<int, object> */
    public array $settlements = [];

    /** @var array<int, array<string, mixed>> */
    public array $pendingBalances = [];

    /** @var array<int, array<string, mixed>> */
    public array $driversMissingProfiles = [];

    /** @var array<int, array<string, mixed>> */
    public array $auditRows = [];

    public bool $hasSettlementsForPeriod = false;

    public function mount(): void
    {
        $start = Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString();
        $end = Carbon::today()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $this->form->fill([
            'period_start' => $start,
            'period_end' => $end,
            'driver_id' => null,
        ]);

        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                DatePicker::make('period_start')
                    ->label('Periodo inicio')
                    ->native(false)
                    ->required(),
                DatePicker::make('period_end')
                    ->label('Periodo fim')
                    ->native(false)
                    ->required(),
                Select::make('driver_id')
                    ->label('Motorista')
                    ->options(fn (): array => Driver::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->statePath('data');
    }

    public function applyFilters(): void
    {
        $this->loadData();
    }

    public function generateSettlements(): void
    {
        $data = $this->form->getState();
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;
        $driverId = $data['driver_id'] ?? null;

        if (! $periodStart || ! $periodEnd) {
            Notification::make()
                ->danger()
                ->title('Selecione o periodo para gerar settlements')
                ->send();

            return;
        }

        try {
            $result = app(DriverSettlementCalculator::class)
                ->calculate($periodStart, $periodEnd, $driverId ? (int) $driverId : null);

            Notification::make()
                ->success()
                ->title('Settlements gerados')
                ->body("Criados: {$result['created']} | Ignorados: {$result['skipped']} | Sem perfil: {$result['missing_profiles']}")
                ->send();

            $this->loadData();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->danger()
                ->title('Falha ao gerar settlements')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function deletePeriodData(): void
    {
        $data = $this->form->getState();
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;

        if (! $periodStart || ! $periodEnd) {
            Notification::make()
                ->danger()
                ->title('Selecione o periodo para eliminar os dados')
                ->send();

            return;
        }

        $deletedSettlements = 0;
        $deletedBalances = 0;

        DB::transaction(function () use ($periodStart, $periodEnd, &$deletedSettlements, &$deletedBalances): void {
            $deletedSettlements = DriverSettlement::query()
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->delete();

            $deletedBalances = PlatformDriverBalance::query()
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->delete();
        });

        Notification::make()
            ->success()
            ->title('Dados eliminados')
            ->body("Settlements apagados: {$deletedSettlements} | Balances apagados: {$deletedBalances}")
            ->send();

        $this->dispatch('close-modal', id: 'delete-period-modal');
        $this->loadData();
    }

    protected function loadData(): void
    {
        $data = $this->form->getState();
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;
        $driverId = $data['driver_id'] ?? null;

        $settlementsQuery = DriverSettlement::query()
            ->leftJoin('drivers', 'drivers.id', '=', 'driver_settlements.driver_id')
            ->select([
                'driver_settlements.id',
                'driver_settlements.driver_id',
                'driver_settlements.period_start',
                'driver_settlements.period_end',
                'driver_settlements.net_total',
                'driver_settlements.tips_total',
                'driver_settlements.amount_payable',
                'drivers.name as driver_name',
                'drivers.email as driver_email',
            ])
            ->selectSub(
                PlatformDriverBalance::query()
                    ->selectRaw('COALESCE(SUM(net_amount), 0)')
                    ->where('platform', 'bolt')
                    ->whereColumn('platform_driver_balances.driver_id', 'driver_settlements.driver_id')
                    ->whereColumn('platform_driver_balances.period_start', '>=', 'driver_settlements.period_start')
                    ->whereColumn('platform_driver_balances.period_end', '<=', 'driver_settlements.period_end'),
                'bolt_net'
            )
            ->selectSub(
                PlatformDriverBalance::query()
                    ->selectRaw('COALESCE(SUM(net_amount), 0)')
                    ->where('platform', 'uber')
                    ->whereColumn('platform_driver_balances.driver_id', 'driver_settlements.driver_id')
                    ->whereColumn('platform_driver_balances.period_start', '>=', 'driver_settlements.period_start')
                    ->whereColumn('platform_driver_balances.period_end', '<=', 'driver_settlements.period_end'),
                'uber_net'
            );

        if ($periodStart && $periodEnd) {
            $settlementsQuery
                ->whereDate('driver_settlements.period_start', '>=', $periodStart)
                ->whereDate('driver_settlements.period_end', '<=', $periodEnd);
        }

        if ($driverId) {
            $settlementsQuery->where('driver_settlements.driver_id', $driverId);
        }

        $settlements = $settlementsQuery
            ->orderByDesc('driver_settlements.period_start')
            ->get();

        $this->hasSettlementsForPeriod = $settlements->isNotEmpty();

        $this->settlements = $settlements->all();

        $pendingQuery = PlatformDriverBalance::query()
            ->whereNull('driver_id');

        if ($periodStart && $periodEnd) {
            $pendingQuery
                ->whereDate('period_start', '>=', $periodStart)
                ->whereDate('period_end', '<=', $periodEnd);
        }

        $this->pendingBalances = $pendingQuery
            ->orderByDesc('period_start')
            ->get([
                'platform',
                'driver_code',
                'period_start',
                'period_end',
                'net_amount',
                'tips_amount',
                'source_file',
            ])
            ->map(fn ($row): array => [
                'platform' => $row->platform,
                'driver_code' => $row->driver_code,
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
                'net_amount' => (float) $row->net_amount,
                'tips_amount' => (float) $row->tips_amount,
                'source_file' => $row->source_file,
            ])
            ->all();

        $driverIds = PlatformDriverBalance::query()
            ->whereNotNull('driver_id')
            ->when(
                $periodStart && $periodEnd,
                fn ($query) => $query
                    ->whereDate('period_start', '>=', $periodStart)
                    ->whereDate('period_end', '<=', $periodEnd)
            )
            ->distinct()
            ->pluck('driver_id');

        $this->driversMissingProfiles = $driverIds->isEmpty()
            ? []
            : Driver::query()
                ->whereIn('id', $driverIds)
                ->whereDoesntHave('billingProfiles', function ($query) use ($periodStart, $periodEnd): void {
                    $query->where('active', true);

                    if ($periodEnd) {
                        $query->where(function ($query) use ($periodEnd): void {
                            $query->whereNull('valid_from')
                                ->orWhereDate('valid_from', '<=', $periodEnd);
                        });
                    }

                    if ($periodStart) {
                        $query->where(function ($query) use ($periodStart): void {
                            $query->whereNull('valid_to')
                                ->orWhereDate('valid_to', '>=', $periodStart);
                        });
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (Driver $driver): array => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                ])
                ->all();

        $auditQuery = PlatformDriverBalance::query()
            ->leftJoin('drivers', 'drivers.id', '=', 'platform_driver_balances.driver_id')
            ->select([
                'platform_driver_balances.id',
                'platform_driver_balances.platform',
                'platform_driver_balances.driver_id',
                'platform_driver_balances.driver_code',
                'platform_driver_balances.period_start',
                'platform_driver_balances.period_end',
                'platform_driver_balances.net_amount',
                'platform_driver_balances.tips_amount',
                'platform_driver_balances.net_source_column',
                'platform_driver_balances.tips_source_column',
                'platform_driver_balances.raw_row',
                'drivers.name as driver_name',
                'drivers.email as driver_email',
            ]);

        if ($periodStart && $periodEnd) {
            $auditQuery
                ->whereDate('platform_driver_balances.period_start', '>=', $periodStart)
                ->whereDate('platform_driver_balances.period_end', '<=', $periodEnd);
        }

        if ($driverId) {
            $auditQuery->where('platform_driver_balances.driver_id', $driverId);
        }

        $this->auditRows = $auditQuery
            ->orderByDesc('platform_driver_balances.period_start')
            ->get()
            ->map(fn (PlatformDriverBalance $row): array => [
                'platform' => $row->platform,
                'driver_code' => $row->driver_code,
                'driver_name' => $row->driver_name,
                'driver_email' => $row->driver_email,
                'net_amount' => (float) $row->net_amount,
                'tips_amount' => (float) $row->tips_amount,
                'net_source_column' => $row->net_source_column,
                'tips_source_column' => $row->tips_source_column,
                'raw_row' => $row->raw_row,
            ])
            ->all();
    }
}
