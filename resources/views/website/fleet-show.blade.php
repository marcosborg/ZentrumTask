@extends('website.layout')

@section('title', $vehicle->displayName().' | Zentrum TVDE')

@push('head')
  <meta name="description" content="{{ $vehicle->notes ?: 'Consulte a ficha da viatura '.$vehicle->displayName().' com estado e pedido de contacto.' }}">
  <meta property="og:title" content="{{ $vehicle->displayName().' | Zentrum TVDE' }}" />
  <meta property="og:description" content="{{ $vehicle->notes ?: 'Consulte a ficha da viatura '.$vehicle->displayName().' com estado e pedido de contacto.' }}" />
  <meta property="og:image" content="{{ $vehicle->primaryImageUrl() }}" />
  <meta name="twitter:image" content="{{ $vehicle->primaryImageUrl() }}" />
  <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $vehicle->displayName(),
        'description' => $vehicle->notes ?: 'Ficha publica da viatura TVDE.',
        'image' => $vehicle->galleryImageUrls(),
        'brand' => $vehicle->make ? [
            '@type' => 'Brand',
            'name' => $vehicle->make,
        ] : null,
        'model' => trim((string) collect([$vehicle->model, $vehicle->trim])->filter()->implode(' ')),
        'offers' => [
            '@type' => 'Offer',
            'availability' => $vehicle->status === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => $vehicle->publicUrl(),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
@endpush

@section('content')
  @php
      $galleryImages = $vehicle->galleryImageUrls();
      $heroImage = $galleryImages[0] ?? asset('website/assets/car_sedan.png');
      $secondaryImages = collect($galleryImages)->slice(1, 4)->values();
  @endphp

  <section class="fleet-product-hero">
    <div class="container">
      <div class="fleet-breadcrumb">
        <a href="{{ url('/') }}">Inicio</a>
        <span>/</span>
        <a href="{{ route('vehicle.index') }}">Frota</a>
        <span>/</span>
        <span>{{ $vehicle->displayName() }}</span>
      </div>

      <div class="row g-4 align-items-start">
        <div class="col-lg-7">
          <div class="fleet-gallery-shell">
            <div class="fleet-gallery-main">
              <img src="{{ $heroImage }}" alt="{{ $vehicle->displayName() }}" />
            </div>

            @if ($secondaryImages->isNotEmpty())
              <div class="row g-3 mt-1">
                @foreach ($secondaryImages as $image)
                  <div class="col-6">
                    <div class="fleet-gallery-thumb">
                      <img src="{{ $image }}" alt="{{ $vehicle->displayName() }}" />
                    </div>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>

        <div class="col-lg-5">
          <div class="fleet-product-panel">
            <span class="fleet-detail-kicker">{{ $vehicle->make ?: 'Viatura TVDE' }}</span>
            <h1 class="fleet-detail-title">{{ $vehicle->displayName() }}</h1>

            <div class="fleet-detail-price-row">
              <div class="fleet-detail-price">{{ $vehicle->websiteAvailabilityLabel() }}</div>
              <div class="fleet-detail-badge">{{ $vehicle->statusLabel() }}</div>
            </div>

            <div class="fleet-detail-meta">
              <div class="fleet-detail-meta-item">
                <span>Marca</span>
                <strong>{{ $vehicle->make ?: 'Sob consulta' }}</strong>
              </div>
              <div class="fleet-detail-meta-item">
                <span>Modelo</span>
                <strong>{{ trim((string) collect([$vehicle->model, $vehicle->trim])->filter()->implode(' ')) ?: '-' }}</strong>
              </div>
              <div class="fleet-detail-meta-item">
                <span>Matricula</span>
                <strong>{{ $vehicle->license_plate }}</strong>
              </div>
            </div>

            <p class="fleet-detail-excerpt">{{ $vehicle->notes ?: 'Ficha publica da viatura TVDE com informacao de estado e contacto imediato para a equipa.' }}</p>

            <div class="fleet-detail-actions">
              <a href="#pedido-viatura" class="btn btn-primary btn-lg fleet-detail-primary">Pedir contacto</a>
              <a href="tel:256112333" class="btn btn-outline-secondary btn-lg">Ligar agora</a>
            </div>

            <div class="fleet-trust-list">
              <span>URL propria para indexacao</span>
              <span>Estado sincronizado com o backoffice</span>
              <span>Contexto enviado para o kanban</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="container pb-4">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="fleet-copy-card">
          <h2>Descricao da viatura</h2>
          <div class="fleet-copy-body">
            {!! nl2br(e($vehicle->notes ?: 'Adicione notas operacionais na viatura para enriquecer a ficha publica. Esta pagina ja expõe marca, modelo, matricula, estado e fotos reais da viatura.')) !!}
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="fleet-contact-shell" id="pedido-viatura">
          <x-contact
            :vehicle="$vehicle"
            heading="Pedir contacto sobre esta viatura"
            intro="A task sera criada com o titulo '{{ $vehicle->displayName() }} - ' + nome do contacto para acelerar o seguimento pela equipa."
            submit-label="Quero saber mais"
            source="website_vehicle_product"
            anchor=""
            container-class="p-0"
            :show-success="true"
          />
        </div>
      </div>
    </div>
  </section>
@endsection

@pushOnce('styles')
  <style>
    .fleet-product-hero {
      padding: 2.5rem 0 1.5rem;
      background:
        radial-gradient(circle at top left, rgba(70, 169, 253, 0.2), transparent 30%),
        linear-gradient(180deg, #f6fbff 0%, #eeeeee 100%);
    }

    .fleet-breadcrumb {
      display: flex;
      gap: 0.55rem;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
      color: #5c748f;
      font-size: 0.92rem;
    }

    .fleet-breadcrumb a {
      color: #2a66b5;
      text-decoration: none;
    }

    .fleet-gallery-shell,
    .fleet-product-panel,
    .fleet-copy-card {
      background: #fff;
      border: 1px solid #dde7f2;
      border-radius: 24px;
      box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
    }

    .fleet-gallery-shell {
      padding: 18px;
    }

    .fleet-gallery-main img,
    .fleet-gallery-thumb img {
      width: 100%;
      display: block;
      object-fit: cover;
      border-radius: 18px;
      background: #f8fafc;
    }

    .fleet-gallery-main img {
      aspect-ratio: 4 / 3;
    }

    .fleet-gallery-thumb img {
      aspect-ratio: 4 / 3;
    }

    .fleet-product-panel {
      padding: 2rem;
      position: sticky;
      top: 96px;
    }

    .fleet-detail-kicker {
      display: inline-flex;
      padding: 0.42rem 0.8rem;
      border-radius: 999px;
      background: #e8f2ff;
      color: #1d4f91;
      font-size: 0.84rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 1rem;
    }

    .fleet-detail-title {
      font-size: clamp(2rem, 4vw, 3.3rem);
      line-height: 0.98;
      margin-bottom: 1rem;
      color: #0f172a;
    }

    .fleet-detail-price-row,
    .fleet-detail-meta,
    .fleet-detail-actions,
    .fleet-trust-list {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .fleet-detail-price-row {
      align-items: center;
      margin-bottom: 1rem;
      justify-content: space-between;
    }

    .fleet-detail-price {
      font-size: 2rem;
      font-weight: 800;
      color: #0f172a;
    }

    .fleet-detail-badge,
    .fleet-trust-list span {
      background: #f8fbff;
      border: 1px solid #dce7f5;
      border-radius: 999px;
      padding: 0.45rem 0.8rem;
      color: #45617d;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .fleet-detail-meta {
      margin-bottom: 1.25rem;
    }

    .fleet-detail-meta-item {
      flex: 1 1 180px;
      background: #f8fbff;
      border-radius: 18px;
      padding: 1rem;
      border: 1px solid #dce7f5;
    }

    .fleet-detail-meta-item span {
      display: block;
      color: #68819a;
      font-size: 0.88rem;
      margin-bottom: 0.25rem;
    }

    .fleet-detail-excerpt {
      color: #49627b;
      font-size: 1.04rem;
      margin-bottom: 1.4rem;
    }

    .fleet-detail-actions {
      margin-bottom: 1.15rem;
    }

    .fleet-detail-primary {
      background: #2a66b5;
      border-color: #2a66b5;
    }

    .fleet-copy-card {
      padding: 2rem;
      height: 100%;
    }

    .fleet-copy-card h2 {
      margin-bottom: 1rem;
      color: #0f172a;
    }

    .fleet-copy-body {
      color: #425b75;
      font-size: 1.02rem;
      line-height: 1.8;
    }

    .fleet-contact-shell .contact-section {
      padding: 2rem;
    }

    @media (max-width: 991.98px) {
      .fleet-product-panel {
        position: static;
      }
    }

    @media (max-width: 767.98px) {
      .fleet-product-panel,
      .fleet-copy-card {
        padding: 1.4rem;
      }

      .fleet-detail-price {
        font-size: 1.7rem;
      }
    }
  </style>
@endpushOnce
