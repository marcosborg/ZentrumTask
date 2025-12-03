<x-filament::page>
    <style>
        .ops-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));}
        .ops-card{background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);border:1px solid #1f2937;border-radius:14px;padding:16px;box-shadow:0 12px 30px rgba(0,0,0,0.35);color:#e5e7eb;}
        .ops-title{font-size:18px;font-weight:700;margin:0 0 6px;}
        .ops-sub{color:#cbd5e1;font-size:13px;margin-bottom:12px;}
        .ops-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;}
        .ops-pill{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:10px;display:flex;flex-direction:column;gap:4px;}
        .ops-pill strong{font-size:15px;}
        .ops-links{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;}
        .ops-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:#e5e7eb;font-weight:600;font-size:12px;transition:all .15s;}
        .ops-btn:hover{border-color:#fbbf24;color:#fbbf24;}
        .ops-list{margin-top:12px;display:flex;flex-direction:column;gap:8px;}
        .ops-item{display:flex;justify-content:space-between;gap:10px;padding:10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);}
        .ops-item small{color:#94a3b8;}
        .ops-chip{padding:4px 8px;border-radius:8px;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;border:1px solid rgba(255,255,255,0.14);}
        .ops-charts{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:12px;}
        .ops-bar{height:12px;border-radius:999px;background:linear-gradient(90deg,#fbbf24,#f97316);}
        .ops-bar-blue{background:linear-gradient(90deg,#38bdf8,#6366f1);}
        .ops-bar-wrap{background:rgba(255,255,255,0.06);border-radius:999px;border:1px solid rgba(255,255,255,0.08);padding:3px;}
    </style>

    <div class="ops-grid">
        <div class="ops-card">
            <h2 class="ops-title">Kanban</h2>
            <div class="ops-sub">Atalhos rápidos e visão geral.</div>
            <div class="ops-stats">
                <div class="ops-pill">
                    <small>Boards</small>
                    <strong>{{ $kanbanStats['boards'] }}</strong>
                </div>
                <div class="ops-pill">
                    <small>Estágios</small>
                    <strong>{{ $kanbanStats['stages'] }}</strong>
                </div>
                <div class="ops-pill">
                    <small>Tarefas</small>
                    <strong>{{ $kanbanStats['tasks'] }}</strong>
                </div>
                <div class="ops-pill">
                    <small>Notificações</small>
                    <strong>{{ $kanbanStats['notifications'] }}</strong>
                </div>
            </div>
            <div class="ops-links">
                <a href="{{ $this->getKanbanBoardUrl() }}" class="ops-btn">Abrir quadro</a>
                <a href="{{ route('filament.admin.resources.boards.index') }}" class="ops-btn">Boards</a>
                <a href="{{ route('filament.admin.resources.stages.index') }}" class="ops-btn">Estágios</a>
                <a href="{{ route('filament.admin.resources.tasks.index') }}" class="ops-btn">Tarefas</a>
                <a href="{{ route('filament.admin.resources.tags.index') }}" class="ops-btn">Etiquetas</a>
                <a href="{{ route('filament.admin.resources.notification-rules.index') }}" class="ops-btn">Regras</a>
                <a href="{{ route('filament.admin.resources.notification-logs.index') }}" class="ops-btn">Logs</a>
            </div>
            <div class="ops-list">
                @foreach ($recentTasks as $task)
                    <div class="ops-item">
                        <div>
                            <div>#{{ $task->id }} • {{ $task->title }}</div>
                            <small>{{ $task->created_at?->diffForHumans() }}</small>
                        </div>
                        <span class="ops-chip">{{ $task->stage?->name ?? 'Sem estágio' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ops-card">
            <h2 class="ops-title">TVDE</h2>
            <div class="ops-sub">Resumo rápido e links principais.</div>
            <div class="ops-stats">
                <div class="ops-pill">
                    <small>Motoristas</small>
                    <strong>{{ $tvdeStats['drivers'] }}</strong>
                </div>
                <div class="ops-pill">
                    <small>Perfis</small>
                    <strong>{{ $tvdeStats['profiles'] }} <span style="color:#a3e635;">(ativos {{ $tvdeStats['active_profiles'] }})</span></strong>
                </div>
                <div class="ops-pill">
                    <small>Extratos</small>
                    <strong>{{ $tvdeStats['statements'] }}</strong>
                </div>
                <div class="ops-pill">
                    <small>Rascunhos</small>
                    <strong>{{ $tvdeStats['draft_statements'] }}</strong>
                </div>
            </div>
            <div class="ops-links">
                <a href="{{ route('filament.admin.resources.drivers.index') }}" class="ops-btn">Motoristas</a>
                <a href="{{ route('filament.admin.resources.driver-billing-profiles.index') }}" class="ops-btn">Perfis</a>
                <a href="{{ route('filament.admin.resources.driver-week-statements.index') }}" class="ops-btn">Extratos</a>
                <a href="{{ route('filament.admin.resources.driver-week-statements.create') }}" class="ops-btn">Gerar extrato</a>
            </div>
            <div class="ops-list">
                @foreach ($recentStatements as $statement)
                    <div class="ops-item">
                        <div>
                            <div>#{{ $statement->id }} • {{ $statement->driver?->name ?? 'Motorista' }}</div>
                            <small>{{ $statement->week_start_date?->format('d/m') }} - {{ $statement->week_end_date?->format('d/m') }}</small>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="ops-chip">{{ strtoupper($statement->status) }}</span>
                            <strong>€ {{ number_format((float) $statement->amount_payable_to_driver, 2, ',', ' ') }}</strong>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="ops-card" style="margin-top:12px;">
        <h2 class="ops-title">Visão gráfica</h2>
        <div class="ops-sub">Resumo visual rápido de Kanban e TVDE.</div>
        <div class="ops-charts">
            <div>
                <div class="ops-sub" style="margin-bottom:6px;">Tarefas por estágio (top 6)</div>
                @php
                    $maxStage = max(1, collect($kanbanStageChart)->max('total') ?? 1);
                @endphp
                @foreach ($kanbanStageChart as $row)
                    @php $w = ($row['total'] / $maxStage) * 100; @endphp
                    <div style="margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:#cbd5e1;">
                            <span>{{ $row['label'] }}</span>
                            <span>{{ $row['total'] }}</span>
                        </div>
                        <div class="ops-bar-wrap">
                            <div class="ops-bar" style="width: {{ $w }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div>
                <div class="ops-sub" style="margin-bottom:6px;">Extratos TVDE (últimos 6)</div>
                @php
                    $maxAmt = max(1, collect($tvdeAmountChart)->max('total') ?? 1);
                @endphp
                @foreach ($tvdeAmountChart as $row)
                    @php $w = ($row['total'] / $maxAmt) * 100; @endphp
                    <div style="margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:#cbd5e1;">
                            <span>{{ $row['label'] }}</span>
                            <span>€ {{ number_format($row['total'], 2, ',', ' ') }}</span>
                        </div>
                        <div class="ops-bar-wrap">
                            <div class="ops-bar ops-bar-blue" style="width: {{ $w }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament::page>
