<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// The `routeSegments` shared prop carries a per-locale URI-segment translation table
// (derived from lang/{locale}/routes.php) so the frontend can keep English-canonical
// nav hrefs in the active locale rather than reverting to English on navigation
// (ADR-0008). English is canonical at the root, so it has no entry.
class LocaleRouteSegmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_segments_translate_structural_words_for_french(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page
                // No entry for the default locale — English is canonical at the root.
                ->missing('routeSegments.en')
                // Single-word structural segments.
                ->where('routeSegments.fr.dashboard', 'tableau-de-bord')
                ->where('routeSegments.fr.groups', 'groupes')
                ->where('routeSegments.fr.calendar', 'calendrier')
                // Multi-segment officer routes translate each differing word.
                ->where('routeSegments.fr.officer', 'officier')
                ->where('routeSegments.fr.members', 'membres')
                ->where('routeSegments.fr.reports', 'rapports')
        );
    }

    public function test_route_segments_omit_unchanged_words(): void
    {
        $this->actingAs(User::factory()->create());

        // 'documents' is identical in both locales, so it is not recorded — the
        // frontend passes unknown segments through verbatim.
        $this->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->missing('routeSegments.fr.documents')
        );
    }
}
