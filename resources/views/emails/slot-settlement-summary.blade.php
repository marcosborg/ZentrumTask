<!doctype html>
<html lang="pt">
<body style="font-family:Arial,sans-serif;color:#17313f">
<h1>Zentrum TVDE - Extrato SLOT</h1>
<p>{{ $settlement->driver->name }} | {{ $settlement->period_start->format('d/m/Y') }} a {{ $settlement->period_end->format('d/m/Y') }}</p>
<table cellpadding="8" style="border-collapse:collapse">
@foreach ([
    'Ganhos (inclui gorjetas)' => $settlement->net_total,
    'Gorjetas incluídas nos ganhos' => $settlement->tips_total,
    'IVA do motorista' => $settlement->rules_snapshot['vat_amount'] ?? 0,
    'Retenção na fonte' => $settlement->rules_snapshot['withholding_amount'] ?? 0,
    'Ajustes documentados' => $settlement->expenses_total,
    'Pack '.$settlement->rules_snapshot['slot_pack_name'].' (IVA incluído)' => $settlement->slot_fee,
    'Valor da semana' => $settlement->amount_payable,
    'Saldo anterior' => $settlement->carry_over_balance,
    'Valor transferido' => $settlement->amount_transferred,
] as $label => $value)
<tr><td>{{ $label }}</td><td>{{ number_format((float) $value,2,',',' ') }} €</td></tr>
@endforeach
</table>
<p>Pagamentos à segunda-feira, após recebimento e reconciliação. Estado: {{ $settlement->is_paid ? 'Pago' : 'Pendente' }}.</p>
@if ($settlement->payment_delay_reason)<p>{{ $settlement->payment_delay_reason }}</p>@endif
</body>
</html>
