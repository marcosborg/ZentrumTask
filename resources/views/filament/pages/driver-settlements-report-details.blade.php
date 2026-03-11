@php
    $tableContainerClasses = 'mt-4 overflow-hidden rounded-xl border border-white/10 bg-white/[0.02]';
    $tableScrollClasses = 'w-full overflow-x-auto';
    $tableClasses = 'w-full min-w-full table-fixed text-sm text-gray-200';
    $theadClasses = 'bg-white/5 text-xs uppercase tracking-wide text-gray-400';
    $thClasses = 'px-4 py-3 text-left font-semibold';
    $tdClasses = 'px-4 py-3 align-top';
    $tbodyClasses = 'divide-y divide-white/10';
@endphp

<div class="space-y-4">
    <x-filament::section heading="Resumo">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <div class="text-xs uppercase text-gray-400">Motorista</div>
                <div class="text-sm font-semibold text-gray-100">{{ $driverIdentity['name'] ?? '-' }}</div>
                <div class="text-sm text-gray-400">{{ $driverIdentity['email'] ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Periodo</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $settlement->period_start?->format('d/m/Y') ?? '-' }}
                    -
                    {{ $settlement->period_end?->format('d/m/Y') ?? '-' }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Emails enviados</div>
                <div class="text-sm font-semibold text-gray-100">{{ (int) ($settlement->email_sent_count ?? 0) }}</div>
                <div class="text-xs text-gray-400">
                    Ultimo envio: {{ $settlement->last_emailed_at?->format('d/m/Y H:i') ?? '-' }}
                    @if (! empty($settlement->last_emailed_to))
                        ({{ $settlement->last_emailed_to }})
                    @endif
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Saldo transitado">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase text-gray-400">Saldo anterior</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($settlement->carry_over_balance ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Saldo atual</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($balance->current_balance ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">A pagar</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($settlement->amount_due ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Transferido</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ number_format((float) ($settlement->amount_transferred ?? 0), 2, ',', ' ') }} &euro;
                </div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-400">Estado</div>
                <div class="text-sm font-semibold text-gray-100">
                    {{ $settlement->is_paid ? 'Pago' : 'Pendente' }}
                </div>
                @if ($settlement->paid_at)
                    <div class="text-xs text-gray-400">Pago em {{ $settlement->paid_at->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Movimentos de saldo">
        @if (($balanceMovements ?? []) === [])
            <p class="text-sm text-gray-500">Sem movimentos registados para este settlement.</p>
        @else
            <div class="{{ $tableContainerClasses }}">
                <div class="{{ $tableScrollClasses }}">
                <table class="{{ $tableClasses }}">
                    <thead class="{{ $theadClasses }}">
                        <tr>
                            <th class="{{ $thClasses }} whitespace-nowrap">Data</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Tipo</th>
                            <th class="{{ $thClasses }}">Descricao</th>
                            <th class="{{ $thClasses }} whitespace-nowrap text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="{{ $tbodyClasses }}">
                        @foreach ($balanceMovements as $movement)
                            <tr class="odd:bg-white/[0.02]">
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $movement['created_at']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $movement['type'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} break-words">{{ $movement['description'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-right">
                                    {{ number_format((float) ($movement['amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Historico de emails">
        @if (($emailLogs ?? []) === [])
            <p class="text-sm text-gray-500">Sem registos de envio para este settlement.</p>
        @else
            <div class="{{ $tableContainerClasses }}">
                <div class="{{ $tableScrollClasses }}">
                    <table class="{{ $tableClasses }}">
                        <thead class="{{ $theadClasses }}">
                            <tr>
                                <th class="{{ $thClasses }} whitespace-nowrap">Data</th>
                                <th class="{{ $thClasses }} whitespace-nowrap">Estado</th>
                                <th class="{{ $thClasses }}">Destinatario</th>
                                <th class="{{ $thClasses }}">Message-ID</th>
                                <th class="{{ $thClasses }}">Erro</th>
                            </tr>
                        </thead>
                        <tbody class="{{ $tbodyClasses }}">
                            @foreach ($emailLogs as $row)
                                <tr class="odd:bg-white/[0.02]">
                                    <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['created_at']?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="{{ $tdClasses }} whitespace-nowrap">
                                        {{ ($row['status'] ?? '-') === 'sent' ? 'Enviado' : 'Falhou' }}
                                    </td>
                                    <td class="{{ $tdClasses }} break-all">{{ $row['recipient'] ?? '-' }}</td>
                                    <td class="{{ $tdClasses }} break-all">{{ $row['message_id'] ?? '-' }}</td>
                                    <td class="{{ $tdClasses }} break-words">{{ $row['error_message'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
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
                    -
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
                    -
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
            <div class="{{ $tableContainerClasses }}">
                <div class="{{ $tableScrollClasses }}">
                <table class="{{ $tableClasses }}">
                    <thead class="{{ $theadClasses }}">
                        <tr>
                            <th class="{{ $thClasses }} whitespace-nowrap">Plataforma</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Periodo</th>
                            <th class="{{ $thClasses }} whitespace-nowrap text-right">Liquido</th>
                            <th class="{{ $thClasses }} whitespace-nowrap text-right">Tips</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Fonte</th>
                        </tr>
                    </thead>
                    <tbody class="{{ $tbodyClasses }}">
                        @foreach ($balances as $row)
                            <tr class="odd:bg-white/[0.02]">
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ strtoupper($row['platform']) }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-gray-300">
                                    {{ \Illuminate\Support\Carbon::parse($row['period_start'])->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($row['period_end'])->format('d/m/Y') }}
                                </td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-right">
                                    {{ number_format($row['net_amount'], 2, ',', ' ') }} &euro;
                                </td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-right">
                                    {{ number_format($row['tips_amount'], 2, ',', ' ') }} &euro;
                                </td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-gray-300">{{ $row['source_file'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
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
            <div class="{{ $tableContainerClasses }}">
                <div class="{{ $tableScrollClasses }}">
                <table class="{{ $tableClasses }}">
                    <thead class="{{ $theadClasses }}">
                        <tr>
                            <th class="{{ $thClasses }} whitespace-nowrap">Data/Hora</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Cartao</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Matricula</th>
                            <th class="{{ $thClasses }} whitespace-nowrap text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="{{ $tbodyClasses }}">
                        @foreach ($prioExpenses['rows'] as $row)
                            <tr class="odd:bg-white/[0.02]">
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['occurred_at']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['card_code'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['vehicle_plate'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-right">
                                    {{ number_format((float) ($row['net_amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
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
            <div class="{{ $tableContainerClasses }}">
                <div class="{{ $tableScrollClasses }}">
                <table class="{{ $tableClasses }}">
                    <thead class="{{ $theadClasses }}">
                        <tr>
                            <th class="{{ $thClasses }} whitespace-nowrap">Data/Hora</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Matricula</th>
                            <th class="{{ $thClasses }}">Local</th>
                            <th class="{{ $thClasses }} whitespace-nowrap text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="{{ $tbodyClasses }}">
                        @foreach ($viaVerdeExpenses['rows'] as $row)
                            <tr class="odd:bg-white/[0.02]">
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['occurred_at']?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['vehicle_plate'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} break-words">{{ $row['location'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-right">
                                    {{ number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
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
            <div class="{{ $tableContainerClasses }}">
                <div class="{{ $tableScrollClasses }}">
                <table class="{{ $tableClasses }}">
                    <thead class="{{ $theadClasses }}">
                        <tr>
                            <th class="{{ $thClasses }} whitespace-nowrap">Data</th>
                            <th class="{{ $thClasses }} whitespace-nowrap">Tipo</th>
                            <th class="{{ $thClasses }}">Descricao</th>
                            <th class="{{ $thClasses }} whitespace-nowrap text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="{{ $tbodyClasses }}">
                        @foreach ($adjustments['rows'] as $row)
                            <tr class="odd:bg-white/[0.02]">
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['occurred_at']?->format('d/m/Y') ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap">{{ $row['category'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} break-words">{{ $row['description'] ?? '-' }}</td>
                                <td class="{{ $tdClasses }} whitespace-nowrap text-right">
                                    {{ number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') }} &euro;
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif
    </x-filament::section>
</div>
