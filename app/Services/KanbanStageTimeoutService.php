<?php

namespace App\Services;

use App\Models\Stage;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KanbanStageTimeoutService
{
    /**
     * @return array{moved:int, skipped:int}
     */
    public function apply(?int $boardId = null, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $moved = 0;
        $skipped = 0;

        $stages = Stage::query()
            ->whereNotNull('timeout_days')
            ->where('timeout_days', '>', 0)
            ->whereNotNull('timeout_target_stage_id')
            ->when($boardId, fn (Builder $query): Builder => $query->where('board_id', $boardId))
            ->with('timeoutTargetStage')
            ->orderBy('id')
            ->get();

        foreach ($stages as $stage) {
            $targetStage = $stage->timeoutTargetStage;

            if (! $targetStage || (int) $targetStage->board_id !== (int) $stage->board_id || (int) $targetStage->id === (int) $stage->id) {
                $skipped++;

                continue;
            }

            $cutoff = $now->copy()->subDays((int) $stage->timeout_days);

            $tasks = Task::query()
                ->where('board_id', $stage->board_id)
                ->where('stage_id', $stage->id)
                ->where(function (Builder $query) use ($cutoff): void {
                    $query
                        ->where('stage_entered_at', '<=', $cutoff)
                        ->orWhere(function (Builder $query) use ($cutoff): void {
                            $query
                                ->whereNull('stage_entered_at')
                                ->where(function (Builder $query) use ($cutoff): void {
                                    $query
                                        ->where('updated_at', '<=', $cutoff)
                                        ->orWhere(function (Builder $query) use ($cutoff): void {
                                            $query
                                                ->whereNull('updated_at')
                                                ->where('created_at', '<=', $cutoff);
                                        });
                                });
                        });
                })
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($tasks as $task) {
                DB::transaction(function () use ($task, $targetStage, &$moved): void {
                    $nextPosition = (int) Task::query()
                        ->where('board_id', $task->board_id)
                        ->where('stage_id', $targetStage->id)
                        ->max('position');

                    $task->update([
                        'stage_id' => $targetStage->id,
                        'position' => $nextPosition + 1,
                    ]);

                    $moved++;
                });
            }
        }

        return [
            'moved' => $moved,
            'skipped' => $skipped,
        ];
    }
}
