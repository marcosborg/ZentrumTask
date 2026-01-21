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
                    <div class="rounded-xl border border-gray-800 bg-gray-900 px-4 py-3">
                        <div class="text-xs uppercase text-gray-400">Periodo</div>
                        <div class="text-lg font-semibold text-gray-100">
                            {{ $result['period_start'] ?? '-' }} - {{ $result['period_end'] ?? '-' }}
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
