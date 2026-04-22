<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Reserva de viatura</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; color:#111827; font-family:Segoe UI, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:820px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px; border-bottom:1px solid #e5e7eb;">
                <p style="margin:0 0 8px 0; color:#2563eb; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;">Reserva de viatura</p>
                <h1 style="margin:0; font-size:24px;">Dados de pagamento da sua reserva</h1>
                <p style="margin:12px 0 0 0; color:#6b7280; line-height:1.6;">
                    Olá {{ $application->full_name ?: 'motorista' }}, a sua reserva foi recebida com sucesso.
                    Em baixo seguem os dados para liquidar a caução inicial da reserva da viatura
                    {{ $application->vehicleType?->display_name ?: 'selecionada' }}.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate; border-spacing:0; margin-bottom:20px; border:1px solid #bfdbfe; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:16px 20px; border-bottom:1px solid #dbeafe;">
                            <span style="display:block; font-size:12px; color:#64748b; text-transform:uppercase; font-weight:700; letter-spacing:0.06em;">Entidade</span>
                            <strong style="display:block; margin-top:6px; font-size:28px;">{{ $payment['entity'] ?? '-' }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 20px; border-bottom:1px solid #dbeafe; background:#0f172a;">
                            <span style="display:block; font-size:12px; color:#cbd5e1; text-transform:uppercase; font-weight:700; letter-spacing:0.06em;">Referência</span>
                            <strong style="display:block; margin-top:6px; font-size:28px; color:#ffffff;">{{ $payment['reference'] ?? 'A gerar' }}</strong>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 20px;">
                            <span style="display:block; font-size:12px; color:#64748b; text-transform:uppercase; font-weight:700; letter-spacing:0.06em;">Valor</span>
                            <strong style="display:block; margin-top:6px; font-size:28px;">{{ $payment['formatted_amount'] ?? '-' }}</strong>
                            <p style="margin:10px 0 0 0; color:#64748b;">
                                Caução base: {{ $payment['formatted_base_amount'] ?? '-' }}<br>
                                IVA: {{ $payment['formatted_vat_amount'] ?? '-' }}
                            </p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-bottom:20px;">
                    <tr>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Subentidade</strong><br>{{ $payment['sub_entity'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Estado</strong><br>{{ $payment['message'] ?? '-' }}</td>
                        <td style="padding:10px; border:1px solid #e5e7eb;"><strong>Validade</strong><br>{{ $payment['expires_at'] ? \Carbon\Carbon::parse($payment['expires_at'])->format('d/m/Y H:i') : 'Sem data indicada' }}</td>
                    </tr>
                </table>

                <p style="margin:0 0 20px 0; color:#111827; background:#eff6ff; border:1px solid #bfdbfe; padding:14px; border-radius:10px; line-height:1.6;">
                    Assim que o pagamento for identificado, a equipa dará seguimento à sua reserva.
                    Se precisar de ajuda, pode responder a este email ou entrar em contacto connosco.
                </p>

                @foreach (($reservationOffer['sections'] ?? []) as $section)
                    <h2 style="font-size:16px; margin:0 0 10px;">{{ $section['title'] }}</h2>
                    <ul style="margin:0 0 18px 20px; padding:0; color:#374151; line-height:1.7;">
                        @foreach (($section['items'] ?? []) as $item)
                            <li style="margin-bottom:6px;">{{ $item }}</li>
                        @endforeach
                    </ul>
                @endforeach

                <p style="margin:20px 0 0 0; color:#6b7280; font-size:12px;">
                    {{ $reservationOffer['tax_message'] ?? '' }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
