@php
    $items = collect($services ?? []);
@endphp

<section class="container py-5">
  <div class="row g-4">
    @forelse ($items as $service)
      <div class="col-md-4">
        <div class="service-card h-100" style="--service-icon-bg: {{ $service->icon_color ?? '#625de3' }};">
          <i class="{{ $service->icon ?? 'fa-solid fa-circle-info' }}" style="background-color: var(--service-icon-bg)"></i>
          <h5 class="mb-2">{{ $service->name }}</h5>
          <p class="mb-0">
            {{ $service->description ?: 'Descrição indisponível no momento.' }}
          </p>
        </div>
      </div>
    @empty
      <div class="col-12 text-center text-light-subtle">
        Nenhum serviço cadastrado ainda.
      </div>
    @endforelse
  </div>
</section>
