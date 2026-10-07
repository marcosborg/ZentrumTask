<?php

namespace App\Providers;

use App\Http\Responses\FilamentLogoutResponse;
use App\Support\AwsDatabaseService;
use App\Support\LocalAdminUserProvider;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, FilamentLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('local-admin', fn (Application $app, array $configuration): LocalAdminUserProvider => new LocalAdminUserProvider($app['hash'], $configuration['model']));

        if (! $this->app->runningInConsole() && config('database-management.local') && config('database.mode') === 'production'
            && ! $this->isDatabaseManagementRequest()) {
            $this->app->make(AwsDatabaseService::class)->ensureTunnel();
        }

        $storageUrl = (string) config('filesystems.disks.public.url');

        if ($storageUrl === '') {
            $storageUrl = rtrim((string) config('app.url', URL::to('/')), '/').'/storage';
        }

        config()->set('filesystems.disks.public.url', $storageUrl);
    }

    protected function isDatabaseManagementRequest(): bool
    {
        $request = $this->app['request'];
        $paths = ['admin/download-database-backup', 'admin/login'];

        if ($request->is(...$paths)) {
            return true;
        }

        if (! $request->is('livewire/update') || ! is_array($request->input('components'))) {
            return false;
        }

        foreach ($request->input('components') as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);

            if (! in_array(data_get($snapshot, 'memo.path'), $paths, true)) {
                return false;
            }
        }

        return true;
    }
}
