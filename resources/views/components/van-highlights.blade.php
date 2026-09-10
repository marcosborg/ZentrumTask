@php($rentalVans = \App\Models\RentalVan::query()->where('status', 'published')->where('featured', true)->orderBy('name')->limit(4)->get())
@push('styles') @vite('resources/css/van-rentals.css') @endpush
<section class="van-area van-section" id="aluguer-carrinhas">
    <div class="container">
        <div class="vr:flex vr:flex-wrap vr:items-end vr:justify-between vr:gap-4 vr:mb-6">
            <div><span class="van-kicker">Mercadorias & mudanças</span><h2 class="vr:mt-3">Mais espaço para os seus planos.</h2><p>Carrinhas à hora, com ou sem motorista. Escolha a solução para o seu transporte.</p></div>
            <a href="{{ route('van-rentals.index') }}" class="van-button secondary">Ver carrinhas ↗</a>
        </div>
        @if ($rentalVans->isNotEmpty())
            <div class="vr:grid vr:grid-cols-1 vr:gap-5 vr:md:grid-cols-2 vr:xl:grid-cols-4">@foreach ($rentalVans as $van)<x-van-card :van="$van" />@endforeach</div>
        @else
            <div class="van-notice">Estamos a preparar as carrinhas disponíveis para aluguer. Consulte esta área em breve.</div>
        @endif
    </div>
</section>
