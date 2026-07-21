<div class="grid gap-4">
    <div class="rounded-xl bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
        Serão enviados <strong>{{ $vehicles->count() }} emails individuais</strong>. Confirma os destinatários antes de continuar.
    </div>

    <div class="max-h-80 overflow-y-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3">Viatura</th>
                    <th class="px-4 py-3">Motorista</th>
                    <th class="px-4 py-3">Email</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($vehicles as $vehicle)
                    @php($driver = $vehicle->vehicle->currentAllocation->driver)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $vehicle->display_name ?: $vehicle->vin }}</td>
                        <td class="px-4 py-3">{{ $driver->name }}</td>
                        <td class="px-4 py-3">{{ $driver->email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-300">
        Cada motorista receberá apenas a informação da viatura que lhe está atualmente atribuída.
    </p>
</div>
