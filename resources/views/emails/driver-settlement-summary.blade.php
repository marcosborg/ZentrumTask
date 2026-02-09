<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Settlement semanal</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; color:#111827; font-family:Segoe UI, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:820px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px; border-bottom:1px solid #e5e7eb;">
                <h1 style="margin:0; font-size:20px;">Settlement semanal</h1>
                <p style="margin:8px 0 0 0; color:#6b7280;">
                    Periodo {{ $payload['period_label'] ?? '-' }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 12px 0;"><strong>Motorista:</strong> {{ $payload['driver']['name'] ?? '-' }}</p>
                <p style="margin:0 0 12px 0;"><strong>Email:</strong> {{ $payload['driver']['email'] ?? '-' }}</p>
                <p style="margin:0 0 20px 0;"><strong>Valor a transferir:</strong> {{ $payload['totals']['amount_due'] ?? '-' }}</p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:20px;">
                    <tr>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Uber</strong><br>{{ $payload['totals']['uber_net'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Bolt</strong><br>{{ $payload['totals']['bolt_net'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Tips</strong><br>{{ $payload['totals']['tips_total'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Despesas</strong><br>{{ $payload['totals']['expenses_total'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Aluguer</strong><br>{{ $payload['totals']['rent_total'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Saldo transitado</strong><br>{{ $payload['totals']['carry_over_balance'] ?? '-' }}</td>
                    </tr>
                </table>

                <h2 style="font-size:16px; margin:0 0 10px;">Movimentos de saldo</h2>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:20px;">
                    <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:left;">Data</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:left;">Tipo</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:left;">Descricao</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:right;">Valor</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse (($payload['balance_movements'] ?? []) as $row)
                        <tr>
                            <td style="padding:8px; border:1px solid #e5e7eb;">{{ $row['created_at'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb;">{{ $row['type'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb;">{{ $row['description'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb; text-align:right;">{{ $row['amount'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:8px; border:1px solid #e5e7eb; color:#6b7280;">Sem movimentos.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                <h2 style="font-size:16px; margin:0 0 10px;">Balances por plataforma</h2>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:20px;">
                    <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:left;">Plataforma</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:left;">Periodo</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:right;">Liquido</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:right;">Tips</th>
                        <th style="padding:8px; border:1px solid #e5e7eb; text-align:left;">Fonte</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse (($payload['balances'] ?? []) as $row)
                        <tr>
                            <td style="padding:8px; border:1px solid #e5e7eb;">{{ $row['platform'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb;">{{ $row['period'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb; text-align:right;">{{ $row['net_amount'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb; text-align:right;">{{ $row['tips_amount'] ?? '-' }}</td>
                            <td style="padding:8px; border:1px solid #e5e7eb;">{{ $row['source_file'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:8px; border:1px solid #e5e7eb; color:#6b7280;">Sem balances.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

                <p style="margin:0; color:#6b7280; font-size:12px;">
                    Email de settlement gerado automaticamente.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
