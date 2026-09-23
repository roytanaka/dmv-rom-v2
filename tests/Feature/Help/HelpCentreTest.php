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

// #623: the index — a Start here box fed by Getting started, then one card per other
// section with its overview, lead line, up to three published tasks, and a task count.
function bindIndexFixtures(): void
{
    app()->instance(HelpArticleRenderer::class, new HelpArticleRenderer(base_path('tests/Fixtures/help')));
    app()->instance(HelpManifest::class, new HelpManifest([
        new HelpArticle('role-task', HelpSection::GettingStarted, isOverview: true, status: ArticleStatus::Published),
        new HelpArticle('open-task', HelpSection::GettingStarted, status: ArticleStatus::Published),
        new HelpArticle('link-fixture', HelpSection::Dashboard, isOverview: true, status: ArticleStatus::Published),
        new HelpArticle('figure-fixture', HelpSection::Scheduling, isOverview: true, status: ArticleStatus::Published),
        new HelpArticle('callout-fixture', HelpSection::Scheduling, requires: ['scheduler', 'chair'], status: ArticleStatus::Published),
        new HelpArticle('draft-task', HelpSection::Scheduling, status: ArticleStatus::Draft),
        new HelpArticle('raw-html-fixture', HelpSection::Scheduling, status: ArticleStatus::Published),
        new HelpArticle('headings-fixture', HelpSection::Scheduling, status: ArticleStatus::Published),
        new HelpArticle('extra-task', HelpSection::Scheduling, status: ArticleStatus::Published),
        new HelpArticle('draft-overview', HelpSection::Support, isOverview: true, status: ArticleStatus::Draft),
        new HelpArticle('support-task', HelpSection::Support, requires: ['support_operator'], status: ArticleStatus::Published),
    ]));
}

it('feeds the Start here box from Getting started, not a card', function () {
    bindIndexFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help/Index')
            ->where('locale', 'en')
            ->where('startHere.overview', ['title' => 'Role task', 'href' => '/help/role-task'])
            ->where('startHere.articles.0.title', 'Open task')
            ->where('startHere.articles.0.href', '/help/open-task')
            ->count('sections', 3)
            ->where('sections.0.key', 'dashboard'));
});

it('gives each section card its overview, lead line and published task count', function () {
    bindIndexFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help')
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.overview', ['title' => 'Link fixture', 'href' => '/help/link-fixture'])
            ->where('sections.0.lead', 'Read Sign up for a shift next.')
            ->where('sections.0.count', 0)
            ->where('sections.0.articles', [])
            ->where('sections.1.key', 'scheduling')
            ->where('sections.1.lead', 'This article references a screenshot.')
            // Four published tasks; the draft is left out of the count.
            ->where('sections.1.count', 4));
});

it('lists at most three published task articles on a card, with their Required-role badge data', function () {
    bindIndexFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help')
        ->assertInertia(fn (Assert $page) => $page
            ->count('sections.1.articles', 3)
            ->where('sections.1.articles.0', ['slug' => 'callout-fixture', 'title' => 'Callout fixture', 'requires' => ['scheduler', 'chair'], 'href' => '/help/callout-fixture'])
            ->where('sections.1.articles.1.slug', 'raw-html-fixture')
            ->where('sections.1.articles.1.requires', [])
            ->where('sections.1.articles.2.slug', 'headings-fixture'));
});

it('gives a section with a draft overview no lead line', function () {
    bindIndexFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help')
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.2.key', 'support')
            ->where('sections.2.lead', null)
            // The title still opens the overview by URL, like the section crumb.
            ->where('sections.2.overview.href', '/help/draft-overview')
            ->where('sections.2.count', 1)
            ->where('sections.2.articles.0.requires', ['support_operator']));
});

it('lists the index in French with French titles, leads and /fr/aide/ hrefs', function () {
    bindIndexFixtures();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('help/Index')
                ->where('locale', 'fr')
                ->where('startHere.overview', ['title' => 'Tâche avec rôle', 'href' => '/fr/aide/role-task'])
                ->where('startHere.articles.0.title', 'Tâche ouverte')
                ->where('sections.0.overview', ['title' => 'Lien fixture', 'href' => '/fr/aide/link-fixture'])
                ->where('sections.0.lead', "Lisez ensuite S'inscrire à un quart.")
                ->where('sections.1.articles.0.title', 'Fixture des encadrés')
                ->where('sections.1.articles.0.href', '/fr/aide/callout-fixture')
                ->where('sections.1.articles.0.requires', ['scheduler', 'chair']));
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
            ->where('html', fn (string $html) => str_contains($html, '<h2 id="') && str_contains($html, 'Switch your language')));
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

// #620: Previous and Next follow the manifest's published display order across the
// whole help centre. This fixture spans two sections, with a draft in each.
function bindNeighbourFixtures(): void
{
    app()->instance(HelpArticleRenderer::class, new HelpArticleRenderer(base_path('tests/Fixtures/help')));
    app()->instance(HelpManifest::class, new HelpManifest([
        new HelpArticle('role-task', HelpSection::GettingStarted, isOverview: true, status: ArticleStatus::Published),
        new HelpArticle('draft-task', HelpSection::GettingStarted, status: ArticleStatus::Draft),
        new HelpArticle('open-task', HelpSection::GettingStarted, status: ArticleStatus::Published),
        new HelpArticle('link-fixture', HelpSection::Dashboard, isOverview: true, status: ArticleStatus::Published),
        new HelpArticle('callout-fixture', HelpSection::Dashboard, status: ArticleStatus::Draft),
    ]));
}

it('links an article to its published neighbours, skipping drafts', function () {
    bindNeighbourFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help/open-task')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('previous', ['title' => 'Role task', 'href' => '/help/role-task', 'section' => 'Getting started'])
            ->where('next', ['title' => 'Link fixture', 'href' => '/help/link-fixture', 'section' => 'Dashboard']));
});

it('gives the first article no Previous and the last no Next', function () {
    bindNeighbourFixtures();

    $this->actingAs(Member::factory()->create());

    $this->get('/help/role-task')
        ->assertInertia(fn (Assert $page) => $page
            ->where('previous', null)
            ->where('next.href', '/help/open-task'));

    $this->get('/help/link-fixture')
        ->assertInertia(fn (Assert $page) => $page
            ->where('previous', ['title' => 'Open task', 'href' => '/help/open-task', 'section' => 'Getting started'])
            ->where('next', null));
});

it('gives a draft opened by URL neither Previous nor Next', function () {
    bindNeighbourFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/help/draft-task')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('previous', null)
            ->where('next', null));
});

it('links French neighbours by French title and /fr/aide/ href', function () {
    bindNeighbourFixtures();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide/open-task')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('previous', ['title' => 'Tâche avec rôle', 'href' => '/fr/aide/role-task', 'section' => 'Pour commencer'])
                ->where('next', ['title' => 'Lien fixture', 'href' => '/fr/aide/link-fixture', 'section' => 'Tableau de bord']));
    });
});

// #621: the "On this page" list — the article's level-two headings, in order, with
// ids the page links to.
function bindHeadingFixtures(): void
{
    app()->instance(HelpArticleRenderer::class, new HelpArticleRenderer(base_path('tests/Fixtures/help')));
    app()->instance(HelpManifest::class, new HelpManifest([
        new HelpArticle('headings-fixture', HelpSection::GettingStarted, isOverview: true, status: ArticleStatus::Published),
        new HelpArticle('open-task', HelpSection::GettingStarted, status: ArticleStatus::Published),
    ]));
}

it('carries the article headings in order with their ids', function () {
    bindHeadingFixtures();

    $this->actingAs(Member::factory()->create());

    $this->get('/help/headings-fixture')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('headings', 4)
            ->where('headings.0', ['text' => 'Before you start', 'id' => 'before-you-start'])
            ->where('headings.3', ['text' => 'Fix a mistake', 'id' => 'fix-a-mistake-2']));

    $this->get('/help/open-task')
        ->assertInertia(fn (Assert $page) => $page->where('headings', []));
});

it('carries French headings and ids on a French article', function () {
    bindHeadingFixtures();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/aide/headings-fixture')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('headings.1', ['text' => "Ouvrir l'onglet « Quarts »", 'id' => 'ouvrir-longlet-quarts']));
    });
});

it('returns 404 for an unknown article slug', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/help/does-not-exist')
        ->assertNotFound();
});
