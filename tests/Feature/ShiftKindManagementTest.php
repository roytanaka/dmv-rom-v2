<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\ShiftKind;

/*
 * Maintaining a Group's shift kinds (#567, ADR-0021 §3). A schedule admin — a Scheduler or
 * Chair of a scheduling Group — adds, renames, retires, reinstates and reorders the kinds on
 * their own Group, through the same schedule-admin gate every scheduling write uses
 * (SchedulePolicy `manageShiftKinds`). There is no delete: a kind is retired and reinstated,
 * never removed, so the Shifts already carrying it keep their name. Prior art:
 * EmptyDeskSettingsTest.
 */

/** A Member of $group carrying an optional role. */
function shiftKindMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

it('lets a Scheduler add a kind at the end of the sort order', function () {
    $group = Group::factory()->program()->create();
    ShiftKind::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $scheduler = shiftKindMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->post(route('groups.shift-kinds.store', ['group' => $group]), ['name' => 'Highlights tour'])
        ->assertRedirect();

    $added = $group->shiftKinds()->where('name', 'Highlights tour')->sole();
    expect($added->active)->toBeTrue();
    expect($added->sort_order)->toBe(1);
});

it('lets a Chair rename a kind, and existing Shifts show the new name', function () {
    $group = Group::factory()->program()->create();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Desk']);
    $chair = shiftKindMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('shift-kinds.update', ['shiftKind' => $kind]), ['name' => 'Front desk'])
        ->assertRedirect();

    expect($kind->refresh()->name)->toBe('Front desk');
});

it('lets a Scheduler retire and reinstate a kind', function () {
    $group = Group::factory()->program()->create();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'active' => true]);
    $scheduler = shiftKindMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('shift-kinds.update', ['shiftKind' => $kind]), ['active' => false])
        ->assertRedirect();
    expect($kind->refresh()->active)->toBeFalse();

    $this->actingAs($scheduler)
        ->patch(route('shift-kinds.update', ['shiftKind' => $kind]), ['active' => true])
        ->assertRedirect();
    expect($kind->refresh()->active)->toBeTrue();
});

it('lets a Scheduler reorder kinds, setting the picker order', function () {
    $group = Group::factory()->program()->create();
    $first = ShiftKind::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $second = ShiftKind::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);
    $scheduler = shiftKindMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.shift-kinds.reorder', ['group' => $group]), ['ids' => [$second->id, $first->id]])
        ->assertRedirect();

    expect($second->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
});

it('rejects a duplicate name in the same Group but allows it in a different Group', function () {
    $group = Group::factory()->program()->create();
    ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights']);
    $other = Group::factory()->program()->create();
    ShiftKind::factory()->create(['group_id' => $other->id, 'name' => 'Highlights']);
    $scheduler = shiftKindMemberOf($group, role: Role::Scheduler);
    $otherScheduler = shiftKindMemberOf($other, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->post(route('groups.shift-kinds.store', ['group' => $group]), ['name' => 'Highlights'])
        ->assertSessionHasErrors('name');

    // The same name is free in a different Group — uniqueness is scoped to the Group.
    $this->actingAs($otherScheduler)
        ->post(route('groups.shift-kinds.store', ['group' => $other]), ['name' => 'Desk'])
        ->assertRedirect();
});

it('lets a rename keep the same name without a self-collision', function () {
    $group = Group::factory()->program()->create();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Desk']);
    $scheduler = shiftKindMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('shift-kinds.update', ['shiftKind' => $kind]), ['name' => 'Desk'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('forbids a plain member from every write', function () {
    $group = Group::factory()->program()->create();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id]);
    $plain = shiftKindMemberOf($group);

    $this->actingAs($plain)
        ->post(route('groups.shift-kinds.store', ['group' => $group]), ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($plain)
        ->patch(route('shift-kinds.update', ['shiftKind' => $kind]), ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($plain)
        ->patch(route('groups.shift-kinds.reorder', ['group' => $group]), ['ids' => [$kind->id]])
        ->assertForbidden();
});

it('forbids an admin of another Group from writing here', function () {
    $group = Group::factory()->program()->create();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id]);
    $foreignScheduler = shiftKindMemberOf(Group::factory()->program()->create(), role: Role::Scheduler);

    $this->actingAs($foreignScheduler)
        ->patch(route('shift-kinds.update', ['shiftKind' => $kind]), ['name' => 'Nope'])
        ->assertForbidden();
});

it('forbids maintaining kinds on a Group that runs no scheduling', function () {
    $group = Group::factory()->create(['has_scheduling' => false]);
    $chair = shiftKindMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->post(route('groups.shift-kinds.store', ['group' => $group]), ['name' => 'Nope'])
        ->assertForbidden();
});
