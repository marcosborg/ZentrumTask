@php
    $fleetItems = collect($fleets ?? []);
    if ($fleetItems->isEmpty() && \Illuminate\Support\Facades\Schema::hasTable('fleets')) {
        $fleetItems = \App\Models\Fleet::query()->latest('id')->get();
    }
    $appUrl = rtrim(config('app.url'), '/');
@endphp

<section class="container pb-4 section-gap">
  <div class="fleet-wrapper">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h3 class="mb-0">A nossa frota</h3>
    </div>

    <div class="row g-3">
      @forelse($fleetItems as $fleet)
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="fleet-card">
            <img
              src="{{ $fleet->photo_path ? $appUrl.'/storage/'.$fleet->photo_path : asset('website/assets/car_sedan.png') }}"
              alt="{{ $fleet->name }}"
            />
            <p class="fleet-name">{{ $fleet->name }}</p>
          </div>
        </div>
      @empty
        <div class="col-12 text-light-subtle">Nenhum veÇðculo na frota ainda.</div>
      @endforelse
    </div>

    <div class="row g-4 mt-4 align-items-start">
      <div class="col-lg-4">
        <h3 class="mb-5">Como funciona</h3>
        <div class="steps-list">
          <div class="step-item">
            <div class="step-number">1</div>
            <div class="step-text">Registe-se na plataforma</div>
          </div>
          <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-text">Encontre a viatura ideal</div>
          </div>
          <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-text">Comece a conduzir</div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <h3 class="mb-3">Perguntas frequentes</h3>
        <div class="faq-item">
          <h6>Que documentos são necessários para me tornar motorista?</h6>
          <p>
            Vai precisar do documento de identificação, carta de condução e comprovativo de residência, entre outros.
          </p>
        </div>
        <div class="faq-item">
          <h6>Quais são os requisitos para alugar uma viatura?</h6>
          <p>
            Ter carta de condução válida e cumprir os critérios de idade mínima previstos pela plataforma.
          </p>
        </div>
        <div class="faq-item">
          <h6>Posso utilizar a minha própria viatura como motorista TVDE?</h6>
          <p>
            Sim, desde que a viatura cumpra os requisitos legais e seja registada na plataforma.
          </p>
        </div>
        <div class="faq-item">
          <h6>Qual é o processo para comprar uma viatura?</h6>
          <p>
            Contacte-nos para obter informações sobre a nossa oferta de veículos em venda e as condições de aquisição.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

@pushOnce('styles')
  <style>
    .fleet-wrapper {
      background: #ffffff;
      border-radius: 18px;
      padding: 32px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
      border: 1px solid #e5e7eb;
    }

    .fleet-wrapper h3 {
      color: #0f172a;
    }

    .fleet-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 12px;
      text-align: center;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .fleet-card img {
      border-radius: 12px;
      width: 100%;
      display: block;
    }

    .fleet-name {
      margin: 0;
      padding: 1rem;
      text-align: center;
      color: #2a66b5;
      font-weight: 700;
    }

    .steps-list {
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
    }

    .step-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .step-number {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: #46a9fd;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 1.05rem;
      box-shadow: 0 8px 16px rgba(70, 169, 253, 0.35);
    }

    .step-text {
      color: #0f172a;
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      letter-spacing: 0.01em;
    }

    .faq-item {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      padding: 14px 16px;
      margin-bottom: 12px;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
    }

    .faq-item h6 {
      color: #0f172a;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .faq-item p {
      color: #475569;
      margin: 0;
    }
  </style>
@endpushOnce
