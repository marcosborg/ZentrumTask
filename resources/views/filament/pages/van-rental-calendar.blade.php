<x-filament-panels::page>
    @vite('resources/css/van-rentals.css')
    <div class="vr:flex vr:flex-wrap vr:items-end vr:gap-4">
        <label class="vr:grid vr:gap-2">Carrinha
            <select wire:model.live="vanId" class="vr:rounded-lg vr:border vr:border-gray-300 vr:bg-white vr:p-2 vr:dark:border-gray-600 vr:dark:bg-gray-900">
                <option value="">Todas as carrinhas</option>
                @foreach (\App\Models\RentalVan::query()->orderBy('name')->get() as $van)
                    <option value="{{ $van->id }}">{{ $van->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="vr:grid vr:gap-2">Vista
            <select wire:model.live="period" class="vr:rounded-lg vr:border vr:border-gray-300 vr:bg-white vr:p-2 vr:dark:border-gray-600 vr:dark:bg-gray-900"><option value="month">Mensal</option><option value="week">Semanal</option></select>
        </label>
        <label class="vr:grid vr:gap-2">Data <input type="date" wire:model.live="date" class="vr:rounded-lg vr:border vr:border-gray-300 vr:bg-white vr:p-2 vr:dark:border-gray-600 vr:dark:bg-gray-900"></label>
        <x-filament::button wire:click="move(-1)" color="gray">Anterior</x-filament::button>
        <x-filament::button wire:click="move(1)" color="gray">Seguinte</x-filament::button>
        <span wire:loading role="status">A atualizar…</span>
    </div>
    <p class="vr:text-sm vr:text-gray-600 vr:dark:text-gray-400">Horários de Lisboa · Pedidos pendentes não bloqueiam a agenda. Confirme também a disponibilidade do motorista antes de aceitar o pedido.</p>
    <div class="vr:grid vr:grid-cols-1 vr:gap-3 vr:md:grid-cols-7">
        @foreach ($this->calendar as $day)
            <section wire:key="day-{{ $day['date']->format('Y-m-d') }}" class="vr:min-h-32 vr:rounded-xl vr:border vr:border-gray-200 vr:bg-white vr:p-3 vr:dark:border-gray-700 vr:dark:bg-gray-900">
                <h2 class="vr:font-semibold">{{ $day['date']->locale('pt')->translatedFormat('D, d/m') }}</h2>
                <div class="vr:mt-3 vr:grid vr:gap-2">
                @foreach ($day['items'] as $item)
                    <div class="vr:rounded-lg vr:border vr:p-2 vr:text-xs {{ $item['pending'] ? 'vr:border-amber-300 vr:bg-amber-50 vr:text-amber-900 vr:dark:bg-amber-950 vr:dark:text-amber-100' : 'vr:border-gray-300 vr:bg-gray-50 vr:text-gray-900 vr:dark:bg-gray-800 vr:dark:text-gray-100' }}">
                        @if ($item['url']) <a href="{{ $item['url'] }}" class="vr:font-semibold vr:underline">{{ $item['label'] }}</a> @else <strong>{{ $item['label'] }}</strong> @endif
                        <p>{{ $item['time'] }}</p><p>{{ $item['state'] }}</p>
                    </div>
                @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
