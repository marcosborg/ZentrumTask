<x-filament-panels::page>
    <div class="space-y-8">
        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Filtros</h2>
            <div class="grid gap-4 md:grid-cols-3">
                {{ $this->form }}
            </div>
            <div class="flex flex-wrap gap-3">
                <x-filament::button wire:click="applyFilters">
                    Aplicar filtros
                </x-filament::button>
                @if (! $hasSettlementsForPeriod)
                    <x-filament::button color="warning" wire:click="generateSettlements">
                        Gerar settlements para este periodo
                    </x-filament::button>
                @endif
                @if (($this->data['period_start'] ?? null) && ($this->data['period_end'] ?? null))
                    <x-filament::button color="danger" x-on:click="$dispatch('open-modal', { id: 'delete-period-modal' })">
                        Eliminar dados do periodo
                    </x-filament::button>
                @endif
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Settlements por periodo</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-700 bg-gray-900">
                <table class="min-w-full text-sm text-gray-100">
                    <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="min-w-[220px] px-4 py-3 text-left">Motorista</th>
                            <th class="min-w-[220px] px-4 py-3 text-left">Email</th>
                            <th class="min-w-[260px] px-4 py-3 text-left">Periodo</th>
                            <th class="min-w-[120px] px-4 py-3 text-right">Bolt (liq.)</th>
                            <th class="min-w-[120px] px-4 py-3 text-right">Uber (liq.)</th>
                            <th class="min-w-[120px] px-4 py-3 text-right">Liquido</th>
                            <th class="min-w-[120px] px-4 py-3 text-right">Tips</th>
                            <th class="min-w-[120px] px-4 py-3 text-right">A pagar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($settlements as $settlement)
                            <tr class="transition hover:bg-zinc-800/50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-100">{{ $settlement->driver_name ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-400">{{ $settlement->driver_email ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-400">
                                    {{ $settlement->period_start ? \Illuminate\Support\Carbon::parse($settlement->period_start)->format('d/m/Y') : '-' }}
                                    &rarr;
                                    {{ $settlement->period_end ? \Illuminate\Support\Carbon::parse($settlement->period_end)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) $settlement->bolt_net, 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) $settlement->uber_net, 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) $settlement->net_total, 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) $settlement->tips_total, 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-100">
                                    {{ number_format((float) $settlement->amount_payable, 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-400">
                                    Sem settlements para o periodo selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Pendentes - balances sem driver</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Plataforma</th>
                            <th class="px-4 py-3 text-left">Driver code</th>
                            <th class="px-4 py-3 text-left">Periodo</th>
                            <th class="px-4 py-3 text-right">Liquido</th>
                            <th class="px-4 py-3 text-right">Tips</th>
                            <th class="px-4 py-3 text-left">Fonte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($pendingBalances as $row)
                            <tr>
                                <td class="px-4 py-3">{{ strtoupper($row['platform']) }}</td>
                                <td class="px-4 py-3">{{ $row['driver_code'] }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $row['period_start'] }} - {{ $row['period_end'] }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['net_amount'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tips_amount'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $row['source_file'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    Sem pendentes no periodo selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Pendentes - motoristas sem perfil ativo</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Motorista</th>
                            <th class="px-4 py-3 text-left">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($driversMissingProfiles as $row)
                            <tr>
                                <td class="px-4 py-3">{{ $row['name'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $row['email'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-gray-500">
                                    Sem motoristas pendentes no periodo selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Auditoria de imports</h2>
            <div class="overflow-x-auto rounded-xl border border-gray-700 bg-gray-900">
                <table class="min-w-full text-sm text-gray-100">
                    <thead class="bg-gray-800 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="min-w-[220px] px-4 py-3 text-left">Motorista</th>
                            <th class="min-w-[140px] px-4 py-3 text-left">Plataforma</th>
                            <th class="min-w-[220px] px-4 py-3 text-left">Driver code</th>
                            <th class="min-w-[140px] px-4 py-3 text-right">Liquido</th>
                            <th class="min-w-[140px] px-4 py-3 text-right">Tips</th>
                            <th class="min-w-[260px] px-4 py-3 text-left">Coluna liquido</th>
                            <th class="min-w-[260px] px-4 py-3 text-left">Coluna tips</th>
                            <th class="min-w-[240px] px-4 py-3 text-left">Raw row</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse ($auditRows as $row)
                            <tr class="transition hover:bg-zinc-800/50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-100">{{ $row['driver_name'] ?? '-' }}</div>
                                    <div class="text-sm text-gray-400">{{ $row['driver_email'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ strtoupper($row['platform'] ?? '') }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $row['driver_code'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) ($row['net_amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ number_format((float) ($row['tips_amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ $row['net_source_column'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-300">{{ $row['tips_source_column'] ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <details class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-xs text-gray-300">
                                        <summary class="cursor-pointer text-gray-200">Ver raw row</summary>
                                        <pre class="mt-2 whitespace-pre-wrap">{{ json_encode($row['raw_row'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-400">
                                    Sem dados de auditoria para o periodo selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <x-filament::modal
            id="delete-period-modal"
            heading="Eliminar dados do periodo"
            description="Esta acao vai apagar settlements e balances do periodo selecionado. Esta acao nao pode ser revertida."
        >
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'delete-period-modal' })">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button
                        color="danger"
                        wire:click="deletePeriodData"
                        x-on:click="$dispatch('close-modal', { id: 'delete-period-modal' })"
                    >
                        Eliminar dados
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>


