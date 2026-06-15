<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Honest language-boundary behavior (ADR-0008). A /fr/ URL with no registered
// French route must NOT silently fall through to the English page — it returns a
// locale-aware "not translated yet" response. The polished 404 + request-
// translation CTA is a later ADR-0008 slice; this just proves the boundary.
class FrenchFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_unregistered_french_url_returns_a_not_translated_response(): void
    {
        $this->actingAs(Member::factory()->create());

        $this->withLocaleRoutes('fr', function () {
            $response = $this->get('/fr/cette-page-nexiste-pas');

            $response->assertStatus(404);
            $response->assertInertia(
                fn (Assert $page) => $page->component('NotTranslated')->where('locale', 'fr')
            );
        });
    }

    public function test_unregistered_english_url_is_a_plain_404_not_the_inertia_boundary_page(): void
    {
        $this->actingAs(Member::factory()->create());

        // English is canonical; an unknown English path is an ordinary 404, not the
        // "not translated yet" boundary page (which is reserved for /fr/ misses).
        $response = $this->get('/this-page-does-not-exist');

        $response->assertStatus(404);
        $this->assertStringNotContainsString('NotTranslated', $response->getContent());
    }
}
