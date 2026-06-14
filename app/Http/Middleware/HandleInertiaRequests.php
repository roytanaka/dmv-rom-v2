<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            // Active locale, resolved from the URL by mcamara's localize middleware
            // (ADR-0008). Surfaced so the laravel-vue-i18n bridge boots in the right
            // locale on first paint (the prop is in the initial Inertia payload).
            'locale' => app()->getLocale(),
            // Target of the avatar-menu Language switcher (#110): the current
            // page's twin in the other locale, built from the registered
            // translated route so the Volunteer keeps their place. Null on any
            // page that has no twin, so the switcher is hidden rather than
            // offering a link that 404s.
            'localeSwitch' => $this->localeSwitch($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Persisted sidebar state. The cookie is written client-side by the
            // shadcn SidebarProvider (raw, hence excepted from encryption in
            // bootstrap/app.php); seeding it here lets the rail render expanded
            // or collapsed on first paint without a flash. Defaults to open.
            'sidebarOpen' => $request->cookie('sidebar:state') !== 'false',
        ]);
    }

    /**
     * Resolve the Language switcher's target: the current page's twin in the
     * other locale, or null when the current page has no registered twin.
     *
     * The twin is computed via LaravelLocalization::getLocalizedURL() for the
     * current route (ADR-0008). We only offer it when the current path is a
     * registered translated route AND the other locale has a segment for it —
     * pages outside the localized route group (auth, settings, design-system)
     * have no twin and the switcher is hidden.
     *
     * @return array{locale: string, url: string}|null
     */
    private function localeSwitch(Request $request): ?array
    {
        $current = app()->getLocale();

        $target = collect(array_keys(LaravelLocalization::getSupportedLocales()))
            ->first(fn (string $locale) => $locale !== $current);

        if ($target === null) {
            return null;
        }

        // A page has a twin only if its route is a localized route — i.e. its
        // name maps to a `routes.*` segment registered for the target locale
        // (ADR-0008). Routes outside the localized group (home, auth, settings,
        // design-system) have no such key, so the switcher stays hidden.
        $routeName = $request->route()?->getName();

        if ($routeName === null || ! Lang::has("routes.{$routeName}", $target)) {
            return null;
        }

        return [
            'locale' => $target,
            // Resolve the twin from the current URL explicitly rather than the
            // package's internally-held request, which is bound once at route
            // registration and would otherwise resolve the wrong path.
            'url' => LaravelLocalization::getLocalizedURL($target, $request->fullUrl()),
        ];
    }
}
