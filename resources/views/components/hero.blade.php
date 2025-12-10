@php
    $collection = collect($heroes ?? []);
    $slides = $collection->isNotEmpty() ? $collection : collect([null]);
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
            <div class="hero-slide" style="background-image: url('{{ $imageUrl ?: asset('website/assets/hero_car_final.png') }}');">
              <div class="hero-content">
                <div class="hero-overlay">
                  <h1 class="display-4">{{ $title }}</h1>
                  <p class="lead mb-4">
                    {{ $subtitle }}
                  </p>
                  <div class="d-flex flex-wrap gap-3">
                    <a href="{{ $ctaLink }}" class="cta-btn btn-primaria text-decoration-none">{{ $ctaText }}</a>
                    @if ($ctaSecondaryText && $ctaSecondaryLink)
                      <a href="{{ $ctaSecondaryLink }}" class="cta-btn btn-secundaria text-decoration-none">{{ $ctaSecondaryText }}</a>
                    @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if ($slides->count() > 1)
        <div class="hero-pagination swiper-pagination"></div>
      @endif
    </div>
  </div>
</section>

@pushOnce('styles')
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
    .hero h1,
    .hero .lead {
      color: #0f172a;
    }
    .hero-slide {
      position: relative;
      min-height: 420px;
      border-radius: 0;
      overflow: hidden;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      padding: 40px 6vw;
    }
    .hero-content {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: flex-start;
    }
    .hero-overlay {
      background: linear-gradient(90deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.75) 60%, rgba(255,255,255,0.35) 100%);
      padding: 48px;
      max-width: 640px;
      border-radius: 0;
      margin: 24px;
      box-shadow: 0 14px 30px rgba(0,0,0,0.08);
    }
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
    @media (max-width: 991px) {
      .hero-slider { padding-top: 2.5rem; }
      .hero-slide { min-height: 360px; padding: 32px 5vw; }
      .hero-overlay { margin: 0; width: 100%; }
    }
    @media (max-width: 767px) {
      .hero-slide { min-height: 320px; padding: 28px 20px; }
      .hero-overlay { padding: 32px; }
      .hero-overlay h1 { font-size: 2rem; }
      .hero-overlay .lead { font-size: 1rem; }
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
