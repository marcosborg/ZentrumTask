@extends('website.layout')

@section('title', 'Noticias - Zentrum TVDE')

@push('head')
  <meta name="description" content="Noticias e novidades da Zentrum TVDE para motoristas e parceiros." />
@endpush

@section('content')
  <section class="container pb-4 section-gap">
    <div class="blog-page-header">
      <h2 class="mb-2">Noticias</h2>
      <p class="text-muted">Conteudos, novidades e dicas para quem trabalha com TVDE.</p>
    </div>

    <div class="row g-4">
      @forelse ($posts as $post)
        @php
            $imageUrl = $post->getFirstMediaUrl('featured_image', 'featured_thumb');
            $description = $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 140);
            $publishedAt = $post->published_at?->format('d/m/Y');
        @endphp
        <div class="col-12 col-md-6 col-lg-4">
          <a class="blog-card" href="{{ route('blog.show', ['blogPost' => $post->getKey(), 'slug' => $post->slug]) }}">
            <div
              class="blog-card-image"
              style="background-image: url('{{ $imageUrl ?: asset('website/assets/hero_car_final.png') }}');"
            ></div>
            <div class="blog-card-body">
              <span class="blog-date">{{ $publishedAt }}</span>
              <h5>{{ $post->title }}</h5>
              <p>{{ $description }}</p>
              <span class="blog-link">Ler mais</span>
            </div>
          </a>
        </div>
      @empty
        <div class="col-12 text-muted">Ainda nao existem noticias publicadas.</div>
      @endforelse
    </div>

    @if ($posts->hasPages())
      <div class="d-flex justify-content-center gap-2 mt-4">
        @if ($posts->previousPageUrl())
          <a class="btn-pagination" href="{{ $posts->previousPageUrl() }}">Anterior</a>
        @endif
        @if ($posts->nextPageUrl())
          <a class="btn-pagination" href="{{ $posts->nextPageUrl() }}">Seguinte</a>
        @endif
      </div>
    @endif
  </section>
@endsection

@pushOnce('styles')
  <style>
    .blog-page-header {
      margin-bottom: 2rem;
    }

    .blog-card {
      display: flex;
      flex-direction: column;
      height: 100%;
      text-decoration: none;
      background: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid #e2e8f0;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      color: inherit;
    }

    .blog-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
    }

    .blog-card-image {
      height: 210px;
      background-size: cover;
      background-position: center;
    }

    .blog-card-body {
      padding: 22px;
    }

    .blog-card-body h5 {
      color: #0f172a;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }

    .blog-card-body p {
      color: #475569;
      margin-bottom: 1rem;
    }

    .blog-date {
      display: inline-flex;
      align-items: center;
      font-size: 0.85rem;
      color: #94a3b8;
      margin-bottom: 0.5rem;
    }

    .blog-link {
      color: #2a66b5;
      font-weight: 700;
    }

    .btn-pagination {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      padding: 0.5rem 1.2rem;
      border-radius: 999px;
      text-decoration: none;
      color: #2a66b5;
      font-weight: 700;
    }

    .btn-pagination:hover {
      background: #2a66b5;
      color: #ffffff;
    }
  </style>
@endpushOnce
