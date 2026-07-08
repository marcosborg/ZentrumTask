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
    $vehicleMake = $procedure->vehicle?->make ?? data_get($procedure->vehicle_snapshot, 'make');
    $vehicleModel = trim((string) collect([
        $procedure->vehicle?->model ?? data_get($procedure->vehicle_snapshot, 'model'),
        $procedure->vehicle?->trim ?? data_get($procedure->vehicle_snapshot, 'trim'),
    ])->filter()->implode(' '));
    $licensePlate = $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate');
    $damageItems = collect($procedure->damage_items ?? [])
        ->map(fn ($item, $index) => [
            'number' => $index + 1,
            'type' => ucfirst((string) ($item['type'] ?? 'Dano')),
            'zone' => str_replace('_', ' ', ucfirst((string) ($item['zone'] ?? 'Zona nao indicada'))),
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
        ->map(fn ($item, $index) => [
            'number' => $index + 1,
            'type' => $faultTypeLabels[$item['type'] ?? ''] ?? ucfirst((string) ($item['type'] ?? 'Avaria')),
            'severity' => $severityLabels[$item['severity'] ?? ''] ?? null,
            'description' => $item['description'] ?? 'Sem descricao adicional.',
        ])
        ->values();
    $guidedPhotoItems = collect($procedure->guided_photo_items ?? [])
        ->map(fn ($item, $key) => [
            'key' => $key,
            'label' => $item['label'] ?? $key,
            'view' => $item['view'] ?? 'geral',
            'photo_src' => $publicDataUri($item['photo_path'] ?? null),
        ])
        ->filter(fn ($item) => filled($item['photo_src']))
        ->groupBy('view');
    $generalPhotoItems = collect($procedure->general_photo_paths ?? [])
        ->map(fn ($path, $index) => [
            'label' => 'Foto geral '.($index + 1),
            'photo_src' => $publicDataUri($path),
        ])
        ->filter(fn ($item) => filled($item['photo_src']))
        ->values();
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
    <title>Ficha de oficina #{{ $procedure->id }}</title>
    <style>
        @page { margin: 24px 24px 28px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #102033; font-size: 11px; line-height: 1.45; }
        .header { display: table; width: 100%; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 2px solid #d7e0ea; }
        .header__left, .header__right { display: table-cell; vertical-align: top; }
        .header__right { width: 210px; text-align: right; color: #526174; }
        .logo { max-width: 150px; max-height: 48px; margin-bottom: 8px; }
        .eyebrow { color: #5c6a7c; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 3px; }
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
        .photo { width: 100%; height: 118px; object-fit: cover; border: 1px solid #d4dde8; background: #f5f7fa; }
        .photo--large { height: 150px; }
        .placeholder { width: 100%; height: 118px; padding-top: 45px; text-align: center; color: #7b8798; border: 1px dashed #b9c6d6; background: #f7f9fb; }
        .description { color: #29394d; margin-top: 7px; white-space: pre-line; }
        .field-table { width: 100%; border-collapse: collapse; border: 1px solid #d4dde8; margin-bottom: 10px; page-break-inside: avoid; }
        .field-table td { border: 1px solid #d4dde8; padding: 8px; vertical-align: top; height: 34px; }
        .field-table .field-label { width: 30%; color: #5c6a7c; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; }
        .line { display: block; height: 22px; border-bottom: 1px solid #cbd5e1; }
        .checks { color: #29394d; line-height: 1.8; }
        .video-layout { width: 100%; border-collapse: collapse; }
        .video-layout td { vertical-align: top; }
        .video-qr { width: 76px; padding-right: 9px; }
        .video-qr img { width: 68px; height: 68px; border: 1px solid #d4dde8; }
        .video-link { font-size: 8px; line-height: 1.25; color: #36577c; word-break: break-all; overflow-wrap: anywhere; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header__left">
            @if($logo)
                <img src="{{ $logo }}" alt="Zentrum TVDE" class="logo">
            @endif
            <div class="eyebrow">Ficha de oficina</div>
            <div class="title">Reparacao de danos</div>
            <div class="subtitle">Procedimento #{{ $procedure->id }} | {{ optional($procedure->performed_at)->format('d/m/Y H:i') }}</div>
        </div>
        <div class="header__right">
            <span class="label">Origem</span>
            {{ $typeLabels[$procedure->type] ?? $procedure->type }}<br>
            <span class="label" style="margin-top: 8px;">Danos registados</span>
            {{ $damageItems->count() }} dano(s) / {{ $faultItems->count() }} avaria(s)
        </div>
    </div>

    <table class="summary">
        <tr>
            <td><span class="label">Marca</span>{{ $vehicleMake ?: '-' }}</td>
            <td><span class="label">Modelo</span>{{ $vehicleModel ?: '-' }}</td>
            <td><span class="label">Matricula</span>{{ $licensePlate ?: '-' }}</td>
            <td><span class="label">Quilometros</span>{{ $procedure->vehicle?->current_odometer ?? data_get($procedure->vehicle_snapshot, 'current_odometer', '-') }}</td>
        </tr>
        <tr>
            <td><span class="label">Motorista</span>{{ $procedure->driver?->name ?? data_get($procedure->driver_snapshot, 'name', '-') }}</td>
            <td><span class="label">Contacto</span>{{ $procedure->driver?->phone ?? data_get($procedure->driver_snapshot, 'phone', '-') }}</td>
            <td><span class="label">Operador</span>{{ $procedure->operator?->name ?? '-' }}</td>
            <td><span class="label">Notas</span>{{ $procedure->notes ?: '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Campos da oficina</div>
    <table class="field-table">
        <tr>
            <td class="field-label">Oficina</td>
            <td><span class="line"></span></td>
            <td class="field-label">Tecnico responsavel</td>
            <td><span class="line"></span></td>
        </tr>
        <tr>
            <td class="field-label">Entrada em oficina</td>
            <td><span class="line"></span></td>
            <td class="field-label">Previsao de entrega</td>
            <td><span class="line"></span></td>
        </tr>
        <tr>
            <td class="field-label">Orcamento</td>
            <td><span class="line"></span></td>
            <td class="field-label">Autorizado por</td>
            <td><span class="line"></span></td>
        </tr>
    </table>
    <table class="field-table">
        <tr>
            <td class="field-label">Estado</td>
            <td class="checks">[ ] Recebido &nbsp;&nbsp; [ ] Em diagnostico &nbsp;&nbsp; [ ] Aguarda pecas &nbsp;&nbsp; [ ] Em reparacao &nbsp;&nbsp; [ ] Concluido</td>
        </tr>
        <tr>
            <td class="field-label">Diagnostico</td>
            <td><span class="line"></span><span class="line"></span><span class="line"></span></td>
        </tr>
        <tr>
            <td class="field-label">Pecas / trabalhos</td>
            <td><span class="line"></span><span class="line"></span><span class="line"></span></td>
        </tr>
        <tr>
            <td class="field-label">Observacoes finais</td>
            <td><span class="line"></span><span class="line"></span></td>
        </tr>
    </table>

    <div class="section-title">Danos a reparar</div>
    @if($damageItems->isNotEmpty())
        <table class="card-table">
            @foreach($damageItems->chunk(2) as $row)
                <tr>
                    @foreach($row as $item)
                        <td class="card-cell">
                            <div class="card-title">#{{ $item['number'] }} {{ $item['type'] }}</div>
                            <div class="card-meta">{{ $item['zone'] }}</div>
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
        <div class="section-note">Sem danos registados neste procedimento.</div>
    @endif

    <div class="section-title">Avarias reportadas</div>
    @if($faultItems->isNotEmpty())
        <table class="card-table">
            @foreach($faultItems->chunk(2) as $row)
                <tr>
                    @foreach($row as $item)
                        <td class="card-cell">
                            <div class="card-title">#{{ $item['number'] }} {{ $item['type'] }}</div>
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
        <div class="section-note">Sem avarias registadas neste procedimento.</div>
    @endif

    <div class="section-title">Videos recolhidos</div>
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
        <div class="section-note">Sem videos registados.</div>
    @endif

    <div class="section-title">Imagens recolhidas</div>
    @foreach($guidedPhotoItems as $view => $items)
        <div class="card-meta">{{ ucfirst((string) $view) }}</div>
        <table class="card-table">
            @foreach($items->values()->chunk(3) as $row)
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
    @endforeach
    @if($generalPhotoItems->isNotEmpty())
        <div class="card-meta">Fotos gerais</div>
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
    @endif
    @if($guidedPhotoItems->isEmpty() && $generalPhotoItems->isEmpty())
        <div class="section-note">Sem imagens adicionais registadas.</div>
    @endif
</body>
</html>
