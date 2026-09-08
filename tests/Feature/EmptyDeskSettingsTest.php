<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\ShiftKind;

/*
 * The Group's empty-desk settings (#487, spec #479, ADR-0024 §7). A Chair or Scheduler of a
 * scheduling Group edits three things through one dedicated, schedule-admin-gated endpoint: the
 * alert on/off, the look-ahead days, and which of the Group's shift kinds to watch. A plain
 * member is refused. Prior art: ReminderSettingsTest and its SchedulePolicy gate.
 */

/** A Member of $group carrying an optional role. */
function emptyDeskMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

it('lets a Scheduler edit the settings and mark which kinds to watch', function () {
    $group = Group::factory()->program()->create(['empty_desk_alert_enabled' => false, 'empty_desk_days_ahead' => 3]);
    $desk = ShiftKind::factory()->create(['group_id' => $group->id, 'alert_when_empty' => false]);
    $tour = ShiftKind::factory()->create(['group_id' => $group->id, 'alert_when_empty' => true]);
    $scheduler = emptyDeskMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 5,
            'watched_shift_kinds' => [$desk->id],
        ])
        ->assertRedirect();

    $group->refresh();
    expect($group->empty_desk_alert_enabled)->toBeTrue();
    expect($group->empty_desk_days_ahead)->toBe(5);
    // The Desk kind is now watched; the Tour kind, left off the list, is un-watched.
    expect($desk->refresh()->alert_when_empty)->toBeTrue();
    expect($tour->refresh()->alert_when_empty)->toBeFalse();
});

it('lets a Chair edit the settings', function () {
    $group = Group::factory()->program()->create(['empty_desk_alert_enabled' => true]);
    $chair = emptyDeskMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => false,
            'empty_desk_days_ahead' => 3,
            'watched_shift_kinds' => [],
        ])
        ->assertRedirect();

    expect($group->refresh()->empty_desk_alert_enabled)->toBeFalse();
});

it('forbids a plain member from editing the settings', function () {
    $group = Group::factory()->program()->create();
    $plain = emptyDeskMemberOf($group);

    $this->actingAs($plain)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 3,
            'watched_shift_kinds' => [],
        ])
        ->assertForbidden();
});

it('rejects a non-positive look-ahead', function () {
    $group = Group::factory()->program()->create();
    $scheduler = emptyDeskMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 0,
            'watched_shift_kinds' => [],
        ])
        ->assertSessionHasErrors('empty_desk_days_ahead');
});

it('rejects a watched kind that belongs to another Group', function () {
    $group = Group::factory()->program()->create();
    $scheduler = emptyDeskMemberOf($group, role: Role::Scheduler);
    $foreign = ShiftKind::factory()->create(['group_id' => Group::factory()->program()->create()->id]);

    $this->actingAs($scheduler)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 3,
            'watched_shift_kinds' => [$foreign->id],
        ])
        ->assertSessionHasErrors('watched_shift_kinds.0');
});

it('forbids editing on a Group that runs no scheduling', function () {
    $group = Group::factory()->create(['has_scheduling' => false]);
    $chair = emptyDeskMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 3,
            'watched_shift_kinds' => [],
        ])
        ->assertForbidden();
});
