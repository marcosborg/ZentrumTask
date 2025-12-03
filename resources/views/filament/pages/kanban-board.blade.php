<x-filament::page>
    <style>
        :root { --kb-bg:#0b1220; --kb-card:rgba(17,24,39,0.95); --kb-border:#263244; --kb-muted:#94a3b8; --kb-strong:#e5e7eb; --kb-accent:#fbbf24; --kb-green:#34d399; --kb-blue:#60a5fa; --kb-indigo:#818cf8; --kb-red:#f87171; }
        .kb-page{display:flex;flex-direction:column;gap:16px;color:var(--kb-strong);font-size:14px;} .kb-card{background:var(--kb-card);border:1px solid var(--kb-border);border-radius:12px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,0.3);} .kb-title{font-size:24px;font-weight:700;margin:0;} .kb-sub{color:var(--kb-muted);margin-top:4px;} .kb-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;} .kb-input{background:var(--kb-bg);border:1px solid var(--kb-border);color:var(--kb-strong);border-radius:8px;padding:8px 10px;min-height:34px;} .kb-input:focus{outline:1px solid var(--kb-accent);} .kb-btn{border:1px solid var(--kb-border);background:#1f2937;color:var(--kb-strong);border-radius:8px;padding:8px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:0.15s ease;} .kb-btn:hover{border-color:var(--kb-accent);} .kb-btn-primary{border-color:var(--kb-accent);background:rgba(251,191,36,0.12);color:#fef3c7;} .kb-btn-green{border-color:rgba(52,211,153,0.4);background:rgba(52,211,153,0.12);color:#bbf7d0;} .kb-btn-indigo{border-color:rgba(129,140,248,0.5);background:rgba(129,140,248,0.12);color:#c7d2fe;} .kb-btn-full{width:100%;text-align:center;} .kb-label{font-size:12px;color:var(--kb-muted);display:block;margin-bottom:4px;} .kb-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:10px;background:#111827;border:1px solid var(--kb-border);color:var(--kb-strong);font-size:12px;} .kb-badge{display:inline-block;padding:4px 8px;border-radius:8px;background:#111827;border:1px solid var(--kb-border);font-size:11px;text-transform:uppercase;letter-spacing:0.02em;} .kb-cols{display:flex;gap:14px;padding-bottom:10px;min-width:max-content;} .kb-col{background:rgba(12,20,32,0.9);border:1px solid var(--kb-border);border-radius:12px;width:280px;display:flex;flex-direction:column;position:relative;} .kb-col-head{padding:12px 14px;border-bottom:1px solid var(--kb-border);display:flex;justify-content:space-between;gap:8px;} .kb-col-body{padding:12px;display:flex;flex-direction:column;gap:10px;overflow-y:auto;max-height:70vh;min-height:160px;} .kb-col-footer{padding:10px 12px;border-top:1px solid var(--kb-border);color:var(--kb-muted);font-size:12px;} .kb-task{border:1px solid var(--kb-border);background:#0f172a;border-radius:10px;padding:10px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.02);} .kb-task-title{font-size:13px;font-weight:700;margin:0;} .kb-task-meta{color:var(--kb-muted);font-size:12px;display:flex;gap:12px;flex-wrap:wrap;} .kb-small{font-size:12px;color:var(--kb-muted);} .kb-form-grid{display:grid;gap:10px;} @media (min-width:900px){.kb-form-grid.cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}.kb-form-grid.cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}} textarea.kb-input{min-height:70px;} .kb-tag-chip{border-radius:8px;padding:4px 8px;font-size:11px;color:#0f172a;background:#e5e7eb;} .kb-section-title{font-size:12px;font-weight:700;color:var(--kb-strong);text-transform:uppercase;letter-spacing:0.04em;} .kb-divider{border-top:1px solid var(--kb-border);margin:10px 0;} .kb-drop-target{border:1px dashed var(--kb-accent);background:rgba(251,191,36,0.05);} .kb-task-dragging{opacity:0.6;transform:scale(0.99);}
    </style>

    <div class="kb-page">
        <div class="kb-card">
            <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:space-between;">
                <div style="display:flex;flex-direction:column;gap:8px;min-width:260px;">
                    <div class="kb-section-title" style="color:#fcd34d;">Kanban</div>
                    <h1 class="kb-title">Gestor de tarefas</h1>
                    <p class="kb-sub">Centraliza tarefas, configuracoes, notificacoes e contactos sem sair do quadro.</p>
                    <div class="kb-row">
                        <select wire:model="boardId" class="kb-input">
                            <option value="">-- Selecionar board --</option>
                            @foreach ($this->boards as $board)
                                <option value="{{ $board->id }}">{{ $board->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="startBoardForm(@js($boardId))" class="kb-btn">Gerir board</button>
                        <button type="button" wire:click="startBoardForm(null)" class="kb-btn kb-btn-primary">Novo board</button>
                        <button type="button" wire:click="startStageForm()" class="kb-btn">Novo estagio</button>
                        <button type="button" wire:click="startCreateTask()" class="kb-btn kb-btn-green">Nova tarefa</button>
                        <button type="button" wire:click="$toggle('showAutomationPanel')" class="kb-btn kb-btn-indigo">Automatizar</button>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;min-width:260px;">
                    <div class="kb-row">
                        <input type="text" wire:model.debounce.500ms="search" placeholder="Procurar titulo, referencia ou descricao" class="kb-input" style="width:220px;">
                        <select wire:model="filterPriority" class="kb-input">
                            <option value="">Prioridade</option>
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <select wire:model="filterAssigned" class="kb-input">
                            <option value="">Responsavel</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="filterTag" class="kb-input">
                            <option value="">Etiqueta</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="button" wire:click="clearFilters" class="kb-btn">Limpar filtros</button>
                    </div>
                </div>
            </div>
        </div>

        @if (! $boardId)
            <div class="kb-card">
                <div class="kb-small">Nao ha nenhum board selecionado. Cria um board ou escolhe um existente.</div>
            </div>
        @else
            @if($showBoardForm)
                <div class="kb-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="kb-section-title">Board</div>
                        <button type="button" wire:click="$set('showBoardForm', false)" class="kb-btn">Fechar</button>
                    </div>
                    <form class="kb-form-grid cols-2" wire:submit.prevent="saveBoard" style="margin-top:10px;">
                        <div>
                            <span class="kb-label">Nome</span>
                            <input type="text" wire:model.defer="boardForm.name" class="kb-input" required>
                        </div>
                        <div>
                            <span class="kb-label">Slug</span>
                            <input type="text" wire:model.defer="boardForm.slug" class="kb-input">
                        </div>
                        <div style="grid-column: span 2;">
                            <span class="kb-label">Descricao</span>
                            <textarea wire:model.defer="boardForm.description" class="kb-input" rows="2"></textarea>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--kb-muted);">
                            <input type="checkbox" wire:model="boardForm.is_active"> Ativo
                        </label>
                        <div style="grid-column: span 2;display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" wire:click="$set('showBoardForm', false)" class="kb-btn">Cancelar</button>
                            <button type="submit" class="kb-btn kb-btn-primary">Guardar board</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($showStageForm)
                <div class="kb-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="kb-section-title">Estagio</div>
                        <button type="button" wire:click="$set('showStageForm', false)" class="kb-btn">Fechar</button>
                    </div>
                    <form class="kb-form-grid cols-3" wire:submit.prevent="saveStage" style="margin-top:10px;">
                        <div style="grid-column: span 2;">
                            <span class="kb-label">Nome</span>
                            <input type="text" wire:model.defer="stageForm.name" class="kb-input" required>
                        </div>
                        <div>
                            <span class="kb-label">Slug</span>
                            <input type="text" wire:model.defer="stageForm.slug" class="kb-input">
                        </div>
                        <div>
                            <span class="kb-label">Cor</span>
                            <input type="color" wire:model.defer="stageForm.color" class="kb-input" style="padding:3px;">
                        </div>
                        <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                            <input type="checkbox" wire:model="stageForm.is_initial"> Estagio inicial
                        </label>
                        <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                            <input type="checkbox" wire:model="stageForm.is_final"> Estagio final
                        </label>
                        <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                            <input type="checkbox" wire:model="stageForm.freeze_sla"> Congelar SLA
                        </label>
                        <div style="grid-column: span 3;display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" wire:click="$set('showStageForm', false)" class="kb-btn">Cancelar</button>
                            <button type="submit" class="kb-btn kb-btn-primary">Guardar estagio</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($showTaskForm)
                <div class="kb-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="kb-section-title">@if($taskForm['id']) Editar tarefa #{{ $taskForm['id'] }} @else Nova tarefa @endif</div>
                        <button type="button" wire:click="$set('showTaskForm', false)" class="kb-btn">Fechar</button>
                    </div>
                    <form class="kb-form-grid cols-3" wire:submit.prevent="saveTask" style="margin-top:10px;">
                        <div>
                            <span class="kb-label">Estagio</span>
                            <select wire:model="taskForm.stage_id" class="kb-input">
                                @foreach ($stages as $stage)
                                    <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <span class="kb-label">Responsavel</span>
                            <select wire:model="taskForm.assigned_to_id" class="kb-input">
                                <option value="">--</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <span class="kb-label">Prioridade</span>
                            <select wire:model="taskForm.priority" class="kb-input">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div style="grid-column: span 2;">
                            <span class="kb-label">Titulo</span>
                            <input type="text" wire:model.defer="taskForm.title" class="kb-input" required>
                        </div>
                        <div>
                            <span class="kb-label">Prazo</span>
                            <input type="datetime-local" wire:model.defer="taskForm.due_at" class="kb-input">
                        </div>
                        <div style="grid-column: span 3;">
                            <span class="kb-label">Descricao</span>
                            <textarea wire:model.defer="taskForm.description" rows="2" class="kb-input"></textarea>
                        </div>
                        <div>
                            <span class="kb-label">Referencia externa</span>
                            <input type="text" wire:model.defer="taskForm.external_reference" class="kb-input">
                        </div>
                        <div>
                            <span class="kb-label">Etiquetas</span>
                            <select wire:model="taskForm.tags" multiple class="kb-input">
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="grid-column: span 3;">
                            <span class="kb-label">Meta (JSON)</span>
                            <textarea wire:model.defer="taskForm.meta_raw" rows="2" class="kb-input" placeholder='{"custom":"valor"}'></textarea>
                        </div>
                        <div style="grid-column: span 3;display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" wire:click="$set('showTaskForm', false)" class="kb-btn">Cancelar</button>
                            <button type="submit" class="kb-btn kb-btn-green">Guardar tarefa</button>
                        </div>
                    </form>
                </div>
            @endif

            @if($showAutomationPanel)
                <div class="kb-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div class="kb-section-title">Automatizacoes e comunicacao</div>
                        <button type="button" wire:click="$set('showAutomationPanel', false)" class="kb-btn">Fechar</button>
                    </div>
                    <div style="display:grid;gap:14px;grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));margin-top:12px;">
                        <div class="kb-card" style="background: rgba(17,24,39,0.7); border-style:dashed;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div class="kb-section-title" style="color: var(--kb-indigo);">Regra de notificacao</div>
                                <button type="button" wire:click="startRuleForm({{ $ruleForm['stage_id'] ?? ($stages->first()->id ?? 0) }}, null)" class="kb-btn" style="font-size:11px;">Limpar</button>
                            </div>
                            <form class="kb-form-grid cols-2" wire:submit.prevent="saveRule" style="margin-top:10px;">
                                <div>
                                    <span class="kb-label">Estagio</span>
                                    <select wire:model="ruleForm.stage_id" class="kb-input">
                                        @foreach ($stages as $stage)
                                            <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <span class="kb-label">Template</span>
                                    <select wire:model="ruleForm.message_template_id" class="kb-input">
                                        <option value="">--</option>
                                        @foreach ($messageTemplates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <span class="kb-label">Lista de destinatarios</span>
                                    <select wire:model="ruleForm.recipient_list_id" class="kb-input">
                                        <option value="">--</option>
                                        @foreach ($recipientLists as $list)
                                            <option value="{{ $list->id }}">{{ $list->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <span class="kb-label">Trigger</span>
                                    <select wire:model="ruleForm.trigger" class="kb-input">
                                        <option value="on_enter_stage">Ao entrar no estagio</option>
                                        <option value="on_exit_stage">Ao sair do estagio</option>
                                        <option value="on_task_update">Ao atualizar tarefa</option>
                                    </select>
                                </div>
                                <div>
                                    <span class="kb-label">Modo de envio</span>
                                    <select wire:model="ruleForm.send_mode" class="kb-input">
                                        <option value="always">Sempre</option>
                                        <option value="first_time">So primeira vez</option>
                                        <option value="cooldown">Com cooldown</option>
                                    </select>
                                </div>
                                <div>
                                    <span class="kb-label">Cooldown (horas)</span>
                                    <input type="number" min="1" wire:model.defer="ruleForm.cooldown_hours" class="kb-input" placeholder="ex: 4">
                                </div>
                                <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                                    <input type="checkbox" wire:model="ruleForm.also_send_to_assigned_user"> Enviar tambem ao responsavel
                                </label>
                                <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                                    <input type="checkbox" wire:model="ruleForm.is_active"> Regra ativa
                                </label>
                                <div style="grid-column: span 2;display:flex;justify-content:flex-end;gap:8px;">
                                    <button type="submit" class="kb-btn kb-btn-indigo">Guardar regra</button>
                                </div>
                            </form>

                            @if(!empty($ruleForm['stage_id']) && ($rulesByStage[$ruleForm['stage_id']] ?? collect())->count())
                                <div class="kb-divider"></div>
                                <div class="kb-section-title" style="margin-bottom:6px;">Regras existentes no estagio</div>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    @foreach ($rulesByStage[$ruleForm['stage_id']] as $rule)
                                        <div class="kb-chip" style="justify-content:space-between;">
                                            <div style="display:flex;flex-direction:column;gap:4px;">
                                                <strong>{{ $rule->messageTemplate?->name ?? 'Template' }}</strong>
                                                <div class="kb-task-meta">
                                                    <span class="kb-badge">Trigger: {{ $rule->trigger }}</span>
                                                    <span class="kb-badge">Modo: {{ $rule->send_mode }}</span>
                                                    @if($rule->cooldown_hours)
                                                        <span class="kb-badge">Cooldown: {{ $rule->cooldown_hours }}h</span>
                                                    @endif
                                                    @if($rule->recipientList)
                                                        <span class="kb-badge">Lista: {{ $rule->recipientList->name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <button type="button" wire:click="startRuleForm({{ $rule->stage_id }}, {{ $rule->id }})" class="kb-btn" style="font-size:11px;">Editar</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="kb-card" style="background: rgba(17,24,39,0.7); border-style:dashed;">
                            <div class="kb-section-title">Template rapido</div>
                            <form class="kb-form-grid" wire:submit.prevent="saveMessageTemplate" style="margin-top:8px;">
                                <input type="text" wire:model.defer="messageTemplateForm.name" class="kb-input" placeholder="Nome">
                                <input type="text" wire:model.defer="messageTemplateForm.subject" class="kb-input" placeholder="Assunto">
                                <textarea wire:model.defer="messageTemplateForm.body" rows="3" class="kb-input" placeholder="Corpo da mensagem"></textarea>
                                <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                                    <input type="checkbox" wire:model="messageTemplateForm.is_html"> Enviar como HTML
                                </label>
                                <button type="submit" class="kb-btn kb-btn-indigo kb-btn-full">Guardar template</button>
                            </form>
                        </div>

                        <div class="kb-card" style="background: rgba(17,24,39,0.7); border-style:dashed;">
                            <div class="kb-section-title">Lista de destinatarios</div>
                            <form class="kb-form-grid" wire:submit.prevent="saveRecipientList" style="margin-top:8px;">
                                <input type="text" wire:model.defer="recipientForm.name" class="kb-input" placeholder="Nome">
                                <textarea wire:model.defer="recipientForm.description" rows="2" class="kb-input" placeholder="Descricao"></textarea>
                                <select wire:model="recipientForm.contact_ids" multiple class="kb-input">
                                    @foreach ($contacts as $contact)
                                        <option value="{{ $contact->id }}">{{ $contact->name }} ({{ $contact->email }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="kb-btn kb-btn-indigo kb-btn-full">Guardar lista</button>
                            </form>
                        </div>

                        <div class="kb-card" style="background: rgba(17,24,39,0.7); border-style:dashed;">
                            <div class="kb-section-title">Contacto rapido</div>
                            <form class="kb-form-grid" wire:submit.prevent="saveContact" style="margin-top:8px;">
                                <input type="text" wire:model.defer="contactForm.name" class="kb-input" placeholder="Nome">
                                <input type="email" wire:model.defer="contactForm.email" class="kb-input" placeholder="Email">
                                <input type="text" wire:model.defer="contactForm.type" class="kb-input" placeholder="Tipo (cliente, parceiro...)">
                                <textarea wire:model.defer="contactForm.meta_raw" rows="2" class="kb-input" placeholder='{"area":"marketing"}'></textarea>
                                <button type="submit" class="kb-btn kb-btn-indigo kb-btn-full">Guardar contacto</button>
                            </form>
                        </div>

                        <div class="kb-card" style="background: rgba(17,24,39,0.7); border-style:dashed;">
                            <div class="kb-section-title">Etiqueta rapida</div>
                            <form class="kb-form-grid" wire:submit.prevent="createTag" style="margin-top:8px;">
                                <input type="text" wire:model.defer="tagForm.name" class="kb-input" placeholder="Nome">
                                <input type="color" wire:model.defer="tagForm.color" class="kb-input" style="padding:3px;">
                                <button type="submit" class="kb-btn kb-btn-green kb-btn-full">Criar etiqueta</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if ($stages->isEmpty())
                <div class="kb-card">
                    <div class="kb-small">Este board ainda nao tem estagios configurados.</div>
                </div>
            @else
                <div
                    class="kb-card"
                    style="overflow-x:auto;"
                    x-data="{
                        draggedTaskId: null,
                        draggedFromStageId: null,
                        dropStageId: null,
                        startDrag(taskId, stageId) {
                            this.draggedTaskId = taskId;
                            this.draggedFromStageId = stageId;
                        },
                        endDrag() {
                            this.draggedTaskId = null;
                            this.draggedFromStageId = null;
                            this.dropStageId = null;
                        },
                        handleDrop(stageId) {
                            if (! this.draggedTaskId) {
                                this.endDrag();
                                return;
                            }

                            if (this.draggedFromStageId === stageId) {
                                this.endDrag();
                                return;
                            }

                            this.dropStageId = null;
                            $wire.moveTaskToStage(this.draggedTaskId, stageId);
                            this.endDrag();
                        },
                    }"
                >
                    <div class="kb-small" style="margin-bottom:8px;">Arrasta e larga as tarefas para mudar de estagio.</div>
                    <div class="kb-cols">
                        @foreach ($stages as $stage)
                            @php
                                $prevStage = $stages->where('position', '<', $stage->position)->sortByDesc('position')->first();
                                $nextStage = $stages->where('position', '>', $stage->position)->sortBy('position')->first();
                            @endphp
                            <div
                                class="kb-col"
                                wire:key="stage-{{ $stage->id }}"
                                @dragover.prevent="dropStageId = {{ $stage->id }}"
                                @dragenter.prevent="dropStageId = {{ $stage->id }}"
                                @dragleave="dropStageId = dropStageId === {{ $stage->id }} ? null : dropStageId"
                                @drop.prevent="handleDrop({{ $stage->id }})"
                                x-bind:class="{ 'kb-drop-target': dropStageId === {{ $stage->id }} }"
                            >
                                <div class="kb-col-head" style="border-color: {{ $stage->color ?? '#334155' }};">
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <div style="font-weight:700;">{{ $stage->name }}</div>
                                        <div class="kb-small">{{ $tasksByStage[$stage->id]->count() }} tarefas</div>
                                        <div class="kb-row" style="gap:6px;">
                                            @if($stage->is_initial)
                                                <span class="kb-badge" style="background:rgba(52,211,153,0.1); border-color:rgba(52,211,153,0.4); color:#a7f3d0;">Inicial</span>
                                            @endif
                                            @if($stage->is_final)
                                                <span class="kb-badge" style="background:rgba(96,165,250,0.1); border-color:rgba(96,165,250,0.4); color:#bfdbfe;">Final</span>
                                            @endif
                                            @if($stage->freeze_sla)
                                                <span class="kb-badge" style="background:rgba(251,191,36,0.1); border-color:rgba(251,191,36,0.4); color:#fef3c7;">SLA freeze</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                                        <div class="kb-row" style="gap:6px;">
                                            @if($prevStage)
                                                <button wire:click="moveStage({{ $stage->id }}, 'left')" class="kb-btn" style="padding:6px 8px;">&#8249;</button>
                                            @endif
                                            @if($nextStage)
                                                <button wire:click="moveStage({{ $stage->id }}, 'right')" class="kb-btn" style="padding:6px 8px;">&#8250;</button>
                                            @endif
                                        </div>
                                        <div class="kb-row" style="gap:6px;">
                                            <button wire:click="startStageForm({{ $stage->id }})" class="kb-btn" style="padding:6px 10px;">Editar</button>
                                            <button wire:click="startRuleForm({{ $stage->id }}, null)" class="kb-btn kb-btn-indigo" style="padding:6px 10px;">Regras</button>
                                        </div>
                                        <button wire:click="startCreateTask({{ $stage->id }})" class="kb-btn kb-btn-green" style="padding:6px 10px;">+ Tarefa</button>
                                    </div>
                                </div>

                                <div class="kb-col-body">
                                    @forelse ($tasksByStage[$stage->id] as $task)
                                        <div
                                            class="kb-task"
                                            wire:key="task-{{ $task->id }}"
                                            draggable="true"
                                            @dragstart="startDrag({{ $task->id }}, {{ $stage->id }})"
                                            @dragend="endDrag()"
                                            x-bind:class="{ 'kb-task-dragging': draggedTaskId === {{ $task->id }} }"
                                        >
                                            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                                                <div style="display:flex;flex-direction:column;gap:6px;">
                                                    <div class="kb-task-title">#{{ $task->id }} - {{ $task->title }}</div>
                                                    <div class="kb-row" style="gap:6px;">
                                                        @foreach ($task->tags as $tag)
                                                            <span class="kb-tag-chip" style="background: {{ $tag->color ?? '#e5e7eb' }};">{{ $tag->name }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <span class="kb-badge">{{ $task->priority }}</span>
                                            </div>
                                            <div class="kb-task-meta" style="margin-top:4px;">
                                                @if($task->assignedTo)
                                                    <span>Responsavel: <strong>{{ $task->assignedTo->name }}</strong></span>
                                                @endif
                                                @if($task->due_at)
                                                    <span>Prazo: {{ $task->due_at->format('d/m H:i') }}</span>
                                                @endif
                                                @if($task->external_reference)
                                                    <span>Ref: {{ $task->external_reference }}</span>
                                                @endif
                                                <span>Comentarios: {{ $task->comments_count }}</span>
                                                <span>Anexos: {{ $task->attachments_count }}</span>
                                            </div>
                                            <div class="kb-row" style="margin-top:8px;gap:6px;">
                                                <button wire:click="openTask({{ $task->id }})" class="kb-btn" style="padding:6px 10px;">Detalhes</button>
                                                <button wire:click="editTask({{ $task->id }})" class="kb-btn" style="padding:6px 10px;">Editar</button>
                                            </div>
                                            <div class="kb-small" style="margin-top:4px;">Arrasta para mover para outro estagio.</div>
                                        </div>
                                    @empty
                                        <div class="kb-small" style="font-style:italic;">Sem tarefas neste estagio.</div>
                                    @endforelse
                                </div>

                                <div class="kb-col-footer">
                                    @if(($rulesByStage[$stage->id] ?? collect())->count())
                                        <div style="display:flex;flex-direction:column;gap:6px;">
                                            @foreach ($rulesByStage[$stage->id] as $rule)
                                                <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
                                                    <div class="kb-task-meta" style="gap:6px;">
                                                        <span class="kb-badge">Trigger: {{ $rule->trigger }}</span>
                                                        <span class="kb-badge">Modo: {{ $rule->send_mode }}</span>
                                                        <span class="kb-badge">{{ $rule->messageTemplate?->name }}</span>
                                                    </div>
                                                    <button wire:click="startRuleForm({{ $stage->id }}, {{ $rule->id }})" class="kb-btn kb-btn-indigo" style="padding:4px 8px;font-size:10px;">Editar</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        Sem regras de notificacao.
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($showTaskDetail && $activeTaskId)
                <div style="display:grid;gap:14px;grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                    <div class="kb-card" style="grid-column: span 2;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div class="kb-section-title">Detalhe da tarefa #{{ $taskForm['id'] }}</div>
                            <button type="button" wire:click="$set('showTaskDetail', false)" class="kb-btn">Fechar</button>
                        </div>
                        <form class="kb-form-grid cols-3" wire:submit.prevent="saveTask" style="margin-top:10px;">
                            <div>
                                <span class="kb-label">Estagio</span>
                                <select wire:model="taskForm.stage_id" class="kb-input">
                                    @foreach ($stages as $stage)
                                        <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <span class="kb-label">Responsavel</span>
                                <select wire:model="taskForm.assigned_to_id" class="kb-input">
                                    <option value="">--</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <span class="kb-label">Prioridade</span>
                                <select wire:model="taskForm.priority" class="kb-input">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div style="grid-column: span 2;">
                                <span class="kb-label">Titulo</span>
                                <input type="text" wire:model.defer="taskForm.title" class="kb-input">
                            </div>
                            <div>
                                <span class="kb-label">Prazo</span>
                                <input type="datetime-local" wire:model.defer="taskForm.due_at" class="kb-input">
                            </div>
                            <div>
                                <span class="kb-label">Etiquetas</span>
                                <select wire:model="taskForm.tags" multiple class="kb-input">
                                    @foreach ($tags as $tag)
                                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <span class="kb-label">Referencia externa</span>
                                <input type="text" wire:model.defer="taskForm.external_reference" class="kb-input">
                            </div>
                            <div style="grid-column: span 3;">
                                <span class="kb-label">Descricao</span>
                                <textarea wire:model.defer="taskForm.description" rows="3" class="kb-input"></textarea>
                            </div>
                            <div style="grid-column: span 3;">
                                <span class="kb-label">Meta (JSON)</span>
                                <textarea wire:model.defer="taskForm.meta_raw" rows="2" class="kb-input"></textarea>
                            </div>
                            <div style="grid-column: span 3;display:flex;justify-content:flex-end;">
                                <button type="submit" class="kb-btn kb-btn-green">Atualizar tarefa</button>
                            </div>
                        </form>
                    </div>

                    <div class="kb-card">
                        <div class="kb-section-title">Comentarios</div>
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px;">
                            @forelse ($taskComments as $comment)
                                <div class="kb-task" style="padding:8px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <strong>{{ $comment['user'] ?? 'Sem utilizador' }}</strong>
                                        <span class="kb-small">{{ $comment['created_at'] }}</span>
                                    </div>
                                    <div style="margin-top:4px;">{{ $comment['body'] }}</div>
                                    @if(!empty($comment['is_internal']))
                                        <div class="kb-small" style="color:#fcd34d;margin-top:4px;">Interno</div>
                                    @endif
                                </div>
                            @empty
                                <div class="kb-small">Sem comentarios.</div>
                            @endforelse
                        </div>
                        <form class="kb-form-grid" wire:submit.prevent="addComment" style="margin-top:10px;">
                            <textarea wire:model.defer="commentForm.body" rows="2" class="kb-input" placeholder="Escreve um comentario..."></textarea>
                            <label style="display:flex;gap:8px;align-items:center;font-size:12px;color:var(--kb-muted);">
                                <input type="checkbox" wire:model="commentForm.is_internal"> Marcar como interno
                            </label>
                            <button type="submit" class="kb-btn kb-btn-full">Adicionar comentario</button>
                        </form>
                    </div>

                    <div class="kb-card">
                        <div class="kb-section-title">Anexos</div>
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px;">
                            @forelse ($taskAttachments as $file)
                                <div class="kb-task" style="padding:8px;display:flex;justify-content:space-between;gap:8px;align-items:center;">
                                    <div>
                                        <div style="font-weight:700;">{{ $file['original_name'] ?? 'Anexo' }}</div>
                                        <div class="kb-small">{{ $file['mime_type'] }} - {{ $file['created_at'] }}</div>
                                    </div>
                                    <a href="{{ $file['url'] }}" target="_blank" class="kb-btn kb-btn-green" style="padding:6px 10px;">Abrir</a>
                                </div>
                            @empty
                                <div class="kb-small">Sem anexos.</div>
                            @endforelse
                        </div>
                        <form class="kb-form-grid" wire:submit.prevent="addAttachment" style="margin-top:10px;">
                            <input type="file" wire:model="attachmentUpload" class="kb-input">
                            <button type="submit" class="kb-btn kb-btn-full">Carregar anexo</button>
                        </form>
                    </div>

                    <div class="kb-card">
                        <div class="kb-section-title">Logs de notificacao</div>
                        <div style="margin-top:8px;display:flex;flex-direction:column;gap:8px;">
                            @forelse ($taskLogs as $log)
                                <div class="kb-task" style="padding:8px;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <strong>{{ $log['subject'] ?? 'Sem assunto' }}</strong>
                                        <span class="kb-badge">{{ $log['status'] }}</span>
                                    </div>
                                    <div class="kb-small">Para: {{ $log['to_email'] }} @if($log['stage']) - Estagio: {{ $log['stage'] }} @endif</div>
                                    <div class="kb-small">{{ $log['sent_at'] ?: 'Sem data' }}</div>
                                    @if($log['error_message'])
                                        <div class="kb-small" style="color:var(--kb-red);">{{ $log['error_message'] }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="kb-small">Sem historico de envio.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament::page>
