<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Seam C — the avatar-menu Language switcher target. The shared `localeSwitch`
// prop carries the URL of the current page's twin in the other locale, computed
// via LaravelLocalization::getLocalizedURL(). It is absent on any page that has
// no registered twin, so the Volunteer is never offered a link that 404s.
class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_switcher_targets_the_french_twin_on_a_translatable_english_route(): void
    {
        $this->actingAs(User::factory()->create());

        // The twin URL is what LaravelLocalization::getLocalizedURL('fr') resolves
        // for the dashboard route — the French segment, not a bare /fr/ prefix.
        $this->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page
                ->where('localeSwitch.locale', 'fr')
                ->where('localeSwitch.url', url('/fr/tableau-de-bord'))
        );
    }

    public function test_switcher_targets_the_english_twin_on_a_translatable_french_route(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withLocaleRoutes('fr', function () {
            // English is canonical at the root, so the twin drops the locale
            // prefix entirely (getLocalizedURL('en') → /dashboard).
            $this->get('/fr/tableau-de-bord')->assertInertia(
                fn (Assert $page) => $page
                    ->where('localeSwitch.locale', 'en')
                    ->where('localeSwitch.url', url('/dashboard'))
            );
        });
    }

    public function test_switcher_keeps_the_group_slug_in_the_dynamic_route_twin(): void
    {
        $this->actingAs(User::factory()->create());

        // Only the /groups segment is translated; the {group} slug is content and
        // stays as-authored in the twin URL (ADR-0008).
        $this->get('/groups/docents')->assertInertia(
            fn (Assert $page) => $page
                ->where('localeSwitch.locale', 'fr')
                ->where('localeSwitch.url', url('/fr/groupes/docents'))
        );
    }

    public function test_switcher_is_absent_on_a_page_with_no_registered_twin(): void
    {
        $this->actingAs(User::factory()->create());

        // The internal design-system page is English-only — it lives outside the
        // localized route group, so it has no /fr/ twin (PRD #37). The switcher
        // must not offer a link that would 404.
        $this->get('/design-system')->assertInertia(
            fn (Assert $page) => $page->where('localeSwitch', null)
        );
    }
}
