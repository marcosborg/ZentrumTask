@php
    $collection = collect($heroes ?? []);
    $slides = $collection->isNotEmpty() ? $collection : collect([null]);
@endphp

<section class="hero container" data-hero-slider>
  <div class="hero-slides-wrapper">
    @foreach ($slides as $index => $hero)
      @php
          $title = $hero?->title ?? 'Ganhe mais como motorista TVDE';
          $subtitle = $hero?->subtitle ?? 'Obtenha flexibilidade e autonomia, trabalhando como motorista TVDE.';
          $ctaText = $hero?->cta_text ?? 'Quero ser motorista';
          $ctaLink = $hero?->cta_link ?? '#';
          $imageUrl = $hero?->getFirstMediaUrl('hero_image', 'hero_cover') ?: $hero?->getFirstMediaUrl('hero_image');
      @endphp

      <div class="hero-slide {{ $index === 0 ? 'active' : '' }}" data-hero-slide>
        <div class="row align-items-center">
          <div class="col-lg-6 mb-4 mb-lg-0">
            <h1 class="display-4">{{ $title }}</h1>
            <p class="lead mb-4">
              {{ $subtitle }}
            </p>
            <div class="d-flex flex-wrap gap-3">
              <a href="{{ $ctaLink }}" class="cta-btn btn-primaria text-decoration-none">{{ $ctaText }}</a>
              <a href="#" class="cta-btn btn-secundaria text-decoration-none">Quero alugar viatura</a>
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
    <div class="hero-dots mt-3 d-flex justify-content-center gap-2" data-hero-dots></div>
  @endif
</section>

@pushOnce('styles')
<style>
  .hero-slides-wrapper {
    position: relative;
  }
  .hero-slide {
    display: none;
  }
  .hero-slide.active {
    display: block;
  }
  .hero-dots button {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: none;
    background: #d1d5db;
    padding: 0;
  }
  .hero-dots button.active {
    background: #f59e0b;
  }
</style>
@endpushOnce

@pushOnce('scripts')
<script>
  (() => {
    const slider = document.querySelector('[data-hero-slider]');
    if (!slider) return;

    const slides = [...slider.querySelectorAll('[data-hero-slide]')];
    if (slides.length <= 1) return;

    const dotsWrapper = slider.querySelector('[data-hero-dots]');
    let current = 0;
    let timer;
    const intervalMs = 6000;

    const createDots = () => {
      slides.forEach((_, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', `Ir para slide ${idx + 1}`);
        btn.addEventListener('click', () => goTo(idx));
        dotsWrapper.appendChild(btn);
      });
    };

    const updateActive = () => {
      slides.forEach((slide, idx) => {
        slide.classList.toggle('active', idx === current);
      });
      if (dotsWrapper) {
        dotsWrapper.querySelectorAll('button').forEach((dot, idx) => {
          dot.classList.toggle('active', idx === current);
        });
      }
    };

    const goTo = (idx) => {
      current = idx;
      updateActive();
      restart();
    };

    const next = () => {
      current = (current + 1) % slides.length;
      updateActive();
    };

    const start = () => {
      timer = setInterval(next, intervalMs);
    };

    const restart = () => {
      clearInterval(timer);
      start();
    };

    createDots();
    updateActive();
    start();
  })();
</script>
@endpushOnce
