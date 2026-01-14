<?php

namespace App\Providers;

use App\Http\Responses\FilamentLogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
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
        $storageUrl = (string) config('filesystems.disks.public.url');

        if ($storageUrl === '') {
            $storageUrl = rtrim((string) config('app.url', URL::to('/')), '/').'/storage';
        }

        config()->set('filesystems.disks.public.url', $storageUrl);
    }
}
