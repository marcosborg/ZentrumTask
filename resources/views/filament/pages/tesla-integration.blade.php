<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                A gestao OAuth e sincronizacao de viaturas Tesla abre numa pagina propria.
            </p>

            <div class="mt-4">
                <x-filament::button tag="a" href="{{ route('admin.tesla.index') }}">
                    Abrir integracao Tesla
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
