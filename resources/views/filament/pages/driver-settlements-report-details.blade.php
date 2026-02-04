<div class="space-y-4">
    <x-filament::section heading="Resumo">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase text-gray-400">Motorista</div>
                <div class="text-sm font-semibold text-gray-100">{{ $settlement->driver_name ?? '-' }}</div>
                <div class="text-sm text-gray-400">{{ $settlement->driver_email ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Periodo</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $settlement->period_start?->format('d/m/Y') ?? '-' }}
                    -
                    {{ $settlement->period_end?->format('d/m/Y') ?? '-' }}
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Faturacao">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase text-gray-400">Perfil</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $billing['billing_profile_label'] ?? '-' }}
                </div>
                <div class="text-xs text-gray-400">
                    @if (($billing['profile_status'] ?? '') === 'ambiguous')
                        Perfil multiplo
                    @elseif (($billing['profile_status'] ?? '') === 'missing')
                        Sem perfil
                    @else
                        OK
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Dias / Aluguer</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $billing['rental_days'] ?? 0 }} dias
                    ·
                    {{ isset($billing['rent_total']) ? number_format((float) $billing['rent_total'], 2, ',', ' ') : '-' }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Empresa / Motorista</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $billing['percent_company'] !== null ? number_format((float) $billing['percent_company'], 2, ',', ' ').'%' : '-' }}
                    /
                    {{ $billing['percent_driver'] !== null ? number_format((float) $billing['percent_driver'], 2, ',', ' ').'%' : '-' }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Retencao / IVA 23%</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $billing['withholding_label'] ?? '-' }}
                    ·
                    {{ $billing['vat_label'] ?? '-' }}
                </div>
                @if (! empty($billing['vat_refund_mode']))
                    <div class="text-xs text-gray-400">Modo IVA: {{ $billing['vat_refund_mode'] }}</div>
                @endif
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Balances por plataforma">
        @if ($balances === [])
            <p class="text-sm text-gray-500">Sem balances associados a este settlement.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-gray-200">
                    <thead class="text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Plataforma</th>
                            <th class="px-3 py-2 text-left">Periodo</th>
                            <th class="px-3 py-2 text-right">Liquido</th>
                            <th class="px-3 py-2 text-right">Tips</th>
                            <th class="px-3 py-2 text-left">Fonte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach ($balances as $row)
                            <tr>
                                <td class="px-3 py-2">{{ strtoupper($row['platform']) }}</td>
                                <td class="px-3 py-2 text-gray-300">
                                    {{ $row['period_start'] }} - {{ $row['period_end'] }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    {{ number_format($row['net_amount'], 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-3 py-2 text-right">
                                    {{ number_format($row['tips_amount'], 2, ',', ' ') }} &euro;
                                </td>
                                <td class="px-3 py-2 text-gray-300">{{ $row['source_file'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Despesas PRIO">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase text-gray-400">Total PRIO</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($prioExpenses['total'] ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Transacoes</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $prioExpenses['count'] ?? 0 }}
                </div>
            </div>
        </div>

        @if (($prioExpenses['rows'] ?? []) !== [])
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm text-gray-200">
                    <thead class="text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Data/Hora</th>
                            <th class="px-3 py-2 text-left">Cartao</th>
                            <th class="px-3 py-2 text-left">Matricula</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach ($prioExpenses['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['occurred_at']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['card_code'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['vehicle_plate'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ number_format((float) ($row['net_amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Despesas Via Verde">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase text-gray-400">Total Via Verde</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($viaVerdeExpenses['total'] ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Transacoes</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $viaVerdeExpenses['count'] ?? 0 }}
                </div>
            </div>
        </div>

        @if (($viaVerdeExpenses['rows'] ?? []) !== [])
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm text-gray-200">
                    <thead class="text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Data/Hora</th>
                            <th class="px-3 py-2 text-left">Matricula</th>
                            <th class="px-3 py-2 text-left">Local</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach ($viaVerdeExpenses['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['occurred_at']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['vehicle_plate'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['location'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Ajustes (Caucao / Acertos)">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase text-gray-400">Total ajustes</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($adjustments['total'] ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Ocorrencias</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $adjustments['count'] ?? 0 }}
                </div>
            </div>
        </div>

        @if (($adjustments['rows'] ?? []) !== [])
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm text-gray-200">
                    <thead class="text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Data</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-left">Descricao</th>
                            <th class="px-3 py-2 text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach ($adjustments['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['occurred_at']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['category'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['description'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</div>
