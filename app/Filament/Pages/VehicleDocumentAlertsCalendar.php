<?php

namespace App\Filament\Pages;

use App\Models\VehicleDocumentAlert;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

class VehicleDocumentAlertsCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 26;

    protected static ?string $navigationLabel = 'Calendario de alertas';

    protected static ?string $title = 'Calendario de alertas';

    protected string $view = 'filament.pages.vehicle-document-alerts-calendar';

    #[Url]
    public ?int $month = null;

    #[Url]
    public ?int $year = null;

    public string $monthLabel = '';

    /** @var array<int, array<string, mixed>> */
    public array $weeks = [];

    public function mount(): void
    {
        $today = Carbon::today();
        $month = $this->month ?? $today->month;
        $year = $this->year ?? $today->year;

        $current = Carbon::createFromDate($year, $month, 1);
        $this->monthLabel = $current->translatedFormat('F Y');

        $start = $current->copy()->startOfMonth();
        $end = $current->copy()->endOfMonth();
        $calendarStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $alerts = VehicleDocumentAlert::query()
            ->with(['document.vehicle'])
            ->whereBetween('triggered_on', [$start, $end])
            ->orderBy('level')
            ->get()
            ->groupBy(fn (VehicleDocumentAlert $alert): string => $alert->triggered_on?->toDateString() ?? '');

        $this->weeks = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->toDateString();
                $dayAlerts = $alerts->get($dateKey, collect());

                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $current->month,
                    'alerts' => $dayAlerts->map(function (VehicleDocumentAlert $alert): array {
                        return [
                            'id' => $alert->id,
                            'level' => $alert->level,
                            'message' => $alert->message,
                            'is_resolved' => $alert->is_resolved,
                            'vehicle' => $alert->document?->vehicle?->license_plate,
                            'document' => $alert->document?->title,
                        ];
                    })->all(),
                ];

                $cursor->addDay();
            }

            $this->weeks[] = $week;
        }
    }

    public function previousMonthUrl(): string
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();

        return static::getUrl([
            'month' => $date->month,
            'year' => $date->year,
        ]);
    }

    public function nextMonthUrl(): string
    {
        $date = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();

        return static::getUrl([
            'month' => $date->month,
            'year' => $date->year,
        ]);
    }
}
