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
        '@'.'context' => 'https://schema.org',
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
            'priceCurrency' => $vehicle->hasWeeklyRentalPrice() ? 'EUR' : null,
            'price' => $vehicle->hasWeeklyRentalPrice() ? (float) $vehicle->weekly_rental_price : null,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
@endpush

@section('content')
  @php
      $reservationOffer = \App\Support\ReservationOfferContent::data();
      $galleryImages = $vehicle->galleryImageUrls();
      $heroImage = $galleryImages[0] ?? asset('website/assets/car_sedan.png');
      $secondaryImages = collect($galleryImages)->slice(1, 4)->values();
      $contactModalId = 'vehicle-contact-modal-'.$vehicle->getKey();
      $reservationCtaId = 'reservation-cta-'.$vehicle->getKey();
      $isVehicleAvailable = $vehicle->status === 'available';
      $reservationUnavailableMessage = 'A viatura já não está disponível. Se desejar saber quais as viaturas disponiveis, queira entrar em contacto nos botões Ligar agora ou Pedir contacto';
      $reservationTaxMessage = $reservationOffer['tax_message'];
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

            @if ($vehicle->hasWeeklyRentalPrice())
              <div class="fleet-detail-rental">
                <span>Aluguer semanal</span>
                <strong>{{ $vehicle->weeklyRentalPriceFormatted() }}&euro;</strong>
              </div>
            @endif

            <div class="fleet-detail-meta">
              <div class="fleet-detail-meta-item">
                <span>Marca</span>
                <strong>{{ $vehicle->make ?: 'Sob consulta' }}</strong>
              </div>
              <div class="fleet-detail-meta-item">
                <span>Modelo</span>
                <strong>{{ trim((string) collect([$vehicle->model, $vehicle->trim])->filter()->implode(' ')) ?: '-' }}</strong>
              </div>
              @if ($vehicle->maskedVin())
                <div class="fleet-detail-meta-item">
                  <span>Chassis</span>
                  <strong>{{ $vehicle->maskedVin() }}</strong>
                </div>
              @endif
            </div>

            <p class="fleet-detail-excerpt">{{ $vehicle->notes ?: 'Ficha publica da viatura TVDE com informacao de estado e contacto imediato para a equipa.' }}</p>

            <div class="fleet-detail-actions">
              <a href="tel:256112333" class="btn btn-outline-secondary btn-lg">Ligar agora</a>
              <button type="button" class="btn btn-primary btn-lg fleet-detail-primary" data-bs-toggle="modal" data-bs-target="#{{ $contactModalId }}">
                Pedir contacto
              </button>
              <a
                href="{{ $isVehicleAvailable ? '#'.$reservationCtaId : '#' }}"
                class="btn btn-outline-primary btn-lg fleet-detail-reserve @unless($isVehicleAvailable) is-disabled @endunless"
                @unless($isVehicleAvailable) data-unavailable-alert="true" aria-disabled="true" @endunless
              >
                Reserva imediata
              </a>
            </div>

            <div class="fleet-unavailable-notice" id="fleet-unavailable-notice" hidden aria-live="polite">
              <div class="fleet-unavailable-notice__icon" aria-hidden="true">!</div>
              <div class="fleet-unavailable-notice__copy">
                <strong>Viatura indisponível neste momento</strong>
                <p>{{ $reservationUnavailableMessage }}</p>
              </div>
            </div>
          </div>

          <div class="fleet-reservation-card mt-4">
            <div class="fleet-reservation-head">
              <span class="fleet-reservation-kicker">Reserva imediata</span>
              <h2>Reserve esta viatura com 250€
                <span class="fleet-tax-popover">
                  <button type="button" class="fleet-tax-link" aria-label="Informação sobre IVA">*</button>
                  <span class="fleet-tax-popover__bubble" role="tooltip">{{ $reservationTaxMessage }}</span>
                </span>
                de caução inicial
              </h2>
              <p>
                Garanta já a viatura, avance com a reserva e finalize o processo assim que liquidar a referência
                multibanco dos 250€
                <span class="fleet-tax-popover">
                  <button type="button" class="fleet-tax-link" aria-label="Informação sobre IVA">*</button>
                  <span class="fleet-tax-popover__bubble" role="tooltip">{{ $reservationTaxMessage }}</span>
                </span>
                iniciais.
              </p>
            </div>

            <div class="fleet-reservation-highlight">
              @if ($vehicle->hasWeeklyRentalPrice())
                <div class="fleet-reservation-highlight-item">
                  <span>Aluguer semanal</span>
                  <strong>{{ $vehicle->weeklyRentalPriceFormatted() }}&euro;</strong>
                </div>
              @endif
              <div class="fleet-reservation-highlight-item">
                <span>Caução inicial</span>
                <div class="fleet-highlight-value">
                  <strong>{{ $reservationOffer['formatted_base_amount'] }}</strong>
                  <span class="fleet-tax-popover fleet-tax-popover--inline">
                    <button type="button" class="fleet-tax-link fleet-tax-link--inline" aria-label="Informação sobre IVA">*</button>
                    <span class="fleet-tax-popover__bubble" role="tooltip">{{ $reservationTaxMessage }}</span>
                  </span>
                </div>
              </div>
              <div class="fleet-reservation-highlight-item">
                <span>Km incluídos</span>
                <strong>{{ $reservationOffer['included_km'] }}</strong>
              </div>
              <div class="fleet-reservation-highlight-item">
                <span>Extrato semanal</span>
                <strong>{{ $reservationOffer['statement_deadline'] }}</strong>
              </div>
            </div>

            @foreach ($reservationOffer['sections'] as $section)
              <div class="fleet-reservation-section">
                <h3>{{ $section['title'] }}</h3>
                <ul class="fleet-reservation-list">
                  @foreach ($section['items'] as $item)
                    <li>
                      {!! str_replace($reservationOffer['formatted_base_amount'], $reservationOffer['formatted_base_amount'].'<span class="fleet-tax-popover fleet-tax-popover--inline"><button type="button" class="fleet-tax-link fleet-tax-link--inline" aria-label="Informação sobre IVA">*</button><span class="fleet-tax-popover__bubble" role="tooltip">'.$reservationTaxMessage.'</span></span>', e($item)) !!}
                    </li>
                  @endforeach
                </ul>
              </div>
            @endforeach

            @if ($isVehicleAvailable)
              <div class="fleet-reservation-cta">
                <p>
                  Se esta viatura faz sentido para si, avance já com a reserva e prepare o pagamento dos {{ $reservationOffer['formatted_base_amount'] }}
                  <span class="fleet-tax-popover">
                    <button type="button" class="fleet-tax-link" aria-label="Informação sobre IVA">*</button>
                    <span class="fleet-tax-popover__bubble" role="tooltip">{{ $reservationTaxMessage }}</span>
                  </span>
                  iniciais para garantir a reserva.
                </p>
                <a
                  href="{{ route('reserva.show', ['vehicle' => $vehicle->getKey()]) }}"
                  class="btn btn-primary btn-lg"
                  id="{{ $reservationCtaId }}"
                >
                  Iniciar reserva
                </a>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="container pb-4">
    <div class="row g-4">
      @if (filled($vehicle->notes))
        <div class="col-lg-7">
          <div class="fleet-copy-card">
            <h2>Descricao da viatura</h2>
            <div class="fleet-copy-body">
              {!! nl2br(e($vehicle->notes)) !!}
            </div>
          </div>
        </div>
      @endif
    </div>
  </section>

  <div class="modal fade fleet-contact-modal" id="{{ $contactModalId }}" tabindex="-1" aria-labelledby="{{ $contactModalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <span class="fleet-modal-kicker">{{ $vehicle->make ?: 'Viatura TVDE' }}</span>
            <h2 class="modal-title" id="{{ $contactModalId }}-label">Pedir contacto</h2>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <x-contact
            :vehicle="$vehicle"
            heading=""
            intro=""
            submit-label="Quero saber mais"
            source="website_vehicle_product"
            anchor=""
            container-class="p-0"
            :showSuccess="true"
            :hideFormOnSuccess="true"
          />
        </div>
      </div>
    </div>
  </div>
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

    html {
      scroll-behavior: smooth;
    }

    .fleet-gallery-shell,
    .fleet-product-panel,
    .fleet-copy-card,
    .fleet-reservation-card {
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

    .fleet-detail-rental {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      padding: 1rem 1.1rem;
      border-radius: 18px;
      background: #f3f8ff;
      border: 1px solid #dbeafe;
      margin-bottom: 1.25rem;
    }

    .fleet-detail-rental span {
      color: #47637f;
      font-size: 0.8rem;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .fleet-detail-rental strong {
      color: #0f172a;
      font-size: 1.8rem;
      line-height: 1;
      font-weight: 900;
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

    .fleet-detail-reserve {
      border-color: #2a66b5;
      color: #2a66b5;
    }

    .fleet-detail-reserve.is-disabled,
    .fleet-reservation-cta .btn.is-disabled {
      opacity: 0.55;
      pointer-events: auto;
      cursor: not-allowed;
    }

    .fleet-unavailable-notice {
      display: flex;
      gap: 0.9rem;
      align-items: flex-start;
      margin-top: 1rem;
      padding: 1rem 1.05rem;
      border-radius: 18px;
      background: linear-gradient(180deg, #fff8eb 0%, #fff2d8 100%);
      border: 1px solid #f4d7a1;
      box-shadow: 0 12px 24px rgba(180, 120, 20, 0.08);
    }

    .fleet-unavailable-notice__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2rem;
      height: 2rem;
      flex: 0 0 2rem;
      border-radius: 999px;
      background: #f0a100;
      color: #fff;
      font-size: 1rem;
      font-weight: 800;
      line-height: 1;
    }

    .fleet-unavailable-notice__copy strong {
      display: block;
      margin-bottom: 0.2rem;
      color: #6c4300;
      font-size: 0.98rem;
    }

    .fleet-unavailable-notice__copy p {
      margin: 0;
      color: #7a5a1e;
      font-size: 0.96rem;
      line-height: 1.55;
    }

    .fleet-copy-card {
      padding: 2rem;
      height: 100%;
    }

    .fleet-reservation-card {
      padding: 1.75rem;
    }

    .fleet-reservation-head h2 {
      margin-bottom: 0.75rem;
      color: #0f172a;
      font-size: clamp(1.55rem, 3vw, 2.15rem);
      line-height: 1.05;
    }

    .fleet-reservation-head p,
    .fleet-reservation-cta p {
      color: #49627b;
      font-size: 1rem;
      line-height: 1.7;
      margin: 0;
    }

    .fleet-tax-link {
      display: inline-block;
      margin: 0;
      padding: 0;
      border: none;
      background: transparent;
      color: #1d4f91;
      font-weight: 800;
      text-decoration: none;
      line-height: 1;
      vertical-align: super;
      font-size: 0.8em;
      cursor: pointer;
    }

    .fleet-tax-link--inline {
      margin-left: 0.08rem;
    }

    .fleet-tax-popover {
      position: relative;
      display: inline-flex;
      align-items: flex-start;
    }

    .fleet-tax-popover__bubble {
      position: absolute;
      left: 50%;
      bottom: calc(100% + 0.65rem);
      transform: translateX(-50%);
      width: min(320px, 72vw);
      padding: 0.8rem 0.9rem;
      border-radius: 14px;
      background: #0f172a;
      color: #fff;
      font-size: 0.88rem;
      line-height: 1.55;
      box-shadow: 0 18px 32px rgba(15, 23, 42, 0.22);
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.18s ease, transform 0.18s ease;
      z-index: 10;
    }

    .fleet-tax-popover__bubble::after {
      content: '';
      position: absolute;
      left: 50%;
      top: 100%;
      transform: translateX(-50%);
      border-width: 7px 6px 0 6px;
      border-style: solid;
      border-color: #0f172a transparent transparent transparent;
    }

    .fleet-tax-popover:hover .fleet-tax-popover__bubble,
    .fleet-tax-popover:focus-within .fleet-tax-popover__bubble {
      opacity: 1;
      transform: translateX(-50%) translateY(-2px);
    }

    .fleet-reservation-kicker {
      display: inline-flex;
      margin-bottom: 0.7rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      background: #e8f2ff;
      color: #1d4f91;
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .fleet-reservation-highlight {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 0.85rem;
      margin: 1.35rem 0 1.5rem;
    }

    .fleet-reservation-highlight-item {
      background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
      border: 1px solid #d9e7fb;
      border-radius: 18px;
      padding: 1rem;
    }

    .fleet-reservation-highlight-item span {
      display: block;
      margin-bottom: 0.3rem;
      color: #68819a;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .fleet-reservation-highlight-item strong {
      color: #0f172a;
      font-size: 1.15rem;
      font-weight: 800;
      line-height: 1.2;
    }

    .fleet-highlight-value {
      display: inline-flex;
      align-items: baseline;
      gap: 0.08rem;
      flex-wrap: nowrap;
      white-space: nowrap;
    }

    .fleet-reservation-section + .fleet-reservation-section,
    .fleet-reservation-section + .fleet-reservation-cta,
    .fleet-reservation-head + .fleet-reservation-section {
      margin-top: 1.35rem;
    }

    .fleet-reservation-section h3 {
      margin-bottom: 0.75rem;
      color: #0f172a;
      font-size: 1.05rem;
    }

    .fleet-reservation-list {
      margin: 0;
      padding-left: 1.15rem;
      color: #49627b;
      line-height: 1.7;
    }

    .fleet-reservation-list li + li {
      margin-top: 0.35rem;
    }

    .fleet-reservation-cta {
      margin-top: 1.5rem;
      padding: 1.2rem;
      border-radius: 18px;
      background: linear-gradient(180deg, #eef6ff 0%, #e3f0ff 100%);
      border: 1px solid #d6e5fb;
    }

    .fleet-reservation-cta .btn {
      margin-top: 0.95rem;
      min-width: 220px;
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

    .fleet-contact-modal .modal-content {
      border: 1px solid #dde7f2;
      border-radius: 24px;
      box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
    }

    .fleet-contact-modal .modal-header {
      border-bottom: none;
      padding: 1.5rem 1.5rem 0.5rem;
      align-items: start;
    }

    .fleet-contact-modal .modal-body {
      padding: 0 1.5rem 1.5rem;
    }

    .fleet-modal-kicker {
      display: inline-flex;
      margin-bottom: 0.35rem;
      color: #1d4f91;
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .fleet-contact-modal .contact-section {
      padding: 0;
      margin: 0;
      box-shadow: none;
      border: none;
      background: transparent;
    }

    @media (max-width: 991.98px) {
    }

    @media (max-width: 767.98px) {
      .fleet-product-panel,
      .fleet-copy-card,
      .fleet-reservation-card {
        padding: 1.4rem;
      }

      .fleet-detail-actions .btn {
        width: 100%;
      }

      .fleet-reservation-highlight {
        grid-template-columns: 1fr;
      }

      .fleet-detail-price {
        font-size: 1.7rem;
      }
    }
  </style>
@endpushOnce

@pushOnce('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var unavailableNotice = document.getElementById('fleet-unavailable-notice');

      document.querySelectorAll('[data-unavailable-alert="true"]').forEach(function (element) {
        element.addEventListener('click', function (event) {
          event.preventDefault();

          if (! unavailableNotice) {
            return;
          }

          unavailableNotice.hidden = false;
          unavailableNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
      });
    });
  </script>

  @if ($errors->any() || session('contact_success'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var modalElement = document.getElementById(@json($contactModalId));

        if (! modalElement || typeof bootstrap === 'undefined') {
          return;
        }

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
      });
    </script>
  @endif
@endpushOnce
