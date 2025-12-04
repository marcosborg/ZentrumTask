@extends('website.layout')

@push('head')
  <title>{{ $page->meta_title ?? $page->title }} | Zentrum TVDE</title>
  @if ($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}">
  @endif
@endpush

@section('content')
  @php
      $heroImage = $page->getFirstMediaUrl('featured_image', 'featured_cover') ?: '/website/assets/hero.jpg';
  @endphp

  <section
    class="py-5"
    style="background: linear-gradient(135deg, rgba(17,24,39,0.85), rgba(17,24,39,0.65)), url('{{ $heroImage }}') center/cover no-repeat;"
  >
    <div class="container text-white py-5">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <p class="mb-2 text-uppercase fw-semibold text-warning small">Zentrum TVDE</p>
          <h1 class="display-4 fw-bold mb-3">{{ $page->title }}</h1>
          <p class="lead mb-0">
            {{ $page->highlight }}
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5 bg-light">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <article class="bg-white shadow-sm rounded-3 p-4 p-lg-5">
            <div class="cms-body">
              {!! $page->body !!}
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container">
      <x-contact />
    </div>
  </section>
@endsection
