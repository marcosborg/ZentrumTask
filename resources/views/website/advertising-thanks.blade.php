@extends('website.layout')
@section('title','Pedido recebido | Zentrum Ads')
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')<main class="container py-5"><h1>Obrigado pelo seu pedido.</h1><p>A equipa Zentrum Ads vai analisar a campanha e entrar em contacto através dos dados que indicou.</p><a href="{{ route('ads.index') }}">Voltar à Zentrum Ads</a></main>@endsection
@if(session('ads_received')) @push('scripts')<script>if(document.cookie.split(';').some(c=>c.trim()==='zentrum-cookie-consent=accepted'))window.gtag?.('event','ads_proposal_received');</script>@endpush @endif
