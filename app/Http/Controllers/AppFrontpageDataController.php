<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Fleet;
use App\Models\Hero;
use App\Models\CmsPage;
use App\Models\Service;
use App\Models\Stat;
use App\Models\Testimonial;
use App\Models\WebsiteMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AppFrontpageDataController extends Controller
{
    private const PRODUCTION_BASE_URL = 'https://zentrum-tvde.com';

    public function __invoke(): JsonResponse
    {
        $menuItems = Schema::hasTable('website_menu_items')
            ? WebsiteMenuItem::query()
                ->with('children')
                ->whereNull('parent_id')
                ->orderBy('position')
                ->get(['id', 'label', 'url', 'position'])
            : collect();

        $blogUrl = route('blog.index');

        if ($menuItems->isNotEmpty() && ! $menuItems->contains(fn ($item) => $item->url === $blogUrl)) {
            $menuItems->push((object) [
                'id' => 'blog',
                'label' => 'Noticias',
                'url' => $blogUrl,
                'children' => collect(),
            ]);
        }

        $heroes = Schema::hasTable('heroes')
            ? Hero::query()->with('media')->latest('id')->get()
            : collect();

        $services = Schema::hasTable('services')
            ? Service::query()->orderBy('id', 'desc')->get()
            : collect();

        $stats = Schema::hasTable('stats')
            ? Stat::query()->orderBy('id')->get()
            : collect();

        $testimonials = Schema::hasTable('testimonials')
            ? Testimonial::query()->latest('id')->get()
            : collect();

        $fleets = Schema::hasTable('fleets')
            ? Fleet::query()->latest('id')->get()
            : collect();

        $blogPosts = Schema::hasTable('blog_posts')
            ? BlogPost::query()->published()->latest('published_at')->get()
            : collect();

        $cmsPages = Schema::hasTable('cms_pages')
            ? CmsPage::query()->where('is_active', true)->orderBy('id')->get()
            : collect();

        return response()->json([
            'menu' => $menuItems->map(fn ($item) => [
                'id' => (string) $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'path' => $this->resolveAppPath($item->url),
                'icon' => $this->resolveMenuIcon($item->label, $item->url, collect($item->children ?? [])->isNotEmpty()),
                'children' => collect($item->children ?? [])->map(fn ($child) => [
                    'id' => (string) $child->id,
                    'label' => $child->label,
                    'url' => $child->url,
                    'path' => $this->resolveAppPath($child->url),
                    'icon' => $this->resolveMenuIcon($child->label, $child->url, false),
                ])->values()->all(),
            ])->values()->all(),
            'heroes' => $heroes->map(fn (Hero $hero) => [
                'id' => $hero->id,
                'title' => $hero->title,
                'subtitle' => $hero->subtitle,
                'cta_text' => $hero->cta_text,
                'cta_link' => $hero->cta_link,
                'cta_secondary_text' => $hero->cta_secondary_text,
                'cta_secondary_link' => $hero->cta_secondary_link,
                'image_url' => $this->productionUrl(
                    $hero->getFirstMediaUrl('hero_image', 'hero_cover') ?: asset('website/assets/hero_car_final.png')
                ),
            ])->values()->all(),
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'icon' => $service->icon,
                'icon_color' => $service->icon_color,
            ])->values()->all(),
            'stats' => $stats->map(fn (Stat $stat) => [
                'id' => $stat->id,
                'name' => $stat->name,
                'value' => $stat->value,
            ])->values()->all(),
            'testimonials' => $testimonials->map(fn (Testimonial $testimonial) => [
                'id' => $testimonial->id,
                'author_name' => $testimonial->author_name,
                'content' => $testimonial->content,
                'stars' => (int) $testimonial->stars,
                'photo_url' => $testimonial->photo_path
                    ? $this->productionUrl(asset('storage/'.$testimonial->photo_path))
                    : null,
            ])->values()->all(),
            'fleets' => $fleets->map(fn (Fleet $fleet) => [
                'id' => $fleet->id,
                'name' => $fleet->name,
                'image_url' => $this->productionUrl(
                    $fleet->photo_path ? asset('storage/'.$fleet->photo_path) : asset('website/assets/car_sedan.png')
                ),
            ])->values()->all(),
            'steps' => [
                'Registe-se na plataforma',
                'Encontre a viatura ideal',
                'Comece a conduzir',
            ],
            'faqs' => [
                [
                    'question' => 'Que documentos sao necessarios para me tornar motorista?',
                    'answer' => 'Vai precisar do documento de identificacao, carta de conducao e comprovativo de residencia, entre outros.',
                ],
                [
                    'question' => 'Quais sao os requisitos para alugar uma viatura?',
                    'answer' => 'Ter carta de conducao valida e cumprir os criterios de idade minima previstos pela plataforma.',
                ],
                [
                    'question' => 'Posso utilizar a minha propria viatura como motorista TVDE?',
                    'answer' => 'Sim, desde que a viatura cumpra os requisitos legais e seja registada na plataforma.',
                ],
                [
                    'question' => 'Qual e o processo para comprar uma viatura?',
                    'answer' => 'Contacte-nos para obter informacoes sobre a nossa oferta de veiculos em venda e as condicoes de aquisicao.',
                ],
            ],
            'blog_posts' => $blogPosts->map(fn (BlogPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt ?: Str::limit(strip_tags($post->body), 120),
                'body' => $post->body,
                'published_at' => $post->published_at?->format('d/m/Y'),
                'image_url' => $this->productionUrl(
                    $post->getFirstMediaUrl('featured_image', 'featured_thumb') ?: asset('website/assets/hero_car_final.png')
                ),
                'url' => route('blog.show', ['blogPost' => $post->getKey(), 'slug' => $post->slug]),
                'path' => '/tabs/blog/'.$post->id,
            ])->values()->all(),
            'cms_pages' => $cmsPages->map(fn (CmsPage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'highlight' => $page->highlight,
                'body' => $page->body,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'image_url' => $this->productionUrl(
                    $page->getFirstMediaUrl('featured_image', 'featured_cover') ?: asset('website/assets/hero_car_final.png')
                ),
                'path' => '/tabs/cms/'.$page->id,
                'url' => $page->publicUrl(),
            ])->values()->all(),
            'contacts' => [
                [
                    'label' => 'Telefone',
                    'value' => '256 112 333',
                    'href' => 'tel:256112333',
                ],
                [
                    'label' => 'Email',
                    'value' => 'info@zentrum-tvde.com',
                    'href' => 'mailto:info@zentrum-tvde.com',
                ],
            ],
            'meta' => [
                'source' => 'zentrum-laravel-frontpage',
                'generated_at' => now()->toIso8601String(),
            ],
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
        ]);
    }

    private function productionUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim(self::PRODUCTION_BASE_URL, '/').$url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return rtrim(self::PRODUCTION_BASE_URL, '/').'/'.ltrim($url, '/');
        }

        return (string) preg_replace('#^https?://[^/]+#i', rtrim(self::PRODUCTION_BASE_URL, '/'), $url);
    }

    private function resolveAppPath(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $normalized = '/'.ltrim((string) ($path ?: $url), '/');

        if ($normalized === '/') {
            return '/tabs/home';
        }

        if (preg_match('#^/cms/(\d+)(?:/[^/]+)?$#', $normalized, $matches)) {
            return '/tabs/cms/'.$matches[1];
        }

        if (preg_match('#^/blog/(\d+)(?:/[^/]+)?$#', $normalized, $matches)) {
            return '/tabs/blog/'.$matches[1];
        }

        if ($normalized === '/blog') {
            return '/tabs/blog';
        }

        return '/tabs/home';
    }

    private function resolveMenuIcon(?string $label, ?string $url, bool $hasChildren): string
    {
        $labelText = Str::lower(trim((string) $label));
        $urlText = Str::lower(trim((string) $url));
        $text = trim($labelText.' '.$urlText);

        if ($text === '' && $hasChildren) {
            return 'folder-open';
        }

        return match (true) {
            str_contains($labelText, 'inicio') || str_contains($labelText, 'início') || rtrim($urlText, '/') === 'https://zentrum-tvde.com' => 'home',
            str_contains($labelText, 'novos motoristas') || str_contains($urlText, 'novos-motoristas') => 'person-add',
            str_contains($labelText, 'experientes') => 'briefcase',
            str_contains($labelText, 'estrangeiros') => 'globe',
            str_contains($labelText, 'onde operamos') || str_contains($labelText, 'operamos') => 'map',
            str_contains($labelText, 'contactos') => 'call',
            str_contains($urlText, '/blog') || str_contains($labelText, 'noticias') || str_contains($labelText, 'notícias') => 'newspaper',
            str_contains($labelText, 'candidato') || (str_contains($labelText, 'motorista') && $hasChildren) => 'people',
            str_contains($labelText, 'sobre') || str_contains($urlText, 'quem-somos') || str_contains($labelText, 'zentrum') => 'information-circle',
            $hasChildren => 'folder-open',
            default => 'document-text',
        };
    }
}
