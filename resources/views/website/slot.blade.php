@extends('website.layout')

@section('title', 'SLOT com viatura própria | Zentrum TVDE')

@push('head')
  <meta name="description" content="Trabalhe em TVDE com a sua viatura na Zentrum. SLOT Base a 30 € e Premium a 50 € por semana, IVA incluído, com gestão e benefícios de oficina." />
@endpush

@section('content')
  <section class="tvde-offers">
    <div class="container">
      <a href="{{ url('/') }}" class="d-inline-block mb-4">Início</a>
      <div class="row g-4 align-items-center">
        <div class="col-lg-8">
          <span class="tvde-eyebrow">SLOT · Viatura própria</span>
          <h1 class="display-4 fw-bold">A sua viatura.<br>O apoio da Zentrum.</h1>
          <p class="lead">Trabalhe em TVDE com a sua própria viatura, com gestão documental, integração Uber/Bolt e acompanhamento da operação.</p>
          <div class="d-flex flex-wrap gap-3">
            <a class="btn btn-primary" href="#packs">Comparar packs</a>
            <a class="btn btn-outline-primary" href="#contactos-slot">Pedir contacto</a>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="tvde-offer-card">
            <span class="tvde-eyebrow">Nos dois packs</span>
            <h2 class="h4">A nossa oficina, ao seu lado.</h2>
            <ul class="mb-0">
              <li>Check-up gratuito na entrada e a cada 12 meses.</li>
              <li>Preços descontados em oficina.</li>
              <li>Atendimento prioritário.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="packs" class="container py-5" aria-labelledby="slot-packs-title">
    <h2 id="slot-packs-title">Escolha o seu pack SLOT</h2>
    <p class="mb-4">Preço semanal fixo, com IVA incluído.</p>
    <div class="row g-4">
      <div class="col-md-6">
        <article class="tvde-offer-card h-100">
          <h3>Base</h3>
          <p><strong class="display-5 fw-bold">30 €</strong> / semana</p>
          <ul>
            <li>Admissão e controlo documental.</li>
            <li>Integração Uber/Bolt.</li>
            <li>Reconciliação de receitas, extratos e pagamentos.</li>
            <li>Check-up anual gratuito e benefícios de oficina.</li>
          </ul>
          <a href="#contactos-slot" class="btn btn-primary mt-auto align-self-start">Pedir contacto sobre o Base</a>
        </article>
      </div>
      <div class="col-md-6">
        <article class="tvde-offer-card tvde-offer-card--slot h-100">
          <h3>Premium</h3>
          <p><strong class="display-5 fw-bold">50 €</strong> / semana</p>
          <ul>
            <li>Todos os benefícios do Base.</li>
            <li>Serviço de acompanhamento de acidentes 24/7.</li>
            <li>Pedidos de indemnização por imobilização.</li>
            <li>Viatura de substituição.</li>
          </ul>
          <p class="small">Os pedidos dependem das circunstâncias do sinistro e da apreciação das entidades responsáveis. Não é garantida a atribuição de indemnização ou de viatura.</p>
          <a href="#contactos-slot" class="btn btn-light mt-auto align-self-start">Pedir contacto sobre o Premium</a>
        </article>
      </div>
    </div>
  </section>

  <section class="container pb-5" aria-labelledby="slot-how-title">
    <h2 id="slot-how-title">Como funciona</h2>
    <div class="row g-4 mt-1">
      <div class="col-md-4"><h3 class="h5">1. Fale connosco</h3><p>Apresente a sua viatura e conheça as condições. A equipa acompanha a validação da documentação, perfil fiscal e contrato.</p></div>
      <div class="col-md-4"><h3 class="h5">2. Prepare a entrada</h3><p>A ativação depende da elegibilidade do motorista e da viatura, da documentação aprovada e do check-up inicial concluído.</p></div>
      <div class="col-md-4"><h3 class="h5">3. Receba semanalmente</h3><p>Pagamentos à segunda-feira, após recebimento e reconciliação dos valores das plataformas.</p></div>
    </div>
  </section>

  <x-contact heading="Vamos falar sobre o seu SLOT?" intro="Escolha o seu pack e indique na mensagem a viatura que pretende integrar. A equipa Zentrum entra em contacto consigo." source="website_slot" anchor="contactos-slot" submitLabel="Pedir contacto SLOT" />
@endsection
