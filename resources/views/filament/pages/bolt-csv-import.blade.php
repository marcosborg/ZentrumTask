<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Importacao manual</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Submete um CSV da Bolt para guardar ganhos por motorista e semana.
            </p>

            <div class="mt-4">
                <form wire:submit="import">
                    {{ $this->form }}

                    <div class="mt-4 flex justify-end">
                        <x-filament::button type="submit">
                            Importar CSV
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>

        @if ($summary = $this->lastRunSummary())
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-50 p-5 text-sm text-emerald-800 shadow-sm dark:border-emerald-400/20 dark:bg-emerald-950/40 dark:text-emerald-100">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="font-semibold">Resumo do ultimo import</div>
                    <div class="text-xs uppercase tracking-[0.2em]">{{ $summary['status'] }}</div>
                </div>
                <div class="mt-3 grid gap-2 text-xs text-emerald-900 dark:text-emerald-100 sm:grid-cols-2">
                    <div>Linhas: {{ $summary['rows'] }}</div>
                    <div>Motoristas: {{ $summary['drivers'] }}</div>
                    <div>Total: {{ number_format((float) $summary['amount'], 2, ',', ' ') }} EUR</div>
                    <div>Inicio: {{ $summary['started_at'] ?? '-' }}</div>
                    <div>Fim: {{ $summary['finished_at'] ?? '-' }}</div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
