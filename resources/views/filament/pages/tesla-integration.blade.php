<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Gestao da ligacao OAuth e sincronizacao da frota Tesla.</p>

            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" href="{{ route('admin.tesla.connect') }}" icon="heroicon-m-link">
                    Ligar conta Tesla
                </x-filament::button>

                <form method="post" action="{{ route('admin.tesla.syncVehicles') }}">
                    @csrf
                    <x-filament::button type="submit" color="gray" icon="heroicon-m-arrow-path">
                        Sincronizar veiculos
                    </x-filament::button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                {{ session('error') }}
            </div>
        @endif

        @unless ($isConfigured)
            <div class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                Configuracao Tesla incompleta. Define TESLA_CLIENT_ID, TESLA_CLIENT_SECRET e TESLA_REDIRECT_URI no .env.
            </div>
        @endunless

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Configuracao</p>
                <p class="mt-4 text-2xl font-semibold text-gray-950 dark:text-white">{{ $isConfigured ? 'Pronta' : 'Incompleta' }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Contas ligadas</p>
                <p class="mt-4 text-2xl font-semibold text-gray-950 dark:text-white">{{ $accounts->count() }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Veiculos sincronizados</p>
                <p class="mt-4 text-2xl font-semibold text-gray-950 dark:text-white">{{ $vehicles->count() }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Contas Tesla</h2>

            @if ($accounts->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Ainda nao existe nenhuma conta Tesla ligada.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="px-3 py-2 font-semibold">ID</th>
                                <th class="px-3 py-2 font-semibold">Email</th>
                                <th class="px-3 py-2 font-semibold">Scopes</th>
                                <th class="px-3 py-2 font-semibold">Expira em</th>
                                <th class="px-3 py-2 font-semibold">Ultima sincronizacao</th>
                                <th class="px-3 py-2 font-semibold">Veiculos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($accounts as $account)
                                <tr>
                                    <td class="px-3 py-2">{{ $account->id }}</td>
                                    <td class="px-3 py-2">{{ $account->owner_email ?: $account->email ?: 'unknown' }}</td>
                                    <td class="px-3 py-2">{{ implode(', ', $account->scopes ?? []) }}</td>
                                    <td class="px-3 py-2">{{ $account->expires_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $account->last_synced_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $account->vehicles_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Veiculos Tesla</h2>

            @if ($vehicles->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Ainda nao existem veiculos Tesla sincronizados.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="px-3 py-2 font-semibold">VIN</th>
                                <th class="px-3 py-2 font-semibold">Nome</th>
                                <th class="px-3 py-2 font-semibold">Estado</th>
                                <th class="px-3 py-2 font-semibold">Modelo</th>
                                <th class="px-3 py-2 font-semibold">Odometro</th>
                                <th class="px-3 py-2 font-semibold">Bateria</th>
                                <th class="px-3 py-2 font-semibold">Ultima atualizacao</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($vehicles as $vehicle)
                                <tr>
                                    <td class="px-3 py-2">{{ $vehicle->vin }}</td>
                                    <td class="px-3 py-2">{{ $vehicle->display_name ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $vehicle->state ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $vehicle->model ?: '-' }}</td>
                                    <td class="px-3 py-2">{{ $vehicle->odometer !== null ? number_format((float) $vehicle->odometer, 1, ',', ' ') : '-' }}</td>
                                    <td class="px-3 py-2">{{ $vehicle->battery_level !== null ? $vehicle->battery_level.'%' : '-' }}</td>
                                    <td class="px-3 py-2">{{ $vehicle->last_seen_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
