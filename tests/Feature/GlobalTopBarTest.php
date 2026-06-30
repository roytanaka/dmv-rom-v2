<?php

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

// The fixed global strip, in render order, with its English-canonical hrefs.
function assertEnglishDestinations(Assert $page): Assert
{
    return $page
        ->where('chromeNav.destinations.0', ['key' => 'hours', 'labelKey' => 'nav.personal.hours', 'href' => '/hours'])
        ->where('chromeNav.destinations.1', ['key' => 'calendar', 'labelKey' => 'nav.personal.calendar', 'href' => '/calendar'])
        ->where('chromeNav.destinations.2', ['key' => 'news', 'labelKey' => 'nav.personal.news', 'href' => '/news'])
        ->where('chromeNav.destinations.3', ['key' => 'directory', 'labelKey' => 'nav.personal.directory', 'href' => '/directory'])
        ->where('chromeNav.help', ['key' => 'help', 'labelKey' => 'nav.help', 'href' => '/help']);
}

it('shares the fixed global destinations on the Dashboard', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertEnglishDestinations($page));
});

it('shares the same fixed global destinations on a Group page', function () {
    $group = Group::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertEnglishDestinations($page));
});

it('shares the same fixed global destinations on a settings page', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => assertEnglishDestinations($page));
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
                ->where('chromeNav.help.href', '/fr/aide'));
    });
});
