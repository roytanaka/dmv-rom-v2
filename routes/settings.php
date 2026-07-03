<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// Settings routes, localized per ADR-0008 (#229). They live in the same localized
// group as the rest of the app so each page has a French twin — /settings/profile
// ↔ /fr/parametres/profil — and a French Member stays in French. transRoute()
// resolves each segment per-locale from lang/{en,fr}/routes.php; the route NAME is
// kept identical to the translation-key suffix (settings.profile ↔ routes.settings.profile)
// so the top-bar language switcher can find the twin (HandleInertiaRequests::twinUrl).
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localize', 'auth'],
], function () {
    Route::get(LaravelLocalization::transRoute('routes.settings'), fn () => redirect()->route('settings.profile'));

    Route::get(LaravelLocalization::transRoute('routes.settings.profile'), [ProfileController::class, 'edit'])->name('settings.profile');
    Route::patch(LaravelLocalization::transRoute('routes.settings.profile'), [ProfileController::class, 'update'])->name('settings.profile.update');
    // Remove the profile photo (#234) — a dedicated action so clearing the picture is
    // independent of the main profile save (no hidden flag riding along a field edit).
    Route::delete(LaravelLocalization::transRoute('routes.settings.profile.photo'), [ProfileController::class, 'destroyPhoto'])->name('settings.profile.photo.destroy');

    Route::get(LaravelLocalization::transRoute('routes.settings.password'), [PasswordController::class, 'edit'])->name('settings.password');
    Route::put(LaravelLocalization::transRoute('routes.settings.password'), [PasswordController::class, 'update'])->name('settings.password.update');
});
