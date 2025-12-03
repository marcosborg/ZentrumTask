@php
    $items = collect($stats ?? []);
    $testimonials = collect($testimonials ?? []);
@endphp

<section class="container pb-5">
  <div class="row g-4 align-items-stretch">
    <div class="col-lg-6">
      <div class="row g-3">
        @forelse ($items as $stat)
          <div class="col-12 col-sm-6">
            <div class="stat-card h-100 d-flex flex-column justify-content-center">
              <h3>{{ $stat->value }}</h3>
              <p class="mb-0">{{ $stat->name }}</p>
            </div>
          </div>
        @empty
          <div class="col-12 text-light-subtle">Nenhuma estatística cadastrada.</div>
        @endforelse
      </div>
    </div>
    <div class="col-lg-6">
      <div class="swiper testimonial-swiper h-100">
        <div class="swiper-wrapper">
          @forelse ($testimonials as $testimonial)
            <div class="swiper-slide h-100">
              <div class="testimonial-card d-flex flex-column justify-content-between h-100">
                <div class="d-flex align-items-center mb-2">
                  @if ($testimonial->photo_path)
                    <img
                      src="{{ Storage::disk('public')->url($testimonial->photo_path) }}"
                      alt="{{ $testimonial->author_name }}"
                      class="testimonial-photo"
                    />
                  @else
                    <div class="testimonial-avatar">
                      <i class="fa-solid fa-user"></i>
                    </div>
                  @endif
                  <div>
                    <h6 class="mb-0">{{ $testimonial->author_name }}</h6>
                    <div class="text-warning">
                      @for ($i = 0; $i < max(0, (int) $testimonial->stars); $i++)
                        <i class="fa-solid fa-star"></i>
                      @endfor
                    </div>
                  </div>
                </div>
                <p class="mb-0" style="font-size: 0.9rem; color: #bec8e4;">
                  {{ $testimonial->content }}
                </p>
              </div>
            </div>
          @empty
            <div class="swiper-slide">
              <div class="testimonial-card">
                <p class="mb-0 text-light-subtle">Nenhum depoimento cadastrado.</p>
              </div>
            </div>
          @endforelse
        </div>
        <div class="swiper-pagination testimonial-pagination mt-3"></div>
      </div>
    </div>
  </div>
</section>

@pushOnce('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
@endpushOnce

@pushOnce('scripts')
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const swiperEl = document.querySelector('.testimonial-swiper');
      if (!swiperEl) return;

      new Swiper(swiperEl, {
        loop: true,
        autoplay: {
          delay: 6000,
          disableOnInteraction: false,
        },
        pagination: {
          el: '.testimonial-pagination',
          clickable: true,
        },
        spaceBetween: 16,
        effect: 'slide',
        speed: 650,
      });
    });
  </script>
@endpushOnce
