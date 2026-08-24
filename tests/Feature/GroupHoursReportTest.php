<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\OrgTime;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Group fiscal-year hours report (#411, PRD #406, ADR-0022 §5) — a Member × twelve-month
 * matrix with a year-to-date column, the Group's own hours and its hours including every
 * descendant side by side. Exercised through the route → auth middleware → viewReports policy
 * → Gate::before, and at the Inertia prop seam for the numbers.
 *
 * Reports are not open reading (§4): an ordinary Member sees nothing, not even a Group total;
 * a sibling-Group Member sees nothing either — only the parent relationship widens a report.
 *
 * Frozen inside Fiscal 2026 (2025-04 through 2026-03) so the default window is fixed.
 */
beforeEach(function () {
    $this->travelTo('2026-01-15 12:00:00');
});

/** A member of the given Group holding the given role. */
function reportOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function reportMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

/** A record at the no-meeting grain for one Member on one Group in one month. */
function reportRecord(Member $member, Group $group, string $yearMonth, int $scheduled, int $extra): HoursRecord
{
    return HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $group->id,
        'year_month' => $yearMonth,
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => $scheduled,
        'extra_hours' => $extra,
        'total_hours' => $scheduled + $extra,
    ]);
}

// --- Who may read the report ------------------------------------------------

it('shows a Chair of the Group the report', function () {
    $group = Group::factory()->create();

    $this->actingAs(reportOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.report', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/HoursReport'));
});

it('shows a Statistician of the Group the report', function () {
    $group = Group::factory()->create();

    $this->actingAs(reportOfficerOf($group, Role::Statistician))
        ->get(route('groups.hours.report', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/HoursReport'));
});

it('shows a Chair of an ancestor Group the report on a descendant', function () {
    $grandparent = Group::factory()->create();
    $parent = Group::factory()->create(['parent_id' => $grandparent->id]);
    $child = Group::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(reportOfficerOf($grandparent, Role::Chair))
        ->get(route('groups.hours.report', $child))
        ->assertOk();
});

it('shows a Statistician of the parent Group the report', function () {
    $parent = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(reportOfficerOf($parent, Role::Statistician))
        ->get(route('groups.hours.report', $child))
        ->assertOk();
});

it('shows the super-tier the report', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.hours.report', Group::factory()->create()))
        ->assertOk();
});

it('forbids an ordinary Member of the Group, showing no report and no Group total', function () {
    $group = Group::factory()->create();

    $this->actingAs(reportMemberOf($group))
        ->get(route('groups.hours.report', $group))
        ->assertForbidden();
});

it('forbids a Member of a sibling Group', function () {
    $parent = Group::factory()->create();
    $target = Group::factory()->create(['parent_id' => $parent->id]);
    $sibling = Group::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(reportOfficerOf($sibling, Role::Chair))
        ->get(route('groups.hours.report', $target))
        ->assertForbidden();
});

it('redirects an unauthenticated visitor to login', function () {
    $this->get(route('groups.hours.report', Group::factory()->create()))
        ->assertRedirect(route('login'));
});

// --- What the report shows --------------------------------------------------

it('renders a Member row per person with hours, a twelve-month matrix and a year-to-date column', function () {
    $group = Group::factory()->create();
    $chair = reportOfficerOf($group, Role::Chair);
    $alice = reportMemberOf($group);
    reportRecord($alice, $group, '202504', 2, 1); // April → bucket 0
    reportRecord($alice, $group, '202603', 0, 4); // March → bucket 11

    $this->actingAs($chair)
        ->get(route('groups.hours.report', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->has('months', 12)
            ->where('months.0.year_month', '202504')
            ->where('months.11.year_month', '202603')
            ->has('members', 1)
            ->has('members.0.months', 12)
            ->where('members.0.months.0.total_hours', 3)
            ->where('members.0.months.11.total_hours', 4)
            ->where('members.0.ytd.total_hours', 7));
});

it('shows the Group\'s own hours and its subtree hours side by side', function () {
    $group = Group::factory()->create();
    $child = Group::factory()->create(['parent_id' => $group->id]);
    $chair = reportOfficerOf($group, Role::Chair);
    reportRecord(reportMemberOf($group), $group, '202504', 0, 3);
    reportRecord(Member::factory()->create(), $child, '202504', 0, 10);

    $this->actingAs($chair)
        ->get(route('groups.hours.report', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.own.ytd', 3)
            ->where('totals.subtree.ytd', 13)
            ->where('totals.own.months.0', 3)
            ->where('totals.subtree.months.0', 13));
});

it('moves the window to a past fiscal year the viewer picks', function () {
    $group = Group::factory()->create();
    $chair = reportOfficerOf($group, Role::Chair);
    // Fiscal 2024 runs 2023-04 through 2024-03.
    reportRecord(reportMemberOf($group), $group, '202309', 6, 0);

    $this->actingAs($chair)
        ->get(route('groups.hours.report', ['group' => $group, 'fy' => 2024]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fiscalYear', 2024)
            ->where('months.0.year_month', '202304')
            ->where('totals.own.ytd', 6));
});

it('defaults to the current fiscal year', function () {
    $group = Group::factory()->create();

    $this->actingAs(reportOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.report', $group))
        ->assertInertia(fn (Assert $page) => $page->where('fiscalYear', OrgTime::currentFiscalYear()));
});

it('resolves the report under the French /fr/ path', function () {
    $group = Group::factory()->create();
    $chair = reportOfficerOf($group, Role::Chair);

    $this->withLocaleRoutes('fr', function () use ($chair, $group) {
        $this->actingAs($chair)
            ->get("/fr/groupes/{$group->slug}/heures/rapport")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('groups/HoursReport')->where('locale', 'fr'));
    });
});
