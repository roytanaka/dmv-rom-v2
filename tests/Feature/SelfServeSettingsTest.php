<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;

/*
 * The Group's self-serve settings (#582, spec #576, ADR-0026 §1 and §2). A Chair or Scheduler of a
 * scheduling Group edits two things through one dedicated, schedule-admin-gated endpoint: self-serve
 * shifts on/off and the unit length in minutes. A plain member is refused. Prior art:
 * EmptyDeskSettingsTest and its SchedulePolicy gate.
 */

/** A Member of $group carrying an optional role. */
function selfServeMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

it('lets a Scheduler switch self-serve on and set the unit minutes', function () {
    $group = Group::factory()->program()->create(['self_serve_shifts' => false, 'self_serve_unit_minutes' => 45]);
    $scheduler = selfServeMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 30,
        ])
        ->assertRedirect();

    $group->refresh();
    expect($group->self_serve_shifts)->toBeTrue();
    expect($group->self_serve_unit_minutes)->toBe(30);
});

it('lets a Chair edit the settings', function () {
    $group = Group::factory()->program()->create(['self_serve_shifts' => true]);
    $chair = selfServeMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => false,
            'self_serve_unit_minutes' => 45,
        ])
        ->assertRedirect();

    expect($group->refresh()->self_serve_shifts)->toBeFalse();
});

it('forbids a plain member from editing the settings', function () {
    $group = Group::factory()->program()->create();
    $plain = selfServeMemberOf($group);

    $this->actingAs($plain)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 45,
        ])
        ->assertForbidden();
});

it('forbids an admin of another Group', function () {
    $group = Group::factory()->program()->create();
    $other = Group::factory()->program()->create();
    $outsider = selfServeMemberOf($other, role: Role::Scheduler);

    $this->actingAs($outsider)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 45,
        ])
        ->assertForbidden();
});

it('forbids editing on a Group that runs no scheduling', function () {
    $group = Group::factory()->create(['has_scheduling' => false]);
    $chair = selfServeMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 45,
        ])
        ->assertForbidden();
});

it('rejects unit minutes below the floor', function () {
    $group = Group::factory()->program()->create();
    $scheduler = selfServeMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 10,
        ])
        ->assertSessionHasErrors('self_serve_unit_minutes');
});

it('rejects unit minutes above the ceiling', function () {
    $group = Group::factory()->program()->create();
    $scheduler = selfServeMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 300,
        ])
        ->assertSessionHasErrors('self_serve_unit_minutes');
});

it('rejects a non-integer unit length', function () {
    $group = Group::factory()->program()->create();
    $scheduler = selfServeMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 'forty-five',
        ])
        ->assertSessionHasErrors('self_serve_unit_minutes');
});
