<?php

use App\Enums\StewardshipFunction;
use App\Help\HelpManifest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

// The Help ledger at /help-status (#520, ADR-0025 §9). Seam A: only super-tier reaches
// it, and its props carry a row per manifest entry plus the gap list of unmapped page
// routes. Seam B: the exclusion list beside the manifest names only real routes.

it('redirects a guest to the login page', function () {
    $this->get('/help-status')->assertRedirect('/login');
});

it('forbids an ordinary member', function () {
    $this->actingAs(Member::factory()->create());

    $this->get('/help-status')->assertForbidden();
});

it('forbids a records member without super-tier', function () {
    // Member-administration authority buys nothing: the gate denies everyone and only the
    // super-tier short-circuit grants it (ADR-0025 §9), exactly as Mail status does.
    $records = Group::factory()->create();
    $records->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);

    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $member->id]);

    expect($member->hasMemberAdminAuthority())->toBeTrue();

    $this->actingAs($member);
    $this->get('/help-status')->assertForbidden();
});

it('lets a super-tier member see the ledger', function () {
    $this->actingAs(Member::factory()->superTier()->create());

    $this->get('/help-status')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('HelpStatus'));
});

it('carries one row per manifest entry with the ledger fields', function () {
    $this->actingAs(Member::factory()->superTier()->create());

    $expected = (new HelpManifest)->all();

    $this->get('/help-status')
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', count($expected))
            ->has('rows.0', fn (Assert $row) => $row
                ->where('slug', $expected[0]->slug)
                ->where('section', $expected[0]->section->value)
                ->where('status', $expected[0]->status->value)
                ->where('fr', $expected[0]->fr->value)
                ->where('route', $expected[0]->route)
                ->hasAll(['requires', 'enFile', 'frFile', 'screenshotsReferenced', 'screenshotsPresent']))
            ->has('counts', fn (Assert $counts) => $counts
                ->where('articles', count($expected))
                ->hasAll(['published', 'drafts', 'frenchReviewed'])));
});

it('lists an unmapped page route as a gap and omits mapped, excluded, and stub routes', function () {
    $this->actingAs(Member::factory()->superTier()->create());

    $this->get('/help-status')
        ->assertInertia(fn (Assert $page) => $page
            ->where('gaps', fn (Collection $gaps) => $gaps
                // A real localized page route no article maps yet.
                ->contains('groups.hours.month')
                // directory is mapped by the volunteer-basics article, even as a draft.
                && ! $gaps->contains('directory')
                // A CSV export twin from the exclusion list — the report page's sibling.
                && ! $gaps->contains('groups.hours.report.csv')
                // A Coming Soon stub from the exclusion list.
                && ! $gaps->contains('calendar')));
});

it('names only real routes in the ledger exclusion list', function () {
    foreach (HelpManifest::ledgerRouteExclusions() as $name) {
        expect(Route::has($name))->toBeTrue("Exclusion list names a route that does not exist: '{$name}'");
    }
});
