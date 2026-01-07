<x-filament-panels::page>
    <style>
        .bolt-wrap{display:flex;flex-direction:column;gap:16px;}
        .bolt-card{background:linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#0b1120 100%);border:1px solid #1f2937;border-radius:16px;padding:16px 18px;box-shadow:0 18px 40px rgba(0,0,0,0.35);color:#e5e7eb;}
        .bolt-card h2{font-size:18px;font-weight:700;margin:0 0 6px;}
        .bolt-card p{margin:0;font-size:13px;color:#94a3b8;}
        .bolt-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;}
        .bolt-link{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;border:1px solid rgba(148,163,184,0.25);background:rgba(15,23,42,0.6);color:#e2e8f0;font-weight:600;font-size:12px;transition:all .15s;}
        .bolt-link:hover{border-color:#38bdf8;color:#38bdf8;}
        .bolt-stats{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));}
        .bolt-stat{border-radius:14px;border:1px solid rgba(148,163,184,0.15);background:rgba(15,23,42,0.7);padding:12px 14px;}
        .bolt-stat h3{margin:0 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:0.2em;color:#94a3b8;}
        .bolt-stat .bolt-metric{display:flex;flex-wrap:wrap;gap:10px;font-size:12px;color:#cbd5e1;}
        .bolt-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:600;border:1px solid rgba(148,163,184,0.2);background:rgba(15,23,42,0.6);}
        .bolt-badge.bolt{border-color:rgba(56,189,248,0.4);color:#7dd3fc;}
        .bolt-badge.uber{border-color:rgba(34,197,94,0.4);color:#86efac;}
        .bolt-table-wrap{border-radius:16px;border:1px solid #1f2937;background:rgba(15,23,42,0.6);overflow:hidden;box-shadow:0 18px 40px rgba(0,0,0,0.25);}
        .bolt-table{width:100%;border-collapse:collapse;font-size:13px;color:#e5e7eb;min-width:680px;}
        .bolt-table th{text-align:left;padding:12px 16px;background:rgba(15,23,42,0.9);color:#cbd5e1;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;border-bottom:1px solid #1f2937;}
        .bolt-table td{padding:12px 16px;border-bottom:1px solid rgba(30,41,59,0.7);vertical-align:top;}
        .bolt-table tbody tr:hover{background:rgba(148,163,184,0.08);}
        .bolt-muted{color:#94a3b8;}
        .bolt-right{text-align:right;}
        .bolt-empty{padding:18px;text-align:center;color:#94a3b8;}
        .bolt-scroll{overflow-x:auto;}
    </style>

    <div class="bolt-wrap">
        <div class="bolt-card">
            <h2>Relatorio semanal</h2>
            <p>
                Totais por motorista e semana (Bolt e Uber).
            </p>
            <div class="bolt-actions">
                <a class="bolt-link" href="{{ route('filament.admin.pages.bolt-csv-import') }}">Importar Bolt CSV</a>
                <a class="bolt-link" href="{{ route('filament.admin.pages.uber-csv-import') }}">Importar Uber CSV</a>
            </div>
        </div>

        <div class="bolt-stats">
            @foreach ($summary as $summaryRow)
                <div class="bolt-stat">
                    <h3>{{ $summaryRow['label'] }}</h3>
                    <div class="bolt-metric">
                        <span>Motoristas: {{ $summaryRow['drivers'] }}</span>
                        <span>Entradas: {{ $summaryRow['entries'] }}</span>
                        <span>Total: {{ number_format((float) $summaryRow['amount'], 2, ',', ' ') }} EUR</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bolt-table-wrap">
            <div class="bolt-scroll">
                <table class="bolt-table">
                    <thead>
                        <tr>
                            <th>Plataforma</th>
                            <th>Semana</th>
                            <th>Motorista</th>
                            <th>Email</th>
                            <th class="bolt-right">Total</th>
                            <th class="bolt-right">Entradas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <span class="bolt-badge {{ strtolower($row['provider']) }}">
                                        {{ $row['provider'] }}
                                    </span>
                                </td>
                                <td>
                                    {{ $row['week_start'] }} - {{ $row['week_end'] }}
                                </td>
                                <td>
                                    {{ $row['driver_name'] ?? '-' }}
                                </td>
                                <td class="bolt-muted">
                                    {{ $row['driver_email'] ?? '-' }}
                                </td>
                                <td class="bolt-right">
                                    {{ number_format($row['total_amount'], 2, ',', ' ') }} {{ $row['currency'] }}
                                </td>
                                <td class="bolt-right bolt-muted">
                                    {{ $row['entries'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="bolt-empty">
                                    Sem dados importados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
