<?php

namespace App\Filament\Pages;

use App\Enums\StatementStatus;
use App\Models\Board;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverWeekStatement;
use App\Models\NotificationLog;
use App\Models\Stage;
use App\Models\Task;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class OpsControl extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'Painel';

    protected static UnitEnum|string|null $navigationGroup = 'Dashboards';

    protected static ?int $navigationSort = -100;

    protected static ?string $slug = '';

    protected string $view = 'filament.pages.ops-control';

    /** @var array<string, int> */
    public array $kanbanStats = [];

    /** @var array<string, int> */
    public array $tvdeStats = [];

    public Collection $recentTasks;

    public Collection $recentStatements;

    /** @var array<int, array{label:string,total:int}> */
    public array $kanbanStageChart = [];

    /** @var array<int, array{label:string,total:float}> */
    public array $tvdeAmountChart = [];

    public function mount(): void
    {
        $this->kanbanStats = [
            'boards' => Board::count(),
            'stages' => Stage::count(),
            'tasks' => Task::count(),
            'notifications' => NotificationLog::count(),
        ];

        $this->tvdeStats = [
            'drivers' => Driver::count(),
            'profiles' => DriverBillingProfile::count(),
            'active_profiles' => DriverBillingProfile::where('active', true)->count(),
            'statements' => DriverWeekStatement::count(),
            'draft_statements' => DriverWeekStatement::where('status', StatementStatus::Draft)->count(),
        ];

        $this->recentTasks = Task::query()
            ->with('stage')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'stage_id', 'created_at']);

        $this->recentStatements = DriverWeekStatement::query()
            ->with(['driver'])
            ->latest('calculated_at')
            ->latest()
            ->limit(5)
            ->get(['id', 'driver_id', 'week_start_date', 'week_end_date', 'amount_payable_to_driver', 'status']);

        $this->kanbanStageChart = Task::query()
            ->selectRaw('stage_id, count(*) as total')
            ->groupBy('stage_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                return [
                    'label' => $row->stage?->name ?? 'Estágio',
                    'total' => (int) $row->total,
                ];
            })
            ->toArray();

        $this->tvdeAmountChart = DriverWeekStatement::query()
            ->latest('week_end_date')
            ->limit(6)
            ->get(['week_start_date', 'week_end_date', 'amount_payable_to_driver'])
            ->reverse()
            ->values()
            ->map(function ($row) {
                return [
                    'label' => $row->week_end_date?->format('d/m') ?? 'Sem data',
                    'total' => (float) $row->amount_payable_to_driver,
                ];
            })
            ->toArray();
    }

    public function getKanbanBoardUrl(): string
    {
        return KanbanBoard::getUrl();
    }
}
