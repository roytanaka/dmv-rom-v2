<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Seam C — the avatar-menu language switcher (ADR-0013). The shared `localeSwitcher`
// prop carries the active locale plus one option per supported locale, each with
// the current page's twin URL in that locale. An option's url is null when the page
// has no twin in it (the active locale, or a page outside the localized route
// group), so the Volunteer is never offered a link that 404s. Locale order follows
// config/laravellocalization.php: en (0), fr (1).
class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_switcher_targets_the_french_twin_on_a_translatable_english_route(): void
    {
        $this->actingAs(Member::factory()->create());

        // The fr option's url is the dashboard route's French twin — the translated
        // segment, not a bare /fr/ prefix.
        $this->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page
                ->where('localeSwitcher.current', 'en')
                ->where('localeSwitcher.options.0.code', 'en')
                ->where('localeSwitcher.options.0.url', null)
                ->where('localeSwitcher.options.1.code', 'fr')
                ->where('localeSwitcher.options.1.url', url('/fr/tableau-de-bord'))
        );
    }

    public function test_switcher_targets_the_english_twin_on_a_translatable_french_route(): void
    {
        $this->actingAs(Member::factory()->create());

        $this->withLocaleRoutes('fr', function () {
            // English is canonical at the root, so the twin drops the locale
            // prefix entirely (the en option's url → /dashboard).
            $this->get('/fr/tableau-de-bord')->assertInertia(
                fn (Assert $page) => $page
                    ->where('localeSwitcher.current', 'fr')
                    ->where('localeSwitcher.options.0.code', 'en')
                    ->where('localeSwitcher.options.0.url', url('/dashboard'))
                    ->where('localeSwitcher.options.1.url', null)
            );
        });
    }

    public function test_switcher_keeps_the_group_slug_in_the_dynamic_route_twin(): void
    {
        Group::factory()->create(['slug' => 'docents']);
        $this->actingAs(Member::factory()->create());

        // Only the /groups segment is translated; the {group} slug is content and
        // stays as-authored in the twin URL (ADR-0008).
        $this->get('/groups/docents')->assertInertia(
            fn (Assert $page) => $page
                ->where('localeSwitcher.current', 'en')
                ->where('localeSwitcher.options.1.code', 'fr')
                ->where('localeSwitcher.options.1.url', url('/fr/groupes/docents'))
        );
    }

    public function test_switcher_translates_the_group_segment_back_on_a_french_route(): void
    {
        Group::factory()->create(['slug' => 'gallery-interpreters']);
        $this->actingAs(Member::factory()->create());

        $this->withLocaleRoutes('fr', function () {
            // FR→EN on the dynamic group route: the structural /groupes segment
            // must translate back to /groups, and the {group} slug stays as
            // authored (ADR-0008). Regression for #121.
            $this->get('/fr/groupes/gallery-interpreters')->assertInertia(
                fn (Assert $page) => $page
                    ->where('localeSwitcher.current', 'fr')
                    ->where('localeSwitcher.options.0.code', 'en')
                    ->where('localeSwitcher.options.0.url', url('/groups/gallery-interpreters'))
            );
        });
    }

    public function test_switcher_translates_the_group_segment_back_with_the_optional_section(): void
    {
        Group::factory()->create(['slug' => 'docents']);
        $this->actingAs(Member::factory()->create());

        $this->withLocaleRoutes('fr', function () {
            // Same FR→EN translation with the optional {section?} present.
            $this->get('/fr/groupes/docents/school-visits')->assertInertia(
                fn (Assert $page) => $page
                    ->where('localeSwitcher.current', 'fr')
                    ->where('localeSwitcher.options.0.code', 'en')
                    ->where('localeSwitcher.options.0.url', url('/groups/docents/school-visits'))
            );
        });
    }

    public function test_switcher_disables_the_other_locale_on_a_page_with_no_twin(): void
    {
        $this->actingAs(Member::factory()->create());

        // The internal design-system page is English-only — it lives outside the
        // localized route group, so it has no /fr/ twin (PRD #37). The fr option
        // arrives with a null url and renders disabled rather than offering a link
        // that would 404.
        $this->get('/design-system')->assertInertia(
            fn (Assert $page) => $page
                ->where('localeSwitcher.current', 'en')
                ->where('localeSwitcher.options.1.code', 'fr')
                ->where('localeSwitcher.options.1.url', null)
        );
    }
}
