<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Procedimento #{{ $procedure->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .wrapper { padding: 20px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header__left, .header__right { display: table-cell; vertical-align: top; }
        .header__right { width: 190px; text-align: right; }
        .logo { max-width: 150px; max-height: 48px; margin-bottom: 8px; }
        .title { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .subtitle { color: #475569; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .summary td { border: 1px solid #cbd5e1; padding: 8px; }
        .section-title { font-size: 14px; font-weight: bold; margin: 18px 0 8px; }
        .checklist, .damage-table { width: 100%; border-collapse: collapse; }
        .checklist th, .checklist td, .damage-table th, .damage-table td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
        .photo-grid { margin-top: 10px; }
        .photo-grid img { width: 170px; height: auto; margin: 0 10px 10px 0; border: 1px solid #cbd5e1; border-radius: 6px; }
        .video-box { display: inline-block; width: 220px; min-height: 150px; margin: 0 10px 12px 0; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; vertical-align: top; word-break: break-all; }
        .video-box img { width: 96px; height: 96px; display: block; margin-top: 8px; }
        .signatures { width: 100%; margin-top: 24px; }
        .signatures td { width: 50%; vertical-align: top; padding-right: 12px; }
        .signature-box { border: 1px dashed #94a3b8; height: 120px; padding: 8px; }
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
                <strong>Operador:</strong><br>{{ $procedure->operator?->name ?? '-' }}
            </div>
        </div>

        <table class="summary">
            <tr>
                <td><strong>Viatura</strong><br>{{ $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate') }}</td>
                <td><strong>Motorista</strong><br>{{ $procedure->driver?->name ?? data_get($procedure->driver_snapshot, 'name') }}</td>
                <td><strong>Inicio efetivo</strong><br>{{ optional($procedure->allocation_effective_start_date)->format('d/m/Y') ?? '-' }}</td>
                <td><strong>Fim efetivo</strong><br>{{ optional($procedure->allocation_effective_end_date)->format('d/m/Y') ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Checklist</div>
        <table class="checklist">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Estado</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($procedure->checklist_payload ?? []) as $item)
                    <tr>
                        <td>{{ $item['label'] ?? '-' }}</td>
                        <td>{{ !empty($item['checked']) ? 'OK' : 'Nao validado' }}</td>
                        <td>
                            @if(!empty($item['value']))
                                {{ $item['value'] }}
                                @if(($item['value_type'] ?? null) === 'currency') EUR @endif
                                @if(($item['value_type'] ?? null) === 'percent') % @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Danos</div>
        <table class="damage-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Zona</th>
                    <th>Descricao</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($procedure->damage_items ?? []) as $item)
                    <tr>
                        <td>{{ ucfirst((string) ($item['type'] ?? '')) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst((string) ($item['zone'] ?? ''))) }}</td>
                        <td>{{ $item['description'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">Sem danos registados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @php
            $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
            $publicDataUri = function (?string $path) use ($publicDisk): ?string {
                if (empty($path) || ! $publicDisk->exists($path)) {
                    return null;
                }

                $mime = $publicDisk->mimeType($path) ?: 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode($publicDisk->get($path));
            };
            $generalPhotoUrls = collect($procedure->general_photo_paths ?? [])
                ->map(fn ($path) => $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null)
                ->filter()
                ->values();
            $guidedPhotoItems = collect($procedure->guided_photo_items ?? [])
                ->map(fn ($item, $key) => [
                    'key' => $key,
                    'label' => $item['label'] ?? $key,
                    'view' => $item['view'] ?? null,
                    'photo_url' => !empty($item['photo_path']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['photo_path']) : null,
                ])
                ->groupBy('view');
            $videoItems = collect($procedure->video_items ?? [])
                ->map(fn ($item) => [
                    'label' => $item['label'] ?? 'Video',
                    'url' => $item['url'] ?? (!empty($item['video_path']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($item['video_path']) : null),
                    'qr_url' => $publicDataUri($item['qr_path'] ?? null),
                ])
                ->filter(fn ($item) => !empty($item['url']))
                ->values();
        @endphp

        @if($guidedPhotoItems->isNotEmpty())
            <div class="section-title">Mapa fotografico</div>
            @foreach($guidedPhotoItems as $view => $items)
                <div style="font-weight: bold; margin: 8px 0 6px;">{{ ucfirst((string) $view) }}</div>
                <div class="photo-grid">
                    @foreach($items as $item)
                        <div style="display:inline-block; margin-right:10px; margin-bottom:10px;">
                            <div style="font-size:11px; margin-bottom:4px;">{{ $item['label'] }}</div>
                            @if(!empty($item['photo_url']))
                                <img src="{{ $item['photo_url'] }}" alt="{{ $item['label'] }}">
                            @else
                                <div style="width:145px; min-height:80px; border:1px solid #cbd5e1; padding:12px; font-size:11px;">Nao registado</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        <div class="section-title">Fotos gerais</div>
        @if($generalPhotoUrls->isNotEmpty())
            <div class="photo-grid">
                @foreach($generalPhotoUrls as $photoUrl)
                    <img src="{{ $photoUrl }}" alt="Foto">
                @endforeach
            </div>
        @else
            <div>Fotos gerais nao registadas.</div>
        @endif

        <div class="section-title">Videos</div>
        @if($videoItems->isNotEmpty())
            @foreach($videoItems as $video)
                <div class="video-box">
                    <strong>{{ $video['label'] }}</strong><br>
                    <a href="{{ $video['url'] }}">{{ $video['url'] }}</a>
                    @if(!empty($video['qr_url']))
                        <img src="{{ $video['qr_url'] }}" alt="QR {{ $video['label'] }}">
                    @endif
                </div>
            @endforeach
        @else
            <div>Videos nao registados.</div>
        @endif

        <div class="section-title">Observacoes</div>
        <div>{{ $procedure->notes ?: 'Sem observacoes adicionais.' }}</div>

        <table class="signatures">
            <tr>
                <td>
                    <div><strong>Assinatura do operador</strong></div>
                    <div class="signature-box">
                        <img src="{{ $procedure->operator_signature_data_url }}" alt="Assinatura do operador">
                    </div>
                </td>
                <td>
                    <div><strong>Assinatura do motorista</strong></div>
                    <div class="signature-box">
                        <img src="{{ $procedure->driver_signature_data_url }}" alt="Assinatura do motorista">
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
