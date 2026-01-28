<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Services\DriverSettlementCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class DriverSettlementsReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Settlements';

    protected static ?string $title = 'Relatorio de settlements';

    protected string $view = 'filament.pages.driver-settlements-report';

    public ?string $periodStart = null;

    public ?string $periodEnd = null;

    public ?int $driverId = null;

    public function mount(): void
    {
        $start = Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString();
        $end = Carbon::today()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $this->periodStart = $start;
        $this->periodEnd = $end;
        $this->driverId = null;

        $this->form->fill([
            'periodStart' => $start,
            'periodEnd' => $end,
            'driverId' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                DatePicker::make('periodStart')
                    ->label('Período início')
                    ->required(),
                DatePicker::make('periodEnd')
                    ->label('Período fim')
                    ->required(),
                Select::make('driverId')
                    ->label('Motorista')
                    ->options(Driver::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $periodStart = $this->periodStart;
        $periodEnd = $this->periodEnd;
        $driverId = $this->driverId;

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

        $hasSettlementsForPeriod = $settlements->isNotEmpty();

        $pendingQuery = PlatformDriverBalance::query()
            ->whereNull('driver_id');

        if ($periodStart && $periodEnd) {
            $pendingQuery
                ->whereDate('period_start', '>=', $periodStart)
                ->whereDate('period_end', '<=', $periodEnd);
        }

        $pendingBalances = $pendingQuery
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

        $driversMissingProfiles = $driverIds->isEmpty()
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

        $auditRows = $auditQuery
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

        return [
            'settlements' => $settlements->all(),
            'pendingBalances' => $pendingBalances,
            'driversMissingProfiles' => $driversMissingProfiles,
            'auditRows' => $auditRows,
            'hasSettlementsForPeriod' => $hasSettlementsForPeriod,
        ];
    }

    public function applyFiltersAction(): Action
    {
        return Action::make('applyFilters')
            ->label('Aplicar filtros')
            ->color('primary')
            ->action(fn () => null)
            ->livewire($this);
    }

    public function generateSettlementsAction(): Action
    {
        return Action::make('generateSettlements')
            ->label('Gerar settlements para este periodo')
            ->color('warning')
            ->requiresConfirmation()
            ->disabled(fn (): bool => ! $this->periodStart || ! $this->periodEnd)
            ->action(fn () => app(DriverSettlementCalculator::class)
                ->calculate($this->periodStart, $this->periodEnd))
            ->livewire($this);
    }

    public function deletePeriodAction(): Action
    {
        return Action::make('deletePeriod')
            ->label('Eliminar dados do periodo')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn () => DriverSettlement::query()
                ->whereBetween('period_start', [$this->periodStart, $this->periodEnd])
                ->delete())
            ->livewire($this);
    }
}
