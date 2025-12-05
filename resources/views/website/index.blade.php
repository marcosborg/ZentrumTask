@extends('website.layout')

@push('head')
  <title>Zentrum TVDE — Apoio Completo para Motoristas TVDE em Portugal</title>
  <meta name="description" content="A Zentrum TVDE ajuda novos e experientes motoristas com formação, documentação, gestão de viaturas e suporte contínuo. Trabalha TVDE com transparência e confiança.">
@endpush

@section('content')

    <x-hero />

    <x-services />

    <x-stats-testimonial />

    <x-fleet-faqs />

    <x-contact /> 

@endsection
