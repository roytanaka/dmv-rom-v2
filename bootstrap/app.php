<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // mcamara/laravel-localization — resolves the locale from the URL prefix
        // and translated path segments (ADR-0008). Applied per-route via the
        // localized route group in routes/web.php.
        $middleware->alias([
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localeCookieRedirect' => LocaleCookieRedirect::class,
            'localeViewPath' => LaravelLocalizationViewPath::class,
        ]);

        // The sidebar:state cookie is written client-side by the shadcn
        // SidebarProvider (document.cookie), so it's unencrypted; except it so
        // the server can read it back to seed the sidebar's open state.
        $middleware->encryptCookies(except: ['sidebar:state']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A 419 (CSRF token mismatch) almost always means the session expired
        // while the page sat open. Never show the bare "Page Expired" screen:
        // a guest goes to sign-in and lands back on the page they were on; a
        // signed-in member goes back to that page with a fresh token. 303 so
        // Inertia follows with a GET after a PUT/PATCH/DELETE.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            $previous = url()->previous();
            $sameOrigin = $previous === url('/') || str_starts_with($previous, url('/').'/');

            if (Auth::check()) {
                return redirect()->to($sameOrigin ? $previous : route('dashboard'), 303);
            }

            if ($sameOrigin && $previous !== route('login')) {
                redirect()->setIntendedUrl($previous);
            }

            return redirect()->route('login', status: 303)->with('sessionExpired', true);
        });
    })->create();
