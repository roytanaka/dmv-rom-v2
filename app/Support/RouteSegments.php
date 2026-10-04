<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * The URI-segment translation table (ADR-0008), derived from the route tables
 * (lang/{locale}/routes.php) so the segment words stay single-sourced.
 *
 * The frontend localises English-canonical nav hrefs by mapping each path segment
 * through {@see table()}, so a Volunteer on /fr/… navigates to /fr/… twins rather than
 * reverting to English. A route whose free segment is a translated word — the Group
 * page's {section} — reads it back to English with {@see canonical()}.
 */
class RouteSegments
{
    /**
     * Per non-default locale, each English segment that differs → its localised word.
     *
     * Derived purely from static config (the route lang files + supported locales),
     * so it's the same for every request — cached forever and rebuilt on deploy when
     * the cache is cleared.
     *
     * @return array<string, array<string, string>>
     */
    public static function table(): array
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

    /**
     * The English word for a segment localised into $locale (`parametres` → `settings`
     * under fr). An English or unknown segment passes through unchanged.
     */
    public static function canonical(string $segment, string $locale): string
    {
        $english = array_search($segment, self::table()[$locale] ?? [], true);

        return $english === false ? $segment : $english;
    }
}
