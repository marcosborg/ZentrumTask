@php
    $collection = collect($heroes ?? []);
    $slides = $collection->isNotEmpty() ? $collection : collect([null]);
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.whatsapp.public_number'));
    $whatsappMessage = (string) config('services.whatsapp.public_message');
    $whatsappHref = $whatsappNumber !== ''
        ? 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($whatsappMessage)
        : null;
@endphp

<section class="hero hero-slider">
  <div class="container hero-container-fluid">
    <div class="swiper hero-swiper">
      <div class="swiper-wrapper">
        @foreach ($slides as $hero)
          @php
              $title = $hero?->title ?? 'Ganhe mais como motorista TVDE';
              $subtitle = $hero?->subtitle ?? 'Obtenha flexibilidade e autonomia, trabalhando como motorista TVDE.';
              $ctaText = $hero?->cta_text ?? 'Quero ser motorista';
              $ctaLink = $hero?->cta_link ?? '#';
              $ctaSecondaryText = $hero?->cta_secondary_text;
              $ctaSecondaryLink = $hero?->cta_secondary_link;
              $imageUrl = $hero?->getFirstMediaUrl('hero_image') ?: $hero?->getFirstMediaUrl('hero_image', 'hero_cover');
          @endphp
          <div class="swiper-slide">
            <div class="ads-cover">
              <div class="container">
                <h1>{{ $title }}</h1>
                <p class="lead">{{ $subtitle }}</p>
                <div class="d-flex flex-wrap gap-2">
                  @if ($whatsappHref)
                    <a href="{{ $whatsappHref }}" target="_blank" rel="noopener noreferrer" class="btn hero-whatsapp-btn">
                      <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                      WhatsApp
                    </a>
                  @endif
                  <a href="{{ $ctaLink }}" class="btn btn-light">{{ $ctaText }}</a>
                  @if ($ctaSecondaryText && $ctaSecondaryLink)
                    <a href="{{ $ctaSecondaryLink }}" class="btn btn-outline-light">{{ $ctaSecondaryText }}</a>
                  @endif
                </div>
              </div>
              <img src="{{ $imageUrl ?: asset('website/assets/hero_car_final.png') }}" alt="">
            </div>
          </div>
        @endforeach
        <div class="swiper-slide">
          <div class="ads-cover">
            <div class="container">
              <p class="ads-label">Zentrum Ads / Publicidade em movimento</p>
              <h1>A sua marca. Sempre em movimento.</h1>
              <p class="lead">Transforme as ruas num espaço de comunicação para a sua empresa. Leve a sua marca mais longe através de campanhas publicitárias em viaturas TVDE.</p>
              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light" href="{{ route('ads.index') }}#proposta">Pedir proposta de publicidade</a>
                <a class="btn btn-outline-light" href="{{ route('ads.index') }}#como-funciona">Descobrir como funciona</a>
              </div>
            </div>
            <img src="{{ asset('website/assets/ads-tesla.webp') }}" width="2000" height="698" alt="Tesla branco com publicidade Zentrum Ads nas portas laterais">
          </div>
        </div>
      </div>

      <div class="hero-pagination swiper-pagination"></div>
    </div>
  </div>
</section>

@pushOnce('styles')
  <link rel="stylesheet" media="screen" href="{{ asset('website/assets/advertising.css') }}?v={{ hash_file('sha256', public_path('website/assets/advertising.css')) }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <style>
    .hero-slider {
      overflow: hidden;
      background: linear-gradient(135deg, #f5f9ff 0%, #e9f1ff 100%);
      padding: 0;
    }
    .hero-container-fluid {
      max-width: 100%;
      padding-left: 0;
      padding-right: 0;
    }
    .hero-swiper {
      position: relative;
    }
    .hero-swiper > .swiper-wrapper {
      height: auto;
      align-items: stretch;
    }
    .hero-swiper .swiper-slide {
      display: flex;
      height: auto;
    }
    .hero-swiper .ads-cover {
      flex: 1;
      min-width: 0;
    }
    .hero-slider .ads-cover img { object-position: 75% center; }
    .hero-pagination {
      margin-top: 1.5rem;
      position: static;
    }
    .hero-pagination .swiper-pagination-bullet {
      background: #475569;
      opacity: 1;
    }
    .hero-pagination .swiper-pagination-bullet-active {
      background: #2dd4bf;
    }
    .hero-whatsapp-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #25d366;
      color: #102e4c;
      border: 0;
      box-shadow: 0 12px 24px rgba(37, 211, 102, 0.28);
    }
    .hero-whatsapp-btn:hover {
      background: #1ebe5d;
      color: #102e4c;
      transform: translateY(-1px);
    }
  </style>
@endpushOnce

@pushOnce('scripts')
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const swiperEl = document.querySelector('.hero-swiper');
      if (!swiperEl) return;

      new Swiper(swiperEl, {
        loop: true,
        autoplay: {
          delay: 5000,
          disableOnInteraction: false,
        },
        pagination: {
          el: '.hero-pagination',
          clickable: true,
        },
        spaceBetween: 32,
        effect: 'fade',
        fadeEffect: {
          crossFade: true,
        },
        speed: 750,
      });
    });
  </script>
@endpushOnce
