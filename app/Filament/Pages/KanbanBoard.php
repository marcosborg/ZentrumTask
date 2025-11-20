<?php

namespace App\Filament\Pages;

use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use Filament\Pages\Page;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class KanbanBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Kanban';

    protected string $view = 'filament.pages.kanban-board';

    public ?int $boardId = null;

    /** @var \Illuminate\Support\Collection */
    public $stages;

    /** @var array<int, \Illuminate\Support\Collection> */
    public array $tasksByStage = [];

    public function mount(): void
    {
        if ($this->boardId === null) {
            $this->boardId = Board::query()->orderBy('position')->value('id');
        }

        $this->loadBoard();
    }

    public function updatedBoardId($value): void
    {
        $this->loadBoard();
    }

    protected function loadBoard(): void
    {
        if (! $this->boardId) {
            $this->stages = collect();
            $this->tasksByStage = [];
            return;
        }

        $this->stages = Stage::query()
            ->where('board_id', $this->boardId)
            ->orderBy('position')
            ->get();

        $this->tasksByStage = [];

        foreach ($this->stages as $stage) {
            $this->tasksByStage[$stage->id] = Task::query()
                ->where('board_id', $this->boardId)
                ->where('stage_id', $stage->id)
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        }
    }

    public function moveTaskToStage(int $taskId, int $stageId): void
    {
        $task = Task::findOrFail($taskId);

        $maxPosition = Task::where('board_id', $task->board_id)
            ->where('stage_id', $stageId)
            ->max('position');

        $task->update([
            'stage_id' => $stageId,
            'position' => ($maxPosition ?? 0) + 1,
        ]);

        $this->loadBoard();
    }

    public function getBoardsProperty()
    {
        return Board::orderBy('position')->get();
    }
}
