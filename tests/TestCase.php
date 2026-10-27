<?php

namespace Tests;

use App\Support\FeedbackIdentity;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\LaravelLocalization;

abstract class TestCase extends BaseTestCase
{
    /**
     * The test class whose committed DemoSeeder data sits in the database, if any.
     */
    private static ?string $demoSeededFor = null;

    protected function setUp(): void
    {
        // A new file follows a seedDemoOnce() file: make RefreshDatabase migrate
        // fresh so the committed demo seed never leaks into this file's tests.
        if (self::$demoSeededFor !== null && self::$demoSeededFor !== static::class) {
            RefreshDatabaseState::$migrated = false;
            self::$demoSeededFor = null;
        }

        parent::setUp();
    }

    /**
     * Seed the full DemoSeeder org once per test file, not once per test.
     *
     * A demo seed takes over a second, and parallel runs hand a whole file to one
     * worker, so a file that re-seeds before each test becomes the slowest worker
     * and sets the suite's wall time. The first call leaves the RefreshDatabase
     * transaction, rebuilds the schema, commits the seed, and re-opens the
     * transaction. Each later test in the file rolls back to that seeded state.
     * Call it from the file's beforeEach, after faking HTTP.
     */
    protected function seedDemoOnce(): void
    {
        if (self::$demoSeededFor === static::class) {
            return;
        }

        $connection = DB::connection();
        $connection->rollBack();
        $this->artisan('migrate:fresh');
        $this->app[Kernel::class]->setArtisan(null);
        $this->seed(DemoSeeder::class);
        $this->updateLocalCacheOfInMemoryDatabases();
        $connection->beginTransaction();

        self::$demoSeededFor = static::class;
    }

    /**
     * Migrate the Tester feedback connection (ADR-0029) from its own folder.
     *
     * The connection is a second in-memory SQLite database (phpunit.xml). RefreshDatabase
     * only manages the default connection, so a feedback test calls this from its
     * beforeEach. Each test boots a new app and so a new, empty feedback database.
     */
    protected function migrateFeedbackDatabase(): void
    {
        $this->artisan('migrate', [
            '--database' => 'feedback',
            '--path' => 'database/migrations/feedback',
        ]);
    }

    /**
     * Force the Feedback tool's "uses account identity" predicate on, the Beta path
     * ({@see FeedbackIdentity}). The Beta spec owns the real setting; until then it is
     * off everywhere, and this is how a test reaches the Beta rules.
     */
    protected function forceAccountIdentity(): void
    {
        config([FeedbackIdentity::CONFIG_KEY => true]);
    }

    /**
     * Re-register the application's routes for a locale, then run the given
     * assertions against them. The default locale reloads the unprefixed routes, so a
     * test can go back to English after a French block.
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
        // The default locale's routes have no prefix (ADR-0008): an empty key gives them.
        putenv(LaravelLocalization::ENV_ROUTE_KEY.'='.($locale === app(LaravelLocalization::class)->getDefaultLocale() ? '' : $locale));

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
