<x-filament-panels::page>
  <div class="space-y-4">
    <div class="p-4 rounded-xl bg-gray-900/60 border border-white/5">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-gray-400">Estado atual</p>
          <p class="text-lg font-semibold">
            {{ $isDown ? 'Em manutenção' : 'Online' }}
          </p>
          @if ($isDown)
            <p class="text-xs text-gray-400 mt-1">
              Aceda com <code>?secret={{ $secret }}</code> para entrar durante a manutenção.
            </p>
          @endif
        </div>
        <x-filament::button
          color="{{ $isDown ? 'success' : 'danger' }}"
          wire:click="toggleMaintenance"
        >
          {{ $isDown ? 'Sair da manutenção' : 'Entrar em manutenção' }}
        </x-filament::button>
      </div>
    </div>
  </div>
</x-filament-panels::page>
