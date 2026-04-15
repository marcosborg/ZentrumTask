@extends('website.layout')

@section('title', 'Frota TVDE | Zentrum TVDE')

@push('head')
  <meta name="description" content="Consulte toda a frota TVDE da Zentrum, incluindo viaturas disponiveis e indisponiveis, com acesso a cada ficha individual." />
@endpush

@section('content')
  <section class="fleet-index-hero">
    <div class="container">
      <div class="fleet-breadcrumb">
        <a href="{{ url('/') }}">Inicio</a>
        <span>/</span>
        <span>Frota TVDE</span>
      </div>

      <div class="fleet-index-shell">
        <div class="fleet-index-head">
          <div>
            <span class="fleet-kicker">Catalogo completo</span>
            <h1 class="fleet-index-title">Toda a frota TVDE</h1>
          </div>
          <div class="fleet-index-stats">
            <span class="fleet-status fleet-status--success">{{ $vehicles->where('status', 'available')->count() }} disponiveis</span>
            <span class="fleet-status fleet-status--danger">{{ $vehicles->where('status', '!=', 'available')->count() }} indisponiveis</span>
          </div>
        </div>

        <div class="row g-4">
          @forelse ($vehicles as $vehicle)
            <div class="col-12 col-md-6 col-xl-4">
              <article class="fleet-product-card h-100" itemscope itemtype="https://schema.org/Product">
                <meta itemprop="name" content="{{ $vehicle->displayName() }}" />
                <meta itemprop="url" content="{{ $vehicle->publicUrl() }}" />
                <meta itemprop="brand" content="{{ $vehicle->make }}" />
                <meta itemprop="availability" content="{{ $vehicle->status === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}" />

                <a href="{{ $vehicle->publicUrl() }}" class="fleet-product-link">
                  <div class="fleet-media">
                    <img src="{{ $vehicle->primaryImageUrl() }}" alt="{{ $vehicle->displayName() }}" itemprop="image" />
                  </div>

                  <div class="fleet-product-body">
                    <div class="fleet-product-topline">
                      <span class="fleet-status fleet-status--{{ $vehicle->websiteAvailabilityColor() }}">{{ $vehicle->websiteAvailabilityLabel() }}</span>
                    </div>

                    <h2 class="fleet-product-title" itemprop="name">{{ $vehicle->displayName() }}</h2>

                    @if ($vehicle->maskedVin())
                      <div class="fleet-product-subline">
                        <span>Chassis</span>
                        <strong>{{ $vehicle->maskedVin() }}</strong>
                      </div>
                    @endif

                    <div class="fleet-cta-row">
                      <span class="fleet-cta">Ver viatura</span>
                    </div>
                  </div>
                </a>
              </article>
            </div>
          @empty
            <div class="col-12 text-light-subtle">Nenhuma viatura TVDE registada para mostrar nesta pagina.</div>
          @endforelse
        </div>
      </div>
    </div>
  </section>
@endsection

@pushOnce('styles')
  <style>
    .fleet-index-hero {
      padding: 2.5rem 0;
      background:
        radial-gradient(circle at top left, rgba(70, 169, 253, 0.2), transparent 30%),
        linear-gradient(180deg, #f6fbff 0%, #eeeeee 100%);
    }

    .fleet-index-shell {
      background: #fff;
      border: 1px solid #dde7f2;
      border-radius: 24px;
      box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
      padding: 2rem;
    }

    .fleet-index-head {
      display: flex;
      justify-content: space-between;
      align-items: end;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }

    .fleet-index-title {
      margin: 0;
      color: #0f172a;
      font-size: clamp(2rem, 4vw, 3rem);
    }

    .fleet-index-stats {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
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

    .fleet-product-subline {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      flex-wrap: wrap;
      color: #5c748f;
      font-size: 0.92rem;
    }

    .fleet-product-subline span {
      font-weight: 700;
    }

    .fleet-product-subline strong {
      color: #0f172a;
      font-weight: 800;
      letter-spacing: 0.04em;
    }

    .fleet-cta-row {
      display: flex;
      align-items: center;
      justify-content: flex-start;
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
      margin: 0;
      color: #0f172a;
      font-size: 1.75rem;
      line-height: 1.08;
    }

    .fleet-cta {
      color: #2a66b5;
      font-weight: 800;
    }

    @media (max-width: 767.98px) {
      .fleet-index-shell {
        padding: 1.35rem;
      }

      .fleet-product-title {
        font-size: 1.35rem;
      }
    }
  </style>
@endpushOnce
