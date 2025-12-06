@extends('website.layout')

@section('title', 'Zentrum TVDE - Apoio Completo para Motoristas TVDE em Portugal')

@push('head')
  <meta name="description" content="A Zentrum TVDE ajuda novos e experientes motoristas com formacao, documentacao, gestao de viaturas e suporte continuo. Trabalha TVDE com transparencia e confianca.">
@endpush

@section('content')

    <x-hero />

    <x-services />

    <x-stats-testimonial />

    <x-fleet-faqs />

    <x-contact /> 

@endsection
