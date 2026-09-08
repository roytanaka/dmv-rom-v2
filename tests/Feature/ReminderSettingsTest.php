<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;

/*
 * The Group's Reminder settings (#486, spec #479, ADR-0024 §7). A Chair or Scheduler of a
 * scheduling Group edits two fields — Reminders on/off and the lead days — through one
 * dedicated, schedule-admin-gated endpoint. A plain member is refused. Prior art: the
 * ScheduleController write seams (GroupSchedulingTest) and their SchedulePolicy gate.
 */

/** A Member of $group carrying an optional role. */
function reminderMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

it('lets a Scheduler edit the two Reminder settings', function () {
    $group = Group::factory()->program()->create(['reminders_enabled' => false, 'reminder_lead_days' => 3]);
    $scheduler = reminderMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => true,
            'reminder_lead_days' => 5,
        ])
        ->assertRedirect();

    $group->refresh();
    expect($group->reminders_enabled)->toBeTrue();
    expect($group->reminder_lead_days)->toBe(5);
});

it('lets a Chair edit the two Reminder settings', function () {
    $group = Group::factory()->program()->create(['reminders_enabled' => true, 'reminder_lead_days' => 3]);
    $chair = reminderMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => false,
            'reminder_lead_days' => 3,
        ])
        ->assertRedirect();

    expect($group->refresh()->reminders_enabled)->toBeFalse();
});

it('forbids a plain member from editing the Reminder settings', function () {
    $group = Group::factory()->program()->create();
    $plain = reminderMemberOf($group);

    $this->actingAs($plain)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => true,
            'reminder_lead_days' => 3,
        ])
        ->assertForbidden();
});

it('rejects a non-positive or missing lead-day count', function () {
    $group = Group::factory()->program()->create();
    $scheduler = reminderMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => true,
            'reminder_lead_days' => 0,
        ])
        ->assertSessionHasErrors('reminder_lead_days');
});

it('forbids editing Reminders on a Group that runs no scheduling', function () {
    // A Chair of a non-scheduling Group has no schedule-admin authority — the capability
    // guard in the gate denies even the officer.
    $group = Group::factory()->create(['has_scheduling' => false]);
    $chair = reminderMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => true,
            'reminder_lead_days' => 3,
        ])
        ->assertForbidden();
});
