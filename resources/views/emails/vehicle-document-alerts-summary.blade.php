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
            <h1 style="margin: 0 0 20px; font-size: 22px;">Alertas de documentos e revisões de viaturas</h1>
            <p>Ola {{ $recipient->name }},</p>
            <p>{{ $alerts->count() }} documentos e {{ $revisions->count() }} revisões com aviso em {{ $alertDate->format('d/m/Y') }}.</p>

            @if ($alerts->isNotEmpty())
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
            @endif

            @if ($revisions->isNotEmpty())
                <h2 style="font-size: 18px;">Revisões próximas e vencidas</h2>
                @foreach ($revisions as $revision)
                    <div style="border-top: 1px solid #d1d5db; padding: 16px 0;">
                        <strong>{{ $revision['vehicle']->license_plate }} — {{ $revision['overdue'] ? 'Vencida' : 'Próxima' }}</strong>
                        <p>Próxima data: {{ $revision['due_at']?->format('d/m/Y') ?? 'Sem limite por data' }}<br>
                        Limite: {{ $revision['next_odometer'] !== null ? number_format($revision['next_odometer'], 0, ',', '.').' km' : 'Sem limite por quilómetros' }}<br>
                        @if ($revision['reading']['value'] !== null)
                            Leitura: {{ number_format($revision['reading']['value'], 0, ',', '.') }} km — {{ $revision['reading']['source'] }}
                            @if ($revision['reading']['date'])
                                ({{ \Illuminate\Support\Carbon::parse($revision['reading']['date'])->format('d/m/Y H:i') }})
                            @endif
                        @else
                            Quilometragem indisponível
                        @endif
                        <br>Agendamento: {{ $revision['scheduled_at']?->format('d/m/Y') ?? 'Por agendar' }}</p>
                        <p>{{ implode(' · ', $revision['reasons']) }}</p>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</body>
</html>
