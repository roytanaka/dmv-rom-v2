<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answer a write in the language of the page that sent it (#668, ADR-0008). The write seams sit
 * outside the localized route group, so their URL carries no locale and a validation message
 * would come back in English on a French page. This reads the locale from the first path segment
 * of the page the request came from (`/fr/...`) and sets it for the request. A page with no
 * locale segment, or no Referer at all, stays in English, the canonical locale.
 */
class LocalizeFromReferer
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH) ?: '';
        $segment = explode('/', trim($path, '/'))[0];

        // mcamara sets the app locale when the segment is a supported locale, and the default
        // locale otherwise.
        LaravelLocalization::setLocale($segment);

        return $next($request);
    }
}
