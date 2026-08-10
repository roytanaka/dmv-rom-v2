<?php

use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Enums\ShiftAudience;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;

/*
 * Role-matrix HTTP harness for Shift authoring (#356, PRD #352, ADR-0021 §2).
 *
 * Exercises add / edit / delete of a Shift through every layer — route → auth
 * middleware → Form Request authorize() → ShiftPolicy → Gate::before. Authoring is
 * gated to the owning Group's Scheduler | Chair | super-tier and requires the Group's
 * `has_scheduling` capability; everyone else is denied, in the same shape as the
 * SchedulePolicy the Shift's Schedule already answers to.
 *
 * The rules exist to make bad states unreachable: the date range is enforced both ways
 * (a Shift may not sit outside its Schedule, and a Schedule may not shrink away from its
 * Shifts), and two identical Shifts are permitted on purpose.
 */

/** A Group that runs scheduling — the only kind a Shift may be managed on. */
function shiftGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

/** A member of the given Group holding the given role. */
function shiftOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function shiftMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

/** A published Schedule spanning the whole of August 2026 on a scheduling-Group. */
function augustSchedule(?Group $group = null): Schedule
{
    return Schedule::factory()->published()->create([
        'group_id' => ($group ?? shiftGroup())->id,
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);
}

/** A valid Shift payload sitting inside the August range. */
function shiftPayload(array $overrides = []): array
{
    return [
        'starts_at' => '2026-08-10 10:00:00',
        'ends_at' => '2026-08-10 13:00:00',
        ...$overrides,
    ];
}

// --- Adding (store) — allow rows --------------------------------------------

it('lets a Scheduler add a Shift to a Schedule, defaulting capacity, kind and audience', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $shift = Shift::sole();
    expect($shift->schedule_id)->toBe($schedule->id)
        ->and($shift->capacity)->toBe(1)
        ->and($shift->shift_kind_id)->toBeNull()
        ->and($shift->audience)->toBe(ShiftAudience::Group);
});

it('lets a Scheduler add a Shift with a capacity, one of the Group\'s kinds, and an open audience', function () {
    $schedule = augustSchedule();
    $kind = ShiftKind::factory()->create(['group_id' => $schedule->group_id]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload([
            'capacity' => 4,
            'shift_kind_id' => $kind->id,
            'audience' => ShiftAudience::Open->value,
        ]))
        ->assertSessionHasNoErrors();

    $shift = Shift::sole();
    expect($shift->capacity)->toBe(4)
        ->and($shift->shift_kind_id)->toBe($kind->id)
        ->and($shift->audience)->toBe(ShiftAudience::Open);
});

it('adds a Shift to a published Schedule — adding is purely additive', function () {
    $schedule = Schedule::factory()->published()->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->state)->toBe(ScheduleState::Published)
        ->and(Shift::count())->toBe(1);
});

it('accepts two identical Shifts in one Schedule — no uniqueness constraint', function () {
    $schedule = augustSchedule();
    $scheduler = shiftOfficerOf($schedule->group, Role::Scheduler);

    $this->actingAs($scheduler)->post(route('shifts.store', $schedule), shiftPayload())->assertSessionHasNoErrors();
    $this->actingAs($scheduler)->post(route('shifts.store', $schedule), shiftPayload())->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(2);
});

it('lets a Chair add a Shift (Chair-implication)', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Chair))
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(1);
});

it('lets a super-tier member add a Shift to any Schedule', function () {
    $schedule = augustSchedule();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(1);
});

// --- Adding (store) — validation --------------------------------------------

it('requires an end time', function () {
    $schedule = augustSchedule();
    $create = shiftPayload();
    unset($create['ends_at']);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), $create)
        ->assertSessionHasErrors('ends_at');

    expect(Shift::count())->toBe(0);
});

it('rejects an end time before the start time', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload(['ends_at' => '2026-08-10 09:00:00']))
        ->assertSessionHasErrors('ends_at');

    expect(Shift::count())->toBe(0);
});

it('rejects a Shift whose start falls before the Schedule range', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload([
            'starts_at' => '2026-07-31 10:00:00',
            'ends_at' => '2026-07-31 13:00:00',
        ]))
        ->assertSessionHasErrors('ends_at');

    expect(Shift::count())->toBe(0);
});

it('rejects a Shift whose end falls after the Schedule range', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload([
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-09-01 13:00:00',
        ]))
        ->assertSessionHasErrors('ends_at');

    expect(Shift::count())->toBe(0);
});

it('rejects a kind belonging to a different Group', function () {
    $schedule = augustSchedule();
    $foreignKind = ShiftKind::factory()->create();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->post(route('shifts.store', $schedule), shiftPayload(['shift_kind_id' => $foreignKind->id]))
        ->assertSessionHasErrors('shift_kind_id');

    expect(Shift::count())->toBe(0);
});

// --- Adding (store) — deny rows ---------------------------------------------

it('forbids an ordinary member from adding a Shift', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftMemberOf($schedule->group))
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

it('forbids a Scheduler of one Group from adding a Shift on another', function () {
    $scheduler = shiftOfficerOf(shiftGroup(), Role::Scheduler);
    $schedule = augustSchedule();

    $this->actingAs($scheduler)
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

it('forbids a Chair of a Group that does not run scheduling', function () {
    $group = Group::factory()->create(['has_scheduling' => false]);
    $schedule = Schedule::factory()->create([
        'group_id' => $group->id,
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
    ]);

    $this->actingAs(shiftOfficerOf($group, Role::Chair))
        ->post(route('shifts.store', $schedule), shiftPayload())
        ->assertForbidden();

    expect(Shift::count())->toBe(0);
});

it('redirects an unauthenticated add to login', function () {
    $this->post(route('shifts.store', augustSchedule()), shiftPayload())
        ->assertRedirect(route('login'));
});

// --- Editing (update) -------------------------------------------------------

it('raises capacity in a single update that disturbs nothing else', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-10 10:00:00',
        'ends_at' => '2026-08-10 13:00:00',
        'capacity' => 1,
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['capacity' => 5])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $shift->refresh();
    expect($shift->capacity)->toBe(5)
        ->and($shift->starts_at->toDateTimeString())->toBe('2026-08-10 10:00:00');
});

it('lets a Scheduler move a Shift within the Schedule range', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-10 10:00:00',
        'ends_at' => '2026-08-10 13:00:00',
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['starts_at' => '2026-08-12 14:00:00', 'ends_at' => '2026-08-12 16:00:00'])
        ->assertSessionHasNoErrors();

    expect($shift->fresh()->starts_at->toDateTimeString())->toBe('2026-08-12 14:00:00');
});

it('rejects moving a Shift outside the Schedule range', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-10 10:00:00',
        'ends_at' => '2026-08-10 13:00:00',
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['starts_at' => '2026-09-01 10:00:00', 'ends_at' => '2026-09-01 13:00:00'])
        ->assertSessionHasErrors('ends_at');

    expect($shift->fresh()->starts_at->toDateTimeString())->toBe('2026-08-10 10:00:00');
});

it('forbids a Scheduler of another Group from editing a Shift', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create(['schedule_id' => $schedule->id, 'capacity' => 1]);

    $this->actingAs(shiftOfficerOf(shiftGroup(), Role::Scheduler))
        ->patch(route('shifts.update', $shift), ['capacity' => 5])
        ->assertForbidden();

    expect($shift->fresh()->capacity)->toBe(1);
});

it('forbids an ordinary member from editing a Shift', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create(['schedule_id' => $schedule->id, 'capacity' => 1]);

    $this->actingAs(shiftMemberOf($schedule->group))
        ->patch(route('shifts.update', $shift), ['capacity' => 5])
        ->assertForbidden();

    expect($shift->fresh()->capacity)->toBe(1);
});

it('redirects an unauthenticated edit to login', function () {
    $shift = Shift::factory()->create();

    $this->patch(route('shifts.update', $shift), ['capacity' => 5])
        ->assertRedirect(route('login'));
});

// --- Deleting (destroy) -----------------------------------------------------

it('lets a Scheduler delete a Shift on their Group', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create(['schedule_id' => $schedule->id]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->delete(route('shifts.destroy', $shift))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Shift::count())->toBe(0);
});

it('lets a super-tier member delete any Shift', function () {
    $shift = Shift::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->delete(route('shifts.destroy', $shift))
        ->assertSessionHasNoErrors();

    expect(Shift::count())->toBe(0);
});

it('forbids a Scheduler of another Group from deleting a Shift', function () {
    $shift = Shift::factory()->create(['schedule_id' => augustSchedule()->id]);

    $this->actingAs(shiftOfficerOf(shiftGroup(), Role::Scheduler))
        ->delete(route('shifts.destroy', $shift))
        ->assertForbidden();

    expect(Shift::count())->toBe(1);
});

it('forbids an ordinary member from deleting a Shift', function () {
    $schedule = augustSchedule();
    $shift = Shift::factory()->create(['schedule_id' => $schedule->id]);

    $this->actingAs(shiftMemberOf($schedule->group))
        ->delete(route('shifts.destroy', $shift))
        ->assertForbidden();

    expect(Shift::count())->toBe(1);
});

it('redirects an unauthenticated delete to login', function () {
    $shift = Shift::factory()->create();

    $this->delete(route('shifts.destroy', $shift))->assertRedirect(route('login'));
});

// --- Schedule range vs its Shifts (the range and its contents never disagree) ---

it('blocks shrinking a Schedule range past a Shift that would fall outside it', function () {
    $schedule = augustSchedule();
    Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-20 10:00:00',
        'ends_at' => '2026-08-20 13:00:00',
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['ends_on' => '2026-08-15'])
        ->assertSessionHasErrors('ends_on');

    expect($schedule->fresh()->ends_on->toDateString())->toBe('2026-08-31');
});

it('blocks shrinking the start of a Schedule past a Shift that would fall outside it', function () {
    $schedule = augustSchedule();
    Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-03 10:00:00',
        'ends_at' => '2026-08-03 13:00:00',
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['starts_on' => '2026-08-10'])
        ->assertSessionHasErrors('starts_on');

    expect($schedule->fresh()->starts_on->toDateString())->toBe('2026-08-01');
});

it('allows shrinking a Schedule range that still contains all its Shifts', function () {
    $schedule = augustSchedule();
    Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => '2026-08-05 10:00:00',
        'ends_at' => '2026-08-05 13:00:00',
    ]);

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['ends_on' => '2026-08-10'])
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->ends_on->toDateString())->toBe('2026-08-10');
});

it('allows editing a Schedule range on a Schedule with no Shifts', function () {
    $schedule = augustSchedule();

    $this->actingAs(shiftOfficerOf($schedule->group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['starts_on' => '2026-08-10', 'ends_on' => '2026-08-15'])
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->ends_on->toDateString())->toBe('2026-08-15');
});
