<?php

use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

// Seam A (#517, ADR-0025) — the help index and one article at the HTTP/Inertia
// boundary, in both locales, as a logged-in Member. Login is required; an unknown
// slug 404s; the `help` route no longer renders ComingSoon.

it('redirects a guest from the help index to login', function () {
    $this->get('/help')->assertRedirect('/login');
});

it('renders the help index for a member with the Getting started section, overview first', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/help')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/Index')
            ->where('locale', 'en')
            ->where('sections.0.key', 'getting-started')
            ->where('sections.0.labelKey', 'help.section.getting-started')
            ->where('sections.0.articles.0.slug', 'getting-started')
            ->where('sections.0.articles.0.title', 'Getting started')
            ->where('sections.0.articles.0.href', '/help/getting-started')
            ->where('sections.0.articles.1.slug', 'change-your-language')
            ->where('sections.0.articles.1.title', 'Change your language'));
});

it('renders the help index in French at /fr/aide', function () {
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('help/Index')
                ->where('locale', 'fr')
                ->where('sections.0.articles.0.title', 'Pour commencer')
                ->where('sections.0.articles.1.title', 'Changer votre langue'));
    });
});

it('renders an article with its title, breadcrumb, and rendered body HTML', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/help/change-your-language')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/Article')
            ->where('slug', 'change-your-language')
            ->where('title', 'Change your language')
            ->where('breadcrumb.0', ['title' => 'Help', 'href' => '/help'])
            ->where('breadcrumb.1', ['title' => 'Getting started', 'href' => '/help/getting-started'])
            ->where('breadcrumb.2', ['title' => 'Change your language', 'href' => '/help/change-your-language'])
            ->where('html', fn (string $html) => str_contains($html, '<h2>') && str_contains($html, 'Switch your language')));
});

it('renders an article in French at /fr/aide/... with a French breadcrumb', function () {
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide/change-your-language')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('help/Article')
                ->where('locale', 'fr')
                ->where('title', 'Changer votre langue')
                ->where('breadcrumb.0.title', 'Aide')
                ->where('breadcrumb.1.title', 'Pour commencer'));
    });
});

it('returns 404 for an unknown article slug', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/help/does-not-exist')
        ->assertNotFound();
});
