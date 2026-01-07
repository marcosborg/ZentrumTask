<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

class BoltEarningsReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'Relatorio semanal (Bolt/Uber)';

    protected static ?string $title = 'Relatorio Bolt e Uber';

    protected string $view = 'filament.pages.bolt-earnings-report';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<string, array<string, mixed>> */
    public array $summary = [];

    public function mount(): void
    {
        $boltRows = Schema::hasTable('bolt_driver_earnings')
            ? DB::table('bolt_driver_earnings')
                ->leftJoin('drivers', 'drivers.id', '=', 'bolt_driver_earnings.driver_id')
                ->selectRaw(
                    "'Bolt' as provider, week_start, week_end, ".
                    'COALESCE(drivers.name, bolt_driver_earnings.bolt_driver_name) as driver_name, '.
                    'COALESCE(drivers.email, bolt_driver_earnings.bolt_driver_email) as driver_email, '.
                    'SUM(total_amount) as total_amount, '.
                    'MAX(currency) as currency, '.
                    'COUNT(*) as entries'
                )
                ->groupBy('week_start', 'week_end')
                ->groupBy(DB::raw('COALESCE(drivers.name, bolt_driver_earnings.bolt_driver_name)'))
                ->groupBy(DB::raw('COALESCE(drivers.email, bolt_driver_earnings.bolt_driver_email)'))
                ->get()
            : collect();

        $uberRows = Schema::hasTable('uber_driver_earnings')
            ? DB::table('uber_driver_earnings')
                ->leftJoin('drivers', 'drivers.id', '=', 'uber_driver_earnings.driver_id')
                ->selectRaw(
                    "'Uber' as provider, week_start, week_end, ".
                    'COALESCE(drivers.name, uber_driver_earnings.uber_driver_name) as driver_name, '.
                    'COALESCE(drivers.email, uber_driver_earnings.uber_driver_email) as driver_email, '.
                    'SUM(total_amount) as total_amount, '.
                    'MAX(currency) as currency, '.
                    'COUNT(*) as entries'
                )
                ->groupBy('week_start', 'week_end')
                ->groupBy(DB::raw('COALESCE(drivers.name, uber_driver_earnings.uber_driver_name)'))
                ->groupBy(DB::raw('COALESCE(drivers.email, uber_driver_earnings.uber_driver_email)'))
                ->get()
            : collect();

        $rows = $boltRows->merge($uberRows)->map(function ($row): array {
            return [
                'provider' => $row->provider,
                'week_start' => $row->week_start,
                'week_end' => $row->week_end,
                'driver_name' => $row->driver_name,
                'driver_email' => $row->driver_email,
                'total_amount' => (float) $row->total_amount,
                'currency' => $row->currency,
                'entries' => (int) $row->entries,
            ];
        });

        $this->rows = $rows
            ->sortByDesc('week_start')
            ->values()
            ->all();

        $this->summary = [
            'bolt' => $this->summarize($boltRows, 'Bolt'),
            'uber' => $this->summarize($uberRows, 'Uber'),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function summarize($rows, string $label): array
    {
        $totalAmount = $rows->sum(fn ($row) => (float) $row->total_amount);
        $entries = $rows->sum(fn ($row) => (int) $row->entries);
        $drivers = $rows->pluck('driver_email')->filter()->unique()->count();

        return [
            'label' => $label,
            'entries' => $entries,
            'amount' => $totalAmount,
            'drivers' => $drivers,
        ];
    }
}
