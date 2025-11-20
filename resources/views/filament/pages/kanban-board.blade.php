<x-filament::page>
    <div class="flex items-center justify-between mb-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-semibold">
                Gestor de tarefas
            </h1>

            <div class="flex items-center gap-2 text-sm">
                <span class="font-medium">Kanban</span>

                <select
                    wire:model="boardId"
                    class="border rounded px-2 py-1 text-xs"
                >
                    <option value="">-- Selecionar board --</option>
                    @foreach ($this->boards as $board)
                        <option value="{{ $board->id }}">{{ $board->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if (! $boardId)
        <p class="text-sm text-gray-400">
            Não há nenhum board selecionado. Cria um board ou escolhe um da lista.
        </p>
    @else
        @if ($stages->isEmpty())
            <p class="text-sm text-gray-400">
                Este board ainda não tem estádios configurados.
            </p>
        @else
            {{-- WRAPPER DAS COLUNAS --}}
            <div style="width: 100%; overflow-x: auto;">
                <div style="display: flex; gap: 1rem; min-width: max-content;">
                    @foreach ($stages as $stage)
                        {{-- CADA COLUNA --}}
                        <div
                            style="
                                background-color: rgba(15,23,42,0.6);
                                border: 1px solid rgb(55,65,81);
                                border-radius: 0.5rem;
                                width: 18rem;
                                max-height: 80vh;
                                display: flex;
                                flex-direction: column;
                            "
                        >
                            {{-- HEADER DA COLUNA --}}
                            <div
                                style="
                                    padding: 0.5rem 0.75rem;
                                    border-bottom: 2px solid {{ $stage->color ?? 'rgb(55,65,81)' }};
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                "
                            >
                                <div>
                                    <div style="font-size: 0.9rem; font-weight: 600;">
                                        {{ $stage->name }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">
                                        {{ $tasksByStage[$stage->id]->count() }} tarefas
                                    </div>
                                </div>

                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:0.15rem;">
                                    @if($stage->is_initial)
                                        <span style="font-size: 0.65rem; text-transform: uppercase; color: #4ade80; font-weight:600;">
                                            inicial
                                        </span>
                                    @endif
                                    @if($stage->is_final)
                                        <span style="font-size: 0.65rem; text-transform: uppercase; color: #60a5fa; font-weight:600;">
                                            final
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- LISTA DE TASKS --}}
                            <div style="padding: 0.5rem; overflow-y: auto; display:flex; flex-direction:column; gap:0.5rem;">
                                @forelse ($tasksByStage[$stage->id] as $task)
                                    <div
                                        style="
                                            background-color: rgb(15,23,42);
                                            border: 1px solid rgb(55,65,81);
                                            border-radius: 0.375rem;
                                            padding: 0.4rem 0.5rem;
                                            font-size: 0.7rem;
                                            display: flex;
                                            flex-direction: column;
                                            gap: 0.15rem;
                                        "
                                    >
                                        <div style="font-weight:600; font-size:0.7rem;">
                                            #{{ $task->id }} — {{ $task->title }}
                                        </div>

                                        @if($task->assignedTo)
                                            <div style="color:#9ca3af;">
                                                Responsável: {{ $task->assignedTo->name }}
                                            </div>
                                        @endif

                                        @if($task->priority)
                                            <div>
                                                Prioridade:
                                                <span style="font-weight:600; text-transform:capitalize;">
                                                    {{ $task->priority }}
                                                </span>
                                            </div>
                                        @endif

                                        @if($task->due_at)
                                            <div style="color:#9ca3af;">
                                                Prazo: {{ $task->due_at->format('d/m/Y H:i') }}
                                            </div>
                                        @endif

                                        @php
                                            $prevStage = $stages
                                                ->where('position', '<', $stage->position)
                                                ->sortByDesc('position')
                                                ->first();

                                            $nextStage = $stages
                                                ->where('position', '>', $stage->position)
                                                ->sortBy('position')
                                                ->first();
                                        @endphp

                                        <div style="display:flex; justify-content:space-between; gap:0.25rem; margin-top:0.25rem;">
                                            @if($prevStage)
                                                <button
                                                    type="button"
                                                    wire:click="moveTaskToStage({{ $task->id }}, {{ $prevStage->id }})"
                                                    style="
                                                        padding: 0.1rem 0.4rem;
                                                        border-radius: 0.25rem;
                                                        border: 1px solid rgb(75,85,99);
                                                        background-color: rgb(31,41,55);
                                                        color: #e5e7eb;
                                                        font-size: 0.65rem;
                                                    "
                                                >
                                                    ← {{ $prevStage->name }}
                                                </button>
                                            @else
                                                <span></span>
                                            @endif

                                            @if($nextStage)
                                                <button
                                                    type="button"
                                                    wire:click="moveTaskToStage({{ $task->id }}, {{ $nextStage->id }})"
                                                    style="
                                                        padding: 0.1rem 0.4rem;
                                                        border-radius: 0.25rem;
                                                        border: 1px solid rgb(75,85,99);
                                                        background-color: rgb(31,41,55);
                                                        color: #e5e7eb;
                                                        font-size: 0.65rem;
                                                    "
                                                >
                                                    {{ $nextStage->name }} →
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div style="font-size:0.7rem; color:#9ca3af; font-style:italic;">
                                        Sem tarefas neste estágio.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-filament::page>
