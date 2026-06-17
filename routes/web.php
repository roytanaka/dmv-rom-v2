<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SuperTierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// Root lands on login for guests, dashboard for authenticated members.
// The login page (auth/Login) is the front door; '/' just routes to it.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
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
        'calendar', 'hours', 'directory', 'documents', 'profile', 'renew',
        // Zone C — officer/admin
        'officer.members', 'officer.communications', 'officer.reports',
        'officer.flash-messages', 'officer.settings',
    ];

    foreach ($stubRoutes as $name) {
        Route::get(LaravelLocalization::transRoute("routes.$name"), fn () => Inertia::render('ComingSoon'))
            ->middleware('auth')
            ->name($name);
    }

    // Org-wide news feed (#155, ADR-0017 §5). The read is open to every logged-in
    // member regardless of their Groups — one feed, localized chrome. Writes live
    // on the non-localized seam routes below; this is the canonical feed page,
    // replacing the earlier ComingSoon stub now that the feature has landed.
    Route::get(LaravelLocalization::transRoute('routes.news'), [NewsController::class, 'index'])
        ->middleware('auth')->name('news');

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

// Member administration (ADR-0017). Editing a member record is gated by the
// MemberPolicy via the UpdateMemberRequest: self by default, Records or super-tier
// for anyone else. The richer member-admin UI (and its localized routes) lands in
// a later slice.
Route::patch('members/{member}', [MemberController::class, 'update'])
    ->middleware(['auth'])
    ->name('members.update');

// Member record (ADR-0017). Readable by any logged-in member; the payload routes
// through the centralized MemberResource, whose allowlist gates contact PII behind
// the `viewContact` ability. The localized directory/profile UI lands in a later
// slice — this is the data seam.
Route::get('members/{member}', [MemberController::class, 'show'])
    ->middleware(['auth'])
    ->name('members.show');

// Grant/revoke super-tier (ADR-0017 §1). A dedicated, separately-gated action —
// never a field on a member form. Reserved to super-tier itself via the
// `manage-super-tier` gate in the UpdateSuperTierRequest; everyone else is denied.
Route::put('members/{member}/super-tier', SuperTierController::class)
    ->middleware(['auth'])
    ->name('members.super-tier.update');

// News feed mutations (#155, ADR-0017 §5). The non-localized write seam: posting,
// editing, and deleting are each structurally authorized in their Form Request,
// which delegates to the NewsPolicy — a news-editor of the posting Group, only
// while its announcements capability is on. The localized read lives on the `news`
// route above.
Route::post('news', [NewsController::class, 'store'])
    ->middleware(['auth'])
    ->name('news.store');
Route::patch('news/{news}', [NewsController::class, 'update'])
    ->middleware(['auth'])
    ->name('news.update');
Route::delete('news/{news}', [NewsController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('news.destroy');

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
