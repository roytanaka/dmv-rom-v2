<?php

use App\Enums\Category;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The six DMV-wide fiscal-year reports (#413, PRD #406, ADR-0022 §8) — the single output the
 * whole Hours feature exists to produce. Summary and Detailed Committee Statistics, Active
 * Members Ranked Hours, and the three zero-hours lists. Exercised through the route → auth
 * middleware → viewOrgReports policy → Gate::before, and at the Inertia prop seam for the numbers.
 *
 * All six are org-wide, always rooted at the DMV root Group, and gated identically: the DMV
 * root's Chair, Secretary, or Statistician, the Records stewardship, or the super-tier. An
 * ordinary Member — and a Chair of any other Group — reaches none of them.
 *
 * Frozen inside Fiscal 2026 (2025-04 through 2026-03) so the default window is fixed.
 */
beforeEach(function () {
    $this->travelTo('2026-01-15 12:00:00');
    $this->root = Group::factory()->create(['slug' => Group::ROOT_SLUG, 'name' => 'DMV']);
});

/** The six DMV-wide report route names, for the shared gating matrix. */
function dmvReports(): array
{
    return [
        'hours.committee-summary',
        'hours.committee-detailed',
        'hours.ranked',
        'hours.zero-hours',
        'hours.zero-shift-hours',
        'hours.zero-extra-hours',
    ];
}

/** A member of the given Group holding the given role. */
function orgOfficer(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** A member holding the Records stewardship — membership in the Group that stewards member-admin. */
function dmvRecordsSteward(): Member
{
    $records = Group::factory()->create(['slug' => 'records-group']);
    $records->stewardships()->create(['function' => StewardshipFunction::MemberAdmin]);

    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $member->id]);

    return $member;
}

/** A record at a chosen grain for one Member on one Group in one month. */
function hoursRowFor(Member $member, Group $group, string $yearMonth, int $scheduled = 0, int $extra = 0, int $meetingId = HoursRecord::NO_MEETING): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => $meetingId,
        'scheduled_hours' => $scheduled,
        'extra_hours' => $extra,
        'total_hours' => $scheduled + $extra,
    ]);
}

/** A record whose Member is incidental — for the committee-shaped reports. */
function hoursRow(Group $group, string $yearMonth, int $scheduled = 0, int $extra = 0, int $meetingId = HoursRecord::NO_MEETING): HoursRecord
{
    return hoursRowFor(Member::factory()->create(), $group, $yearMonth, $scheduled, $extra, $meetingId);
}

// --- Who may read the six reports -------------------------------------------

it('shows a Chair of the DMV root every report', function (string $routeName) {
    $this->actingAs(orgOfficer($this->root, Role::Chair))->get(route($routeName))->assertOk();
})->with('dmvReports');

it('shows a Secretary of the DMV root every report', function (string $routeName) {
    $this->actingAs(orgOfficer($this->root, Role::Secretary))->get(route($routeName))->assertOk();
})->with('dmvReports');

it('shows a Statistician of the DMV root every report', function (string $routeName) {
    $this->actingAs(orgOfficer($this->root, Role::Statistician))->get(route($routeName))->assertOk();
})->with('dmvReports');

it('shows a Records steward every report', function (string $routeName) {
    $this->actingAs(dmvRecordsSteward())->get(route($routeName))->assertOk();
})->with('dmvReports');

it('shows the super-tier every report', function (string $routeName) {
    $this->actingAs(Member::factory()->superTier()->create())->get(route($routeName))->assertOk();
})->with('dmvReports');

it('forbids an ordinary Member from every report', function (string $routeName) {
    $this->actingAs(Member::factory()->create())->get(route($routeName))->assertForbidden();
})->with('dmvReports');

it('forbids a Chair of an ordinary Group from every report', function (string $routeName) {
    $ordinary = Group::factory()->create(['parent_id' => $this->root->id]);

    $this->actingAs(orgOfficer($ordinary, Role::Chair))->get(route($routeName))->assertForbidden();
})->with('dmvReports');

it('redirects an unauthenticated visitor to login from every report', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with('dmvReports');

dataset('dmvReports', dmvReports());

// --- Summary Committee Statistics -------------------------------------------

it('lists only scheduling committees in the summary scheduled section, with org-wide rows below', function () {
    $walker = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'ROMWalks', 'has_scheduling' => true]);
    $office = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'Office', 'has_scheduling' => false]);
    hoursRow($walker, '202504', scheduled: 8);            // scheduled, on a scheduling committee
    hoursRow($office, '202504', extra: 3);                // extra, no meeting
    hoursRow($office, '202504', extra: 5, meetingId: 12); // meeting hours

    $this->actingAs(orgOfficer($this->root, Role::Chair))
        ->get(route('hours.committee-summary'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('hours/CommitteeSummary')
            ->has('scheduled', 1)
            // The non-scheduling committee never carries a scheduled row; the name is as-authored.
            ->where('scheduled.0.name', 'ROMWalks')
            ->where('scheduled.0.months.0', 8)
            ->where('scheduled.0.ytd', 8)
            ->where('orgRows.meetings.months.0', 5)
            ->where('orgRows.extra.months.0', 3)
            ->where('orgRows.total.months.0', 16)); // 8 scheduled + 3 extra + 5 meeting
});

// --- Detailed Committee Statistics ------------------------------------------

it('breaks each committee into shifts, meetings and extra, and completes the org total with sub-groups', function () {
    $committee = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'Docents']);
    $cohort = Group::factory()->create(['parent_id' => $committee->id, 'name' => 'Cohort']);
    hoursRow($committee, '202504', scheduled: 4);
    hoursRow($committee, '202504', extra: 2, meetingId: 9);
    hoursRow($cohort, '202504', extra: 6);   // a grandchild folds into Docents and the DMV total
    hoursRow($this->root, '202504', extra: 1); // logged on the DMV itself

    $this->actingAs(orgOfficer($this->root, Role::Statistician))
        ->get(route('hours.committee-detailed'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('hours/CommitteeDetailed')
            // The grandchild has no committee row of its own; Docents is the only committee.
            ->has('committees', 1)
            ->where('committees.0.name', 'Docents')
            ->where('committees.0.months.0.shifts', 4)
            ->where('committees.0.months.0.meetings', 2)
            ->where('committees.0.months.0.extra', 6)
            ->where('committees.0.months.0.total', 12)
            // The DMV row includes the root's own hours and every sub-group — the complete total.
            ->where('org.months.0.total', 13)
            ->where('org.ytd.total', 13));
});

// --- Active Members Ranked Hours --------------------------------------------

it('ranks active and provisional members by total hours and lists those with no rows', function () {
    $group = Group::factory()->create(['parent_id' => $this->root->id]);
    $alice = Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Active', 'category' => Category::Active]);
    $bob = Member::factory()->create(['first_name' => 'Bob', 'last_name' => 'Provisional', 'category' => Category::Provisional]);
    Member::factory()->create(['first_name' => 'Carol', 'last_name' => 'Absent', 'category' => Category::Active]);
    hoursRowFor($alice, $group, '202504', extra: 5);
    hoursRowFor($bob, $group, '202504', extra: 10);

    $this->actingAs(orgOfficer($this->root, Role::Chair))
        ->get(route('hours.ranked'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('hours/RankedHours')
            // Bob (10) ranks above Alice (5); ties would break by name.
            ->where('ranked.0.name', 'Bob Provisional')
            ->where('ranked.0.total_hours', 10)
            ->where('ranked.1.name', 'Alice Active')
            // Carol has no rows at all → the no-hours list, never the ranking.
            ->where('noHours', fn (Collection $rows) => $rows->pluck('name')->contains('Carol Absent'))
            ->where('ranked', fn (Collection $rows) => ! $rows->pluck('name')->contains('Carol Absent')));
});

it('excludes departed members from the ranked roster', function () {
    $group = Group::factory()->create(['parent_id' => $this->root->id]);
    $resigned = Member::factory()->create(['first_name' => 'Gone', 'last_name' => 'Away', 'category' => Category::Resigned]);
    hoursRowFor($resigned, $group, '202504', extra: 99);

    $this->actingAs(orgOfficer($this->root, Role::Chair))
        ->get(route('hours.ranked'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ranked', fn (Collection $rows) => ! $rows->pluck('name')->contains('Gone Away'))
            ->where('noHours', fn (Collection $rows) => ! $rows->pluck('name')->contains('Gone Away')));
});

// --- The three zero-hours reports -------------------------------------------

it('keeps zero shift, zero extra, and zero total as three different lists', function () {
    $group = Group::factory()->create(['parent_id' => $this->root->id]);
    $shiftOnly = Member::factory()->create(['first_name' => 'Shift', 'last_name' => 'Only', 'category' => Category::Active]);
    $extraOnly = Member::factory()->create(['first_name' => 'Extra', 'last_name' => 'Only', 'category' => Category::Active]);
    hoursRowFor($shiftOnly, $group, '202504', scheduled: 5); // shift hours, zero extra
    hoursRowFor($extraOnly, $group, '202504', extra: 5);     // extra hours, zero shift

    $officer = orgOfficer($this->root, Role::Chair);

    $this->actingAs($officer)->get(route('hours.zero-shift-hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('hours/ZeroHours')
            ->where('variant', 'shift')
            ->where('members', fn (Collection $m) => $m->pluck('name')->contains('Extra Only') && ! $m->pluck('name')->contains('Shift Only')));

    $this->actingAs($officer)->get(route('hours.zero-extra-hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('variant', 'extra')
            ->where('members', fn (Collection $m) => $m->pluck('name')->contains('Shift Only') && ! $m->pluck('name')->contains('Extra Only')));

    $this->actingAs($officer)->get(route('hours.zero-hours'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('variant', 'hours')
            // Both worked some hours, so neither is on the zero-total list.
            ->where('members', fn (Collection $m) => ! $m->pluck('name')->contains('Shift Only') && ! $m->pluck('name')->contains('Extra Only')));
});

// --- Fiscal-year window and locale ------------------------------------------

it('defaults to the current fiscal year and moves to a picked one', function () {
    $committee = Group::factory()->create(['parent_id' => $this->root->id, 'name' => 'Docents']);
    hoursRow($committee, '202309', extra: 7); // Fiscal 2024 runs 2023-04 through 2024-03

    $chair = orgOfficer($this->root, Role::Chair);

    $this->actingAs($chair)->get(route('hours.committee-detailed'))
        ->assertInertia(fn (Assert $page) => $page->where('fiscalYear', OrgTime::currentFiscalYear()));

    $this->actingAs($chair)->get(route('hours.committee-detailed', ['fy' => 2024]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fiscalYear', 2024)
            ->where('months.0.year_month', '202304')
            ->where('org.ytd.extra', 7));
});

it('resolves a DMV report under the French /fr/ path', function () {
    $chair = orgOfficer($this->root, Role::Chair);

    $this->withLocaleRoutes('fr', function () use ($chair) {
        $this->actingAs($chair)
            ->get('/fr/heures/statistiques-sommaire')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('hours/CommitteeSummary')->where('locale', 'fr'));
    });
});
