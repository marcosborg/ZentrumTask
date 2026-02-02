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
</div>
