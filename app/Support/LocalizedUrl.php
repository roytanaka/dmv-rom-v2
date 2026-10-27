<?php

namespace App\Support;

use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Absolute links for an email, built for the recipient's locale (ADR-0008), not the locale of
 * the request that sends it. The Feedback Follower emails use them (spec #856).
 *
 * Laravel's `URL::signedRoute()` can only build the URL for the locale whose routes this
 * request registered, so {@see signed()} builds the localized URL from the route files and
 * signs it the way Laravel signs one: an HMAC of the absolute URL under the current app key,
 * with no expiry. The `signed` middleware checks it, against `app.previous_keys` too.
 */
class LocalizedUrl
{
    /**
     * @param  string  $route  the route's name in the route files, without `routes.`
     * @param  array<string, mixed>  $parameters
     */
    public static function to(string $locale, string $route, array $parameters = []): string
    {
        return LaravelLocalization::getURLFromRouteNameTranslated($locale, "routes.{$route}", $parameters);
    }

    /**
     * @param  string  $route  the route's name in the route files, without `routes.`
     * @param  array<string, mixed>  $parameters
     */
    public static function signed(string $locale, string $route, array $parameters = []): string
    {
        $url = self::to($locale, $route, $parameters);

        return $url.'?signature='.hash_hmac('sha256', $url, config('app.key'));
    }
}
