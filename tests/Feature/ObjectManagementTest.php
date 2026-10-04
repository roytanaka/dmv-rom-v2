<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HandlingObject;
use App\Models\Member;
use App\Models\SignUp;

/*
 * Maintaining a Group's Objects (#584, ADR-0026 §3). A schedule admin — a Scheduler or Chair of a
 * scheduling Group — adds, renames, retires, reinstates and reorders the Objects on their own
 * Group, through the same schedule-admin gate every scheduling write uses (SchedulePolicy
 * `manageObjects`). The endpoints mirror the shift-kind ones of #567 one for one. There is no
 * delete: an Object is retired and reinstated, never removed, so the Sign-ups already reserving it
 * keep their name. Prior art: ShiftKindManagementTest.
 */

/** A Member of $group carrying an optional role. */
function objectMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

it('lets a Scheduler add an Object at the end of the sort order', function () {
    $group = Group::factory()->program()->create();
    HandlingObject::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $scheduler = objectMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->post(route('groups.objects.store', ['group' => $group]), ['name' => 'Trilobite fossil'])
        ->assertRedirect();

    $added = $group->objects()->where('name', 'Trilobite fossil')->sole();
    expect($added->active)->toBeTrue();
    expect($added->sort_order)->toBe(1);
});

it('lets a Chair rename an Object', function () {
    $group = Group::factory()->program()->create();
    $object = HandlingObject::factory()->create(['group_id' => $group->id, 'name' => 'Ammonite']);
    $chair = objectMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->patch(route('objects.update', ['object' => $object]), ['name' => 'Ammonite shell'])
        ->assertRedirect();

    expect($object->refresh()->name)->toBe('Ammonite shell');
});

it('lets a Scheduler retire and reinstate an Object', function () {
    $group = Group::factory()->program()->create();
    $object = HandlingObject::factory()->create(['group_id' => $group->id, 'active' => true]);
    $scheduler = objectMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('objects.update', ['object' => $object]), ['active' => false])
        ->assertRedirect();
    expect($object->refresh()->active)->toBeFalse();

    $this->actingAs($scheduler)
        ->patch(route('objects.update', ['object' => $object]), ['active' => true])
        ->assertRedirect();
    expect($object->refresh()->active)->toBeTrue();
});

it('drops a retired Object from the active list but keeps it on the full list', function () {
    $group = Group::factory()->program()->create();
    $active = HandlingObject::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $retired = HandlingObject::factory()->inactive()->create(['group_id' => $group->id, 'sort_order' => 1]);

    expect($group->objects()->active()->orderBy('sort_order')->pluck('id')->all())->toBe([$active->id]);
    expect($group->objects()->orderBy('sort_order')->pluck('id')->all())->toBe([$active->id, $retired->id]);
});

it('lets a Scheduler reorder Objects, setting the picker order', function () {
    $group = Group::factory()->program()->create();
    $first = HandlingObject::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $second = HandlingObject::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);
    $scheduler = objectMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('groups.objects.reorder', ['group' => $group]), ['ids' => [$second->id, $first->id]])
        ->assertRedirect();

    expect($second->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
});

it('rejects a duplicate name in the same Group but allows it in a different Group', function () {
    $group = Group::factory()->program()->create();
    HandlingObject::factory()->create(['group_id' => $group->id, 'name' => 'Meteorite']);
    $other = Group::factory()->program()->create();
    HandlingObject::factory()->create(['group_id' => $other->id, 'name' => 'Meteorite']);
    $scheduler = objectMemberOf($group, role: Role::Scheduler);
    $otherScheduler = objectMemberOf($other, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->post(route('groups.objects.store', ['group' => $group]), ['name' => 'Meteorite'])
        ->assertSessionHasErrors('name');

    // The same name is free in a different Group — uniqueness is scoped to the Group.
    $this->actingAs($otherScheduler)
        ->post(route('groups.objects.store', ['group' => $other]), ['name' => 'Trilobite fossil'])
        ->assertRedirect();
});

it('lets a rename keep the same name without a self-collision', function () {
    $group = Group::factory()->program()->create();
    $object = HandlingObject::factory()->create(['group_id' => $group->id, 'name' => 'Ammonite']);
    $scheduler = objectMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('objects.update', ['object' => $object]), ['name' => 'Ammonite'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('forbids a plain member from every write', function () {
    $group = Group::factory()->program()->create();
    $object = HandlingObject::factory()->create(['group_id' => $group->id]);
    $plain = objectMemberOf($group);

    $this->actingAs($plain)
        ->post(route('groups.objects.store', ['group' => $group]), ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($plain)
        ->patch(route('objects.update', ['object' => $object]), ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($plain)
        ->patch(route('groups.objects.reorder', ['group' => $group]), ['ids' => [$object->id]])
        ->assertForbidden();
});

it('forbids an admin of another Group from writing here', function () {
    $group = Group::factory()->program()->create();
    $object = HandlingObject::factory()->create(['group_id' => $group->id]);
    $foreignScheduler = objectMemberOf(Group::factory()->program()->create(), role: Role::Scheduler);

    $this->actingAs($foreignScheduler)
        ->patch(route('objects.update', ['object' => $object]), ['name' => 'Nope'])
        ->assertForbidden();
});

it('links an Object to a Sign-up through the object_sign_up pivot', function () {
    // The pivot is created here so the schema is complete; the seats ticket puts it to use.
    $group = Group::factory()->program()->create();
    $object = HandlingObject::factory()->create(['group_id' => $group->id]);
    $signUp = SignUp::factory()->create();

    $object->signUps()->attach($signUp);

    expect($object->signUps()->pluck('sign_ups.id')->all())->toBe([$signUp->id]);
});

it('forbids maintaining Objects on a Group that runs no scheduling', function () {
    $group = Group::factory()->create(['has_scheduling' => false]);
    $chair = objectMemberOf($group, role: Role::Chair);

    $this->actingAs($chair)
        ->post(route('groups.objects.store', ['group' => $group]), ['name' => 'Nope'])
        ->assertForbidden();
});
