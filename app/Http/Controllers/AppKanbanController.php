<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppKanbanController extends AppApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $boards = Board::query()
            ->orderBy('position')
            ->get(['id', 'name', 'position']);

        $boardId = (int) ($request->integer('board_id') ?: ($boards->first()?->id ?? 0));

        $stages = Stage::query()
            ->where('board_id', $boardId)
            ->orderBy('position')
            ->get();

        $tasks = Task::query()
            ->where('board_id', $boardId)
            ->with(['assignedTo', 'stage'])
            ->orderBy('stage_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('stage_id');

        return $this->corsJson([
            'boards' => $boards->map(fn (Board $board) => [
                'id' => $board->id,
                'name' => $board->name,
            ])->values()->all(),
            'board_id' => $boardId,
            'stages' => $stages->map(function (Stage $stage) use ($tasks): array {
                $stageTasks = $tasks->get($stage->id, collect());

                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'is_initial' => (bool) $stage->is_initial,
                    'is_final' => (bool) $stage->is_final,
                    'tasks' => $stageTasks->map(fn (Task $task) => $this->mapTaskSummary($task))->values()->all(),
                ];
            })->values()->all(),
        ]);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $task->load(['assignedTo', 'stage', 'comments.user']);

        $stages = Stage::query()
            ->where('board_id', $task->board_id)
            ->orderBy('position')
            ->get(['id', 'name', 'color', 'is_initial', 'is_final']);

        return $this->corsJson([
            'task' => $this->mapTaskDetail($task),
            'available_stages' => $stages
                ->where('id', '!=', $task->stage_id)
                ->map(fn (Stage $stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'is_initial' => (bool) $stage->is_initial,
                    'is_final' => (bool) $stage->is_final,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function addComment(Request $request, Task $task): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'body' => ['required', 'string', 'min:2'],
                'is_internal' => ['nullable', 'boolean'],
            ], [
                'body.required' => 'Escreva uma observacao.',
                'body.min' => 'A observacao deve ter pelo menos 2 caracteres.',
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar a observacao.',
                'errors' => $exception->errors(),
            ], 422);
        }

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? true),
        ]);

        $task->markFirstInteraction();
        $task->load(['assignedTo', 'stage', 'comments.user']);

        return $this->corsJson([
            'message' => 'Observacao guardada.',
            'task' => $this->mapTaskDetail($task),
        ]);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'stage_id' => ['required', 'integer', 'exists:stages,id'],
            ], [
                'stage_id.required' => 'Selecione o estadio de destino.',
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar a mudanca de estadio.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $targetStage = Stage::query()
            ->where('board_id', $task->board_id)
            ->findOrFail($data['stage_id']);

        $nextPosition = (int) Task::query()
            ->where('board_id', $task->board_id)
            ->where('stage_id', $targetStage->id)
            ->max('position');

        $task->update([
            'stage_id' => $targetStage->id,
            'position' => $nextPosition + 1,
        ]);

        $task->refresh()->load(['assignedTo', 'stage', 'comments.user']);

        return $this->corsJson([
            'message' => 'Tarefa movida.',
            'task' => $this->mapTaskDetail($task),
        ]);
    }

    protected function mapTaskSummary(Task $task): array
    {
        $meta = is_array($task->meta) ? $task->meta : [];

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'created_at' => optional($task->created_at)?->toIso8601String(),
            'created_at_label' => optional($task->created_at)?->format('d/m H:i'),
            'first_interaction_at' => optional($task->first_interaction_at)?->toIso8601String(),
            'first_interaction_at_label' => optional($task->first_interaction_at)?->format('d/m H:i'),
            'assigned_to' => $task->assignedTo ? [
                'id' => $task->assignedTo->id,
                'name' => $task->assignedTo->name,
            ] : null,
            'email' => is_string($meta['email'] ?? null) ? $meta['email'] : null,
            'phone' => is_string($meta['phone'] ?? null) ? $meta['phone'] : null,
            'source' => is_string($meta['source'] ?? null) ? $meta['source'] : null,
            'meta' => $meta,
        ];
    }

    protected function mapTaskDetail(Task $task): array
    {
        return array_merge($this->mapTaskSummary($task), [
            'stage' => $task->stage ? [
                'id' => $task->stage->id,
                'name' => $task->stage->name,
                'color' => $task->stage->color,
                'is_initial' => (bool) $task->stage->is_initial,
                'is_final' => (bool) $task->stage->is_final,
            ] : null,
            'comments' => $task->comments
                ->sortByDesc('created_at')
                ->map(fn (TaskComment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'is_internal' => (bool) $comment->is_internal,
                    'created_at' => optional($comment->created_at)?->toIso8601String(),
                    'created_at_label' => optional($comment->created_at)?->format('d/m H:i'),
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'email' => $comment->user->email,
                    ] : null,
                ])
                ->values()
                ->all(),
        ]);
    }
}
