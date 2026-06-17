<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Autos de viatura</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; color:#111827; font-family:Segoe UI, Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:760px; margin:0 auto; background:#ffffff; border-radius:12px; border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px; border-bottom:1px solid #e5e7eb;">
                <h1 style="margin:0; font-size:20px;">Autos de viatura</h1>
                <p style="margin:8px 0 0; color:#6b7280;">Segue em anexo o(s) PDF(s) do procedimento.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                @foreach($procedures as $procedure)
                    <div style="padding:16px; margin-bottom:14px; border:1px solid #e5e7eb; border-radius:10px;">
                        <h2 style="margin:0 0 10px; font-size:16px;">
                            {{ $typeLabels[$procedure->type] ?? $procedure->type }} #{{ $procedure->id }}
                        </h2>
                        <p style="margin:0 0 6px;"><strong>Motorista:</strong> {{ $procedure->driver?->name ?? data_get($procedure->driver_snapshot, 'name', '-') }}</p>
                        <p style="margin:0 0 6px;"><strong>Viatura:</strong> {{ $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate', '-') }}</p>
                        <p style="margin:0 0 12px;"><strong>Data:</strong> {{ optional($procedure->performed_at)->format('d/m/Y H:i') ?? '-' }}</p>
                        @php
                            $emailVideoItems = collect($procedure->video_items ?? [])->filter(fn ($video) => !empty($video['url']));
                        @endphp
                        @if($emailVideoItems->isNotEmpty())
                            <p style="margin:0 0 8px;"><strong>Videos:</strong></p>
                            <ul style="margin:0; padding-left:20px;">
                                @foreach($emailVideoItems as $video)
                                    <li>
                                        <a href="{{ $video['url'] }}">{{ $video['label'] ?? 'Video' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
                <p style="margin:0; color:#6b7280; font-size:12px;">Email gerado automaticamente pela Zentrum TVDE.</p>
            </td>
        </tr>
    </table>
</body>
</html>
