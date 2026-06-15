<?php

namespace Tests\Feature;

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
            'hours' => ['en' => '/hours', 'fr' => '/fr/heures', 'component' => 'ComingSoon'],
            'directory' => ['en' => '/directory', 'fr' => '/fr/annuaire', 'component' => 'ComingSoon'],
            'documents' => ['en' => '/documents', 'fr' => '/fr/documents', 'component' => 'ComingSoon'],
            'news' => ['en' => '/news', 'fr' => '/fr/nouvelles', 'component' => 'ComingSoon'],
            'profile' => ['en' => '/profile', 'fr' => '/fr/profil', 'component' => 'ComingSoon'],
            'renew' => ['en' => '/renew', 'fr' => '/fr/renouveler', 'component' => 'ComingSoon'],

            // Zone C — officer/admin
            'officer members' => ['en' => '/officer/members', 'fr' => '/fr/officier/membres', 'component' => 'ComingSoon'],
            'officer communications' => ['en' => '/officer/communications', 'fr' => '/fr/officier/communications', 'component' => 'ComingSoon'],
            'officer reports' => ['en' => '/officer/reports', 'fr' => '/fr/officier/rapports', 'component' => 'ComingSoon'],
            'officer flash-messages' => ['en' => '/officer/flash-messages', 'fr' => '/fr/officier/messages-eclair', 'component' => 'ComingSoon'],
            'officer settings' => ['en' => '/officer/settings', 'fr' => '/fr/officier/parametres', 'component' => 'ComingSoon'],

            // Dynamic group route — the {group} slug is content, echoed as-authored
            // in both locales (ADR-0008); only the /groups segment is translated.
            'group (dynamic)' => ['en' => '/groups/docents', 'fr' => '/fr/groupes/docents', 'component' => 'ComingSoon'],
        ];
    }

    #[DataProvider('translatableRoutes')]
    public function test_english_url_resolves_the_english_locale(string $en, string $fr, string $component): void
    {
        $this->actingAs(Member::factory()->create());

        $this->get($en)->assertInertia(
            fn (Assert $page) => $page->component($component)->where('locale', 'en')
        );
    }

    #[DataProvider('translatableRoutes')]
    public function test_french_twin_resolves_the_french_locale_and_same_component(string $en, string $fr, string $component): void
    {
        $this->actingAs(Member::factory()->create());

        $this->withLocaleRoutes('fr', function () use ($fr, $component) {
            $this->get($fr)->assertInertia(
                fn (Assert $page) => $page->component($component)->where('locale', 'fr')
            );
        });
    }

    public function test_dynamic_group_route_echoes_the_group_slug_back(): void
    {
        $this->actingAs(Member::factory()->create());

        $this->get('/groups/gallery-interpreters')->assertInertia(
            fn (Assert $page) => $page->component('ComingSoon')->where('group', 'gallery-interpreters')
        );
    }

    public function test_dynamic_group_route_echoes_an_as_authored_french_slug_unchanged(): void
    {
        $this->actingAs(Member::factory()->create());

        // Group slugs are content: they stay as-authored even under /fr/ (no
        // model lookup, no per-locale slug translation — ADR-0008).
        $this->withLocaleRoutes('fr', function () {
            $this->get('/fr/groupes/docents')->assertInertia(
                fn (Assert $page) => $page->component('ComingSoon')->where('group', 'docents')->where('locale', 'fr')
            );
        });
    }
}
