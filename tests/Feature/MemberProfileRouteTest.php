<?php

use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Bilingual member profile route (#172, PRD #167, ADR-0008). The profile is
 * canonical at /members/{member} and has a French twin at /fr/benevoles/{member}.
 * The /members segment translates to /benevoles independently of the officer
 * /membres mapping — the two share the English word "members" but render distinct
 * French segments.
 */

it('renders the profile at the canonical English route', function () {
    $member = Member::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('members/Show'));
});

it('renders the profile at the French twin /fr/benevoles/{member}', function () {
    $member = Member::factory()->create();
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($member) {
        $this->get("/fr/benevoles/{$member->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/Show')
                ->where('locale', 'fr'));
    });
});
