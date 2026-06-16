<?php

namespace App\Providers;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Traits\LoadsTranslatedCachedRoutes;

class AppServiceProvider extends ServiceProvider
{
    // Locale-aware route caching (ADR-0008). The localized routes are incompatible
    // with Laravel's stock `route:cache`; this loads the per-locale cache files
    // produced by `php artisan route:trans:cache` (use that instead of route:cache).
    use LoadsTranslatedCachedRoutes;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RouteServiceProvider::loadCachedRoutesUsing(fn () => $this->loadCachedRoutes());

        // Strict-model mode everywhere but production (#153, ADR-0017 §1). Silently
        // dropped mass-assignment — the exact vector that could flip `super_tier` —
        // now throws instead of passing quietly, as do lazy loads (N+1) and reads of
        // attributes that were never loaded. On in development and the test suite so
        // these surface before they ship; off in production so a missed case degrades
        // rather than 500s for a volunteer.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super-tier — the one org-wide grant (ADR-0017 §1). Runs ahead of every
        // gate and policy: `true` grants everything, `null` falls through to the
        // normal check (never `false`), so org-wide authority lives in exactly one
        // place and is never re-checked inside a policy.
        Gate::before(fn (Member $member) => $member->isAllDmv() ? true : null);

        // Coarse, app-wide ability for chrome/nav (ADR-0017 §9): may this member
        // reach member administration at all? Held via the Records stewardship
        // (ADR-0011); super-tier passes through the Gate::before above. Shared as
        // `auth.can.administerMembers`; fine-grained per-record checks use the
        // MemberPolicy. `can` is a UI hint — the server still enforces every action.
        Gate::define('administer-members', fn (Member $member) => $member->hasMemberAdminAuthority());

        // Granting or revoking super-tier is reserved to super-tier itself (ADR-0017
        // §1): the only org-wide grant administers its own membership. The Gate::before
        // above short-circuits `true` for a super-tier actor, so this definition only
        // ever runs for everyone else — and denies them. Records stewardship or any
        // Group officer role buys nothing here.
        Gate::define('manage-super-tier', fn (Member $member) => false);
    }
}
