<x-filament-panels::page>
    <div class="space-y-6">
        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Import de plataformas</h2>
            <div class="grid gap-4 md:grid-cols-2">
                {{ $this->form }}
            </div>
            <x-filament::button wire:click="runImport">
                Executar import
            </x-filament::button>
        </section>

        <x-filament::section heading="Historico de imports">
            @if ($importsHistory === [])
                <div class="text-sm text-gray-500">
                    Ainda nao existem ficheiros importados.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-900">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Plataforma</th>
                                <th class="px-4 py-3 text-left">Ficheiro</th>
                                <th class="px-4 py-3 text-left">Periodo</th>
                                <th class="px-4 py-3 text-right">Registos</th>
                                <th class="px-4 py-3 text-right">Alocados</th>
                                <th class="px-4 py-3 text-right">Pendentes</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-right">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            @foreach ($importsHistory as $row)
                                @php
                                    $statusClasses = match ($row['status_color']) {
                                        'success' => 'border border-emerald-700/40 bg-emerald-900/30 text-emerald-200',
                                        'warning' => 'border border-amber-700/40 bg-amber-900/30 text-amber-200',
                                        'danger' => 'border border-red-700/40 bg-red-900/30 text-red-200',
                                        default => 'border border-gray-700 bg-gray-800 text-gray-200',
                                    };
                                @endphp
                                <tr class="text-gray-100 transition hover:bg-gray-800/50">
                                    <td class="px-4 py-3">{{ $row['platform_label'] ?? strtoupper($row['platform']) }}</td>
                                    <td class="px-4 py-3 text-gray-100">{{ $row['source_file'] ? basename($row['source_file']) : '-' }}</td>
                                    <td class="px-4 py-3 text-gray-300">
                                        {{ $row['period_start'] ? \Illuminate\Support\Carbon::parse($row['period_start'])->format('d/m/Y') : '-' }}
                                        &rarr;
                                        {{ $row['period_end'] ? \Illuminate\Support\Carbon::parse($row['period_end'])->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['total_records']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['allocated_count']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['pending_count']) }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge color="gray" class="{{ $statusClasses }}">
                                            {{ $row['status_label'] }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-filament::actions
                                            :actions="[
                                                $this->deleteImportAction()->arguments([
                                                    'platform' => $row['platform'],
                                                    'import_type' => $row['import_type'] ?? 'platform',
                                                    'source_file' => $row['source_file'],
                                                    'period_start' => $row['period_start'],
                                                    'period_end' => $row['period_end'],
                                                ]),
                                            ]"
                                            alignment="end"
                                            class="justify-end"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if ($errorMessage)
            <section class="rounded-xl border border-red-700/70 bg-red-900/80 px-4 py-3 text-sm text-red-100">
                {{ $errorMessage }}
            </section>
        @endif

        @if ($missingDriverCodes !== [])
            <section class="rounded-xl border border-amber-700 bg-amber-900 px-4 py-3 text-sm text-amber-100">
                <p class="font-semibold">Estes motoristas ainda nao existem no sistema.</p>
                <p class="mt-1 text-amber-200">
                    Cole o codigo no driver correspondente e execute a alocacao.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($missingDriverCodes as $code)
                        <span
                            x-data="{ copied: false }"
                            role="button"
                            tabindex="0"
                            title="Clique para copiar"
                            class="cursor-pointer select-text rounded-full border border-amber-700 bg-amber-800 px-3 py-1 text-xs font-semibold text-amber-100 transition"
                            :class="copied ? 'border-emerald-600 bg-emerald-700 text-emerald-50' : ''"
                            @click="navigator.clipboard.writeText('{{ $code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            @keydown.enter="navigator.clipboard.writeText('{{ $code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            @keydown.space.prevent="navigator.clipboard.writeText('{{ $code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <span x-show="!copied">{{ $code }}</span>
                            <span x-show="copied">Copiado!</span>
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($result)
            <section class="space-y-3">
                <h3 class="text-lg font-semibold">Resumo</h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Processado</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $result['total'] }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Importados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $result['inserted'] }}</div>
                    </div>
                    @if (($result['import_type'] ?? null) === 'prio')
                        <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                            <div class="text-xs uppercase text-gray-400">Atualizados</div>
                            <div class="text-2xl font-semibold text-gray-100">{{ $result['updated'] ?? 0 }}</div>
                        </div>
                    @endif
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Duplicados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $result['duplicates'] }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Ignorados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $result['skipped'] }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Linhas invalidas</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $result['invalid_rows'] }}</div>
                    </div>
                    @if (($result['import_type'] ?? null) === 'prio')
                        <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                            <div class="text-xs uppercase text-gray-400">Sem viatura</div>
                            <div class="text-2xl font-semibold text-gray-100">{{ $result['unassigned_vehicle'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                            <div class="text-xs uppercase text-gray-400">Sem motorista</div>
                            <div class="text-2xl font-semibold text-gray-100">{{ $result['unassigned_driver'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                            <div class="text-xs uppercase text-gray-400">Motorista ambiguo</div>
                            <div class="text-2xl font-semibold text-gray-100">{{ $result['ambiguous_driver'] ?? 0 }}</div>
                        </div>
                    @endif
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Periodo</div>
                        <div class="text-lg font-semibold text-gray-100">
                            {{ $result['period_start'] ?? '-' }} - {{ $result['period_end'] ?? '-' }}
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <x-filament::section heading="Transacoes PRIO por associar">
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
