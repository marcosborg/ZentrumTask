<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\AndroidPushNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function show(Request $request, int $task): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $task = $this->resolveTask($task);

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
            'restore_stages' => $stages
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

    public function search(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'q' => ['required', 'string', 'min:1', 'max:255'],
                'board_id' => ['nullable', 'integer', 'exists:boards,id'],
            ], [
                'q.required' => 'Indique o texto a pesquisar.',
                'q.min' => 'Indique pelo menos 1 caractere.',
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar a pesquisa.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $term = trim((string) $data['q']);
        $normalizedTerm = $this->normalizeSearchTerm($term);

        $tasks = Task::query()
            ->withTrashed()
            ->with(['assignedTo', 'stage', 'board'])
            ->when(
                isset($data['board_id']),
                fn (Builder $query): Builder => $query->where('board_id', (int) $data['board_id'])
            )
            ->where(function (Builder $query) use ($term, $normalizedTerm): void {
                $like = '%'.$term.'%';

                $query
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('priority', 'like', $like)
                    ->orWhere('external_reference', 'like', $like)
                    ->orWhereHas('assignedTo', fn (Builder $subQuery): Builder => $subQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like))
                    ->orWhereHas('stage', fn (Builder $subQuery): Builder => $subQuery
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like))
                    ->orWhereHas('board', fn (Builder $subQuery): Builder => $subQuery
                        ->where('name', 'like', $like))
                    ->orWhereHas('comments', fn (Builder $subQuery): Builder => $subQuery
                        ->where('body', 'like', $like));

                $this->addMetaSearchConstraints($query, $like, $normalizedTerm);
            })
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->corsJson([
            'query' => $term,
            'results' => $tasks->map(fn (Task $task) => $this->mapTaskSummary($task))->values()->all(),
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

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $task->delete();

        return $this->corsJson([
            'message' => 'Tarefa eliminada.',
        ]);
    }

    public function restore(Request $request, int $task): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        $task = $this->resolveTask($task);

        try {
            $data = $request->validate([
                'stage_id' => ['required', 'integer', 'exists:stages,id'],
            ], [
                'stage_id.required' => 'Selecione o estadio de destino.',
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar o restauro da tarefa.',
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

        $task->restore();
        $task->update([
            'stage_id' => $targetStage->id,
            'position' => $nextPosition + 1,
        ]);

        $task->refresh()->load(['assignedTo', 'stage', 'comments.user']);

        return $this->corsJson([
            'message' => 'Tarefa restaurada.',
            'task' => $this->mapTaskDetail($task),
        ]);
    }

    public function storeContact(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:50'],
                'email' => ['required', 'email', 'max:255'],
                'board_id' => ['nullable', 'integer', 'exists:boards,id'],
            ], [
                'name.required' => 'Indique o nome do contacto.',
                'phone.required' => 'Indique o telefone do contacto.',
                'email.required' => 'Indique o email do contacto.',
                'email.email' => 'Indique um email valido.',
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar o contacto.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $boardId = (int) ($data['board_id'] ?? 1);
        $stage = $this->resolveLeadStage($boardId);

        if (! $stage) {
            return $this->corsJson([
                'message' => 'Nao encontrei o estadio de entrada para este board.',
            ], 422);
        }

        $task = DB::transaction(function () use ($data, $stage, $boardId, $user): Task {
            $nextPosition = (int) Task::query()
                ->where('board_id', $boardId)
                ->where('stage_id', $stage->id)
                ->max('position');

            return Task::query()->create([
                'board_id' => $boardId,
                'stage_id' => $stage->id,
                'assigned_to_id' => $user->id,
                'title' => 'Lead: '.$data['name'],
                'description' => 'Contacto criado na area reservada.',
                'priority' => 'normal',
                'position' => $nextPosition + 1,
                'meta' => [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'source' => 'reserved_area',
                    'contact_name' => $data['name'],
                ],
            ]);
        });

        $task->load(['assignedTo', 'stage', 'comments.user']);

        app(AndroidPushNotificationService::class)->sendNewContactTask($task);

        return $this->corsJson([
            'message' => 'Contacto criado com sucesso.',
            'task' => $this->mapTaskDetail($task),
        ], 201);
    }

    protected function resolveLeadStage(int $boardId): ?Stage
    {
        return Stage::query()
            ->where('board_id', $boardId)
            ->where(function ($query): void {
                $query
                    ->where('is_initial', true)
                    ->orWhereRaw('LOWER(name) = ?', ['entrada'])
                    ->orWhereRaw('LOWER(slug) = ?', ['entrada']);
            })
            ->orderByRaw("CASE WHEN LOWER(name) = 'entrada' OR LOWER(slug) = 'entrada' THEN 0 ELSE 1 END")
            ->orderByDesc('is_initial')
            ->orderBy('position')
            ->first()
            ?? Stage::query()
                ->where('board_id', $boardId)
                ->orderBy('position')
                ->first();
    }

    protected function resolveTask(int $taskId): Task
    {
        return Task::query()
            ->withTrashed()
            ->findOrFail($taskId);
    }

    protected function addMetaSearchConstraints(Builder $query, string $like, string $normalizedTerm): void
    {
        $query
            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.email')) like ?", [$like])
            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.phone')) like ?", [$like])
            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.contact_name')) like ?", [$like])
            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) like ?", [$like]);

        if ($normalizedTerm === '') {
            return;
        }

        $query->orWhereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.phone')), ' ', ''), '+', ''), '-', ''), '/', ''), '(', ''), ')', '') like ?",
            ['%'.$normalizedTerm.'%']
        );
    }

    protected function normalizeSearchTerm(string $value): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', $value) ?? '';
    }

    protected function mapTaskSummary(Task $task): array
    {
        $meta = is_array($task->meta) ? $task->meta : [];

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'board' => $task->board ? [
                'id' => $task->board->id,
                'name' => $task->board->name,
            ] : null,
            'stage' => $task->stage ? [
                'id' => $task->stage->id,
                'name' => $task->stage->name,
                'color' => $task->stage->color,
                'is_initial' => (bool) $task->stage->is_initial,
                'is_final' => (bool) $task->stage->is_final,
            ] : null,
            'created_at' => optional($task->created_at)?->toIso8601String(),
            'created_at_label' => optional($task->created_at)?->format('d/m H:i'),
            'first_interaction_at' => optional($task->first_interaction_at)?->toIso8601String(),
            'first_interaction_at_label' => optional($task->first_interaction_at)?->format('d/m H:i'),
            'deleted_at' => optional($task->deleted_at)?->toIso8601String(),
            'deleted_at_label' => optional($task->deleted_at)?->format('d/m H:i'),
            'is_deleted' => $task->trashed(),
            'assigned_to' => $task->assignedTo ? [
                'id' => $task->assignedTo->id,
                'name' => $task->assignedTo->name,
            ] : null,
            'email' => is_string($meta['email'] ?? null) ? $meta['email'] : null,
            'phone' => is_string($meta['phone'] ?? null) ? $meta['phone'] : null,
            'contact_name' => is_string($meta['contact_name'] ?? null) ? $meta['contact_name'] : null,
            'source' => is_string($meta['source'] ?? null) ? $meta['source'] : null,
            'meta' => $meta,
        ];
    }

    protected function mapTaskDetail(Task $task): array
    {
        return array_merge($this->mapTaskSummary($task), [
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
