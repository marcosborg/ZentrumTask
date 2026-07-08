<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\VehicleDetailsOverviewTable;
use App\Filament\Widgets\VehicleDocumentAlertsOverview;
use App\Filament\Widgets\VehicleDocumentAlertsUrgentTable;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $navigationGroups = [
            NavigationGroup::make('Dashboards'),
            NavigationGroup::make('Kanban')->collapsed(),
            NavigationGroup::make('TVDE')->collapsed(),
            NavigationGroup::make('Entradas TVDE')->collapsed(),
            NavigationGroup::make('Website')->collapsed(),
            NavigationGroup::make('Administracao')->collapsed(),
        ];

        $collapsedGroupLabels = collect($navigationGroups)
            ->map(fn (NavigationGroup $group): string => $group->getLabel())
            ->values()
            ->all();

        $homeUrl = url('/');
        $defaultGroupsJson = $this->encodeForJs($collapsedGroupLabels);

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->homeUrl(fn () => \App\Filament\Pages\OpsControl::getUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups($navigationGroups)
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function () use ($defaultGroupsJson): string {
                    $script = <<<'HTML'
                    <script>
                        (() => {
                            const storageKey = 'collapsedGroups';
                            const defaultGroups = __DEFAULT_GROUPS__;

                            let stored;

                            try {
                                stored = JSON.parse(localStorage.getItem(storageKey));
                            } catch (error) {
                                stored = null;
                            }

                            if (!Array.isArray(stored) || stored.length === 0) {
                                localStorage.setItem(storageKey, JSON.stringify(defaultGroups));
                            }

                            const lightboxId = 'vehicle-photo-lightbox';
                            const lightboxStyleId = 'vehicle-photo-lightbox-style';
                            const lightboxState = {
                                urls: [],
                                index: 0,
                            };

                            const ensureLightboxStyle = () => {
                                if (document.getElementById(lightboxStyleId)) {
                                    return;
                                }

                                const style = document.createElement('style');

                                style.id = lightboxStyleId;
                                style.textContent = `
                                    #${lightboxId} {
                                        position: fixed;
                                        inset: 0;
                                        z-index: 9999;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                    }
                                    #${lightboxId}[hidden] {
                                        display: none;
                                    }
                                    #${lightboxId} .zt-lightbox-backdrop {
                                        position: absolute;
                                        inset: 0;
                                        background: rgba(0, 0, 0, 0.82);
                                    }
                                    #${lightboxId} .zt-lightbox-content {
                                        position: relative;
                                        z-index: 1;
                                        width: min(94vw, 1400px);
                                        height: min(92vh, 900px);
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                    }
                                    #${lightboxId} .zt-lightbox-image {
                                        max-width: 100%;
                                        max-height: 100%;
                                        object-fit: contain;
                                        border-radius: 0.75rem;
                                    }
                                    #${lightboxId} .zt-lightbox-button {
                                        position: absolute;
                                        z-index: 2;
                                        width: 2.75rem;
                                        height: 2.75rem;
                                        border: 0;
                                        border-radius: 9999px;
                                        background: rgba(255, 255, 255, 0.2);
                                        color: #fff;
                                        font-size: 1.5rem;
                                        line-height: 1;
                                        cursor: pointer;
                                    }
                                    #${lightboxId} .zt-lightbox-button:hover {
                                        background: rgba(255, 255, 255, 0.32);
                                    }
                                    #${lightboxId} .zt-lightbox-button[disabled] {
                                        opacity: 0.4;
                                        cursor: default;
                                    }
                                    #${lightboxId} .zt-lightbox-prev {
                                        left: 1rem;
                                        top: 50%;
                                        transform: translateY(-50%);
                                    }
                                    #${lightboxId} .zt-lightbox-next {
                                        right: 1rem;
                                        top: 50%;
                                        transform: translateY(-50%);
                                    }
                                    #${lightboxId} .zt-lightbox-close {
                                        right: 1rem;
                                        top: 1rem;
                                        font-size: 1.25rem;
                                    }
                                    #${lightboxId} .zt-lightbox-counter {
                                        position: absolute;
                                        left: 50%;
                                        bottom: 1rem;
                                        transform: translateX(-50%);
                                        color: #fff;
                                        font-size: 0.875rem;
                                        background: rgba(0, 0, 0, 0.45);
                                        padding: 0.375rem 0.625rem;
                                        border-radius: 9999px;
                                    }
                                `;

                                document.head.appendChild(style);
                            };

                            const ensureLightboxElement = () => {
                                let lightbox = document.getElementById(lightboxId);

                                if (lightbox) {
                                    return lightbox;
                                }

                                lightbox = document.createElement('div');
                                lightbox.id = lightboxId;
                                lightbox.hidden = true;
                                lightbox.innerHTML = `
                                    <div class="zt-lightbox-backdrop" data-action="close"></div>
                                    <div class="zt-lightbox-content" role="dialog" aria-modal="true" aria-label="Fotos da viatura">
                                        <button type="button" class="zt-lightbox-button zt-lightbox-prev" data-action="prev" aria-label="Anterior">&#8249;</button>
                                        <img class="zt-lightbox-image" alt="Foto da viatura">
                                        <button type="button" class="zt-lightbox-button zt-lightbox-next" data-action="next" aria-label="Seguinte">&#8250;</button>
                                        <button type="button" class="zt-lightbox-button zt-lightbox-close" data-action="close" aria-label="Fechar">&#10005;</button>
                                        <div class="zt-lightbox-counter"></div>
                                    </div>
                                `;

                                document.body.appendChild(lightbox);

                                return lightbox;
                            };

                            const getPhotoUrls = () => {
                                return Array.from(document.querySelectorAll('.vehicle-photo-upload .filepond--item a.filepond--open-icon'))
                                    .map((link) => link.getAttribute('href'))
                                    .filter((href) => typeof href === 'string' && href.length > 0);
                            };

                            const renderLightbox = () => {
                                const lightbox = ensureLightboxElement();
                                const image = lightbox.querySelector('.zt-lightbox-image');
                                const counter = lightbox.querySelector('.zt-lightbox-counter');
                                const prev = lightbox.querySelector('[data-action="prev"]');
                                const next = lightbox.querySelector('[data-action="next"]');
                                const total = lightboxState.urls.length;

                                if (!total) {
                                    return;
                                }

                                image.src = lightboxState.urls[lightboxState.index];
                                counter.textContent = `${lightboxState.index + 1} / ${total}`;
                                prev.disabled = total <= 1;
                                next.disabled = total <= 1;
                            };

                            const closeLightbox = () => {
                                const lightbox = ensureLightboxElement();

                                lightbox.hidden = true;
                                document.body.style.removeProperty('overflow');
                            };

                            const openLightbox = (url) => {
                                lightboxState.urls = getPhotoUrls();

                                if (!lightboxState.urls.length) {
                                    return;
                                }

                                const nextIndex = lightboxState.urls.indexOf(url);

                                lightboxState.index = nextIndex >= 0 ? nextIndex : 0;

                                const lightbox = ensureLightboxElement();

                                renderLightbox();
                                lightbox.hidden = false;
                                document.body.style.overflow = 'hidden';
                            };

                            const goPrevious = () => {
                                if (!lightboxState.urls.length) {
                                    return;
                                }

                                lightboxState.index = (lightboxState.index - 1 + lightboxState.urls.length) % lightboxState.urls.length;
                                renderLightbox();
                            };

                            const goNext = () => {
                                if (!lightboxState.urls.length) {
                                    return;
                                }

                                lightboxState.index = (lightboxState.index + 1) % lightboxState.urls.length;
                                renderLightbox();
                            };

                            ensureLightboxStyle();
                            ensureLightboxElement();

                            document.addEventListener('click', (event) => {
                                const lightboxAction = event.target.closest(`#${lightboxId} [data-action]`);

                                if (lightboxAction) {
                                    const action = lightboxAction.getAttribute('data-action');

                                    if (action === 'close') {
                                        closeLightbox();
                                    }

                                    if (action === 'prev') {
                                        goPrevious();
                                    }

                                    if (action === 'next') {
                                        goNext();
                                    }

                                    return;
                                }

                                const item = event.target.closest('.vehicle-photo-upload .filepond--item');

                                if (!item) {
                                    return;
                                }

                                if (event.target.closest('a.filepond--download-icon')) {
                                    return;
                                }

                                const openLink = item.querySelector('a.filepond--open-icon');

                                if (!openLink) {
                                    return;
                                }

                                event.preventDefault();
                                event.stopPropagation();

                                openLightbox(openLink.href);
                            });

                            document.addEventListener('keydown', (event) => {
                                const lightbox = document.getElementById(lightboxId);

                                if (!lightbox || lightbox.hidden) {
                                    return;
                                }

                                if (event.key === 'Escape') {
                                    closeLightbox();
                                }

                                if (event.key === 'ArrowLeft') {
                                    goPrevious();
                                }

                                if (event.key === 'ArrowRight') {
                                    goNext();
                                }
                            });
                        })();
                    </script>
                HTML;

                    return str_replace('__DEFAULT_GROUPS__', $defaultGroupsJson, $script);
                },
            )
            ->userMenuItems([
                Action::make('visit-website')
                    ->label('Website')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedGlobeAlt)
                    ->url($homeUrl, true)
                    ->sort(PHP_INT_MAX - 1),
            ])
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\OpsControl::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                VehicleDocumentAlertsOverview::class,
                VehicleDocumentAlertsUrgentTable::class,
                VehicleDetailsOverviewTable::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        if ($this->hasBuiltViteAssets()) {
            $panel->viteTheme('resources/css/filament/admin/theme.css');
        }

        return $panel;
    }

    private function encodeForJs(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function hasBuiltViteAssets(): bool
    {
        return File::exists(public_path('build/manifest.json'))
            && ! Vite::isRunningHot();
    }
}
