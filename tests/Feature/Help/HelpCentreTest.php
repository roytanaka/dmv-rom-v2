<?php

use App\Enums\ArticleStatus;
use App\Enums\HelpSection;
use App\Help\HelpArticle;
use App\Help\HelpArticleRenderer;
use App\Help\HelpManifest;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

// Seam A (#517, #518, ADR-0025) — the help index and one article at the HTTP/Inertia
// boundary, in both locales, as a logged-in Member. Login is required; an unknown
// slug 404s. #518 adds the Required-role badge data, hides drafts from the index, and
// keeps a draft reachable by URL.
//
// The real manifest ships only the two Getting started drafts, so the index-listing
// tests bind a fixture manifest and renderer root (the seam the renderer already
// established) to exercise published, published-open, and draft states side by side.

function bindHelpFixtures(): void
{
    app()->instance(HelpArticleRenderer::class, new HelpArticleRenderer(base_path('tests/Fixtures/help')));
    app()->instance(HelpManifest::class, new HelpManifest([
        new HelpArticle('role-task', HelpSection::GettingStarted, isOverview: true, requires: ['scheduler', 'chair'], status: ArticleStatus::Published),
        new HelpArticle('open-task', HelpSection::GettingStarted, status: ArticleStatus::Published),
        new HelpArticle('draft-task', HelpSection::GettingStarted, status: ArticleStatus::Draft),
    ]));
}

it('redirects a guest from the help index to login', function () {
    $this->get('/help')->assertRedirect('/login');
});

it('lists published articles with their Required-role badge data and omits drafts', function () {
    bindHelpFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/Index')
            ->where('locale', 'en')
            ->where('sections.0.key', 'getting-started')
            ->count('sections.0.articles', 2)
            ->where('sections.0.articles.0.slug', 'role-task')
            ->where('sections.0.articles.0.title', 'Role task')
            ->where('sections.0.articles.0.requires', ['scheduler', 'chair'])
            ->where('sections.0.articles.1.slug', 'open-task')
            ->where('sections.0.articles.1.requires', []));
});

it('lists the index in French with translated titles and the badge data intact', function () {
    bindHelpFixtures();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('help/Index')
                ->where('locale', 'fr')
                ->where('sections.0.articles.0.title', 'Tâche avec rôle')
                ->where('sections.0.articles.0.requires', ['scheduler', 'chair'])
                ->where('sections.0.articles.1.title', 'Tâche ouverte'));
    });
});

it('carries the required roles on the article page', function () {
    bindHelpFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help/role-task')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/Article')
            ->where('slug', 'role-task')
            ->where('requires', ['scheduler', 'chair']));
});

it('renders a draft by URL though it is absent from the index', function () {
    bindHelpFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help/draft-task')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/Article')
            ->where('slug', 'draft-task')
            ->where('requires', []));
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

it('links a French article to the French articles it names', function () {
    // #618: a "What next" link resolves in the request's language, so the Member stays in French.
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide/sign-up-for-a-shift')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('html', fn (string $html) => str_contains($html, '<a href="/fr/aide/cancel-a-sign-up">Annuler une inscription</a>')));
    });
});

it('returns 404 for an unknown article slug', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/help/does-not-exist')
        ->assertNotFound();
});
