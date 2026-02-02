<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Filtros">
            <form wire:submit.prevent="applyFilters" class="space-y-4">
                <div>
                    {{ $this->getFiltersForm() }}
                </div>
                <div class="flex justify-end">
                    <x-filament::button type="submit" color="primary">
                        Aplicar filtros
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section heading="Acoes">
            <x-filament::actions
                :actions="[
                    $this->generateSettlementsAction(),
                    $this->deletePeriodSettlementsAction(),
                    $this->regenerateSettlementsAction(),
                ]"
            />
        </x-filament::section>

        <x-filament::section heading="Settlements">
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
