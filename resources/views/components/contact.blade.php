@props([
    'heading' => 'Pronto para comecar?',
    'intro' => null,
    'submitLabel' => 'Enviar',
    'source' => 'website_form',
    'vehicle' => null,
    'anchor' => 'contactos',
    'containerClass' => 'container pb-4 section-gap',
    'showSuccess' => true,
    'hideFormOnSuccess' => false,
])

@php
    $formId = 'contact-form-'.($anchor ?: 'default');
    $hasSuccessMessage = $showSuccess && session('contact_success');
    $shouldHideForm = $hideFormOnSuccess && $hasSuccessMessage && ! $errors->any();
@endphp

<section class="{{ $containerClass }}" @if($anchor) id="{{ $anchor }}" @endif>
  <div class="contact-section">
    <h3 class="mb-3">{{ $heading }}</h3>
    @if ($intro)
      <p class="text-muted mb-4">{{ $intro }}</p>
    @endif

    @if ($hasSuccessMessage)
      <div class="alert alert-success mb-3">
        {{ session('contact_success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger mb-3">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @unless ($shouldHideForm)
      <form method="POST" action="{{ route('contact.submit') }}">
        @csrf

        <input type="hidden" name="source" value="{{ $source }}" />
        <input type="hidden" name="page_url" value="{{ url()->current() }}" />

        @if ($vehicle)
          <input type="hidden" name="vehicle_id" value="{{ $vehicle->getKey() }}" />
        @endif

        <div class="row g-3">
          <div class="col-md-6">
            <label for="{{ $formId }}-name" class="form-label">Nome</label>
            <input
              type="text"
              class="form-control"
              id="{{ $formId }}-name"
              name="name"
              placeholder="O seu nome"
              value="{{ old('name') }}"
              required
            />
          </div>
          <div class="col-md-6">
            <label for="{{ $formId }}-phone" class="form-label">Telefone</label>
            <input
              type="text"
              class="form-control"
              id="{{ $formId }}-phone"
              name="phone"
              placeholder="O seu telefone"
              value="{{ old('phone') }}"
              required
            />
          </div>
          <div class="col-md-6">
            <label for="{{ $formId }}-email" class="form-label">Email</label>
            <input
              type="email"
              class="form-control"
              id="{{ $formId }}-email"
              name="email"
              placeholder="O seu email"
              value="{{ old('email') }}"
              required
            />
          </div>
          <div class="col-12">
            <label for="{{ $formId }}-message" class="form-label">Mensagem</label>
            <textarea
              class="form-control"
              id="{{ $formId }}-message"
              name="message"
              rows="3"
              placeholder="{{ $vehicle ? 'Diga-nos quando pretende comecar, duvidas sobre a viatura ou pedido de simulacao.' : 'Escreva a sua mensagem aqui' }}"
              required
            >{{ old('message') }}</textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-submit">{{ $submitLabel }}</button>
          </div>
        </div>
      </form>
    @endunless
  </div>
</section>
