@extends('website.layout')

@section('title', $post->meta_title ?: $post->title)

@push('head')
  @if ($post->meta_description)
    <meta name="description" content="{{ $post->meta_description }}" />
  @endif
@endpush

@section('content')
  @php
      $imageUrl = $post->getFirstMediaUrl('featured_image', 'featured_cover');
  @endphp
  <section class="container pb-4 section-gap">
    <div class="blog-post-wrapper">
      <a class="blog-back" href="{{ route('blog.index') }}">Voltar as noticias</a>
      <h1>{{ $post->title }}</h1>
      <div class="blog-meta">
        <span>{{ $post->published_at?->format('d/m/Y') }}</span>
      </div>

      @if ($imageUrl)
        <img class="blog-hero" src="{{ $imageUrl }}" alt="{{ $post->title }}" />
      @endif

      <div class="blog-content">
        {!! $post->body !!}
      </div>
    </div>
  </section>
@endsection

@pushOnce('styles')
  <style>
    .blog-post-wrapper {
      background: #ffffff;
      border-radius: 18px;
      padding: 32px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .blog-back {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      margin-bottom: 1.5rem;
      color: #2a66b5;
      text-decoration: none;
      font-weight: 700;
    }

    .blog-post-wrapper h1 {
      color: #0f172a;
      font-weight: 800;
      margin-bottom: 0.75rem;
    }

    .blog-meta {
      color: #94a3b8;
      margin-bottom: 1.5rem;
    }

    .blog-hero {
      width: 100%;
      border-radius: 16px;
      margin-bottom: 1.5rem;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .blog-content {
      color: #475569;
      line-height: 1.7;
    }

    .blog-content h2,
    .blog-content h3,
    .blog-content h4 {
      color: #0f172a;
      margin-top: 1.5rem;
      margin-bottom: 0.75rem;
    }

    .blog-content ul {
      padding-left: 1.25rem;
    }
  </style>
@endpushOnce
