<div class="space-y-4">
    @if (! $reference)
        <p>Não existe uma entrega concluída anterior à data desta inspeção para esta viatura.</p>
    @else
        <p><strong>Entrega #{{ $reference['id'] }}</strong> · {{ $reference['performed_at_label'] }} · {{ $reference['driver_name'] }}</p>
        <p>Referência para comparação. Os danos anteriores não são copiados para esta inspeção; regista os danos novos ou alterações no estado atual.</p>
        <details open>
            <summary class="font-semibold">Danos já registados na entrega ({{ count($reference['damage_items']) }})</summary>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($reference['damage_items'] as $damage)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <strong>{{ $damage['zone'] }} · {{ $damage['type'] }}</strong>
                        <p>{{ $damage['description'] }}</p>
                        @if ($damage['photo_url'])
                            <a href="{{ $damage['photo_url'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $damage['photo_url'] }}" alt="Dano registado na entrega" loading="lazy" style="max-height:240px;max-width:100%;object-fit:contain">
                            </a>
                        @endif
                    </div>
                @empty
                    <p>Sem danos registados na última entrega. Isto não confirma a ausência de danos.</p>
                @endforelse
            </div>
        </details>
        <details>
            <summary class="font-semibold">Fotos da última entrega ({{ count($reference['photos']) }})</summary>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($reference['photos'] as $photo)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <strong>{{ $photo['label'] }}</strong>
                        <div class="grid grid-cols-2 gap-3">
                            <div><p>Na entrega</p><a href="{{ $photo['url'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }} na entrega" loading="lazy" style="max-height:240px;max-width:100%;object-fit:contain">
                            </a></div>
                            @php
                                $currentPhoto = $currentPhotos[$photo['key']]['photo_path'] ?? $currentPhotos[$photo['key']]['photo'] ?? null;
                                if (is_array($currentPhoto)) {
                                    $currentPhoto = reset($currentPhoto) ?: null;
                                }
                                $currentUrl = $currentPhoto instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                    ? $currentPhoto->temporaryUrl()
                                    : (is_string($currentPhoto) && $currentPhoto !== '' ? Storage::disk('public')->url($currentPhoto) : null);
                            @endphp
                            @if ($currentUrl)
                                <div><p>Na inspeção</p><a href="{{ $currentUrl }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $currentUrl }}" alt="{{ $photo['label'] }} na inspeção" loading="lazy" style="max-height:240px;max-width:100%;object-fit:contain">
                                </a></div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p>Sem fotografias registadas na última entrega.</p>
                @endforelse
            </div>
        </details>
    @endif
</div>
