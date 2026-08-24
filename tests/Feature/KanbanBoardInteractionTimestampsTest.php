<?php

use App\Filament\Pages\KanbanBoard;
use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\KanbanStageTimeoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function createKanbanFixture(): array
{
    $board = Board::query()->create([
        'name' => 'Board teste',
        'slug' => 'board-teste',
        'is_active' => true,
        'position' => 1,
    ]);

    $initialStage = Stage::query()->create([
        'board_id' => $board->id,
        'name' => 'Novo',
        'slug' => 'novo',
        'position' => 1,
        'is_initial' => true,
        'is_final' => false,
        'freeze_sla' => false,
    ]);

    $nextStage = Stage::query()->create([
        'board_id' => $board->id,
        'name' => 'Em curso',
        'slug' => 'em-curso',
        'position' => 2,
        'is_initial' => false,
        'is_final' => false,
        'freeze_sla' => false,
    ]);

    $task = Task::query()->create([
        'board_id' => $board->id,
        'stage_id' => $initialStage->id,
        'title' => 'Task teste',
        'priority' => 'normal',
        'position' => 1,
    ]);

    return [$board, $initialStage, $nextStage, $task];
}

function ensureUsersSoftDeletesColumn(): void
{
    if (Schema::hasColumn('users', 'deleted_at')) {
        return;
    }

    Schema::table('users', function (Blueprint $table) {
        $table->softDeletes();
    });
}

it('stores the first interaction timestamp when a task is moved for the first time', function () {
    ensureUsersSoftDeletesColumn();

    $user = User::factory()->create();
    [, $initialStage, $nextStage, $task] = createKanbanFixture();

    $this->actingAs($user);

    expect($task->first_interaction_at)->toBeNull();

    $page = new KanbanBoard;
    $page->boardId = $initialStage->board_id;
    $page->moveTaskToStage($task->id, $nextStage->id);

    $task->refresh();

    expect($task->stage_id)->toBe($nextStage->id)
        ->and($task->first_interaction_at)->not->toBeNull()
        ->and($task->stage_entered_at)->not->toBeNull();
});

it('builds personalized WhatsApp instructions from the task contact labels', function () {
    [, , , $task] = createKanbanFixture();
    $task->update([
        'meta' => [
            'contact_name' => 'Frederico',
            'phone' => '912 345 678',
        ],
    ]);

    $page = new KanbanBoard;
    $page->openTaskDetail($task->id);

    expect($page->whatsappInstructionsUrl)
        ->toStartWith('whatsapp://send?phone=351912345678&text=')
        ->and(urldecode((string) parse_url($page->whatsappInstructionsUrl, PHP_URL_QUERY)))
        ->toContain('Olá, Frederico! 👋')
        ->toContain('Viatura: Tesla Model 3')
        ->toContain('pagamento adicional de 25 € por semana durante 30 semanas');
});

it('does not expose WhatsApp instructions when the task has no valid phone', function () {
    [, , , $task] = createKanbanFixture();
    $task->update(['meta' => ['contact_name' => 'Frederico']]);

    $page = new KanbanBoard;
    $page->openTaskDetail($task->id);

    expect($page->whatsappInstructionsUrl)->toBeNull();
});

it('stores the first interaction timestamp when the first comment is added and keeps the original value afterwards', function () {
    ensureUsersSoftDeletesColumn();

    $user = User::factory()->create();
    [, , , $task] = createKanbanFixture();

    $this->actingAs($user);

    $page = new KanbanBoard;
    $page->activeTaskId = $task->id;
    $page->commentForm = [
        'body' => 'Primeira observacao',
        'is_internal' => true,
    ];
    $page->addComment();

    $task->refresh();
    $firstInteractionAt = $task->first_interaction_at;

    expect($firstInteractionAt)->not->toBeNull()
        ->and(TaskComment::query()->where('task_id', $task->id)->count())->toBe(1);

    $page = new KanbanBoard;
    $page->activeTaskId = $task->id;
    $page->commentForm = [
        'body' => 'Segunda observacao',
        'is_internal' => false,
    ];
    $page->addComment();

    $task->refresh();

    expect($task->first_interaction_at?->equalTo($firstInteractionAt))->toBeTrue()
        ->and(TaskComment::query()->where('task_id', $task->id)->count())->toBe(2);
});

it('soft deletes a task from the edit modal action', function () {
    ensureUsersSoftDeletesColumn();

    $user = User::factory()->create();
    [, $initialStage, , $task] = createKanbanFixture();

    $this->actingAs($user);

    $page = new KanbanBoard;
    $page->boardId = $initialStage->board_id;
    $page->taskForm['id'] = $task->id;
    $page->activeTaskId = $task->id;
    $page->showTaskForm = true;
    $page->showTaskDetail = true;

    $page->deleteTask();

    expect(Task::query()->find($task->id))->toBeNull()
        ->and(Task::withTrashed()->find($task->id)?->trashed())->toBeTrue()
        ->and($page->showTaskForm)->toBeFalse()
        ->and($page->showTaskDetail)->toBeFalse();
});

it('moves tasks to the configured target stage after the stage timeout expires', function () {
    Carbon::setTestNow('2026-04-27 10:00:00');

    try {
        [$board, $initialStage, $nextStage, $task] = createKanbanFixture();

        $initialStage->update([
            'timeout_days' => 2,
            'timeout_target_stage_id' => $nextStage->id,
        ]);

        $task->forceFill([
            'stage_entered_at' => now()->subDays(3),
        ])->saveQuietly();

        $result = app(KanbanStageTimeoutService::class)->apply($board->id);

        $task->refresh();

        expect($result['moved'])->toBe(1)
            ->and($task->stage_id)->toBe($nextStage->id)
            ->and($task->stage_entered_at?->equalTo(now()))->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('keeps tasks in place while the configured stage timeout has not expired', function () {
    Carbon::setTestNow('2026-04-27 10:00:00');

    try {
        [$board, $initialStage, $nextStage, $task] = createKanbanFixture();

        $initialStage->update([
            'timeout_days' => 2,
            'timeout_target_stage_id' => $nextStage->id,
        ]);

        $task->forceFill([
            'stage_entered_at' => now()->subDay(),
        ])->saveQuietly();

        $result = app(KanbanStageTimeoutService::class)->apply($board->id);

        $task->refresh();

        expect($result['moved'])->toBe(0)
            ->and($task->stage_id)->toBe($initialStage->id);
    } finally {
        Carbon::setTestNow();
    }
});
