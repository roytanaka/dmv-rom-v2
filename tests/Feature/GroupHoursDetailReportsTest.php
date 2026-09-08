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
 * The three officer surfaces a Chair or Statistician reaches for after the fiscal-year
 * matrix (#412, PRD #406, ADR-0022 §8): a month picker, a Member History, and the two
 * Member × twelve-month summaries kept apart — Member Extra Hours (rows carrying no
 * Meeting) and Member Meeting Hours (rows carrying one).
 *
 * All four are gated identically to the fiscal-year report via the HoursRecordPolicy's
 * viewReports — a Chair or Statistician of the Group or any ancestor, or the super-tier;
 * an ordinary Member reaches none of them. Exercised through the route → auth middleware →
 * policy → Gate::before, and at the Inertia prop seam for the numbers.
 *
 * Frozen inside Fiscal 2026 (2025-04 through 2026-03) so the default windows are fixed.
 */
beforeEach(function () {
    $this->travelTo('2026-01-15 12:00:00');
});

/** A member of the given Group holding the given role. */
function detailOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function detailMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

/** A record for one Member on one Group in one month, optionally attached to a Meeting. */
function detailRecord(Member $member, Group $group, string $yearMonth, int $scheduled, int $extra, int $meetingId = HoursRecord::NO_MEETING): HoursRecord
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

// --- The month picker -------------------------------------------------------

it('shows a Chair the month picker', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.month', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/HoursMonth'));
});

it('forbids an ordinary Member the month picker', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailMemberOf($group))
        ->get(route('groups.hours.month', $group))
        ->assertForbidden();
});

it('shows one month\'s entries across the Group, a row per Member', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    $alice = detailMemberOf($group);
    $bob = detailMemberOf($group);
    detailRecord($alice, $group, '202511', 3, 2);
    detailRecord($bob, $group, '202511', 0, 5);
    detailRecord($alice, $group, '202510', 9, 9); // a different month, excluded

    $this->actingAs($chair)
        ->get(route('groups.hours.month', ['group' => $group, 'month' => '202511']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('month.year_month', '202511')
            ->has('members', 2)
            ->where('members.0.total_hours', 5) // Alice: 3 + 2, name-ordered
            ->where('members.1.total_hours', 5));
});

it('defaults the month picker to the current month', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.month', $group))
        ->assertInertia(fn (Assert $page) => $page->where('month.year_month', '202601'));
});

// --- Member History ---------------------------------------------------------

it('shows a Chair the Member History', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.member', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/HoursMemberHistory'));
});

it('forbids an ordinary Member the Member History', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailMemberOf($group))
        ->get(route('groups.hours.member', $group))
        ->assertForbidden();
});

it('lists the Members with hours in the Group for the picker', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    $alice = detailMemberOf($group);
    detailRecord($alice, $group, '202511', 3, 0);

    $this->actingAs($chair)
        ->get(route('groups.hours.member', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 1)
            ->where('members.0.id', $alice->id)
            ->where('member', null)
            ->has('rows', 0));
});

it('shows one Member\'s hours in this Group over time', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    $alice = detailMemberOf($group);
    detailRecord($alice, $group, '202504', 2, 1);
    detailRecord($alice, $group, '202511', 0, 4);

    $this->actingAs($chair)
        ->get(route('groups.hours.member', ['group' => $group, 'member' => $alice->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.id', $alice->id)
            ->has('rows', 2)
            ->where('rows.0.year_month', '202511') // newest first
            ->where('rows.0.total_hours', 4)
            ->where('rows.1.year_month', '202504')
            ->where('rows.1.total_hours', 3));
});

it('ignores a Member with no hours in this Group, leaking nothing', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    $stranger = Member::factory()->create();

    $this->actingAs($chair)
        ->get(route('groups.hours.member', ['group' => $group, 'member' => $stranger->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('member', null)
            ->has('rows', 0));
});

// --- Member Extra Hours summary ---------------------------------------------

it('shows a Chair the Member Extra Hours summary', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.extra', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/HoursExtraSummary'));
});

it('forbids an ordinary Member the Member Extra Hours summary', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailMemberOf($group))
        ->get(route('groups.hours.extra', $group))
        ->assertForbidden();
});

it('renders Member Extra Hours as a Member × twelve-month matrix reading only no-meeting rows', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    $alice = detailMemberOf($group);
    detailRecord($alice, $group, '202504', 0, 3); // extra, no meeting → April bucket 0
    detailRecord($alice, $group, '202603', 0, 4); // extra, no meeting → March bucket 11
    detailRecord($alice, $group, '202504', 0, 9, 77); // a meeting row — excluded here

    $this->actingAs($chair)
        ->get(route('groups.hours.extra', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->has('months', 12)
            ->where('months.0.year_month', '202504')
            ->has('members', 1)
            ->has('members.0.months', 12)
            ->where('members.0.months.0.hours', 3)
            ->where('members.0.months.11.hours', 4)
            ->where('members.0.ytd', 7));
});

it('defaults the Member Extra Hours summary to the current fiscal year', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.extra', $group))
        ->assertInertia(fn (Assert $page) => $page->where('fiscalYear', OrgTime::currentFiscalYear()));
});

// --- Member Meeting Hours summary -------------------------------------------

it('shows a Chair the Member Meeting Hours summary', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailOfficerOf($group, Role::Chair))
        ->get(route('groups.hours.meetings', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/HoursMeetingSummary'));
});

it('forbids an ordinary Member the Member Meeting Hours summary', function () {
    $group = Group::factory()->create();

    $this->actingAs(detailMemberOf($group))
        ->get(route('groups.hours.meetings', $group))
        ->assertForbidden();
});

it('renders Member Meeting Hours reading only rows carrying a Meeting', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    $alice = detailMemberOf($group);
    detailRecord($alice, $group, '202504', 0, 6, 42); // meeting row → April bucket 0
    detailRecord($alice, $group, '202504', 0, 9); // a no-meeting extra row — excluded here

    $this->actingAs($chair)
        ->get(route('groups.hours.meetings', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 1)
            ->where('members.0.months.0.hours', 6)
            ->where('members.0.ytd', 6));
});

it('renders an empty Member Meeting Hours summary rather than erroring when there is no meeting data', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);
    detailRecord(detailMemberOf($group), $group, '202504', 0, 5); // no-meeting extra only

    $this->actingAs($chair)
        ->get(route('groups.hours.meetings', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('members', 0));
});

// --- Gating shared by all four ----------------------------------------------

it('shows a Chair of an ancestor Group every detail report on a descendant', function () {
    $grandparent = Group::factory()->create();
    $parent = Group::factory()->create(['parent_id' => $grandparent->id]);
    $child = Group::factory()->create(['parent_id' => $parent->id]);
    $ancestorChair = detailOfficerOf($grandparent, Role::Chair);

    foreach (['groups.hours.month', 'groups.hours.member', 'groups.hours.extra', 'groups.hours.meetings'] as $name) {
        $this->actingAs($ancestorChair)->get(route($name, $child))->assertOk();
    }
});

it('resolves the detail reports under the French /fr/ path', function () {
    $group = Group::factory()->create();
    $chair = detailOfficerOf($group, Role::Chair);

    $this->withLocaleRoutes('fr', function () use ($chair, $group) {
        $this->actingAs($chair)->get("/fr/groupes/{$group->slug}/heures/mois")->assertOk();
        $this->actingAs($chair)->get("/fr/groupes/{$group->slug}/heures/membre")->assertOk();
        $this->actingAs($chair)->get("/fr/groupes/{$group->slug}/heures/supplementaires")->assertOk();
        $this->actingAs($chair)->get("/fr/groupes/{$group->slug}/heures/reunions")->assertOk();
    });
});
