<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Seam A — locale resolution at the HTTP/Inertia boundary (ADR-0008's both-URLs
// smoke test). For each translatable route, the English URL resolves locale `en`
// and the /fr/ twin resolves locale `fr` to the SAME page component. Written as a
// data provider so every route twin is exercised; new twins just extend the set.
class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The full set of translatable route twins (#109). Every entry is hit in both
     * locales by the tests below — there is no sampling or truncation, so adding a
     * route here is the single source of truth for the smoke coverage.
     *
     * @return array<string, array{en: string, fr: string, component: string}>
     */
    public static function translatableRoutes(): array
    {
        return [
            'dashboard' => ['en' => '/dashboard', 'fr' => '/fr/tableau-de-bord', 'component' => 'Dashboard'],

            // Zone A — personal
            'calendar' => ['en' => '/calendar', 'fr' => '/fr/calendrier', 'component' => 'ComingSoon'],
            'hours' => ['en' => '/hours', 'fr' => '/fr/heures', 'component' => 'MyHours'],
            'directory' => ['en' => '/directory', 'fr' => '/fr/annuaire', 'component' => 'members/Index'],
            'documents' => ['en' => '/documents', 'fr' => '/fr/documents', 'component' => 'ComingSoon'],
            'news' => ['en' => '/news', 'fr' => '/fr/nouvelles', 'component' => 'news/Index'],
            'profile' => ['en' => '/profile', 'fr' => '/fr/profil', 'component' => 'ComingSoon'],
            'renew' => ['en' => '/renew', 'fr' => '/fr/renouveler', 'component' => 'ComingSoon'],

            // Zone C — officer/admin
            'officer members' => ['en' => '/officer/members', 'fr' => '/fr/officier/membres', 'component' => 'ComingSoon'],
            'officer communications' => ['en' => '/officer/communications', 'fr' => '/fr/officier/communications', 'component' => 'ComingSoon'],
            'officer reports' => ['en' => '/officer/reports', 'fr' => '/fr/officier/rapports', 'component' => 'ComingSoon'],
            'officer flash-messages' => ['en' => '/officer/flash-messages', 'fr' => '/fr/officier/messages-eclair', 'component' => 'ComingSoon'],
            'officer settings' => ['en' => '/officer/settings', 'fr' => '/fr/officier/parametres', 'component' => 'ComingSoon'],

            // Dynamic group route — the {group} slug is content, resolved to a real
            // Group (#188) and rendered as-authored in both locales (ADR-0008); only
            // the /groups segment is translated. The Group is seeded per-test below.
            'group (dynamic)' => ['en' => '/groups/docents', 'fr' => '/fr/groupes/docents', 'component' => 'groups/Show'],
        ];
    }

    #[DataProvider('translatableRoutes')]
    public function test_english_url_resolves_the_english_locale(string $en, string $fr, string $component): void
    {
        $this->seedGroupFor($en);
        $this->actingAs(Member::factory()->create());

        $this->get($en)->assertInertia(
            fn (Assert $page) => $page->component($component)->where('locale', 'en')
        );
    }

    #[DataProvider('translatableRoutes')]
    public function test_french_twin_resolves_the_french_locale_and_same_component(string $en, string $fr, string $component): void
    {
        $this->seedGroupFor($en);
        $this->actingAs(Member::factory()->create());

        $this->withLocaleRoutes('fr', function () use ($fr, $component) {
            $this->get($fr)->assertInertia(
                fn (Assert $page) => $page->component($component)->where('locale', 'fr')
            );
        });
    }

    public function test_dynamic_group_route_resolves_the_group_by_slug(): void
    {
        Group::factory()->create(['slug' => 'gallery-interpreters']);
        $this->actingAs(Member::factory()->create());

        $this->get('/groups/gallery-interpreters')->assertInertia(
            fn (Assert $page) => $page->component('groups/Show')->where('group.slug', 'gallery-interpreters')
        );
    }

    public function test_dynamic_group_route_keeps_an_as_authored_slug_unchanged_under_fr(): void
    {
        Group::factory()->create(['slug' => 'docents']);
        $this->actingAs(Member::factory()->create());

        // Group slugs are content: they stay as-authored even under /fr/ (no
        // per-locale slug translation — only the /groups segment is — ADR-0008).
        $this->withLocaleRoutes('fr', function () {
            $this->get('/fr/groupes/docents')->assertInertia(
                fn (Assert $page) => $page->component('groups/Show')->where('group.slug', 'docents')->where('locale', 'fr')
            );
        });
    }

    /**
     * Seed the Group a dynamic /groups/{slug} route resolves, so the data-provider
     * smoke tests don't 404 on the now-real route. A no-op for non-group routes.
     */
    private function seedGroupFor(string $en): void
    {
        if (str_contains($en, '/groups/')) {
            Group::factory()->create(['slug' => basename($en)]);
        }
    }
}
