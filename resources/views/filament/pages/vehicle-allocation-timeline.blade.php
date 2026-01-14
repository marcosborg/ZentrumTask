<x-filament-panels::page>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap');

        .vt-shell {
            font-family: "Space Grotesk", sans-serif;
        }

        .vt-panel {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(248, 113, 113, 0.08));
        }

        .vt-card {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.9), rgba(9, 9, 11, 0.92));
        }

        .vt-track {
            background: linear-gradient(90deg, rgba(248, 113, 113, 0.2), rgba(248, 113, 113, 0.45));
        }

        .vt-segment {
            background: linear-gradient(90deg, rgba(16, 185, 129, 0.85), rgba(45, 212, 191, 0.9));
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.35);
        }

        .vt-pill {
            backdrop-filter: blur(8px);
        }

        .vt-grid {
            background-image:
                linear-gradient(to right, rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>

    @php
        $tabs = [
            'tvde' => 'TVDE',
            'outsource' => 'Outsource',
            'company' => 'Company',
            'private' => 'Private',
        ];
    @endphp

    <div x-data="{ tab: 'tvde' }" class="vt-shell space-y-6">
        <div class="vt-panel rounded-2xl border border-emerald-500/20 p-4 shadow-sm dark:border-emerald-300/20">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-300">Operacao</p>
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        Alocacoes de viaturas
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">
                        Janela: {{ $rangeStartLabel }} - {{ $rangeEndLabel }} ({{ $rangeDays }} dias)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @foreach ($tabs as $key => $label)
                        <button
                            class="vt-pill rounded-full border px-4 py-1.5 text-sm font-medium"
                            :class="tab === '{{ $key }}' ? 'border-emerald-500 bg-emerald-500/15 text-emerald-700 dark:border-emerald-300 dark:bg-emerald-400/15 dark:text-emerald-100' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'"
                            @click="tab = '{{ $key }}'"
                            type="button"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @foreach ($tabs as $key => $label)
            <div class="space-y-4" x-show="tab === '{{ $key }}'" x-cloak>
                <div class="vt-card vt-grid rounded-2xl border border-gray-800/70 p-5 shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Timeline operacional
                            </h3>
                            <p class="text-sm text-gray-400">
                                Linhas de tempo com alocacoes e paragens.
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                Alocado
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                                Paragem
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse ($timelineBySource[$key] ?? [] as $row)
                            <div class="rounded-2xl border border-gray-800/70 bg-black/30 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div class="font-medium text-white">
                                        {{ $row['license_plate'] }} Жњ {{ $row['make'] }} {{ $row['model'] }}
                                    </div>
                                    <div class="text-gray-400">
                                        Motorista atual: {{ $row['current_driver'] ?? '-' }}
                                    </div>
                                </div>
                                <div class="relative mt-3 h-7 overflow-hidden rounded-full border border-rose-500/20 vt-track">
                                    @foreach ($row['segments'] as $segment)
                                        <div
                                            class="absolute top-0 h-7 rounded-full vt-segment"
                                            style="left: {{ $segment['left'] }}%; width: {{ $segment['width'] }}%;"
                                            title="{{ $segment['label'] }}"
                                        ></div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-700 p-6 text-center text-sm text-gray-400">
                                Sem alocacoes no periodo.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="vt-card rounded-2xl border border-gray-800/70 p-5 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">
                                Utilizacao em pilha
                            </h3>
                            <p class="text-sm text-gray-400">
                                Percentil de utilizacao a verde e paragem a vermelho.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse ($utilizationBySource[$key] ?? [] as $row)
                            <div class="rounded-2xl border border-gray-800/70 bg-black/30 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div class="font-medium text-white">
                                        {{ $row['license_plate'] }} Жњ {{ $row['label'] }}
                                    </div>
                                    <div class="text-gray-400">
                                        Motorista atual: {{ $row['current_driver'] ?? '-' }}
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-xs text-gray-400">
                                        <span>Utilizacao {{ $row['utilization'] }}%</span>
                                        <span>Paragem {{ $row['downtime'] }}%</span>
                                    </div>
                                    <div class="mt-2 h-4 overflow-hidden rounded-full bg-rose-500/30">
                                        <div
                                            class="h-4 rounded-full bg-emerald-400/90"
                                            style="width: {{ $row['utilization'] }}%;"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-700 p-6 text-center text-sm text-gray-400">
                                Sem viaturas registadas.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
