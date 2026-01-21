<x-filament-panels::page>
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Alocacao de balances</h2>
            <div class="grid gap-4 md:grid-cols-2">
                {{ $this->form }}
            </div>
            <x-filament::button
                color="warning"
                size="lg"
                wire:click="runAllocation"
                wire:loading.attr="disabled"
                wire:target="runAllocation"
            >
                <span wire:loading.remove wire:target="runAllocation">Executar alocacao</span>
                <span wire:loading wire:target="runAllocation">Alocacao executada</span>
            </x-filament::button>
        </section>

        @if ($errorMessage)
            <section class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errorMessage }}
            </section>
        @endif

        @if ($result)
            <section class="space-y-3">
                <h3 class="text-lg font-semibold">Resumo</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 border-l-4 border-l-emerald-500">
                        <div class="text-xs uppercase text-gray-400">Alocados</div>
                        <div class="text-2xl font-bold text-white">{{ $result['allocated'] }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 border-l-4 border-l-amber-500">
                        <div class="text-xs uppercase text-gray-400">Pendentes</div>
                        <div class="text-2xl font-bold {{ $result['pending'] > 0 ? 'text-amber-300' : 'text-emerald-300' }}">
                            {{ $result['pending'] }}
                            @if ($result['pending'] > 0)
                                <span class="ml-1">⚠️</span>
                            @endif
                        </div>
                    </div>
                </div>
                @if ($result['pending'] > 0)
                    <div class="rounded-xl border border-amber-700 bg-amber-900 px-4 py-3 text-sm text-amber-100">
                        Existem balances pendentes. Confirme os codigos nos drivers e execute novamente a alocacao.
                    </div>
                @else
                    <div class="rounded-xl border border-emerald-700 bg-emerald-900 px-4 py-3 text-sm text-emerald-100">
                        Sem pendentes. A alocacao esta completa para o periodo atual.
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
