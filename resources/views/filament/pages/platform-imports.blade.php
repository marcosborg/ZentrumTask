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
            <div class="mb-4 grid gap-3 md:grid-cols-4">
                <div class="flex flex-col gap-1">
                    <span class="text-xs uppercase text-gray-400">Plataforma</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="$set('importsPlatformFilter', 'all')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsPlatformFilter === 'all', 'border-gray-700 bg-gray-900 text-gray-200' => $importsPlatformFilter !== 'all'])>Todas</button>
                        <button type="button" wire:click="$set('importsPlatformFilter', 'bolt')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsPlatformFilter === 'bolt', 'border-gray-700 bg-gray-900 text-gray-200' => $importsPlatformFilter !== 'bolt'])>Bolt</button>
                        <button type="button" wire:click="$set('importsPlatformFilter', 'uber')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsPlatformFilter === 'uber', 'border-gray-700 bg-gray-900 text-gray-200' => $importsPlatformFilter !== 'uber'])>Uber</button>
                        <button type="button" wire:click="$set('importsPlatformFilter', 'prio')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsPlatformFilter === 'prio', 'border-gray-700 bg-gray-900 text-gray-200' => $importsPlatformFilter !== 'prio'])>PRIO</button>
                        <button type="button" wire:click="$set('importsPlatformFilter', 'via_verde')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsPlatformFilter === 'via_verde', 'border-gray-700 bg-gray-900 text-gray-200' => $importsPlatformFilter !== 'via_verde'])>Via Verde</button>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-xs uppercase text-gray-400">Estado</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="$set('importsStatusFilter', 'all')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsStatusFilter === 'all', 'border-gray-700 bg-gray-900 text-gray-200' => $importsStatusFilter !== 'all'])>Todos</button>
                        <button type="button" wire:click="$set('importsStatusFilter', 'success')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsStatusFilter === 'success', 'border-gray-700 bg-gray-900 text-gray-200' => $importsStatusFilter !== 'success'])>Completo</button>
                        <button type="button" wire:click="$set('importsStatusFilter', 'warning')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsStatusFilter === 'warning', 'border-gray-700 bg-gray-900 text-gray-200' => $importsStatusFilter !== 'warning'])>Parcial</button>
                        <button type="button" wire:click="$set('importsStatusFilter', 'danger')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsStatusFilter === 'danger', 'border-gray-700 bg-gray-900 text-gray-200' => $importsStatusFilter !== 'danger'])>Nao alocado</button>
                    </div>
                </div>
                <label class="flex flex-col gap-1">
                    <span class="text-xs uppercase text-gray-400">Pesquisa</span>
                    <input
                        wire:model.live.debounce.300ms="importsSearch"
                        type="text"
                        placeholder="Ficheiro ou plataforma"
                        class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500"
                    />
                </label>
                <div class="flex flex-col gap-1">
                    <span class="text-xs uppercase text-gray-400">Linhas</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="$set('importsLimit', '10')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsLimit === '10', 'border-gray-700 bg-gray-900 text-gray-200' => $importsLimit !== '10'])>10</button>
                        <button type="button" wire:click="$set('importsLimit', '25')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsLimit === '25', 'border-gray-700 bg-gray-900 text-gray-200' => $importsLimit !== '25'])>25</button>
                        <button type="button" wire:click="$set('importsLimit', '50')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsLimit === '50', 'border-gray-700 bg-gray-900 text-gray-200' => $importsLimit !== '50'])>50</button>
                        <button type="button" wire:click="$set('importsLimit', 'all')" @class(['rounded-full border px-3 py-1 text-xs', 'border-primary-500 bg-primary-600/20 text-primary-300' => $importsLimit === 'all', 'border-gray-700 bg-gray-900 text-gray-200' => $importsLimit !== 'all'])>Todas</button>
                    </div>
                </div>
            </div>
            <div class="mb-4 text-sm text-gray-400">
                A mostrar {{ $importsHistoryFilteredTotal ?? 0 }} de {{ $importsHistoryTotal ?? 0 }} imports.
            </div>
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

        @if ($missingPrioCards !== [])
            <section class="rounded-xl border border-amber-700 bg-amber-900 px-4 py-3 text-sm text-amber-100">
                <p class="font-semibold">Estes cartoes PRIO ainda nao estao associados a viaturas.</p>
                <p class="mt-1 text-amber-200">
                    Copie o cartao e associe na viatura correspondente.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($missingPrioCards as $code)
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

        @if ($missingViaVerdePlates !== [])
            <section class="rounded-xl border border-amber-700 bg-amber-900 px-4 py-3 text-sm text-amber-100">
                <p class="font-semibold">Estas matriculas ainda nao existem no sistema.</p>
                <p class="mt-1 text-amber-200">
                    Crie a viatura e volte a importar o ficheiro Via Verde.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($missingViaVerdePlates as $plate)
                        <span
                            x-data="{ copied: false }"
                            role="button"
                            tabindex="0"
                            title="Clique para copiar"
                            class="cursor-pointer select-text rounded-full border border-amber-700 bg-amber-800 px-3 py-1 text-xs font-semibold text-amber-100 transition"
                            :class="copied ? 'border-emerald-600 bg-emerald-700 text-emerald-50' : ''"
                            @click="navigator.clipboard.writeText('{{ $plate }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            @keydown.enter="navigator.clipboard.writeText('{{ $plate }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            @keydown.space.prevent="navigator.clipboard.writeText('{{ $plate }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        >
                            <span x-show="!copied">{{ $plate }}</span>
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
                    @if (in_array(($result['import_type'] ?? null), ['prio', 'via_verde'], true))
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
                    @endif
                    @if (in_array(($result['import_type'] ?? null), ['prio', 'via_verde'], true))
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

        <x-filament::section heading="Importar ajustes (caucao e acertos)">
            <div class="text-sm text-gray-400">
                CSV com colunas: motorista (email ou codigo), data, valor, descricao, categoria (caucao/acerto) e semanas (opcional).
            </div>
            <div class="mt-4">
                <x-filament::actions
                    :actions="[
                        $this->importAdjustmentsAction(),
                    ]"
                />
            </div>
            <div class="mt-4">
                <x-filament::button
                    color="gray"
                    tag="a"
                    href="{{ route('driver-adjustments.sample') }}"
                >
                    Download exemplo CSV
                </x-filament::button>
            </div>

            @if ($adjustmentError)
                <div class="mt-3 rounded-xl border border-red-700/70 bg-red-900/80 px-4 py-3 text-sm text-red-100">
                    {{ $adjustmentError }}
                </div>
            @endif

            @if ($missingAdjustmentDrivers !== [])
                <div class="mt-3 rounded-xl border border-amber-700 bg-amber-900 px-4 py-3 text-sm text-amber-100">
                    <p class="font-semibold">Motoristas nao encontrados.</p>
                    <p class="mt-1 text-amber-200">
                        Confirme o email ou codigo no ficheiro e volte a importar.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($missingAdjustmentDrivers as $code)
                            <span
                                class="select-text rounded-full border border-amber-700 bg-amber-800 px-3 py-1 text-xs font-semibold text-amber-100"
                            >
                                {{ $code }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($adjustmentResult)
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Processado</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $adjustmentResult['total'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Importados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $adjustmentResult['inserted'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Atualizados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $adjustmentResult['updated'] ?? 0 }}</div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Importar km semanais (km extra)">
            <div class="text-sm text-gray-400">
                CSV/XLSX com colunas obrigatorias: matricula e km_semana. Defina o periodo da semana no modal.
            </div>
            <div class="mt-4">
                <x-filament::actions
                    :actions="[
                        $this->importWeeklyKmAction(),
                    ]"
                />
            </div>
            <div class="mt-4">
                <x-filament::button
                    color="gray"
                    tag="a"
                    href="{{ route('weekly-km.sample') }}"
                >
                    Download exemplo CSV
                </x-filament::button>
            </div>

            @if ($weeklyKmError)
                <div class="mt-3 rounded-xl border border-red-700/70 bg-red-900/80 px-4 py-3 text-sm text-red-100">
                    {{ $weeklyKmError }}
                </div>
            @endif

            @if ($missingWeeklyKmPlates !== [])
                <div class="mt-3 rounded-xl border border-amber-700 bg-amber-900 px-4 py-3 text-sm text-amber-100">
                    <p class="font-semibold">Matriculas nao encontradas.</p>
                    <p class="mt-1 text-amber-200">
                        Crie ou corrija as viaturas e volte a importar.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($missingWeeklyKmPlates as $plate)
                            <span class="select-text rounded-full border border-amber-700 bg-amber-800 px-3 py-1 text-xs font-semibold text-amber-100">
                                {{ $plate }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($weeklyKmResult)
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Processado</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $weeklyKmResult['total'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Importados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $weeklyKmResult['inserted'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Atualizados</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $weeklyKmResult['updated'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Sem motorista</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $weeklyKmResult['unassigned_driver'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Motorista ambiguo</div>
                        <div class="text-2xl font-semibold text-gray-100">{{ $weeklyKmResult['ambiguous_driver'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Periodo</div>
                        <div class="text-lg font-semibold text-gray-100">
                            {{ $weeklyKmResult['period_start'] ?? '-' }} - {{ $weeklyKmResult['period_end'] ?? '-' }}
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Historico de ajustes">
            @if (($adjustmentImports ?? []) === [])
                <div class="text-sm text-gray-500">
                    Ainda nao existem ajustes importados.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-900">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Ficheiro</th>
                                <th class="px-4 py-3 text-left">Periodo</th>
                                <th class="px-4 py-3 text-right">Registos</th>
                                <th class="px-4 py-3 text-right">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            @foreach ($adjustmentImports as $row)
                                <tr class="text-gray-100 transition hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-gray-100">
                                        {{ $row['source_file'] ? basename($row['source_file']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-300">
                                        {{ $row['period_start'] ? \Illuminate\Support\Carbon::parse($row['period_start'])->format('d/m/Y') : '-' }}
                                        &rarr;
                                        {{ $row['period_end'] ? \Illuminate\Support\Carbon::parse($row['period_end'])->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['total_records']) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <x-filament::actions
                                            :actions="[
                                                $this->deleteAdjustmentImportAction()->arguments([
                                                    'source_file' => $row['source_file'],
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

        <x-filament::section heading="Historico de km semanais">
            @if (($weeklyKmImports ?? []) === [])
                <div class="text-sm text-gray-500">
                    Ainda nao existem km semanais importados.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-900">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Ficheiro</th>
                                <th class="px-4 py-3 text-left">Periodo</th>
                                <th class="px-4 py-3 text-right">Registos</th>
                                <th class="px-4 py-3 text-right">Alocados</th>
                                <th class="px-4 py-3 text-right">Pendentes</th>
                                <th class="px-4 py-3 text-right">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            @foreach ($weeklyKmImports as $row)
                                <tr class="text-gray-100 transition hover:bg-gray-800/50">
                                    <td class="px-4 py-3 text-gray-100">
                                        {{ $row['source_file'] ? basename($row['source_file']) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-300">
                                        {{ $row['period_start'] ? \Illuminate\Support\Carbon::parse($row['period_start'])->format('d/m/Y') : '-' }}
                                        &rarr;
                                        {{ $row['period_end'] ? \Illuminate\Support\Carbon::parse($row['period_end'])->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['total_records']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['allocated_count']) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-100">{{ number_format($row['pending_count']) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <x-filament::actions
                                            :actions="[
                                                $this->deleteWeeklyKmImportAction()->arguments([
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

        <x-filament::section heading="Transacoes PRIO por associar">
            {{ $this->table }}
        </x-filament::section>

        <x-filament::section heading="Transacoes Via Verde por associar">
            @if (($viaVerdePending ?? []) === [])
                <p class="text-sm text-gray-500">Sem transacoes por associar.</p>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-900">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Data/Hora</th>
                                <th class="px-4 py-3 text-left">Matricula</th>
                                <th class="px-4 py-3 text-left">Local</th>
                                <th class="px-4 py-3 text-right">Valor</th>
                                <th class="px-4 py-3 text-left">Motorista</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            @foreach ($viaVerdePending as $row)
                                <tr class="text-gray-100 transition hover:bg-gray-800/50">
                                    <td class="px-4 py-3">
                                        {{ $row['occurred_at']?->format('d/m/Y H:i') ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-100">{{ $row['vehicle_plate'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-300">{{ $row['location'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-100">
                                        {{ number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') }} &euro;
                                    </td>
                                    <td class="px-4 py-3 text-gray-300">{{ $row['driver_name'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-300">
                                        {{ $row['status'] === 'ambiguous_driver' ? 'Ambiguo' : 'Sem motorista' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
