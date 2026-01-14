<x-filament::page>
    <style>
        :root { --kb-bg:#0b1220; --kb-card:#0f172a; --kb-border:#1f2937; --kb-text:#e5e7eb; --kb-muted:#9ca3af; --kb-accent:#22d3ee; }
        .kb-page{display:flex;flex-direction:column;gap:14px;color:var(--kb-text);font-size:14px;}
        .kb-card{background:var(--kb-card);border:1px solid var(--kb-border);border-radius:12px;padding:14px;}
        .kb-row{display:flex;gap:8px;flex-wrap:wrap;}
        .kb-input{background:#0b1220;border:1px solid var(--kb-border);color:var(--kb-text);border-radius:8px;padding:8px 10px;min-height:34px;}
        .kb-btn{border:1px solid var(--kb-border);background:#111827;color:var(--kb-text);border-radius:8px;padding:8px 12px;font-size:12px;font-weight:600;cursor:pointer;}
        .kb-btn:hover{border-color:var(--kb-accent);}
        .kb-btn-primary{background:rgba(34,211,238,0.12);border-color:var(--kb-accent);color:#a5f3fc;}
        .kb-col-wrap{display:flex;gap:12px;overflow-x:auto;padding-bottom:10px;}
        .kb-col{min-width:260px;background:rgba(12,18,32,0.9);border:1px solid var(--kb-border);border-radius:10px;display:flex;flex-direction:column;}
        .kb-col-head{padding:10px 12px;border-bottom:1px solid var(--kb-border);display:flex;justify-content:space-between;align-items:center;gap:6px;}
        .kb-col-body{padding:10px;display:flex;flex-direction:column;gap:8px;}
        .kb-task{border:1px solid var(--kb-border);background:#0b1220;border-radius:10px;padding:10px;}

        .kb-modal{position:fixed;inset:0;background:rgba(3,7,18,0.75);display:flex;align-items:flex-start;justify-content:center;padding:24px;z-index:50;}
        .kb-modal-panel{width:100%;max-width:1200px;max-height:calc(100vh - 48px);overflow:auto;}
        .kb-form-grid{display:grid;gap:10px;}
        @media (min-width:900px){.cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}}
    </style>

    <div class="kb-page">
        <div class="kb-card">
            <div class="kb-row" style="justify-content:space-between;align-items:center;">
                <div class="kb-row">
                    <select wire:model.live="boardId" class="kb-input">
                        <option value="">-- Selecionar board --</option>
                        @foreach ($this->boards as $board)
                            <option value="{{ $board->id }}">{{ $board->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="startStageForm()" class="kb-btn">Novo estagio</button>
                    <button type="button" wire:click="startCreateTask()" class="kb-btn kb-btn-primary">Nova tarefa</button>
                </div>
            </div>
        </div>

        @if($showStageForm)
            <div class="kb-card">
                <div class="kb-row" style="justify-content:space-between;">
                    <strong>{{ $stageForm['id'] ? 'Editar estagio' : 'Novo estagio' }}</strong>
                    <button type="button" class="kb-btn" wire:click="closeForms">Fechar</button>
                </div>
                <form wire:submit.prevent="saveStage" class="kb-form-grid cols-3" style="margin-top:10px;">
                    <div>
                        <span class="kb-muted">Nome</span>
                        <input type="text" class="kb-input" wire:model="stageForm.name" required>
                    </div>
                    <div>
                        <span class="kb-muted">Cor</span>
                        <input type="color" class="kb-input" wire:model="stageForm.color" style="padding:4px;">
                    </div>
                    <label style="display:flex;gap:6px;align-items:center;font-size:12px;color:var(--kb-muted);">
                        <input type="checkbox" wire:model="stageForm.is_initial"> Inicial
                    </label>
                    <label style="display:flex;gap:6px;align-items:center;font-size:12px;color:var(--kb-muted);">
                        <input type="checkbox" wire:model="stageForm.is_final"> Final
                    </label>
                    <div style="grid-column:span 3;display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" class="kb-btn" wire:click="closeForms">Cancelar</button>
                        <button type="submit" class="kb-btn kb-btn-primary">Guardar estagio</button>
                    </div>
                </form>
            </div>
        @endif

        @if($showAutomationPanel)
            <div class="kb-card">
                <div class="kb-row" style="justify-content:space-between;">
                    <strong>{{ $ruleForm['id'] ? 'Editar regra' : 'Nova regra' }}</strong>
                    <button type="button" class="kb-btn" wire:click="closeForms">Fechar</button>
                </div>
                <form wire:submit.prevent="saveRule" class="kb-form-grid cols-3" style="margin-top:10px;">
                    <div>
                        <span class="kb-muted">Estagio</span>
                        <select class="kb-input" wire:model="ruleForm.stage_id">
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <span class="kb-muted">Template</span>
                        <select class="kb-input" wire:model="ruleForm.message_template_id">
                            <option value="">--</option>
                            @foreach ($messageTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <span class="kb-muted">Destinatarios</span>
                        <select class="kb-input" wire:model="ruleForm.recipient_list_id">
                            <option value="">--</option>
                            @foreach ($recipientLists as $list)
                                <option value="{{ $list->id }}">{{ $list->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <span class="kb-muted">Trigger</span>
                        <select class="kb-input" wire:model="ruleForm.trigger">
                            <option value="on_enter_stage">Ao entrar</option>
                            <option value="on_exit_stage">Ao sair</option>
                            <option value="on_task_update">Ao atualizar</option>
                        </select>
                    </div>
                    <div>
                        <span class="kb-muted">Modo</span>
                        <select class="kb-input" wire:model="ruleForm.send_mode">
                            <option value="always">Sempre</option>
                            <option value="first_time">Primeira vez</option>
                            <option value="cooldown">Cooldown</option>
                        </select>
                    </div>
                    <div>
                        <span class="kb-muted">Cooldown (h)</span>
                        <input type="number" class="kb-input" wire:model="ruleForm.cooldown_hours">
                    </div>
                    <label style="display:flex;gap:6px;align-items:center;font-size:12px;color:var(--kb-muted);">
                        <input type="checkbox" wire:model="ruleForm.also_send_to_assigned_user"> Enviar ao responsavel
                    </label>
                    <label style="display:flex;gap:6px;align-items:center;font-size:12px;color:var(--kb-muted);">
                        <input type="checkbox" wire:model="ruleForm.is_active"> Ativa
                    </label>
                    <div style="grid-column:span 3;display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" class="kb-btn" wire:click="closeForms">Cancelar</button>
                        <button type="submit" class="kb-btn kb-btn-primary">Guardar regra</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="kb-card">
            <div class="kb-col-wrap"
                 x-data="{
                    draggedTaskId: null,
                    draggedFromStageId: null,
                    dropStageId: null,
                    startDrag(id, stageId) { this.draggedTaskId = id; this.draggedFromStageId = stageId; },
                    endDrag() { this.draggedTaskId = null; this.draggedFromStageId = null; this.dropStageId = null; },
                    handleDrop(stageId) {
                        if (!this.draggedTaskId) return this.endDrag();
                        if (this.draggedFromStageId === stageId) return this.endDrag();
                        this.dropStageId = null;
                        $wire.moveTaskToStage(this.draggedTaskId, stageId);
                        this.endDrag();
                    },
                 }"
            >
                @foreach ($stages as $stage)
                    @php $tasks = $tasksByStage[$stage->id] ?? collect(); @endphp
                    <div class="kb-col"
                         wire:key="stage-{{ $stage->id }}"
                         @dragover.prevent="dropStageId = {{ $stage->id }}"
                         @drop.prevent="handleDrop({{ $stage->id }})"
                         x-bind:class="{ 'kb-task': dropStageId === {{ $stage->id }} }"
                    >
                        <div class="kb-col-head" style="border-color: {{ $stage->color ?? '#1f2937' }};">
                            <div>
                                <div style="font-weight:700;">{{ $stage->name }}</div>
                                <div class="kb-muted">{{ $tasks->count() }} tarefas</div>
                                <div class="kb-row" style="gap:4px;">
                                    @if($stage->is_initial)<span class="kb-badge">Inicial</span>@endif
                                    @if($stage->is_final)<span class="kb-badge">Final</span>@endif
                                </div>
                            </div>
                    <div class="kb-row" style="gap:6px;">
                        <button type="button" class="kb-btn" wire:click="startStageForm({{ $stage->id }})">Editar</button>
                        <button type="button" class="kb-btn kb-btn-primary" wire:click="startCreateTask({{ $stage->id }})">+ Tarefa</button>
                        <button type="button" class="kb-btn" wire:click="startRuleForm({{ $stage->id }}, null)">Regra</button>
                    </div>
                </div>
                        <div class="kb-col-body">
                            @forelse ($tasks as $task)
                                <div class="kb-task"
                                     wire:key="task-{{ $task->id }}"
                                     draggable="true"
                                     @dragstart="startDrag({{ $task->id }}, {{ $stage->id }})"
                                     @dragend="endDrag()"
                                >
                                    <div style="display:flex;justify-content:space-between;gap:8px;">
                                        <div style="font-weight:700;">#{{ $task->id }} - {{ $task->title }}</div>
                                        <span class="kb-badge">{{ strtoupper($task->priority) }}</span>
                                    </div>
                                    @php
                                        $metaBadges = $this->formatMetaBadges($task->meta);
                                    @endphp
                                    @if ($metaBadges)
                                        <div class="kb-row" style="gap:6px;margin-top:6px;flex-wrap:wrap;">
                                            @foreach ($metaBadges as $badge)
                                                <span class="kb-badge">{{ $badge }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="kb-muted" style="margin-top:4px;">
                                        @if($task->assignedTo) Respons+avel: {{ $task->assignedTo->name }} @endif
                                        @if($task->due_at) - Prazo: {{ $task->due_at->format('d/m H:i') }} @endif
                                    </div>
                                    <div class="kb-row" style="gap:6px;margin-top:6px;">                                        <button type="button" class="kb-btn" wire:click="openTaskDetail({{ $task->id }})">Detalhes</button>
                                    </div>
                                </div>
                            @empty
                                <div class="kb-muted">Sem tarefas neste estagio.</div>
                            @endforelse
                        </div>
                        @if(($rulesByStage[$stage->id] ?? collect())->count())
                            <div style="padding:8px 10px;border-top:1px solid var(--kb-border);">
                                <div class="kb-muted" style="margin-bottom:6px;">Regras</div>
                                @foreach ($rulesByStage[$stage->id] as $rule)
                                    <div class="kb-row" style="gap:6px;align-items:center;margin-bottom:6px;">
                                        <span class="kb-badge">{{ $rule->trigger }}</span>
                                        <span class="kb-badge">{{ $rule->send_mode }}</span>
                                        <button type="button" class="kb-btn" style="padding:4px 8px;font-size:11px;" wire:click="startRuleForm({{ $stage->id }}, {{ $rule->id }})">Editar</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @if($showTaskForm || $showTaskDetail)
            <div class="kb-modal" wire:click.self="closeForms">
                <div class="kb-modal-panel">
                    <div class="kb-card" wire:key="task-modal-{{ $activeTaskId ?? ($taskForm['id'] ?? 'new') }}">
                        <div class="kb-row" style="justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <div>
                                <div style="font-weight:700;font-size:16px;">
                                    @if($showTaskForm)
                                        {{ $taskForm['id'] ? 'Editar tarefa #'.$taskForm['id'] : 'Nova tarefa' }}
                                    @else
                                        Detalhe da tarefa #{{ $activeTaskId }}
                                    @endif
                                </div>
                                <div class="kb-muted" style="margin-top:2px;">Preenche os campos e guarda para atualizar o quadro.</div>
                            </div>
                            <div class="kb-row" style="gap:8px;">
                                <button type="button" class="kb-btn" wire:click="closeForms">Fechar</button>
                            </div>
                        </div>

                        @if($showTaskForm)
                            <form wire:submit.prevent="saveTask" style="display:flex;flex-direction:column;gap:12px;">
                                <div class="kb-row" style="gap:12px;flex-wrap:wrap;">
                                    <label style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Estagio</span>
                                        <select class="kb-input" wire:model="taskForm.stage_id">
                                            @foreach ($stages as $stage)
                                                <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Responsavel</span>
                                        <select class="kb-input" wire:model="taskForm.assigned_to_id">
                                            <option value="">--</option>
                                            @foreach ($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label style="flex:1;min-width:240px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Titulo</span>
                                        <input type="text" class="kb-input" wire:model="taskForm.title" required>
                                    </label>
                                </div>

                                <div class="kb-row" style="gap:12px;flex-wrap:wrap;">
                                    <label style="flex:1;min-width:180px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Prioridade</span>
                                        <select class="kb-input" wire:model="taskForm.priority">
                                            <option value="low">Low</option>
                                            <option value="normal">Normal</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </label>
                                    <label style="flex:1;min-width:200px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Prazo</span>
                                        <input type="datetime-local" class="kb-input" wire:model="taskForm.due_at">
                                    </label>
                                    <label style="flex:2;min-width:260px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Referencia externa</span>
                                        <input type="text" class="kb-input" wire:model="taskForm.external_reference" placeholder="Ref. CRM, ticket...">
                                    </label>
                                </div>

                                <div class="kb-row" style="gap:12px;flex-wrap:wrap;">
                                    <label style="flex:1;min-width:300px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Resumo rapido</span>
                                        <textarea class="kb-input" rows="3" wire:model="taskForm.description" placeholder="Resumo rapido ou primeira nota."></textarea>
                                    </label>
                                    <label style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:4px;">
                                        <span class="kb-muted">Etiquetas</span>
                                        <select class="kb-input" multiple wire:model="taskForm.tags" size="5">
                                            @foreach ($tags as $tag)
                                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <input type="hidden" wire:model="taskForm.meta_raw">
                                    <div style="flex:1;min-width:220px;display:flex;flex-direction:column;gap:6px;">
                                        <span class="kb-muted">Badges (preview)</span>
                                        @php
                                            $metaPreview = json_decode($taskForm['meta_raw'] ?? '', true);
                                        @endphp
                                        <div class="kb-row" style="gap:6px;flex-wrap:wrap;">
                                            @if (is_array($metaPreview))
                                                @forelse ($metaPreview as $key => $value)
                                                    @php
                                                        if (is_array($value)) {
                                                            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                                        } elseif (is_bool($value)) {
                                                            $value = $value ? 'true' : 'false';
                                                        } elseif ($value === null) {
                                                            $value = 'null';
                                                        }

                                                        $label = is_string($key) ? $key : (string) $key;
                                                        $badgeLabel = trim($label.': '.$value);
                                                    @endphp
                                                    <span class="kb-badge" style="display:flex;gap:6px;align-items:center;">
                                                        {{ $badgeLabel }}
                                                        <button type="button" class="kb-btn" style="padding:2px 6px;font-size:10px;" wire:click="removeMetaBadge('{{ $label }}')">x</button>
                                                    </span>
                                                @empty
                                                    <span class="kb-muted">Sem badges.</span>
                                                @endforelse
                                            @else
                                                <span class="kb-muted">JSON invalido.</span>
                                            @endif
                                        </div>
                                        <div class="kb-row" style="gap:6px;">
                                            <input type="text" class="kb-input" wire:model.defer="taskForm.meta_badge_key" placeholder="Chave">
                                            <input type="text" class="kb-input" wire:model.defer="taskForm.meta_badge_value" placeholder="Valor">
                                            <button type="button" class="kb-btn kb-btn-primary" wire:click="addMetaBadge">Adicionar</button>
                                        </div>
                                    </div>
                                </div>

                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
                                    <button type="button" class="kb-btn" wire:click="closeForms">Cancelar</button>
                                    <button type="submit" class="kb-btn kb-btn-primary">Guardar tarefa</button>
                                </div>
                            </form>
                        @endif

                        @if($showTaskDetail && $activeTaskId)
                            <div style="margin-top:16px;border-top:1px solid var(--kb-border);padding-top:16px;">
                                <div class="kb-row" style="margin-top:10px;gap:12px;flex-wrap:wrap;">
                                    <div style="flex:1;min-width:260px;">
                                        <div class="kb-muted" style="margin-bottom:6px;">Comentarios</div>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            @forelse ($taskComments as $comment)
                                                <div class="kb-task">
                                                    <div style="display:flex;justify-content:space-between;">
                                                        <strong>{{ $comment['user'] ?? '-' }}</strong>
                                                        <span class="kb-muted">{{ $comment['created_at'] }}</span>
                                                    </div>
                                                    <div style="margin-top:4px;">{{ $comment['body'] }}</div>
                                                    @if(!empty($comment['is_internal']))
                                                        <div class="kb-muted" style="margin-top:4px;">Interno</div>
                                                    @endif
                                                </div>
                                            @empty
                                                <div class="kb-muted">Sem comentarios.</div>
                                            @endforelse
                                        </div>
                                        <form wire:submit.prevent="addComment" class="kb-form-grid" style="margin-top:10px;">
                                            <textarea class="kb-input" rows="2" wire:model="commentForm.body" placeholder="Escreve um comentario..."></textarea>
                                            <label style="display:flex;gap:6px;align-items:center;font-size:12px;color:var(--kb-muted);">
                                                <input type="checkbox" wire:model="commentForm.is_internal"> Marcar como interno
                                            </label>
                                            <button type="submit" class="kb-btn kb-btn-primary">Adicionar comentario</button>
                                        </form>
                                    </div>
                                    <div style="flex:1;min-width:260px;">
                                        <div class="kb-muted" style="margin-bottom:6px;">Anexos</div>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            @forelse ($taskAttachments as $file)
                                                <div class="kb-task" style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                                                    <div>
                                                        <div style="font-weight:700;">{{ $file['original_name'] }}</div>
                                                        <div class="kb-muted">{{ $file['mime_type'] }} - {{ $file['created_at'] }}</div>
                                                    </div>
                                                    <a class="kb-btn kb-btn-primary" href="{{ $file['url'] }}" target="_blank">Abrir</a>
                                                </div>
                                            @empty
                                                <div class="kb-muted">Sem anexos.</div>
                                            @endforelse
                                        </div>
                                        <form wire:submit.prevent="addAttachment" class="kb-form-grid" style="margin-top:10px;">
                                            <input type="file" class="kb-input" wire:model="attachmentUpload">
                                            <button type="submit" class="kb-btn kb-btn-primary">Carregar anexo</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
</div>
</x-filament::page>
