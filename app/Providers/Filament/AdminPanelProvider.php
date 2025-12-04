<?php

namespace App\Providers\Filament;

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
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $navigationGroups = [
            NavigationGroup::make('Dashboards'),
            NavigationGroup::make('Kanban')->collapsed(),
            NavigationGroup::make('TVDE')->collapsed(),
            NavigationGroup::make('Website')->collapsed(),
            NavigationGroup::make('Administracao')->collapsed(),
        ];

        $collapsedGroupLabels = collect($navigationGroups)
            ->map(fn (NavigationGroup $group): string => $group->getLabel())
            ->values()
            ->all();

        return $panel
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
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => <<<HTML
                    <script>
                        (() => {
                            const storageKey = 'collapsedGroups';
                            const defaultGroups = {$this->encodeForJs($collapsedGroupLabels)};

                            let stored;

                            try {
                                stored = JSON.parse(localStorage.getItem(storageKey));
                            } catch (error) {
                                stored = null;
                            }

                            if (!Array.isArray(stored) || stored.length === 0) {
                                localStorage.setItem(storageKey, JSON.stringify(defaultGroups));
                            }
                        })();
                    </script>
                HTML,
            )
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
    }

    private function encodeForJs(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}
