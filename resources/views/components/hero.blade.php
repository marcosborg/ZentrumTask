@php
    $collection = collect($heroes ?? []);
    $slides = $collection->isNotEmpty() ? $collection : collect([null]);
@endphp

<section class="hero container hero-slider">
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
          <div class="row align-items-center gx-lg-5">
            <div class="col-lg-6 mb-4 mb-lg-0">
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
            <div class="col-lg-6 text-center">
              <img
                src="{{ $imageUrl ?: asset('website/assets/hero_car_final.png') }}"
                alt="{{ $title }}"
                class="hero-image img-fluid"
              />
            </div>
          </div>
        </div>
      @endforeach
    </div>

    @if ($slides->count() > 1)
      <div class="hero-pagination swiper-pagination"></div>
    @endif
  </div>
</section>

@pushOnce('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <style>
    .hero-slider {
      overflow: hidden;
    }
    .hero-swiper {
      position: relative;
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
