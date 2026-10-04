<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// A directory under public/ that shares its name with a route's first segment
// shadows that route on Apache: mod_dir redirects `/help` to `/help/`, the
// directory exists, `-Indexes` answers 403, and Laravel never runs. Sail's
// `artisan serve` does not do this, so the tests are the only gate. The help
// screenshots live under public/help-images/ for exactly this reason (#539).
it('has no public folder that shadows a route', function () {
    $folders = collect(File::directories(public_path()))
        ->map(fn (string $path) => basename($path));

    $segments = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => Str::before(ltrim($route->uri(), '/'), '/'))
        ->reject(fn (string $segment) => $segment === '' || Str::startsWith($segment, '{'))
        ->unique();

    expect($folders->intersect($segments)->values()->all())->toBe([]);
});
