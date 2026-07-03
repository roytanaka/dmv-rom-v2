<?php

use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Bilingual Settings → Skills route (#246, PRD #243, ADR-0008). The self-service
 * Skills page joins the localized route group so /settings/skills ↔
 * /fr/parametres/competences resolve the same page and a French Member stays in
 * French. Mirrors the Profile settings locale seam.
 */

it('renders the skills settings page at the canonical English route', function () {
    $user = Member::factory()->create();

    $this->actingAs($user)
        ->get('/settings/skills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Skills')
            ->where('locale', 'en'));
});

it('renders the French twin /fr/parametres/competences and reports the fr locale', function () {
    $user = Member::factory()->create();
    $this->actingAs($user);

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/parametres/competences')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Skills')
                ->where('locale', 'fr'));
    });
});

it('offers the skills settings twin in the language switcher', function () {
    $user = Member::factory()->create();

    $this->actingAs($user)
        ->get('/settings/skills')
        ->assertInertia(fn (Assert $page) => $page
            ->where('localeSwitcher.current', 'en')
            ->where('localeSwitcher.options', function ($options) {
                $fr = collect($options)->firstWhere('code', 'fr');

                return $fr !== null
                    && str_contains((string) ($fr['url'] ?? ''), '/fr/parametres/competences');
            }));
});
