@php
    $postsCollection = collect($posts ?? []);
    if ($postsCollection->isEmpty() && \Illuminate\Support\Facades\Schema::hasTable('blog_posts')) {
        $postsCollection = \App\Models\BlogPost::query()
            ->published()
            ->latest('published_at')
            ->take(3)
            ->get();
    }
@endphp

<section class="container pb-4 section-gap" id="blog">
  <div class="blog-news-wrapper">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <h3 class="mb-1">Noticias do blog</h3>
        <p class="mb-0 text-muted">Acompanhe as ultimas novidades e dicas para motoristas TVDE.</p>
      </div>
      <a href="{{ route('blog.index') }}" class="btn-blog-link">Ver todas</a>
    </div>

    <div class="row g-4">
      @forelse ($postsCollection as $post)
        @php
            $imageUrl = $post->getFirstMediaUrl('featured_image', 'featured_thumb');
            $description = $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 120);
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
        <div class="col-12 text-muted">Sem noticias publicadas de momento.</div>
      @endforelse
    </div>
  </div>
</section>

@pushOnce('styles')
  <style>
    .blog-news-wrapper {
      background: #ffffff;
      border-radius: 18px;
      padding: 32px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .blog-card {
      display: flex;
      flex-direction: column;
      height: 100%;
      text-decoration: none;
      background: #f8fafc;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid #e2e8f0;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      color: inherit;
    }

    .blog-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
    }

    .blog-card-image {
      height: 180px;
      background-size: cover;
      background-position: center;
    }

    .blog-card-body {
      padding: 20px;
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

    .btn-blog-link {
      background: #2a66b5;
      color: #ffffff;
      padding: 0.55rem 1.4rem;
      border-radius: 999px;
      text-decoration: none;
      font-weight: 700;
    }

    .btn-blog-link:hover {
      background: #1f4d87;
      color: #ffffff;
    }
  </style>
@endpushOnce
