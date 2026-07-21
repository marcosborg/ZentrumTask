@php
    $labels = [
        'fl' => 'Dianteiro esquerdo',
        'fr' => 'Dianteiro direito',
        'rl' => 'Traseiro esquerdo',
        'rr' => 'Traseiro direito',
    ];
@endphp

<div class="grid gap-4">
    <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
        <p><strong>Destinatario:</strong> {{ $driver->name }} &lt;{{ $driver->email }}&gt;</p>
        <p><strong>Viatura:</strong> {{ $vehicle->display_name ?: $vehicle->vin }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        @foreach ($labels as $position => $label)
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="text-lg font-semibold">{{ number_format($assessment['pressures'][$position], 1, ',', ' ') }} PSI</div>
            </div>
        @endforeach
    </div>

    <div>
        <p class="font-semibold">Problemas detetados</p>
        <ul class="mt-2 list-disc space-y-1 ps-5 text-sm">
            @foreach ($assessment['problems'] as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-300">
        O email recomenda a correcao antes da utilizacao regular e explica os riscos de menor aderencia, travagem menos eficaz, aquecimento, deformacao, desgaste irregular e maior consumo de energia.
    </p>
</div>
