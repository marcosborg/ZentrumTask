<?php

namespace App\Console\Commands;

use App\Services\KanbanStageTimeoutService;
use Illuminate\Console\Command;

class ApplyKanbanStageTimeouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kanban:apply-stage-timeouts {--board= : Limit to a specific board id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move Kanban tasks that exceeded their current stage timeout';

    /**
     * Execute the console command.
     */
    public function handle(KanbanStageTimeoutService $service): int
    {
        $boardId = $this->option('board') ? (int) $this->option('board') : null;

        $result = $service->apply($boardId);

        $this->info("Tarefas movidas: {$result['moved']}");
        $this->info("Estagios ignorados: {$result['skipped']}");

        return self::SUCCESS;
    }
}
