<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Localized routes (ADR-0008). The group prefix is '' for English (canonical root)
// and 'fr' for French; transRoute() resolves each segment per-locale from
// lang/{en,fr}/routes.php. So 'dashboard' ↔ '/dashboard' and '/fr/tableau-de-bord'
// resolve the same page. Routes are added French-second as features land.
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localize'],
], function () {
    Route::get(LaravelLocalization::transRoute('routes.dashboard'), function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    // Route stubs (#109). Representative Zone A (personal) and Zone C (officer)
    // routes, each with a French twin whose segments are translated words —
    // exercising the translated-segment pipeline end-to-end before the real pages
    // exist. They all render one shared "coming soon" placeholder.
    $stubRoutes = [
        // Zone A — personal
        'calendar', 'hours', 'directory', 'documents', 'news', 'profile', 'renew',
        // Zone C — officer/admin
        'officer.members', 'officer.communications', 'officer.reports',
        'officer.flash-messages', 'officer.settings',
    ];

    foreach ($stubRoutes as $name) {
        Route::get(LaravelLocalization::transRoute("routes.$name"), fn () => Inertia::render('ComingSoon'))
            ->middleware('auth')
            ->name($name);
    }

    // Dynamic group route. The {group} slug is content: it echoes straight back
    // (no Group model lookup) and stays as-authored in the French URL — only the
    // /groups segment is translated (ADR-0008).
    Route::get(LaravelLocalization::transRoute('routes.groups.show'), function (string $group, ?string $section = null) {
        return Inertia::render('ComingSoon', [
            'group' => $group,
            'section' => $section,
        ]);
    })->middleware('auth')->name('groups.show');
});

// Internal design-system reference page. Login-only (auth) but available in all
// environments so Volunteers can be invited to give feedback via a shared link.
// English-only standalone page; see PRD #37.
Route::get('design-system', function () {
    return Inertia::render('DesignSystem');
})->middleware(['auth'])->name('design-system');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Honest language-boundary fallback (ADR-0008). A /fr/ URL whose French route is
// not registered returns a locale-aware "not translated yet" page (404) rather
// than silently rendering the English page. The polished 404 with a request-
// translation CTA is a later ADR-0008 slice; this is just the honest boundary.
Route::fallback(function (Request $request) {
    $locale = $request->segment(1);
    $supported = array_keys(LaravelLocalization::getSupportedLocales());

    if ($locale !== LaravelLocalization::getDefaultLocale() && in_array($locale, $supported, true)) {
        return Inertia::render('NotTranslated', ['locale' => $locale])
            ->toResponse($request)
            ->setStatusCode(404);
    }

    abort(404);
});
