<?php

namespace App\Filament\Pages;

use App\Models\Board;
use App\Models\Contact;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;
use UnitEnum;

use function class_uses_recursive;
use function collect;

class KanbanBoard extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Kanban';

    protected static UnitEnum|string|null $navigationGroup = 'Kanban';

    protected string $view = 'filament.pages.kanban-board';

    public ?int $boardId = null;

    public ?int $editingBoardId = null;

    public array $boardForm = [
        'name' => '',
        'slug' => '',
        'description' => '',
        'is_active' => true,
    ];

    /** @var \Illuminate\Support\Collection */
    public $stages;

    /** @var array<int, \Illuminate\Support\Collection> */
    public array $tasksByStage = [];

    /** @var array<int, \Illuminate\Support\Collection> */
    public array $rulesByStage = [];

    public array $stageForm = [
        'id' => null,
        'name' => '',
        'slug' => '',
        'color' => null,
        'is_initial' => false,
        'is_final' => false,
        'freeze_sla' => false,
    ];

    public ?int $editingRuleId = null;

    public array $ruleForm = [
        'stage_id' => null,
        'message_template_id' => null,
        'recipient_list_id' => null,
        'trigger' => 'on_enter_stage',
        'send_mode' => 'always',
        'cooldown_hours' => null,
        'also_send_to_assigned_user' => false,
        'is_active' => true,
    ];

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

    public ?int $activeTaskId = null;

    public bool $showTaskForm = false;

    public bool $showStageForm = false;

    public bool $showBoardForm = false;

    public bool $showAutomationPanel = false;

    public bool $showTaskDetail = false;

    public array $taskComments = [];

    public array $taskAttachments = [];

    public array $taskLogs = [];

    public array $commentForm = [
        'body' => '',
        'is_internal' => true,
    ];

    public $attachmentUpload = null;

    public array $tagForm = [
        'name' => '',
        'color' => null,
    ];

    public array $recipientForm = [
        'name' => '',
        'description' => '',
        'contact_ids' => [],
    ];

    public array $messageTemplateForm = [
        'name' => '',
        'subject' => '',
        'body' => '',
        'is_html' => false,
    ];

    public array $contactForm = [
        'name' => '',
        'email' => '',
        'type' => '',
        'meta_raw' => null,
    ];

    /** colecoes de lookup */
    public $users;

    public $tags;

    public $messageTemplates;

    public $recipientLists;

    public $contacts;

    public ?int $filterAssigned = null;

    public ?string $filterPriority = null;

    public ?int $filterTag = null;

    public string $search = '';

    public function mount(): void
    {
        $this->loadLookups();

        if ($this->boardId === null) {
            $this->boardId = Board::query()->orderBy('position')->value('id');
        }

        $this->loadBoard();
    }

    public function updatedBoardId($value): void
    {
        $this->loadBoard();
        $this->resetTaskForm();
    }

    public function updatedFilterAssigned(): void
    {
        $this->loadBoard();
    }

    public function updatedFilterPriority(): void
    {
        $this->loadBoard();
    }

    public function updatedFilterTag(): void
    {
        $this->loadBoard();
    }

    public function updatedSearch(): void
    {
        $this->loadBoard();
    }

    protected function loadLookups(): void
    {
        $this->users = User::orderBy('name')->get();
        $this->tags = Tag::orderBy('name')->get();
        $this->messageTemplates = MessageTemplate::orderBy('name')->get();
        $this->recipientLists = RecipientList::orderBy('name')->get();
        $this->contacts = Contact::orderBy('name')->get();
    }

    protected function findInitialStageId(): ?int
    {
        $initial = $this->stages?->firstWhere('is_initial', true);

        return $initial?->id ?? $this->stages?->first()?->id;
    }

    protected function resetTaskForm(?int $stageId = null): void
    {
        $this->taskForm = [
            'id' => null,
            'board_id' => $this->boardId,
            'stage_id' => $stageId ?? $this->findInitialStageId(),
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
            'name' => '',
            'slug' => '',
            'color' => null,
            'is_initial' => false,
            'is_final' => false,
            'freeze_sla' => false,
        ];
    }

    protected function resetBoardForm(?int $boardId = null): void
    {
        $this->boardForm = [
            'name' => '',
            'slug' => '',
            'description' => '',
            'is_active' => true,
        ];
        $this->editingBoardId = $boardId;
    }

    protected function loadBoard(): void
    {
        if (! $this->boardId) {
            $this->stages = collect();
            $this->tasksByStage = [];
            $this->rulesByStage = [];

            return;
        }

        $this->stages = Stage::query()
            ->where('board_id', $this->boardId)
            ->orderBy('position')
            ->get();

        $taskQuery = Task::query()
            ->where('board_id', $this->boardId)
            ->with(['assignedTo', 'tags'])
            ->withCount(['comments', 'attachments'])
            ->orderBy('stage_id')
            ->orderBy('position')
            ->orderBy('id');

        if ($this->filterPriority) {
            $taskQuery->where('priority', $this->filterPriority);
        }

        if ($this->filterAssigned) {
            $taskQuery->where('assigned_to_id', $this->filterAssigned);
        }

        if ($this->filterTag) {
            $taskQuery->whereHas('tags', fn ($query) => $query->where('tags.id', $this->filterTag));
        }

        if ($this->search) {
            $term = '%'.$this->search.'%';
            $taskQuery->where(function ($query) use ($term) {
                $query->where('title', 'like', $term)
                    ->orWhere('external_reference', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $groupedTasks = $taskQuery->get()->groupBy('stage_id');

        $this->tasksByStage = [];

        foreach ($this->stages as $stage) {
            $this->tasksByStage[$stage->id] = $groupedTasks->get($stage->id, collect());
        }

        $rules = NotificationRule::query()
            ->whereIn('stage_id', $this->stages->pluck('id'))
            ->with(['messageTemplate', 'recipientList'])
            ->orderBy('trigger')
            ->get()
            ->groupBy('stage_id');

        $this->rulesByStage = [];

        foreach ($this->stages as $stage) {
            $this->rulesByStage[$stage->id] = $rules->get($stage->id, collect());
        }
    }

    public function clearFilters(): void
    {
        $this->filterAssigned = null;
        $this->filterPriority = null;
        $this->filterTag = null;
        $this->search = '';

        $this->loadBoard();
    }

    public function startBoardForm(?int $boardId = null): void
    {
        $this->resetValidation();

        $this->resetBoardForm($boardId);
        $this->showBoardForm = true;

        if ($boardId) {
            $board = Board::findOrFail($boardId);

            $this->boardForm = [
                'name' => $board->name,
                'slug' => $board->slug,
                'description' => $board->description,
                'is_active' => (bool) $board->is_active,
            ];
        }
    }

    public function saveBoard(): void
    {
        $this->resetValidation();

        $data = validator($this->boardForm, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ])->validate();

        $slug = $data['slug'] ?: $this->makeUniqueSlug($data['name'], Board::class);

        $payload = [
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?: null,
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($this->editingBoardId) {
            $board = Board::findOrFail($this->editingBoardId);
            $board->update($payload);
            $this->boardId = $board->id;
        } else {
            $position = Board::max('position') ?? 0;
            $board = Board::create(array_merge($payload, [
                'position' => $position + 1,
            ]));
            $this->boardId = $board->id;
        }

        $this->showBoardForm = false;
        $this->loadLookups();
        $this->loadBoard();

        Notification::make()
            ->title('Board guardado')
            ->success()
            ->send();
    }

    public function closeBoardForm(): void
    {
        $this->resetValidation();
        $this->showBoardForm = false;
    }

    public function startStageForm(?int $stageId = null): void
    {
        $this->resetValidation();

        $this->resetStageForm($stageId);
        $this->stageForm['board_id'] = $this->boardId;
        $this->showStageForm = true;

        if ($stageId) {
            $stage = Stage::findOrFail($stageId);

            $this->stageForm = [
                'id' => $stage->id,
                'name' => $stage->name,
                'slug' => $stage->slug,
                'color' => $stage->color,
                'is_initial' => (bool) $stage->is_initial,
                'is_final' => (bool) $stage->is_final,
                'freeze_sla' => (bool) $stage->freeze_sla,
            ];
        }
    }

    public function saveStage(): void
    {
        if (! $this->boardId) {
            return;
        }

        $this->resetValidation();

        $data = validator($this->stageForm, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'is_initial' => ['boolean'],
            'is_final' => ['boolean'],
            'freeze_sla' => ['boolean'],
        ])->validate();

        $payload = [
            'board_id' => $this->boardId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?: $this->makeUniqueSlug($data['name'], Stage::class),
            'color' => $data['color'] ?: null,
            'is_initial' => $data['is_initial'] ?? false,
            'is_final' => $data['is_final'] ?? false,
            'freeze_sla' => $data['freeze_sla'] ?? false,
        ];

        if ($this->stageForm['id']) {
            $stage = Stage::findOrFail($this->stageForm['id']);
            $stage->update($payload);
        } else {
            $position = Stage::where('board_id', $this->boardId)->max('position') ?? 0;
            $payload['position'] = $position + 1;

            Stage::create($payload);
        }

        $this->showStageForm = false;
        $this->loadBoard();

        Notification::make()
            ->title('Estagio guardado')
            ->success()
            ->send();
    }

    public function closeStageForm(): void
    {
        $this->resetValidation();
        $this->showStageForm = false;
    }

    public function moveStage(int $stageId, string $direction): void
    {
        $stages = Stage::where('board_id', $this->boardId)
            ->orderBy('position')
            ->get();

        $index = $stages->search(fn (Stage $stage) => $stage->id === $stageId);

        if ($index === false) {
            return;
        }

        if ($direction === 'left' && $index > 0) {
            $swapWith = $stages[$index - 1];
        } elseif ($direction === 'right' && $index < $stages->count() - 1) {
            $swapWith = $stages[$index + 1];
        } else {
            return;
        }

        $current = $stages[$index];

        Stage::whereKey($current->id)->update(['position' => $swapWith->position]);
        Stage::whereKey($swapWith->id)->update(['position' => $current->position]);

        $this->loadBoard();
    }

    public function startRuleForm(int $stageId, ?int $ruleId = null): void
    {
        $this->resetValidation();

        $this->ruleForm = [
            'stage_id' => $stageId,
            'message_template_id' => null,
            'recipient_list_id' => null,
            'trigger' => 'on_enter_stage',
            'send_mode' => 'always',
            'cooldown_hours' => null,
            'also_send_to_assigned_user' => false,
            'is_active' => true,
        ];

        $this->editingRuleId = $ruleId;
        $this->showAutomationPanel = true;

        if ($ruleId) {
            $rule = NotificationRule::findOrFail($ruleId);
            $this->ruleForm = [
                'stage_id' => $rule->stage_id,
                'message_template_id' => $rule->message_template_id,
                'recipient_list_id' => $rule->recipient_list_id,
                'trigger' => $rule->trigger,
                'send_mode' => $rule->send_mode,
                'cooldown_hours' => $rule->cooldown_hours,
                'also_send_to_assigned_user' => (bool) $rule->also_send_to_assigned_user,
                'is_active' => (bool) $rule->is_active,
            ];
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

        if ($this->editingRuleId) {
            $rule = NotificationRule::findOrFail($this->editingRuleId);
            $rule->update($data);
        } else {
            NotificationRule::create($data);
        }

        $this->editingRuleId = null;
        $this->loadBoard();

        Notification::make()
            ->title('Regra de notificacao guardada')
            ->success()
            ->send();
    }

    public function closeAutomationPanel(): void
    {
        $this->resetValidation();
        $this->editingRuleId = null;
        $this->showAutomationPanel = false;
    }

    public function startCreateTask(?int $stageId = null): void
    {
        $this->resetValidation();

        $this->resetTaskForm($stageId);
        $this->showTaskForm = true;
        $this->showTaskDetail = false;
    }

    public function editTask(int $taskId): void
    {
        $this->resetValidation();

        $task = Task::with('tags')->findOrFail($taskId);

        $this->showTaskForm = true;
        $this->showTaskDetail = false;

        $this->fillTaskForm($task);
        logger()->info('kanban: editTask filled', $this->taskForm);

        $this->activeTaskId = $task->id;
        $this->dispatch('$refresh');
    }

    public function saveTask(): void
    {
        $data = validator($this->taskForm, [
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
                Notification::make()
                    ->title('Meta deve ser JSON valido')
                    ->danger()
                    ->send();

                return;
            }
        }

        $dueAt = $data['due_at'] ? Carbon::parse($data['due_at']) : null;

        if ($this->activeTaskId) {
            $task = Task::findOrFail($this->activeTaskId);
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
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'due_at' => $dueAt,
            'external_reference' => $data['external_reference'] ?? null,
            'meta' => $meta,
        ]);

        $task->save();
        $task->tags()->sync($data['tags'] ?? []);

        $this->activeTaskId = $task->id;
        $this->showTaskForm = false;

        $this->openTask($task->id);
        $this->loadBoard();

        Notification::make()
            ->title('Tarefa guardada')
            ->success()
            ->send();
    }

    public function closeTaskForm(): void
    {
        $this->resetValidation();
        $this->showTaskForm = false;
    }

    public function openTask(int $taskId): void
    {
        $this->resetValidation();

        $task = Task::with([
            'assignedTo',
            'stage',
            'board',
            'tags',
            'comments.user',
            'attachments',
            'notificationLogs.rule.stage',
        ])->findOrFail($taskId);

        $this->activeTaskId = $task->id;
        $this->fillTaskForm($task);

        $this->taskComments = $task->comments
            ->sortByDesc('created_at')
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'user' => $comment->user?->name,
                'is_internal' => (bool) $comment->is_internal,
                'created_at' => optional($comment->created_at)?->format('d/m H:i'),
            ])
            ->values()
            ->all();

        $this->taskAttachments = $task->attachments
            ->map(fn ($attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'url' => $attachment->url,
                'created_at' => optional($attachment->created_at)?->format('d/m H:i'),
            ])
            ->values()
            ->all();

        $this->taskLogs = $task->notificationLogs
            ->sortByDesc('sent_at')
            ->map(fn ($log) => [
                'id' => $log->id,
                'rule' => $log->rule?->id,
                'stage' => $log->rule?->stage?->name,
                'subject' => $log->subject,
                'to_email' => $log->to_email,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'sent_at' => optional($log->sent_at)?->format('d/m H:i'),
            ])
            ->values()
            ->all();

        $this->commentForm = ['body' => '', 'is_internal' => true];
        $this->attachmentUpload = null;

        $this->showTaskDetail = true;
        $this->showTaskForm = false;

        $this->loadBoard();
        $this->dispatch('$refresh');
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

        logger()->info('kanban: fillTaskForm', $this->taskForm);
    }

    public function closeTaskDetail(): void
    {
        $this->resetValidation();
        $this->showTaskDetail = false;
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

        $this->commentForm['body'] = '';

        $this->openTask($this->activeTaskId);
    }

    public function addAttachment(): void
    {
        if (! $this->activeTaskId || ! $this->attachmentUpload) {
            return;
        }

        validator(
            ['attachmentUpload' => $this->attachmentUpload],
            ['attachmentUpload' => ['file', 'max:10240']]
        )->validate();

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

        $this->openTask($this->activeTaskId);
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

        if ($this->activeTaskId === $taskId) {
            $this->openTask($taskId);
        }
    }

    public function detachTagFromTask(int $taskId, int $tagId): void
    {
        $task = Task::findOrFail($taskId);
        $task->tags()->detach($tagId);

        if ($this->activeTaskId === $taskId) {
            $this->openTask($taskId);
        }

        $this->loadBoard();
    }

    public function createTag(): void
    {
        $data = validator([
            'name' => $this->tagForm['name'] ?? '',
            'color' => $this->tagForm['color'] ?? null,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $slug = $this->makeUniqueSlug($data['name'], Tag::class);

        Tag::create([
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'] ?? null,
        ]);

        $this->tagForm = ['name' => '', 'color' => null];
        $this->loadLookups();

        Notification::make()
            ->title('Etiqueta criada')
            ->success()
            ->send();
    }

    public function saveRecipientList(): void
    {
        $data = validator($this->recipientForm, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'contact_ids' => ['array'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
        ])->validate();

        $list = RecipientList::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $list->contacts()->sync($data['contact_ids'] ?? []);

        $this->recipientForm = [
            'name' => '',
            'description' => '',
            'contact_ids' => [],
        ];

        $this->loadLookups();

        Notification::make()
            ->title('Lista de destinatarios criada')
            ->success()
            ->send();
    }

    public function saveMessageTemplate(): void
    {
        $data = validator($this->messageTemplateForm, [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_html' => ['boolean'],
        ])->validate();

        MessageTemplate::create($data);

        $this->messageTemplateForm = [
            'name' => '',
            'subject' => '',
            'body' => '',
            'is_html' => false,
        ];

        $this->loadLookups();

        Notification::make()
            ->title('Template de mensagem criado')
            ->success()
            ->send();
    }

    public function saveContact(): void
    {
        $data = validator($this->contactForm, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'type' => ['nullable', 'string', 'max:100'],
            'meta_raw' => ['nullable', 'string'],
        ])->validate();

        $meta = null;
        if (! empty($data['meta_raw'])) {
            $meta = json_decode($data['meta_raw'], true);

            if (! is_array($meta)) {
                Notification::make()
                    ->title('Meta do contacto deve ser JSON valido')
                    ->danger()
                    ->send();

                return;
            }
        }

        Contact::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'type' => $data['type'] ?: null,
            'meta' => $meta,
        ]);

        $this->contactForm = [
            'name' => '',
            'email' => '',
            'type' => '',
            'meta_raw' => null,
        ];

        $this->loadLookups();

        Notification::make()
            ->title('Contacto guardado')
            ->success()
            ->send();
    }

    protected function makeUniqueSlug(string $value, string $modelClass): string
    {
        $base = Str::slug($value) ?: Str::random(6);
        $slug = $base;
        $counter = 1;

        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass))) {
            $query = $modelClass::withTrashed();
        }

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function getBoardsProperty()
    {
        return Board::orderBy('position')->get();
    }
}
