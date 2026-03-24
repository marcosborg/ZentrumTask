<?php

use App\Filament\Pages\KanbanBoard;
use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->and($task->first_interaction_at)->not->toBeNull();
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
