@extends('website.layout')

@section('title', 'Zentrum TVDE - Apoio Completo para Motoristas TVDE em Portugal')

@push('head')
  <meta name="description" content="A Zentrum TVDE ajuda novos e experientes motoristas com formacao, documentacao, gestao de viaturas e suporte continuo. Trabalha TVDE com transparencia e confianca.">
@endpush

@section('content')

    <x-hero />

    <x-tvde-offers />

    <x-services />

    <x-stats-testimonial />

    <x-fleet-faqs />

    <x-van-highlights />

    <section class="container py-5"><div class="row align-items-center g-4"><div class="col-md-6"><img loading="lazy" class="img-fluid rounded" src="{{ asset('website/assets/ads-city.webp') }}" alt="Viatura com publicidade nas portas traseiras"></div><div class="col-md-6"><span class="tvde-eyebrow">Zentrum Ads</span><h2>Publicidade em movimento.</h2><p>Uma campanha à medida da sua empresa, da seleção das viaturas à aplicação do vinil.</p><a href="{{ route('ads.index') }}#proposta" class="btn btn-primary">Pedir proposta de publicidade</a></div></div></section>
    <x-blog-news />

    <x-contact /> 

    <x-website-chat />

@endsection
