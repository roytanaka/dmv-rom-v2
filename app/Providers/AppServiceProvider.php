<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Traits\LoadsTranslatedCachedRoutes;

class AppServiceProvider extends ServiceProvider
{
    // Locale-aware route caching (ADR-0008). The localized routes are incompatible
    // with Laravel's stock `route:cache`; this loads the per-locale cache files
    // produced by `php artisan route:trans:cache` (use that instead of route:cache).
    use LoadsTranslatedCachedRoutes;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RouteServiceProvider::loadCachedRoutesUsing(fn () => $this->loadCachedRoutes());
    }
}
