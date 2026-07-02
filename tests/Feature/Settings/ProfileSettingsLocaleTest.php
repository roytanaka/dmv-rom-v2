<?php

use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Bilingual Settings → Profile route (#229, PRD #228, ADR-0008). The scaffolded
 * settings pages were English-only; this brings them into the localized route
 * group so /settings/profile ↔ /fr/parametres/profil resolve the same page and a
 * French Member stays in French. The Password page inherits the same fix.
 */

it('renders the profile settings page at the canonical English route', function () {
    $user = Member::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('locale', 'en'));
});

it('renders the French twin /fr/parametres/profil and reports the fr locale', function () {
    $user = Member::factory()->create();
    $this->actingAs($user);

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/parametres/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('locale', 'fr'));
    });
});

it('offers the profile settings twin in the language switcher', function () {
    $user = Member::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('localeSwitcher.current', 'en')
            ->where('localeSwitcher.options', function ($options) {
                $fr = collect($options)->firstWhere('code', 'fr');

                return $fr !== null
                    && str_contains((string) ($fr['url'] ?? ''), '/fr/parametres/profil');
            }));
});
