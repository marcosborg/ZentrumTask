<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap');

        .alerts-calendar {
            font-family: "Space Grotesk", sans-serif;
        }

        .alerts-calendar .calendar-shell {
            background: radial-gradient(circle at top left, rgba(16, 185, 129, 0.12), transparent 45%),
                radial-gradient(circle at top right, rgba(248, 113, 113, 0.15), transparent 50%),
                linear-gradient(180deg, rgba(15, 23, 42, 0.9), rgba(10, 10, 15, 0.95));
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .alerts-calendar .calendar-card {
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.9), rgba(2, 6, 23, 0.95));
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .alerts-calendar .calendar-day {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.9), rgba(2, 6, 23, 0.95));
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .alerts-calendar .calendar-day.outside {
            opacity: 0.5;
        }

        .alerts-calendar .calendar-grid {
            background-image:
                linear-gradient(to right, rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        .alerts-calendar .pill {
            backdrop-filter: blur(10px);
        }

        .alerts-calendar .badge-expired {
            background: rgba(248, 113, 113, 0.16);
            border: 1px solid rgba(248, 113, 113, 0.55);
            color: #fecaca;
        }

        .alerts-calendar .badge-warning {
            background: rgba(251, 191, 36, 0.16);
            border: 1px solid rgba(251, 191, 36, 0.55);
            color: #fde68a;
        }

        .alerts-calendar .badge-ok {
            background: rgba(16, 185, 129, 0.16);
            border: 1px solid rgba(16, 185, 129, 0.55);
            color: #a7f3d0;
        }

        .alerts-calendar .day-label {
            color: rgba(226, 232, 240, 0.8);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 11px;
        }

        .alerts-calendar .day-number {
            color: #f8fafc;
            font-weight: 600;
        }

        .alerts-calendar .month-title {
            color: #f8fafc;
        }

        .alerts-calendar .month-subtitle {
            color: rgba(226, 232, 240, 0.65);
        }

        .alerts-calendar .nav-btn {
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.9);
            color: #e2e8f0;
            transition: all 0.2s ease;
        }

        .alerts-calendar .nav-btn:hover {
            border-color: rgba(16, 185, 129, 0.6);
            color: #a7f3d0;
        }

        .alerts-calendar .alert-card {
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 11px;
            line-height: 1.3;
        }

        .alerts-calendar .calendar-weekdays,
        .alerts-calendar .calendar-week {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
        }

        .alerts-calendar .calendar-weekdays {
            font-size: 11px;
            font-weight: 600;
        }

        .alerts-calendar .calendar-cell {
            min-height: 140px;
            border-radius: 16px;
            padding: 12px;
        }
    </style>
    <div class="alerts-calendar space-y-6">
        <div class="calendar-shell rounded-2xl p-5 shadow-lg">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-emerald-300">Calendario operativo</p>
                    <h2 class="month-title text-2xl font-semibold">
                        {{ $monthLabel }}
                    </h2>
                    <p class="month-subtitle text-sm">
                        Alertas agrupados por data de geracao.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a
                        href="{{ $this->previousMonthUrl() }}"
                        class="nav-btn rounded-full px-4 py-1.5 text-sm shadow-sm"
                    >
                        Mes anterior
                    </a>
                    <a
                        href="{{ $this->nextMonthUrl() }}"
                        class="nav-btn rounded-full px-4 py-1.5 text-sm shadow-sm"
                    >
                        Proximo mes
                    </a>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-gray-300">
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                    Expirado
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                    A expirar
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                    Resolvido
                </span>
            </div>
        </div>

        <div class="calendar-card calendar-grid rounded-2xl p-4 shadow-lg">
            <div class="calendar-weekdays">
                <div class="day-label text-center">Seg</div>
                <div class="day-label text-center">Ter</div>
                <div class="day-label text-center">Qua</div>
                <div class="day-label text-center">Qui</div>
                <div class="day-label text-center">Sex</div>
                <div class="day-label text-center">Sab</div>
                <div class="day-label text-center">Dom</div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($weeks as $week)
                    <div class="calendar-week">
                        @foreach ($week as $day)
                            <div class="calendar-day calendar-cell {{ $day['in_month'] ? '' : 'outside' }}">
                                <div class="flex items-center justify-between">
                                    <span class="day-number text-xs">{{ $day['date']->format('d') }}</span>
                                    <span class="text-[10px] uppercase tracking-[0.2em] text-gray-400">{{ $day['date']->format('D') }}</span>
                                </div>

                                <div class="mt-3 space-y-2">
                                    @forelse ($day['alerts'] as $alert)
                                        <div
                                            class="alert-card"
                                            @class([
                                                'badge-expired' => $alert['level'] === 'expired',
                                                'badge-warning' => in_array($alert['level'], ['expiring_7', 'expiring_60'], true),
                                                'badge-ok' => $alert['is_resolved'],
                                            ])
                                        >
                                            <div class="font-semibold">
                                                {{ $alert['vehicle'] ?? '-' }} · {{ $alert['document'] ?? 'Documento' }}
                                            </div>
                                            <div class="text-[10px] text-gray-300">
                                                {{ $alert['message'] }}
                                            </div>
                                        </div>
                                    @empty
                                        <div class="alert-card border border-dashed border-slate-700 text-gray-400">
                                            Sem alertas
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
