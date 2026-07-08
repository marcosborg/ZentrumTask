@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Number;

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
    $generalPhotoUrls = collect($procedure->general_photo_paths ?? [])
        ->map(fn ($path) => $path ? Storage::disk('public')->url($path) : null)
        ->filter()
        ->values();
    $guidedPhotoItems = collect($procedure->guided_photo_items ?? [])
        ->map(fn ($item, $key) => [
            'key' => $key,
            'label' => $item['label'] ?? $key,
            'view' => $item['view'] ?? null,
            'required' => (bool) ($item['required'] ?? false),
            'photo_url' => !empty($item['photo_path']) ? Storage::disk('public')->url($item['photo_path']) : null,
        ])
        ->groupBy('view');
    $videoItems = collect($procedure->video_items ?? [])
        ->map(fn ($item) => [
            'label' => $item['label'] ?? 'Video',
            'url' => $item['url'] ?? (!empty($item['video_path']) ? Storage::disk('public')->url($item['video_path']) : null),
            'qr_url' => !empty($item['qr_path']) ? Storage::disk('public')->url($item['qr_path']) : null,
        ])
        ->filter(fn ($item) => !empty($item['url']))
        ->values();
@endphp

<div class="handover-doc">
    <div class="handover-doc__hero">
        <div>
            <div class="handover-doc__eyebrow">{{ ($typeLabels[$procedure->type] ?? $procedure->type) === 'Entrega' ? 'Entrega de viatura' : 'Devolucao de viatura' }}</div>
            <h1>{{ $typeLabels[$procedure->type] ?? $procedure->type }}</h1>
            <p>Executado em {{ optional($procedure->performed_at)->format('d/m/Y H:i') }}</p>
        </div>
        <div class="handover-doc__meta">
            <div><strong>Viatura</strong><span>{{ $procedure->vehicle?->license_plate ?? data_get($procedure->vehicle_snapshot, 'license_plate') }}</span></div>
            <div><strong>Motorista</strong><span>{{ $procedure->driver?->name ?? data_get($procedure->driver_snapshot, 'name') }}</span></div>
            <div><strong>Operador</strong><span>{{ $procedure->operator?->name ?? '-' }}</span></div>
            @if($procedure->allocation_effective_start_date)
                <div><strong>Inicio efetivo</strong><span>{{ optional($procedure->allocation_effective_start_date)->format('d/m/Y') }}</span></div>
            @endif
            @if($procedure->allocation_effective_end_date)
                <div><strong>Fim efetivo</strong><span>{{ optional($procedure->allocation_effective_end_date)->format('d/m/Y') }}</span></div>
            @endif
        </div>
    </div>

    <div class="handover-doc__grid">
        <section>
            <h2>Checklist</h2>
            <table class="handover-doc__table">
                <tbody>
                    @foreach(($procedure->checklist_payload ?? []) as $item)
                        <tr>
                            <td>{{ $item['label'] ?? '-' }}</td>
                            <td>{{ !empty($item['checked']) ? 'OK' : 'Nao validado' }}</td>
                            <td>
                                @if(!empty($item['value']))
                                    {{ $item['value'] }}
                                    @if(!empty($item['value_type']) && $item['value_type'] === 'currency')
                                        EUR
                                    @elseif(!empty($item['value_type']) && $item['value_type'] === 'percent')
                                        %
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2>Danos</h2>
            @if(!empty($procedure->damage_items))
                <div class="handover-doc__stack">
                    @foreach($procedure->damage_items as $item)
                        <div class="handover-doc__damage">
                            <div class="handover-doc__damage-head">
                                <strong>{{ ucfirst((string) ($item['type'] ?? '')) }}</strong>
                                <span>{{ str_replace('_', ' ', ucfirst((string) ($item['zone'] ?? ''))) }}</span>
                            </div>
                            <p>{{ $item['description'] ?? 'Sem descricao adicional.' }}</p>
                            @if(!empty($item['photo_path']))
                                <img src="{{ Storage::disk('public')->url($item['photo_path']) }}" alt="Foto do dano" class="handover-doc__photo">
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="handover-doc__muted">Sem danos registados.</p>
            @endif
        </section>
    </div>

    <section>
        <h2>Avarias</h2>
        @if($faultItems->isNotEmpty())
            <div class="handover-doc__stack">
                @foreach($faultItems as $item)
                    <div class="handover-doc__damage">
                        <div class="handover-doc__damage-head">
                            <strong>{{ $item['type'] }}</strong>
                            <span>{{ $item['severity'] ? 'Prioridade: '.$item['severity'] : 'Prioridade nao indicada' }}</span>
                        </div>
                        <p>{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="handover-doc__muted">Sem avarias registadas.</p>
        @endif
    </section>

    <section>
        <h2>Mapa fotografico</h2>
        @if($guidedPhotoItems->isNotEmpty())
            <div class="handover-doc__stack">
                @foreach($guidedPhotoItems as $view => $items)
                    <div class="handover-doc__damage">
                        <div class="handover-doc__damage-head">
                            <strong>{{ ucfirst((string) $view) }}</strong>
                            <span>{{ $items->filter(fn ($item) => !empty($item['photo_url']))->count() }} foto(s)</span>
                        </div>
                        <div class="handover-doc__photos">
                            @foreach($items as $item)
                                <div>
                                    <div class="handover-doc__muted" style="margin-bottom: 8px;">{{ $item['label'] }}</div>
                                    @if(!empty($item['photo_url']))
                                        <img src="{{ $item['photo_url'] }}" alt="{{ $item['label'] }}" class="handover-doc__photo">
                                    @else
                                        <div class="handover-doc__photo" style="display:flex;align-items:center;justify-content:center;min-height:140px;background:#f8fafc;">Nao registado</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="handover-doc__muted">Sem fotografias guiadas.</p>
        @endif
    </section>

    <section>
        <h2>Fotos gerais</h2>
        @if($generalPhotoUrls->isNotEmpty())
            <div class="handover-doc__photos">
                @foreach($generalPhotoUrls as $photoUrl)
                    <img src="{{ $photoUrl }}" alt="Foto da viatura" class="handover-doc__photo">
                @endforeach
            </div>
        @else
            <p class="handover-doc__muted">Sem fotos gerais anexadas.</p>
        @endif
    </section>

    <section>
        <h2>Videos</h2>
        @if($videoItems->isNotEmpty())
            <div class="handover-doc__photos">
                @foreach($videoItems as $video)
                    <div class="handover-doc__damage">
                        <div class="handover-doc__damage-head">
                            <strong>{{ $video['label'] }}</strong>
                            <a href="{{ $video['url'] }}" target="_blank" rel="noopener">Abrir video</a>
                        </div>
                        @if(!empty($video['qr_url']))
                            <img src="{{ $video['qr_url'] }}" alt="QR {{ $video['label'] }}" class="handover-doc__photo" style="max-width:140px;">
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="handover-doc__muted">Videos nao registados.</p>
        @endif
    </section>

    <section>
        <h2>Notas</h2>
        <p>{{ $procedure->notes ?: 'Sem observacoes adicionais.' }}</p>
    </section>

    <section class="handover-doc__signatures">
        <div>
            <h2>Assinatura do operador</h2>
            <img src="{{ $procedure->operator_signature_data_url }}" alt="Assinatura do operador" class="handover-doc__signature">
        </div>
        <div>
            <h2>Assinatura do motorista</h2>
            <img src="{{ $procedure->driver_signature_data_url }}" alt="Assinatura do motorista" class="handover-doc__signature">
        </div>
    </section>
</div>
