@php
    $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
    $publicDataUri = function (?string $path) use ($publicDisk): ?string {
        if (empty($path) || ! $publicDisk->exists($path)) {
            return null;
        }

        $mime = $publicDisk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($publicDisk->get($path));
    };
    $qrDataUri = function (?string $url, ?string $path = null) use ($publicDataUri): ?string {
        $storedQr = $publicDataUri($path);

        if ($storedQr) {
            return $storedQr;
        }

        if (empty($url)) {
            return null;
        }

        try {
            return (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                'imageBase64' => true,
                'svgUseFillAttributes' => true,
            ])))->render($url);
        } catch (\Throwable $exception) {
            return null;
        }
    };
    $formatChecklistValue = function (array $item): string {
        if (blank($item['value'] ?? null)) {
            return '-';
        }

        return trim((string) $item['value'].' '.match ($item['value_type'] ?? null) {
            'currency' => 'EUR',
            'percent' => '%',
            default => '',
        });
    };
    $checklistItems = collect($procedure->checklist_payload ?? [])->values();
    $damageItems = collect($procedure->damage_items ?? [])
        ->map(fn ($item) => [
            'type' => ucfirst((string) ($item['type'] ?? '')),
            'zone' => str_replace('_', ' ', ucfirst((string) ($item['zone'] ?? ''))),
            'description' => $item['description'] ?? 'Sem descricao adicional.',
            'photo_src' => $publicDataUri($item['photo_path'] ?? null),
        ])
        ->values();
    $faultTypeLabels = \App\Support\VehicleHandoverDefinition::faultTypes();
    $severityLabels = [
        'low' => 'Baixa',
        'medium' => 'Media',
        'high' => 'Alta',
        'immobilized' => 'Viatura imobilizada',
    ];
    $faultItems = collect($procedure->fault_items ?? [])
        ->map(fn ($item) => [
            'type' => $faultTypeLabels[$item['type'] ?? ''] ?? ucfirst((string) ($item['type'] ?? 'Avaria')),
            'severity' => $severityLabels[$item['severity'] ?? ''] ?? null,
            'description' => $item['description'] ?? 'Sem descricao adicional.',
        ])
        ->values();
    $generalPhotoItems = collect($procedure->general_photo_paths ?? [])
        ->map(fn ($path, $index) => [
            'label' => 'Foto geral '.($index + 1),
            'photo_src' => $publicDataUri($path),
        ])
        ->filter(fn ($item) => filled($item['photo_src']))
        ->values();
    $guidedPhotoItems = collect($procedure->guided_photo_items ?? [])
        ->map(fn ($item, $key) => [
            'key' => $key,
            'label' => $item['label'] ?? $key,
            'view' => $item['view'] ?? 'geral',
            'photo_src' => $publicDataUri($item['photo_path'] ?? null),
        ])
        ->groupBy('view');
    $videoItems = collect($procedure->video_items ?? [])
        ->map(function ($item) use ($qrDataUri): array {
            $url = $item['url'] ?? (! empty($item['video_path']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['video_path']) : null);

            return [
                'label' => $item['label'] ?? 'Video',
                'url' => $url,
                'qr_src' => $qrDataUri($url, $item['qr_path'] ?? null),
            ];
        })
        ->filter(fn ($item) => filled($item['url']))
        ->values();
@endphp

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Procedimento #{{ $procedure->id }}</title>
    <style>
        @page { margin: 24px 24px 28px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #102033; font-size: 11px; line-height: 1.45; }
        .wrapper { width: 100%; }
        .header { display: table; width: 100%; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 2px solid #d7e0ea; }
        .header__left, .header__right { display: table-cell; vertical-align: top; }
        .header__right { width: 190px; text-align: right; color: #526174; }
        .logo { max-width: 150px; max-height: 48px; margin-bottom: 8px; }
        .title { font-size: 24px; font-weight: bold; color: #0f1f33; margin-bottom: 4px; }
        .subtitle { color: #526174; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 14px; border: 1px solid #d4dde8; }
        .summary td { width: 25%; padding: 9px 10px; border-right: 1px solid #d4dde8; vertical-align: top; }
        .summary td:last-child { border-right: 0; }
        .label { display: block; color: #5c6a7c; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
        .section-title { font-size: 15px; font-weight: bold; color: #0f1f33; margin: 18px 0 8px; page-break-after: avoid; }
        .section-note { color: #607086; margin: 3px 0 8px; }
        .card-table { width: 100%; border-collapse: separate; border-spacing: 8px; margin-left: -8px; margin-right: -8px; }
        .card-cell { width: 50%; vertical-align: top; border: 1px solid #d4dde8; padding: 9px; page-break-inside: avoid; }
        .card-cell--third { width: 33.333%; }
        .card-cell--empty { border: 0; padding: 0; }
        .card-title { font-weight: bold; font-size: 12px; color: #0f1f33; margin-bottom: 6px; }
        .card-meta { color: #607086; font-size: 10px; margin-bottom: 7px; }
        .badge { display: inline-block; padding: 2px 7px; border: 1px solid #c9d4e2; color: #45566b; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge--ok { border-color: #9fc7ad; color: #166534; background: #f0f8f3; }
        .badge--warn { border-color: #e0b7b7; color: #991b1b; background: #fff5f5; }
        .value { margin-top: 7px; color: #0f1f33; font-size: 11px; }
        .photo { display: block; width: auto; height: auto; max-width: 100%; max-height: 118px; margin: 0 auto; border: 1px solid #d4dde8; background: #f5f7fa; }
        .photo--large { max-height: 150px; }
        .placeholder { width: 100%; height: 118px; padding-top: 45px; text-align: center; color: #7b8798; border: 1px dashed #b9c6d6; background: #f7f9fb; }
        .description { color: #29394d; margin-top: 7px; white-space: pre-line; }
        .video-layout { width: 100%; border-collapse: collapse; }
        .video-layout td { vertical-align: top; }
        .video-qr { width: 76px; padding-right: 9px; }
        .video-qr img { width: 68px; height: 68px; border: 1px solid #d4dde8; }
        .video-link { font-size: 8px; line-height: 1.25; color: #36577c; word-break: break-all; overflow-wrap: anywhere; }
        .signatures { width: 100%; margin-top: 22px; border-collapse: separate; border-spacing: 10px 0; }
        .signatures td { width: 50%; vertical-align: top; border: 1px solid #d4dde8; padding: 9px; }
        .signature-box { height: 115px; text-align: center; }
        .signature-box img { max-width: 100%; max-height: 100px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="header__left">
                @if($logo)
                    <img src="{{ $logo }}" alt="Zentrum TVDE" class="logo">
                @endif
                <div class="title">{{ $typeLabels[$procedure->type] ?? $procedure->type }}</div>
                <div class="subtitle">Procedimento #{{ $procedure->id }} | {{ optional($procedure->performed_at)->format('d/m/Y H:i') }}</div>
            </div>
            <div class="header__right">
                <span class="label">Operador</span>
                {{ $procedure->operator?->name ?? '-' }}
            </div>
        </div>

        <table class="summary">
            <tr>
                <td><span class="label">Viatura</span>{{ $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate') }}</td>
                <td><span class="label">Motorista</span>{{ $procedure->driver?->name ?? data_get($procedure->driver_snapshot, 'name') }}</td>
                <td><span class="label">Inicio efetivo</span>{{ optional($procedure->allocation_effective_start_date)->format('d/m/Y') ?? '-' }}</td>
                <td><span class="label">Fim efetivo</span>{{ optional($procedure->allocation_effective_end_date)->format('d/m/Y') ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Checklist</div>
        @if($checklistItems->isNotEmpty())
            <table class="card-table">
                @foreach($checklistItems->chunk(2) as $row)
                    <tr>
                        @foreach($row as $item)
                            <td class="card-cell">
                                <div class="card-title">{{ $item['label'] ?? '-' }}</div>
                                <span class="badge {{ ! empty($item['checked']) ? 'badge--ok' : 'badge--warn' }}">
                                    {{ ! empty($item['checked']) ? 'OK' : 'Nao validado' }}
                                </span>
                                <div class="value">{{ $formatChecklistValue($item) }}</div>
                            </td>
                        @endforeach
                        @if($row->count() === 1)
                            <td class="card-cell card-cell--empty">&nbsp;</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <div class="section-note">Sem checklist registada.</div>
        @endif

        <div class="section-title">Danos</div>
        @if($damageItems->isNotEmpty())
            <table class="card-table">
                @foreach($damageItems->chunk(2) as $row)
                    <tr>
                        @foreach($row as $item)
                            <td class="card-cell">
                                <div class="card-title">{{ $item['type'] ?: 'Dano' }}</div>
                                <div class="card-meta">{{ $item['zone'] ?: 'Zona nao indicada' }}</div>
                                @if($item['photo_src'])
                                    <img src="{{ $item['photo_src'] }}" alt="Foto do dano" class="photo photo--large">
                                @else
                                    <div class="placeholder">Sem foto do dano</div>
                                @endif
                                <div class="description">{{ $item['description'] }}</div>
                            </td>
                        @endforeach
                        @if($row->count() === 1)
                            <td class="card-cell card-cell--empty">&nbsp;</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <div class="section-note">Sem danos registados.</div>
        @endif

        <div class="section-title">Avarias</div>
        @if($faultItems->isNotEmpty())
            <table class="card-table">
                @foreach($faultItems->chunk(2) as $row)
                    <tr>
                        @foreach($row as $item)
                            <td class="card-cell">
                                <div class="card-title">{{ $item['type'] }}</div>
                                <div class="card-meta">{{ $item['severity'] ? 'Prioridade: '.$item['severity'] : 'Prioridade nao indicada' }}</div>
                                <div class="description">{{ $item['description'] }}</div>
                            </td>
                        @endforeach
                        @if($row->count() === 1)
                            <td class="card-cell card-cell--empty">&nbsp;</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <div class="section-note">Sem avarias registadas.</div>
        @endif

        <div class="section-title">Mapa fotografico</div>
        @if($guidedPhotoItems->isNotEmpty())
            @foreach($guidedPhotoItems as $view => $items)
                <div class="card-meta">{{ ucfirst((string) $view) }}</div>
                <table class="card-table">
                    @foreach($items->values()->chunk(3) as $row)
                        <tr>
                            @foreach($row as $item)
                                <td class="card-cell card-cell--third">
                                    <div class="card-title">{{ $item['label'] }}</div>
                                    @if($item['photo_src'])
                                        <img src="{{ $item['photo_src'] }}" alt="{{ $item['label'] }}" class="photo">
                                    @else
                                        <div class="placeholder">Nao registado</div>
                                    @endif
                                </td>
                            @endforeach
                            @for($i = $row->count(); $i < 3; $i++)
                                <td class="card-cell card-cell--third card-cell--empty">&nbsp;</td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            @endforeach
        @else
            <div class="section-note">Sem fotografias guiadas.</div>
        @endif

        <div class="section-title">Fotos gerais</div>
        @if($generalPhotoItems->isNotEmpty())
            <table class="card-table">
                @foreach($generalPhotoItems->chunk(3) as $row)
                    <tr>
                        @foreach($row as $item)
                            <td class="card-cell card-cell--third">
                                <div class="card-title">{{ $item['label'] }}</div>
                                <img src="{{ $item['photo_src'] }}" alt="{{ $item['label'] }}" class="photo">
                            </td>
                        @endforeach
                        @for($i = $row->count(); $i < 3; $i++)
                            <td class="card-cell card-cell--third card-cell--empty">&nbsp;</td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        @else
            <div class="section-note">Fotos gerais nao registadas.</div>
        @endif

        <div class="section-title">Videos</div>
        @if($videoItems->isNotEmpty())
            <table class="card-table">
                @foreach($videoItems->chunk(2) as $row)
                    <tr>
                        @foreach($row as $video)
                            <td class="card-cell">
                                <div class="card-title">{{ $video['label'] }}</div>
                                <table class="video-layout">
                                    <tr>
                                        <td class="video-qr">
                                            @if($video['qr_src'])
                                                <img src="{{ $video['qr_src'] }}" alt="QR {{ $video['label'] }}">
                                            @endif
                                        </td>
                                        <td>
                                            <div class="card-meta">Link do video</div>
                                            <a href="{{ $video['url'] }}" class="video-link">{{ $video['url'] }}</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        @endforeach
                        @if($row->count() === 1)
                            <td class="card-cell card-cell--empty">&nbsp;</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <div class="section-note">Videos nao registados.</div>
        @endif

        <div class="section-title">Observacoes</div>
        <div>{{ $procedure->notes ?: 'Sem observacoes adicionais.' }}</div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="card-title">Assinatura do operador</div>
                    <div class="signature-box">
                        <img src="{{ $procedure->operator_signature_data_url }}" alt="Assinatura do operador">
                    </div>
                </td>
                <td>
                    <div class="card-title">Assinatura do motorista</div>
                    <div class="signature-box">
                        <img src="{{ $procedure->driver_signature_data_url }}" alt="Assinatura do motorista">
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
