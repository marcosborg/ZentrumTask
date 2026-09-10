@extends('website.layout')
@section('title', 'Aluguer de carrinhas para mercadorias e mudanças | Zentrum')
@push('head')<meta name="description" content="Aluguer de carrinhas à hora para mercadorias e mudanças, com ou sem motorista. Consulte características, preços e disponibilidade.">@endpush
@push('styles') @vite('resources/css/van-rentals.css') @endpush
@section('content')
<main class="van-area">
    <section class="van-hero"><div class="container">
        <span class="van-kicker">Zentrum · Aluguer de carrinhas</span>
        <h1>O espaço que precisa.<br>O tempo que quiser.</h1>
        <p>Para transportar mercadorias ou mudar de casa. Alugue uma carrinha à hora e escolha se prefere conduzir ou contar com um motorista.</p>
        <div class="van-tags"><span class="van-tag">Preços por hora</span><span class="van-tag">Com ou sem motorista</span><span class="van-tag">Reserva confirmada pela equipa</span></div>
    </div></section>
    <section class="van-section"><div class="container">
        <form method="get" class="van-panel vr:grid vr:grid-cols-1 vr:gap-4 vr:md:grid-cols-2 vr:lg:grid-cols-5 vr:mb-8">
            <div class="van-field"><label for="filter-mode">Modalidade</label><select name="mode" id="filter-mode"><option value="">Todas</option><option value="self_drive" @selected(request('mode') === 'self_drive')>Sem motorista</option><option value="with_driver" @selected(request('mode') === 'with_driver')>Com motorista</option></select></div>
            <div class="van-field"><label for="filter-start">Início — Lisboa</label><input type="datetime-local" step="1800" id="filter-start" name="starts_at" value="{{ request('starts_at') }}">@error('starts_at')<span class="van-error">{{ $message }}</span>@enderror</div>
            <div class="van-field"><label for="filter-end">Fim — Lisboa</label><input type="datetime-local" step="1800" id="filter-end" name="ends_at" value="{{ request('ends_at') }}">@error('ends_at')<span class="van-error">{{ $message }}</span>@enderror</div>
            <div class="van-field"><label for="filter-capacity">Volume mínimo (m³)</label><input type="number" min="0" max="100" step="0.1" id="filter-capacity" name="capacity" value="{{ request('capacity') }}">@error('capacity')<span class="van-error">{{ $message }}</span>@enderror</div>
            <div class="vr:flex vr:items-end"><button class="van-button vr:w-full">Encontrar carrinha</button></div>
        </form>
        <div class="vr:flex vr:justify-between vr:items-center vr:gap-4 vr:mb-6"><h2>Escolha a sua carrinha</h2><span class="van-small">{{ $vans->count() }} {{ $vans->count() === 1 ? 'carrinha' : 'carrinhas' }}</span></div>
        @if ($vans->isNotEmpty())<div class="vr:grid vr:grid-cols-1 vr:md:grid-cols-2 vr:lg:grid-cols-3 vr:gap-6">@foreach ($vans as $van)<x-van-card :van="$van" />@endforeach</div>
        @else <div class="van-empty"><h3>Nenhuma carrinha disponível para esta pesquisa</h3><p>Experimente outras datas ou volte em breve para consultar a frota.</p><a href="{{ route('van-rentals.index') }}" class="van-button secondary">Limpar filtros</a></div>@endif
    </div></section>
    <section class="van-section" style="background:#f5f7f4"><div class="container vr:grid vr:md:grid-cols-3 vr:gap-8">
        <div><span class="van-kicker">01 · Escolher</span><h3 class="vr:mt-3">Encontre o espaço certo</h3><p>Compare a capacidade e as dimensões de carga com o que precisa de transportar.</p></div>
        <div><span class="van-kicker">02 · Pedir</span><h3 class="vr:mt-3">Indique quando precisa</h3><p>Escolha os horários e a modalidade. Veja a estimativa antes de enviar o pedido.</p></div>
        <div><span class="van-kicker">03 · Confirmar</span><h3 class="vr:mt-3">Nós tratamos do resto</h3><p>A equipa verifica a disponibilidade e contacta-o para confirmar. Ajudantes e carga/descarga sob orçamento.</p></div>
    </div></section>
</main>
@endsection
