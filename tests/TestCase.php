<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\LaravelLocalization;

abstract class TestCase extends BaseTestCase
{
    /**
     * Re-register the application's routes for a non-default locale, then run the
     * given assertions against them.
     *
     * mcamara/laravel-localization registers only one locale's routes per app boot
     * — driven by the URL segment, or the ROUTING_LOCALE env its own
     * route:trans:cache command uses. In production every request boots fresh, so
     * both `/dashboard` and `/fr/tableau-de-bord` resolve; but a test process boots
     * once (routes load in setUp under the default locale), so the non-default
     * locale's routes never register. Forcing the locale via the package's env key
     * and reloading the route files mirrors a real request for that locale. We
     * reload the routes rather than refreshApplication() so the RefreshDatabase
     * transaction (and the acting user) survive.
     */
    protected function withLocaleRoutes(string $locale, callable $callback): void
    {
        putenv(LaravelLocalization::ENV_ROUTE_KEY.'='.$locale);

        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection);
        // Re-register inside the 'web' middleware group (where HandleInertiaRequests
        // — and thus the shared `locale` prop — lives), matching how the framework
        // wraps routes/web.php at boot.
        Route::middleware('web')->group(base_path('routes/web.php'));
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();

        try {
            $callback();
        } finally {
            putenv(LaravelLocalization::ENV_ROUTE_KEY);
        }
    }
}
