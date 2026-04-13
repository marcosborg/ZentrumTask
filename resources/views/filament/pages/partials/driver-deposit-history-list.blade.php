<div class="space-y-4 text-sm">
    <div class="grid gap-4 sm:grid-cols-4">
        <div>
            <div class="text-xs uppercase text-gray-400">Valor acordado</div>
            <div class="text-sm font-semibold text-gray-100">{{ number_format((float) ($summary['agreed_amount'] ?? 0), 2, ',', ' ') }} &euro;</div>
        </div>
        <div>
            <div class="text-xs uppercase text-gray-400">Pago no ato inicial</div>
            <div class="text-sm font-semibold text-gray-100">{{ number_format((float) ($summary['paid_amount'] ?? 0), 2, ',', ' ') }} &euro;</div>
        </div>
        <div>
            <div class="text-xs uppercase text-gray-400">Ajustes caucao cobrados</div>
            <div class="text-sm font-semibold text-gray-100">{{ number_format((float) ($summary['adjustments_total'] ?? 0), 2, ',', ' ') }} &euro;</div>
        </div>
        <div>
            <div class="text-xs uppercase text-gray-400">Debitos</div>
            <div class="text-sm font-semibold text-gray-100">{{ number_format((float) ($summary['debits_total'] ?? 0), 2, ',', ' ') }} &euro;</div>
        </div>
    </div>

    <div>
        <div class="text-xs uppercase text-gray-400">Saldo acumulado</div>
        <div class="text-sm font-semibold text-gray-100">{{ number_format((float) ($summary['current_balance'] ?? 0), 2, ',', ' ') }} &euro;</div>
    </div>

    @if ($history === [])
        <p class="text-sm text-gray-500">Sem historico de caucao.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100/5">
                    <tr>
                        <th class="px-2 py-2 text-left">Data</th>
                        <th class="px-2 py-2 text-left">Tipo</th>
                        <th class="px-2 py-2 text-left">Descricao</th>
                        <th class="px-2 py-2 text-left">Settlement</th>
                        <th class="px-2 py-2 text-right">Movimento</th>
                        <th class="px-2 py-2 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-2 py-2">{{ $row['occurred_at']?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-2 py-2">{{ $row['type'] ?? '-' }}</td>
                            <td class="px-2 py-2">
                                <div>{{ $row['description'] ?? '-' }}</div>
                                @if (! empty($row['notes']))
                                    <div class="text-xs text-gray-500">{{ $row['notes'] }}</div>
                                @endif
                            </td>
                            <td class="px-2 py-2">{{ $row['settlement_label'] ?? '-' }}</td>
                            <td class="px-2 py-2 text-right">{{ number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') }} &euro;</td>
                            <td class="px-2 py-2 text-right">{{ number_format((float) ($row['balance_after'] ?? 0), 2, ',', ' ') }} &euro;</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
