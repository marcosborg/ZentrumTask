<?php

namespace App\Filament\Pages;

use App\Models\Board;
use App\Models\MessageTemplate;
use App\Models\NotificationRule;
use App\Models\RecipientList;
use App\Models\Stage;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use UnitEnum;

class KanbanBoard extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Kanban';

    protected static UnitEnum|string|null $navigationGroup = 'Kanban';

    protected string $view = 'filament.pages.kanban-board';

    public ?int $boardId = null;

    /** @var Collection<int, Stage> */
    public Collection $stages;

    /** @var array<int, Collection<int, Task>> */
    public array $tasksByStage = [];

    /** @var array<int, Collection<int, NotificationRule>> */
    public array $rulesByStage = [];

    public array $taskForm = [
        'id' => null,
        'board_id' => null,
        'stage_id' => null,
        'assigned_to_id' => null,
        'title' => '',
        'description' => '',
        'priority' => 'normal',
        'due_at' => null,
        'external_reference' => null,
        'meta_raw' => null,
        'tags' => [],
    ];

    public array $stageForm = [
        'id' => null,
        'board_id' => null,
        'name' => '',
        'color' => null,
        'is_initial' => false,
        'is_final' => false,
    ];

    public bool $showTaskForm = false;

    public bool $showStageForm = false;

    public bool $showAutomationPanel = false;

    public bool $showTaskDetail = false;

    public ?int $activeTaskId = null;

    /** lookups */
    public Collection $boards;

    public Collection $users;

    public Collection $tags;

    public Collection $messageTemplates;

    public Collection $recipientLists;

    public array $taskComments = [];

    public array $taskAttachments = [];

    public $attachmentUpload = null;

    public array $commentForm = [
        'body' => '',
        'is_internal' => true,
    ];

    public array $ruleForm = [
        'id' => null,
        'stage_id' => null,
        'message_template_id' => null,
        'recipient_list_id' => null,
        'trigger' => 'on_enter_stage',
        'send_mode' => 'always',
        'cooldown_hours' => null,
        'also_send_to_assigned_user' => false,
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->loadLookups();

        $this->boardId ??= $this->boards->first()?->id;

        $this->loadBoard();
    }

    public function updatedBoardId(): void
    {
        $this->resetValidation();
        $this->closeForms();
        $this->loadBoard();
    }

    protected function loadLookups(): void
    {
        $this->boards = Board::orderBy('position')->get();
        $this->users = User::orderBy('name')->get();
        $this->tags = Tag::orderBy('name')->get();
        $this->messageTemplates = MessageTemplate::orderBy('name')->get();
        $this->recipientLists = RecipientList::orderBy('name')->get();
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

        $tasks = Task::query()
            ->where('board_id', $this->boardId)
            ->with(['assignedTo', 'tags'])
            ->orderBy('stage_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('stage_id');

        $this->tasksByStage = [];
        foreach ($this->stages as $stage) {
            $this->tasksByStage[$stage->id] = $tasks->get($stage->id, collect());
        }

        $rules = NotificationRule::query()
            ->whereIn('stage_id', $this->stages->pluck('id'))
            ->with(['messageTemplate', 'recipientList'])
            ->get()
            ->groupBy('stage_id');

        $this->rulesByStage = [];
        foreach ($this->stages as $stage) {
            $this->rulesByStage[$stage->id] = $rules->get($stage->id, collect());
        }

        $this->resetTaskForm();
    }

    protected function resetTaskForm(?int $stageId = null): void
    {
        $this->taskForm = [
            'id' => null,
            'board_id' => $this->boardId,
            'stage_id' => $stageId ?? $this->stages->firstWhere('is_initial', true)?->id ?? $this->stages->first()?->id,
            'assigned_to_id' => null,
            'title' => '',
            'description' => '',
            'priority' => 'normal',
            'due_at' => null,
            'external_reference' => null,
            'meta_raw' => null,
            'tags' => [],
        ];
        $this->activeTaskId = null;
    }

    protected function resetStageForm(?int $stageId = null): void
    {
        $this->stageForm = [
            'id' => $stageId,
            'board_id' => $this->boardId,
            'name' => '',
            'color' => null,
            'is_initial' => false,
            'is_final' => false,
        ];
    }

    public function startCreateTask(?int $stageId = null): void
    {
        $this->resetValidation();
        $this->closeForms();
        $this->resetTaskForm($stageId);
        $this->showTaskForm = true;
        $this->showTaskDetail = false;
    }

    public function editTask(int $taskId): void
    {
        $this->resetValidation();
        $this->closeForms();

        $task = Task::with('tags')->findOrFail($taskId);

        $this->fillTaskForm($task);
        $this->activeTaskId = $task->id;
        $this->showTaskForm = true;
        $this->showTaskDetail = false;
    }

    protected function fillTaskForm(Task $task): void
    {
        $this->taskForm = [
            'id' => $task->id,
            'board_id' => $task->board_id,
            'stage_id' => $task->stage_id,
            'assigned_to_id' => $task->assigned_to_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'due_at' => optional($task->due_at)?->format('Y-m-d\TH:i'),
            'external_reference' => $task->external_reference,
            'meta_raw' => $task->meta ? json_encode($task->meta, JSON_PRETTY_PRINT) : null,
            'tags' => $task->tags->pluck('id')->all(),
        ];
    }

    public function saveTask(): void
    {
        $data = validator($this->taskForm, [
            'id' => ['nullable', 'integer', 'exists:tasks,id'],
            'board_id' => ['required', 'exists:boards,id'],
            'stage_id' => ['required', 'exists:stages,id'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['low', 'normal', 'medium', 'high', 'critical'])],
            'due_at' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'meta_raw' => ['nullable', 'string'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ])->validate();

        $meta = null;
        if (! empty($data['meta_raw'])) {
            $meta = json_decode($data['meta_raw'], true);
            if (! is_array($meta)) {
                Notification::make()->title('Meta deve ser JSON válido')->danger()->send();

                return;
            }
        }

        $dueAt = $data['due_at'] ? Carbon::parse($data['due_at']) : null;

        if ($data['id'] ?? false) {
            $task = Task::findOrFail($data['id']);
        } else {
            $task = new Task;
            $maxPosition = Task::where('board_id', $data['board_id'])
                ->where('stage_id', $data['stage_id'])
                ->max('position');
            $task->position = ($maxPosition ?? 0) + 1;
        }

        $task->fill([
            'board_id' => $data['board_id'],
            'stage_id' => $data['stage_id'],
            'assigned_to_id' => $data['assigned_to_id'] ?: null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'due_at' => $dueAt,
            'external_reference' => $data['external_reference'] ?? null,
            'meta' => $meta,
        ]);

        $task->save();
        $task->tags()->sync($data['tags'] ?? []);

        Notification::make()->title('Tarefa guardada')->success()->send();

        $this->showTaskForm = false;
        $this->showTaskDetail = false;
        $this->loadBoard();
    }

    public function startStageForm(?int $stageId = null): void
    {
        $this->resetValidation();
        $this->closeForms();

        $this->resetStageForm($stageId);
        $this->stageForm['board_id'] = $this->boardId;
        $this->showStageForm = true;

        if ($stageId) {
            $stage = Stage::findOrFail($stageId);
            $this->stageForm = [
                'id' => $stage->id,
                'board_id' => $stage->board_id,
                'name' => $stage->name,
                'color' => $stage->color,
                'is_initial' => (bool) $stage->is_initial,
                'is_final' => (bool) $stage->is_final,
            ];
        }
    }

    public function saveStage(): void
    {
        if (! $this->boardId) {
            return;
        }

        $data = validator($this->stageForm, [
            'board_id' => ['required', 'exists:boards,id'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'is_initial' => ['boolean'],
            'is_final' => ['boolean'],
        ])->validate();

        $payload = [
            'board_id' => $data['board_id'],
            'name' => $data['name'],
            'slug' => $this->generateStageSlug($data['board_id'], $data['name'], $this->stageForm['id']),
            'color' => $data['color'] ?: null,
            'is_initial' => $data['is_initial'] ?? false,
            'is_final' => $data['is_final'] ?? false,
        ];

        if ($this->stageForm['id']) {
            $stage = Stage::findOrFail($this->stageForm['id']);
            $stage->update($payload);
        } else {
            $position = Stage::where('board_id', $data['board_id'])->max('position') ?? 0;
            $payload['position'] = $position + 1;
            Stage::create($payload);
        }

        Notification::make()->title('Estágio guardado')->success()->send();

        $this->showStageForm = false;
        $this->loadBoard();
    }

    protected function generateStageSlug(int $boardId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'stage';
        $slug = $base;
        $suffix = 1;

        while (
            Stage::where('board_id', $boardId)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function moveTaskToStage(int $taskId, int $stageId): void
    {
        $task = Task::findOrFail($taskId);

        $nextPosition = (int) Task::where('board_id', $task->board_id)
            ->where('stage_id', $stageId)
            ->max('position');

        $task->update([
            'stage_id' => $stageId,
            'position' => $nextPosition + 1,
        ]);

        $this->loadBoard();
    }

    public function closeForms(): void
    {
        $this->showTaskForm = false;
        $this->showStageForm = false;
        $this->showTaskDetail = false;
        $this->showAutomationPanel = false;
    }

    public function getBoardsProperty(): Collection
    {
        return $this->boards;
    }

    public function openTaskDetail(int $taskId): void
    {
        $task = Task::with(['comments.user', 'attachments', 'stage'])->findOrFail($taskId);
        $this->activeTaskId = $taskId;
        $this->showTaskDetail = true;
        $this->showTaskForm = false;
        $this->loadTaskMeta($task);
    }

    protected function loadTaskMeta(Task $task): void
    {
        $this->taskComments = $task->comments
            ->sortByDesc('created_at')
            ->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user' => $c->user?->name,
                'is_internal' => (bool) $c->is_internal,
                'created_at' => optional($c->created_at)?->format('d/m H:i'),
            ])->values()->all();

        $this->taskAttachments = $task->attachments
            ->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size' => $a->size,
                'url' => $a->url,
                'created_at' => optional($a->created_at)?->format('d/m H:i'),
            ])->values()->all();
    }

    public function addComment(): void
    {
        if (! $this->activeTaskId) {
            return;
        }

        $data = validator($this->commentForm, [
            'body' => ['required', 'string', 'min:2'],
            'is_internal' => ['boolean'],
        ])->validate();

        TaskComment::create([
            'task_id' => $this->activeTaskId,
            'user_id' => auth()->id(),
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? true,
        ]);

        $this->commentForm = ['body' => '', 'is_internal' => true];
        $task = Task::findOrFail($this->activeTaskId);
        $this->loadTaskMeta($task->load(['comments.user', 'attachments']));
    }

    public function addAttachment(): void
    {
        if (! $this->activeTaskId || ! $this->attachmentUpload) {
            return;
        }

        validator(['file' => $this->attachmentUpload], [
            'file' => ['file', 'max:10240'],
        ])->validate();

        $path = $this->attachmentUpload->store("task-attachments/{$this->activeTaskId}", 'public');

        TaskAttachment::create([
            'task_id' => $this->activeTaskId,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $this->attachmentUpload->getClientOriginalName(),
            'mime_type' => $this->attachmentUpload->getMimeType(),
            'size' => $this->attachmentUpload->getSize(),
        ]);

        $this->attachmentUpload = null;
        $task = Task::findOrFail($this->activeTaskId);
        $this->loadTaskMeta($task->load(['comments.user', 'attachments']));
    }

    public function startRuleForm(int $stageId, ?int $ruleId = null): void
    {
        $this->resetValidation();
        $this->closeForms();
        $this->showAutomationPanel = true;

        $this->ruleForm = [
            'id' => $ruleId,
            'stage_id' => $stageId,
            'message_template_id' => null,
            'recipient_list_id' => null,
            'trigger' => 'on_enter_stage',
            'send_mode' => 'always',
            'cooldown_hours' => null,
            'also_send_to_assigned_user' => false,
            'is_active' => true,
        ];

        if ($ruleId) {
            $rule = NotificationRule::findOrFail($ruleId);
            $this->ruleForm = array_merge($this->ruleForm, [
                'message_template_id' => $rule->message_template_id,
                'recipient_list_id' => $rule->recipient_list_id,
                'trigger' => $rule->trigger,
                'send_mode' => $rule->send_mode,
                'cooldown_hours' => $rule->cooldown_hours,
                'also_send_to_assigned_user' => (bool) $rule->also_send_to_assigned_user,
                'is_active' => (bool) $rule->is_active,
            ]);
        }
    }

    public function saveRule(): void
    {
        $data = validator($this->ruleForm, [
            'stage_id' => ['required', 'exists:stages,id'],
            'message_template_id' => ['required', 'exists:message_templates,id'],
            'recipient_list_id' => ['nullable', 'exists:recipient_lists,id'],
            'trigger' => ['required', Rule::in(['on_enter_stage', 'on_exit_stage', 'on_task_update'])],
            'send_mode' => ['required', Rule::in(['always', 'first_time', 'cooldown'])],
            'cooldown_hours' => ['nullable', 'integer', 'min:1'],
            'also_send_to_assigned_user' => ['boolean'],
            'is_active' => ['boolean'],
        ])->validate();

        if ($data['send_mode'] !== 'cooldown') {
            $data['cooldown_hours'] = null;
        }

        if ($data['id']) {
            $rule = NotificationRule::findOrFail($data['id']);
            $rule->update($data);
        } else {
            NotificationRule::create($data);
        }

        Notification::make()->title('Regra guardada')->success()->send();
        $this->showAutomationPanel = false;
        $this->loadBoard();
    }
}
