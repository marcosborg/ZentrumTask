@php
    $vehicleItems = collect($vehicles ?? []);

    if ($vehicleItems->isEmpty() && \Illuminate\Support\Facades\Schema::hasTable('vehicles')) {
        $vehicleItems = \App\Models\Vehicle::query()->websiteAvailable()->get();
    }

    $vehicleSlides = $vehicleItems->chunk(3)->values();
@endphp

<section class="container pb-4 section-gap" id="frota">
  <div class="fleet-wrapper">
    <div class="fleet-heading">
      <div>
        <span class="fleet-kicker">Viaturas TVDE</span>
        <h3 class="mb-0">A nossa frota</h3>
      </div>
      <div class="fleet-heading-actions">
        <a href="{{ route('vehicle.index') }}" class="fleet-view-all">Ver todos</a>
      </div>
    </div>

    @if ($vehicleSlides->isNotEmpty())
      <div id="fleetCarousel" class="carousel slide fleet-carousel" data-bs-ride="carousel">
        <div class="carousel-inner">
          @foreach ($vehicleSlides as $slideIndex => $slideVehicles)
            <div class="carousel-item @if($slideIndex === 0) active @endif">
              <div class="row g-4">
                @foreach ($slideVehicles as $vehicle)
                  <div class="col-12 col-md-6 col-xl-4">
                    <article class="fleet-product-card h-100" itemscope itemtype="https://schema.org/Product">
                      <meta itemprop="name" content="{{ $vehicle->displayName() }}" />
                      <meta itemprop="url" content="{{ $vehicle->publicUrl() }}" />
                      <meta itemprop="brand" content="{{ $vehicle->make }}" />
                      <meta itemprop="availability" content="https://schema.org/InStock" />

                      <a href="{{ $vehicle->publicUrl() }}" class="fleet-product-link">
                        <div class="fleet-media">
                          <img
                            src="{{ $vehicle->primaryImageUrl() }}"
                            alt="{{ $vehicle->displayName() }}"
                            itemprop="image"
                          />
                        </div>

                        <div class="fleet-product-body">
                          <div class="fleet-product-topline">
                            <span class="fleet-status fleet-status--success">Disponivel</span>
                          </div>

                          <h4 class="fleet-product-title" itemprop="name">{{ $vehicle->displayName() }}</h4>

                          <div class="fleet-cta-row">
                            <span class="fleet-cta">Ver viatura</span>
                          </div>
                        </div>
                      </a>
                    </article>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>

        @if ($vehicleSlides->count() > 1)
          <button class="carousel-control-prev fleet-carousel-control" type="button" data-bs-target="#fleetCarousel" data-bs-slide="prev" aria-label="Slide anterior">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          </button>
          <button class="carousel-control-next fleet-carousel-control" type="button" data-bs-target="#fleetCarousel" data-bs-slide="next" aria-label="Slide seguinte">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
          </button>
        @endif
      </div>
    @else
      <div class="col-12 text-light-subtle">Nenhuma viatura TVDE disponivel para destaque neste momento.</div>
    @endif

    <div class="row g-4 mt-4 align-items-start">
      <div class="col-lg-4">
        <h3 class="mb-5">Como funciona</h3>
        <div class="steps-list">
          <div class="step-item">
            <div class="step-number">1</div>
            <div class="step-text">Veja as viaturas disponiveis em destaque na home.</div>
          </div>
          <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-text">Use “Ver todos” para consultar a frota completa, incluindo indisponiveis.</div>
          </div>
          <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-text">Abra a ficha da viatura e envie o pedido de contacto para o kanban.</div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <h3 class="mb-3">Perguntas frequentes</h3>
        <div class="faq-item">
          <h6>Que viaturas aparecem no carrossel?</h6>
          <p>Apenas viaturas com source TVDE e estado `available`.</p>
        </div>
        <div class="faq-item">
          <h6>Onde vejo as indisponiveis?</h6>
          <p>No botao `Ver todos`, que abre a pagina com toda a frota TVDE.</p>
        </div>
        <div class="faq-item">
          <h6>Posso pedir contacto para uma viatura indisponivel?</h6>
          <p>Sim. A ficha continua publica e o pedido entra no kanban com a referencia exata da viatura.</p>
        </div>
        <div class="faq-item">
          <h6>As fotos podem ser atualizadas no painel?</h6>
          <p>Sim. A galeria publica usa as fotos reais anexadas na propria viatura em `admin/vehicles`.</p>
        </div>
      </div>
    </div>
  </div>
</section>

@pushOnce('styles')
  <style>
    .fleet-wrapper {
      background:
        radial-gradient(circle at top left, rgba(70, 169, 253, 0.18), transparent 26%),
        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      border-radius: 24px;
      padding: 32px;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
      border: 1px solid #dbe7f4;
    }

    .fleet-heading {
      display: flex;
      justify-content: space-between;
      align-items: end;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    .fleet-heading-actions {
      display: flex;
      align-items: center;
      justify-content: end;
      gap: 1rem;
      flex-wrap: wrap;
      max-width: 640px;
    }

    .fleet-kicker {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: #1d4f91;
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 0.65rem;
    }

    .fleet-heading-copy {
      max-width: 420px;
      color: #47637f;
    }

    .fleet-view-all {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 46px;
      padding: 0.85rem 1.25rem;
      border-radius: 999px;
      background: #2a66b5;
      color: #fff;
      font-weight: 800;
      text-decoration: none;
      box-shadow: 0 12px 22px rgba(42, 102, 181, 0.24);
    }

    .fleet-view-all:hover {
      color: #fff;
      background: #1f4f90;
    }

    .fleet-carousel {
      padding: 0 3.4rem;
    }

    .fleet-carousel-control {
      width: 46px;
      height: 46px;
      top: 50%;
      transform: translateY(-50%);
      border-radius: 999px;
      background: #1d4f91;
      opacity: 1;
    }

    .fleet-carousel .carousel-control-prev {
      left: 0;
    }

    .fleet-carousel .carousel-control-next {
      right: 0;
    }

    .fleet-product-card {
      background: #fff;
      border: 1px solid #dbe7f4;
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .fleet-product-card:hover {
      transform: translateY(-4px);
      border-color: rgba(42, 102, 181, 0.28);
      box-shadow: 0 20px 36px rgba(15, 23, 42, 0.12);
    }

    .fleet-product-link {
      color: inherit;
      text-decoration: none;
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .fleet-media {
      padding: 16px 16px 0;
    }

    .fleet-media img {
      width: 100%;
      aspect-ratio: 4 / 3;
      object-fit: cover;
      display: block;
      border-radius: 18px;
      background: #f8fafc;
    }

    .fleet-product-body {
      padding: 1.15rem 1.15rem 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 0.95rem;
      flex: 1;
    }

    .fleet-product-topline {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .fleet-status {
      border-radius: 999px;
      padding: 0.38rem 0.75rem;
      font-weight: 800;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .fleet-status--success {
      background: #e8fff3;
      color: #177245;
    }

    .fleet-status--danger {
      background: #fff1f2;
      color: #b42318;
    }

    .fleet-product-title {
      font-size: 1.45rem;
      line-height: 1.15;
      margin: 0;
      color: #0f172a;
    }

    .fleet-cta-row {
      display: flex;
      align-items: center;
      justify-content: flex-start;
    }

    .fleet-cta {
      color: #2a66b5;
      font-weight: 800;
    }

    @media (max-width: 991.98px) {
      .fleet-carousel {
        padding: 0;
      }

      .fleet-carousel-control {
        display: none;
      }
    }

    @media (max-width: 767.98px) {
      .fleet-wrapper {
        padding: 22px;
      }

      .fleet-product-title {
        font-size: 1.25rem;
      }
    }
  </style>
@endpushOnce
