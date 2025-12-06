<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200/60 dark:border-gray-700/60 bg-white/70 dark:bg-white/5 p-6 shadow-sm space-y-6">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold leading-6 text-gray-900 dark:text-gray-100">Sitemap</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Gere um sitemap XML e submeta em Google Search Console.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Link</p>
                    <a
                        href="{{ $this->sitemapUrl }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 dark:text-primary-400 break-all"
                        target="_blank"
                        rel="noreferrer"
                    >
                        <x-filament::icon icon="heroicon-o-link" class="w-4 h-4" />
                        <span>{{ $this->sitemapUrl }}</span>
                    </a>
                </div>
                <div class="space-y-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Última geração</p>
                    <p class="text-sm font-semibold">
                        {{ $this->lastGenerated ?? 'Ainda não gerado' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
