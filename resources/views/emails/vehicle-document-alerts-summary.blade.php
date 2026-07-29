<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumo diario de alertas</title>
</head>
<body style="margin: 0; background: #f3f4f6; color: #111827; font-family: Arial, sans-serif;">
    <div style="max-width: 680px; margin: 0 auto; padding: 32px 16px;">
        <div style="background: #ffffff; border-radius: 12px; padding: 28px;">
            <h1 style="margin: 0 0 20px; font-size: 22px;">Alertas de documentos de viaturas</h1>
            <p>Ola {{ $recipient->name }},</p>
            <p>Foram criados {{ $alerts->count() }} alertas em {{ $alertDate->format('d/m/Y') }}.</p>

            <table style="border-collapse: collapse; margin: 20px 0; width: 100%;">
                <thead>
                    <tr>
                        <th style="border-bottom: 1px solid #d1d5db; padding: 8px; text-align: left;">Viatura</th>
                        <th style="border-bottom: 1px solid #d1d5db; padding: 8px; text-align: left;">Documento</th>
                        <th style="border-bottom: 1px solid #d1d5db; padding: 8px; text-align: left;">Validade</th>
                        <th style="border-bottom: 1px solid #d1d5db; padding: 8px; text-align: left;">Alerta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($alerts as $alert)
                        <tr>
                            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $alert->document->vehicle?->license_plate ?? '-' }}</td>
                            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $alert->document->title }}</td>
                            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $alert->document->expires_at?->format('d/m/Y') ?? '-' }}</td>
                            <td style="border-bottom: 1px solid #e5e7eb; padding: 8px;">{{ $alert->message }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
