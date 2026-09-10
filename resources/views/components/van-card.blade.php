@props(['van'])
<article class="van-card">
    @if ($van->imageUrl()) <a href="{{ $van->publicUrl() }}" tabindex="-1" aria-hidden="true"><img src="{{ $van->imageUrl() }}" alt="" loading="lazy"></a> @endif
    <div class="van-card-body">
        <div class="van-tags">@if ($van->self_drive)<span class="van-tag">Sem motorista</span>@endif @if ($van->with_driver)<span class="van-tag">Com motorista</span>@endif</div>
        <h3><a href="{{ $van->publicUrl() }}" style="color:inherit;text-decoration:none">{{ $van->name }}</a></h3>
        <p class="van-small vr:m-0">{{ number_format($van->volume_m3, 1, ',', ' ') }} m³ de volume · {{ number_format($van->payload_kg, 0, ',', ' ') }} kg de carga</p>
        <div class="vr:mt-auto"><span class="van-small">Desde</span> <strong class="van-price">{{ number_format($van->startingRate() / 100, 2, ',', ' ') }} €</strong> / hora<br><span class="van-small">IVA incluído · mínimo {{ $van->minimum_hours }} h</span></div>
        <a class="van-button" href="{{ $van->publicUrl() }}">Ver carrinha e disponibilidade <span aria-hidden="true">↗</span></a>
    </div>
</article>
