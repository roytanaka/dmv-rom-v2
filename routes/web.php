<?php

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
});

// Internal design-system reference page. Login-only (auth) but available in all
// environments so Volunteers can be invited to give feedback via a shared link.
// English-only standalone page; see PRD #37.
Route::get('design-system', function () {
    return Inertia::render('DesignSystem');
})->middleware(['auth'])->name('design-system');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
