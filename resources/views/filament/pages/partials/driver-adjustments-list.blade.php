<div class="mb-4 space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <div class="mb-2 text-sm font-semibold">Ajustes existentes (ultimos 20)</div>

    @if ($adjustments === [])
        <p class="text-sm text-gray-500">Nenhum ajuste encontrado para este motorista.</p>
    @else
        <div class="max-h-64 overflow-y-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-2 py-1 text-left">Inicio</th>
                            <th class="px-2 py-1 text-left">Semanas</th>
                            <th class="px-2 py-1 text-left">Categoria</th>
                            <th class="px-2 py-1 text-left">Descricao</th>
                            <th class="px-2 py-1 text-right">Valor</th>
                            <th class="px-2 py-1 text-left">Origem</th>
                        </tr>
                    </thead>
                <tbody>
                    @foreach ($adjustments as $adjustment)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-2 py-1">{{ $adjustment['starts_at']?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-2 py-1">{{ $adjustment['recurrence_weeks'] ?? 1 }}</td>
                            <td class="px-2 py-1">{{ $adjustment['category'] }}</td>
                            <td class="px-2 py-1">{{ $adjustment['description'] }}</td>
                            <td class="px-2 py-1 text-right">{{ number_format((float) $adjustment['amount'], 2, ',', ' ') }} &euro;</td>
                            <td class="px-2 py-1">{{ $adjustment['source_file'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
