<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

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

        // The sidebar:state cookie is written client-side by the shadcn
        // SidebarProvider (document.cookie), so it's unencrypted; except it so
        // the server can read it back to seed the sidebar's open state.
        $middleware->encryptCookies(except: ['sidebar:state']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
