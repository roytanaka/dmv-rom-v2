<?php

use App\Enums\ArticleStatus;
use App\Enums\HelpSection;
use App\Help\HelpArticle;
use App\Help\HelpManifest;
use App\Models\Group;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Fixed global top bar + nav model (#194, PRD #187, ADR-0013 amendment). The black
 * top bar is now ONE fixed global navigation strip, identical on every page: the
 * primary-four destinations (My Hours · My Calendar · News · Directory) plus a Help
 * utility, shared from the server as a chrome-nav model distinct from a Group's
 * section set and the rail. Hrefs are localized server-side per ADR-0008, and any
 * declared destination gating resolves server-side — the client never echoes it.
 *
 * Asserted at the shared-prop seam: the destinations are present and identical on
 * the Dashboard, a Group page, and a settings page, with the right localized hrefs
 * in both locales (including the /fr/ twin of each link).
 */

// The fixed global strip, in render order, with its English-canonical hrefs, then the
// Help menu. The Dashboard maps the Getting started tour; the Group page maps the Groups
// overview; settings maps the settings article.
function assertEnglishDestinations(Assert $page, string $articleHref): Assert
{
    return $page
        ->where('chromeNav.destinations.0', ['key' => 'hours', 'labelKey' => 'nav.personal.hours', 'href' => '/hours'])
        ->where('chromeNav.destinations.1', ['key' => 'calendar', 'labelKey' => 'nav.personal.calendar', 'href' => '/calendar'])
        ->where('chromeNav.destinations.2', ['key' => 'news', 'labelKey' => 'nav.personal.news', 'href' => '/news'])
        ->where('chromeNav.destinations.3', ['key' => 'directory', 'labelKey' => 'nav.personal.directory', 'href' => '/directory'])
        ->where('chromeNav.help', helpMenu(helpPageItem($articleHref), helpCentreItem(), ...feedbackItems()));
}

// The Help menu model (ADR-0025 amendment), built from its items in the order given.
function helpMenu(array ...$items): array
{
    return ['labelKey' => 'nav.help', 'items' => $items];
}

function helpPageItem(string $href): array
{
    return ['key' => 'page', 'labelKey' => 'nav.help_menu.page', 'href' => $href];
}

function helpCentreItem(string $href = '/help'): array
{
    return ['key' => 'centre', 'labelKey' => 'nav.help_menu.centre', 'href' => $href];
}

// The two Tester feedback items (#676, ADR-0029 §12) that close the menu outside
// production. Both point at the Feedback page: Send feedback posts there from its dialog.
function feedbackItems(string $href = '/feedback'): array
{
    return [
        ['key' => 'feedback-send', 'labelKey' => 'nav.help_menu.feedback_send', 'href' => $href],
        ['key' => 'feedback-list', 'labelKey' => 'nav.help_menu.feedback_list', 'href' => $href],
    ];
}

it('shares the fixed global destinations on the Dashboard', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertEnglishDestinations($page, '/help/getting-started'));
});

it('shares the same fixed global destinations on a Group page', function () {
    $group = Group::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertEnglishDestinations($page, '/help/groups'));
});

it('shares the same fixed global destinations on a settings page', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertEnglishDestinations($page, '/help/settings'));
});

/*
 * Help menu (#675, ADR-0025 amendment). The top-bar "?" opens a menu. The middleware
 * resolves the current route name against the help manifest: a published article's
 * mapping adds Help for this page (localized); an unmapped page, or one mapped only by a
 * draft, gets no page item. Help centre is always there. Bound against a fixture manifest
 * so the draft and unmapped cases exist independently of what the real catalogue ships.
 */

// A fixture manifest with one published article mapped to the dashboard route, plus a
// draft mapped to the directory route — the two states the "?" resolution turns on.
function bindHelpRouteFixtures(): void
{
    app()->instance(HelpManifest::class, new HelpManifest([
        new HelpArticle('dashboard-tour', HelpSection::GettingStarted, status: ArticleStatus::Published, route: 'dashboard'),
        new HelpArticle('directory-tour', HelpSection::GettingStarted, status: ArticleStatus::Draft, route: 'directory'),
    ]));
}

it('offers Help for this page and Help centre when a published article maps the page', function () {
    bindHelpRouteFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chromeNav.help', helpMenu(helpPageItem('/help/dashboard-tour'), helpCentreItem(), ...feedbackItems())));
});

it('localizes both help menu hrefs to their French twins', function () {
    bindHelpRouteFixtures();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/tableau-de-bord')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('chromeNav.help', helpMenu(helpPageItem('/fr/aide/dashboard-tour'), helpCentreItem('/fr/aide'), ...feedbackItems('/fr/retroaction'))));
    });
});

it('offers only Help centre on a page no article maps', function () {
    bindHelpRouteFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/news')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chromeNav.help', helpMenu(helpCentreItem(), ...feedbackItems())));
});

it('opens the section overview when it shares the page with a task article', function () {
    // The task article sits first in catalogue order; the overview still wins the route.
    app()->instance(HelpManifest::class, new HelpManifest([
        new HelpArticle('read-your-hours-tour', HelpSection::MyHours, status: ArticleStatus::Published, route: 'hours'),
        new HelpArticle('my-hours-tour', HelpSection::MyHours, isOverview: true, status: ArticleStatus::Published, route: 'hours'),
    ]));

    $this->actingAs(Member::factory()->create())
        ->get('/hours')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chromeNav.help.items.0.href', '/help/my-hours-tour'));
});

it('offers only Help centre when only a draft maps the page', function () {
    bindHelpRouteFixtures();

    $this->actingAs(Member::factory()->create())
        ->get('/directory')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chromeNav.help', helpMenu(helpCentreItem(), ...feedbackItems())));
});

it('leaves the feedback items out of the menu in production', function () {
    bindHelpRouteFixtures();
    $this->app->detectEnvironment(fn () => 'production');

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chromeNav.help', helpMenu(helpPageItem('/help/dashboard-tour'), helpCentreItem())));
});

it('localizes the global destination hrefs to their French twins under /fr/', function () {
    $group = Group::factory()->create(['slug' => 'docents']);
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/groupes/docents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('chromeNav.destinations.0.href', '/fr/heures')
                ->where('chromeNav.destinations.1.href', '/fr/calendrier')
                ->where('chromeNav.destinations.2.href', '/fr/nouvelles')
                ->where('chromeNav.destinations.3.href', '/fr/annuaire')
                ->where('chromeNav.help', helpMenu(helpPageItem('/fr/aide/groups'), helpCentreItem('/fr/aide'), ...feedbackItems('/fr/retroaction'))));
    });
});
