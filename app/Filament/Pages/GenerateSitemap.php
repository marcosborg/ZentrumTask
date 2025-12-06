<?php

namespace App\Filament\Pages;

use App\Models\CmsPage;
use App\Models\WebsiteMenuItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use UnitEnum;

class GenerateSitemap extends Page
{
    protected static ?string $navigationLabel = 'Gerar sitemap';

    protected static ?string $title = 'Gerar sitemap';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'website/sitemap';

    protected string $view = 'filament.pages.generate-sitemap';

    public string $sitemapUrl;

    public ?string $lastGenerated = null;

    public function mount(): void
    {
        $this->sitemapUrl = url('/sitemap.xml');
        $sitemapPath = public_path('sitemap.xml');

        if (File::exists($sitemapPath)) {
            $this->lastGenerated = Carbon::createFromTimestamp(File::lastModified($sitemapPath))->toDayDateTimeString();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSitemap')
                ->label('Gerar sitemap')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->action('generateSitemap'),
        ];
    }

    public function generateSitemap(): void
    {
        $baseUrl = rtrim(config('app.url') ?? url('/'), '/');

        $links = collect([
            $baseUrl.'/',
        ]);

        $menuLinks = WebsiteMenuItem::query()
            ->with('children')
            ->get()
            ->flatMap(function (WebsiteMenuItem $item) {
                return $item->children->isNotEmpty() ? [$item, ...$item->children] : [$item];
            })
            ->filter(fn (WebsiteMenuItem $item) => filled($item->url))
            ->map(fn (WebsiteMenuItem $item) => $this->normalizeUrl($item->url, $baseUrl));

        $cmsLinks = CmsPage::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (CmsPage $page) => url('/cms/'.$page->getKey().'/'.Str::slug($page->title)));

        $links = $links
            ->merge($menuLinks)
            ->merge($cmsLinks)
            ->unique()
            ->values();

        $xml = $this->buildSitemap($links);

        File::put(public_path('sitemap.xml'), $xml);

        $this->lastGenerated = now()->toDayDateTimeString();

        Notification::make()
            ->title('Sitemap gerado')
            ->body("Disponível em {$this->sitemapUrl}")
            ->success()
            ->send();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $links
     */
    private function buildSitemap($links): string
    {
        $items = $links
            ->map(fn (string $link) => '<url><loc>'.e($link).'</loc><lastmod>'.now()->toAtomString().'</lastmod></url>')
            ->implode('');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$items}
</urlset>
XML;
    }

    private function normalizeUrl(string $url, string $baseUrl): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $baseUrl.'/'.ltrim($url, '/');
    }
}
