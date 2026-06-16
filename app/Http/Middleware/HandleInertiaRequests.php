<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
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
            // Per-locale URI-segment translation table, for localising the static
            // nav hrefs (the fixture authors them English-canonical) so in-app
            // navigation stays in the active locale instead of reverting to English
            // (ADR-0008). Keyed by non-default locale → { englishSegment: localised }.
            'routeSegments' => $this->routeSegments(),
            // Top-bar language switcher (ADR-0013): the active locale plus every
            // supported locale's twin URL for the current page. Each option's url
            // is the page's twin in that locale, or null when no twin is
            // registered — the option renders disabled rather than offering a link
            // that 404s (ADR-0008 / #110).
            'localeSwitcher' => $this->localeSwitcher($request),
            'auth' => [
                'user' => $request->user(),
                // Coarse, app-wide capability map for chrome/nav (ADR-0017 §9).
                // UI hint only — every action is enforced server-side; hiding a
                // control is never the lock. Fine-grained per-resource `can` props
                // are computed by the relevant policy on each page.
                'can' => [
                    'administerMembers' => (bool) $request->user()?->can('administer-members'),
                ],
            ],
            // Persisted sidebar state. The cookie is written client-side by the
            // shadcn SidebarProvider (raw, hence excepted from encryption in
            // bootstrap/app.php); seeding it here lets the rail render expanded
            // or collapsed on first paint without a flash. Defaults to open.
            'sidebarOpen' => $request->cookie('sidebar:state') !== 'false',
        ]);
    }

    /**
     * Resolve the top-bar language switcher: the active locale and one option per
     * supported locale, each carrying the current page's twin URL in that locale.
     *
     * A locale's url is null when the current page has no registered twin in it —
     * the active locale (no self-link needed) and any locale outside the page's
     * localized route group (auth, settings, design-system). The component renders
     * those disabled so we never offer a link that 404s (ADR-0008 / #110).
     *
     * @return array{current: string, options: list<array{code: string, label: string, url: string|null}>}
     */
    private function localeSwitcher(Request $request): array
    {
        $current = app()->getLocale();
        $routeName = $request->route()?->getName();

        $options = collect(LaravelLocalization::getSupportedLocales())
            ->map(fn (array $props, string $code) => [
                'code' => $code,
                'label' => $this->localeLabel($code, $props),
                'url' => $code === $current ? null : $this->twinUrl($request, $routeName, $code),
            ])
            ->values()
            ->all();

        return ['current' => $current, 'options' => $options];
    }

    /**
     * The current page's twin URL in the given locale, or null when no twin is
     * registered (the route has no segment translation for that locale).
     */
    private function twinUrl(Request $request, ?string $routeName, string $locale): ?string
    {
        if ($routeName === null || ! Lang::has("routes.{$routeName}", $locale)) {
            return null;
        }

        // Build via route name + params rather than the raw URL string.
        // getLocalizedURL(url) must reverse-match the path to a route before
        // applying the segment table — a step that fails FR→EN on the dynamic
        // group route and leaves /groupes untranslated (#121, ADR-0008).
        return LaravelLocalization::getURLFromRouteNameTranslated(
            $locale,
            "routes.{$routeName}",
            $request->route()->parameters(),
        );
    }

    /**
     * The switcher label for a locale — its autonym (the language named in itself:
     * English, Français), falling back to the configured native name.
     *
     * @param  array{native?: string}  $props
     */
    private function localeLabel(string $code, array $props): string
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
        ][$code] ?? Str::ucfirst($props['native'] ?? $code);
    }

    /**
     * URI-segment translation table per non-default locale, derived from the route
     * tables (lang/{locale}/routes.php) so the segment words stay single-sourced.
     *
     * The frontend localises English-canonical nav hrefs by mapping each path
     * segment through this table (slugs and {params} pass through unchanged), so a
     * Volunteer on /fr/… navigates to /fr/… twins rather than reverting to English.
     *
     * Derived purely from static config (the route lang files + supported locales),
     * so it's the same for every request — cached forever and rebuilt on deploy when
     * the cache is cleared, rather than recomputed on every Inertia response.
     *
     * @return array<string, array<string, string>>
     */
    private function routeSegments(): array
    {
        return Cache::rememberForever('inertia.route_segments', function (): array {
            $default = LaravelLocalization::getDefaultLocale();
            $base = Lang::get('routes', [], $default);

            $out = [];

            foreach (array_keys(LaravelLocalization::getSupportedLocales()) as $locale) {
                if ($locale === $default) {
                    continue;
                }

                $target = Lang::get('routes', [], $locale);
                $dict = [];

                foreach ($base as $key => $basePattern) {
                    $baseSegs = explode('/', $basePattern);
                    $targetSegs = explode('/', $target[$key] ?? $basePattern);

                    foreach ($baseSegs as $i => $segment) {
                        $localised = $targetSegs[$i] ?? $segment;

                        // Only record words that actually differ; skip {param}
                        // placeholders (group slugs are content, never translated).
                        if ($segment !== $localised && ! str_starts_with($segment, '{')) {
                            $dict[$segment] = $localised;
                        }
                    }
                }

                $out[$locale] = $dict;
            }

            return $out;
        });
    }
}
