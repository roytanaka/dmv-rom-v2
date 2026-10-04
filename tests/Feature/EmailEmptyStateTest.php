<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Email control's empty state (#513, ADR-0024 §6) at the Inertia prop seam. Each
 * Group-scoped page carries `email.reason` — null, 'not_member', or 'not_org_wide_sender'
 * — so the control can grey itself and name why; the Schedule page also carries one
 * `can.emailSignups` flag gating the Shift cards' Email button.
 */

function emailPlainMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);

    return $member;
}

function emailRoot(): Group
{
    return Group::factory()->create(['slug' => Group::ROOT_SLUG, 'name' => 'DMV']);
}

// --- email.reason on the Group page and its roster --------------------------

it('carries the org-wide-sender reason for a plain member of the root', function () {
    $root = emailRoot();

    $this->actingAs(emailPlainMemberOf($root))
        ->get(route('groups.show', $root))
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('email.reason', 'not_org_wide_sender'));
});

it('carries the org-wide-sender reason on the root roster tab too', function () {
    $root = emailRoot();

    $this->actingAs(emailPlainMemberOf($root))
        ->get(route('groups.show', ['group' => $root, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'roster')
            ->where('email.reason', 'not_org_wide_sender'));
});

it('carries the join reason for a non-member viewing another Group', function () {
    $root = emailRoot();
    $other = Group::factory()->publicListing()->create();

    // A plain member of the root, not of this other Group, viewing its page.
    $this->actingAs(emailPlainMemberOf($root))
        ->get(route('groups.show', $other))
        ->assertInertia(fn (Assert $page) => $page
            ->where('email.reason', 'not_member'));
});

it('carries no reason for a member of a non-root Group', function () {
    $group = Group::factory()->publicListing()->create();

    $this->actingAs(emailPlainMemberOf($group))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('email.reason', null));
});

// --- can.emailSignups on the opened Schedule --------------------------------

it('grants can.emailSignups to a Scheduler of the Group', function () {
    $group = Group::factory()->program()->publicListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);

    $member = emailPlainMemberOf($group);
    GroupMemberRole::factory()->create([
        'group_member_id' => $member->membershipIn($group)->id,
        'role' => Role::Scheduler,
    ]);

    $this->actingAs($member->fresh())
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.id', $schedule->id)
            ->where('scheduling.open.can.emailSignups', true));
});

it('withholds can.emailSignups from a plain member of the Group', function () {
    $group = Group::factory()->program()->publicListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);

    $this->actingAs(emailPlainMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.can.emailSignups', false));
});
