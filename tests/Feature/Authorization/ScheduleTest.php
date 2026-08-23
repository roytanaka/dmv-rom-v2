<?php

use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Role-matrix HTTP harness for Group scheduling authoring (#354, PRD #352, ADR-0021 §1).
 *
 * Exercises create / edit / publish / un-publish / delete through every layer — route →
 * auth middleware → Form Request authorize() → SchedulePolicy → Gate::before. Authoring
 * is gated to a Group's Scheduler | Chair | super-tier and requires the Group's
 * `has_scheduling` capability; everyone else is denied. The deny rows (ordinary member,
 * Scheduler of another Group, capability off, unauthenticated) prove the fail-closed
 * posture. Publication is a `state` transition on the edit path, each direction with its
 * own guard; the `can` UI hints round it out.
 */

/** A Group that runs scheduling — the only kind a Schedule may be managed on. */
function authoringGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

/** A member of the given Group holding the given role. */
function scheduleOfficerOf(Group $group, Role $role): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);

    return $member;
}

/** An ordinary member of the given Group, holding no role. */
function scheduleMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

$payload = ['name' => 'August 2026', 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-31'];

// --- Creating (store) — allow rows ------------------------------------------

it('lets a Scheduler create a draft Schedule on their scheduling-Group', function () use ($payload) {
    $group = authoringGroup();

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->post(route('schedules.store', $group), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $schedule = Schedule::sole();
    expect($schedule->group_id)->toBe($group->id)
        ->and($schedule->name)->toBe('August 2026')
        ->and($schedule->state)->toBe(ScheduleState::Draft);
});

it('lets a Chair create a Schedule (Chair-implication)', function () use ($payload) {
    $group = authoringGroup();

    $this->actingAs(scheduleOfficerOf($group, Role::Chair))
        ->post(route('schedules.store', $group), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Schedule::count())->toBe(1);
});

it('lets a super-tier member create a Schedule on any scheduling-Group', function () use ($payload) {
    $group = authoringGroup();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('schedules.store', $group), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Schedule::count())->toBe(1);
});

it('stores an optional description and leaves it null when absent', function () use ($payload) {
    $group = authoringGroup();
    $scheduler = scheduleOfficerOf($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->post(route('schedules.store', $group), [...$payload, 'description' => 'Desk cover for the month.'])
        ->assertSessionHasNoErrors();

    expect(Schedule::sole()->description)->toBe('Desk cover for the month.');
});

// --- Creating (store) — validation ------------------------------------------

it('requires a name', function () use ($payload) {
    $group = authoringGroup();

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->post(route('schedules.store', $group), [...$payload, 'name' => ''])
        ->assertSessionHasErrors('name');

    expect(Schedule::count())->toBe(0);
});

it('requires an end date', function () use ($payload) {
    $group = authoringGroup();
    $create = $payload;
    unset($create['ends_on']);

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->post(route('schedules.store', $group), $create)
        ->assertSessionHasErrors('ends_on');

    expect(Schedule::count())->toBe(0);
});

it('rejects an end date before the start date', function () use ($payload) {
    $group = authoringGroup();

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->post(route('schedules.store', $group), [...$payload, 'ends_on' => '2026-07-01'])
        ->assertSessionHasErrors('ends_on');

    expect(Schedule::count())->toBe(0);
});

// --- Creating (store) — deny rows -------------------------------------------

it('forbids an ordinary member from creating a Schedule', function () use ($payload) {
    $group = authoringGroup();

    $this->actingAs(scheduleMemberOf($group))
        ->post(route('schedules.store', $group), $payload)
        ->assertForbidden();

    expect(Schedule::count())->toBe(0);
});

it('forbids a Scheduler of one Group from creating on another', function () use ($payload) {
    $scheduler = scheduleOfficerOf(authoringGroup(), Role::Scheduler);
    $other = authoringGroup();

    $this->actingAs($scheduler)
        ->post(route('schedules.store', $other), $payload)
        ->assertForbidden();

    expect(Schedule::count())->toBe(0);
});

it('forbids a Chair of a Group that does not run scheduling', function () use ($payload) {
    // Chair-implication would otherwise grant schedule powers — but the capability is
    // off, so there is nothing to schedule.
    $group = Group::factory()->create(['has_scheduling' => false]);

    $this->actingAs(scheduleOfficerOf($group, Role::Chair))
        ->post(route('schedules.store', $group), $payload)
        ->assertForbidden();

    expect(Schedule::count())->toBe(0);
});

it('redirects an unauthenticated create to login', function () use ($payload) {
    $this->post(route('schedules.store', authoringGroup()), $payload)
        ->assertRedirect(route('login'));
});

// --- Editing (update) -------------------------------------------------------

it('lets a Scheduler edit a Schedule on their Group', function () {
    $group = authoringGroup();
    $schedule = Schedule::factory()->create(['group_id' => $group->id, 'name' => 'Old']);

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['name' => 'New', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($schedule->fresh()->name)->toBe('New');
});

it('forbids a Scheduler of another Group from editing a Schedule', function () {
    $schedule = Schedule::factory()->create(['group_id' => authoringGroup()->id, 'name' => 'Old']);

    $this->actingAs(scheduleOfficerOf(authoringGroup(), Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['name' => 'New'])
        ->assertForbidden();

    expect($schedule->fresh()->name)->toBe('Old');
});

it('forbids an ordinary member from editing a Schedule', function () {
    $group = authoringGroup();
    $schedule = Schedule::factory()->create(['group_id' => $group->id, 'name' => 'Old']);

    $this->actingAs(scheduleMemberOf($group))
        ->patch(route('schedules.update', $schedule), ['name' => 'New'])
        ->assertForbidden();

    expect($schedule->fresh()->name)->toBe('Old');
});

it('redirects an unauthenticated edit to login', function () {
    $schedule = Schedule::factory()->create();

    $this->patch(route('schedules.update', $schedule), ['name' => 'New'])
        ->assertRedirect(route('login'));
});

// --- Publish / un-publish (state transitions on the edit path) --------------

it('lets a Scheduler publish a draft Schedule', function () {
    $group = authoringGroup();
    $schedule = Schedule::factory()->draft()->create(['group_id' => $group->id]);

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['state' => ScheduleState::Published->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($schedule->fresh()->state)->toBe(ScheduleState::Published);
});

it('lets a Scheduler un-publish a Schedule at zero Sign-ups', function () {
    $group = authoringGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['state' => ScheduleState::Draft->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($schedule->fresh()->state)->toBe(ScheduleState::Draft);
});

it('forbids a Scheduler of another Group from publishing a Schedule', function () {
    $schedule = Schedule::factory()->draft()->create(['group_id' => authoringGroup()->id]);

    $this->actingAs(scheduleOfficerOf(authoringGroup(), Role::Scheduler))
        ->patch(route('schedules.update', $schedule), ['state' => ScheduleState::Published->value])
        ->assertForbidden();

    expect($schedule->fresh()->state)->toBe(ScheduleState::Draft);
});

// --- Deleting (destroy) -----------------------------------------------------

it('lets a Scheduler delete a Schedule on their Group', function () {
    $group = authoringGroup();
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->delete(route('schedules.destroy', $schedule))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Schedule::count())->toBe(0);
});

it('lets a super-tier member delete any Schedule', function () {
    $schedule = Schedule::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->delete(route('schedules.destroy', $schedule))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Schedule::count())->toBe(0);
});

it('forbids a Scheduler of another Group from deleting a Schedule', function () {
    $schedule = Schedule::factory()->create(['group_id' => authoringGroup()->id]);

    $this->actingAs(scheduleOfficerOf(authoringGroup(), Role::Scheduler))
        ->delete(route('schedules.destroy', $schedule))
        ->assertForbidden();

    expect(Schedule::count())->toBe(1);
});

it('forbids an ordinary member from deleting a Schedule', function () {
    $group = authoringGroup();
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);

    $this->actingAs(scheduleMemberOf($group))
        ->delete(route('schedules.destroy', $schedule))
        ->assertForbidden();

    expect(Schedule::count())->toBe(1);
});

it('redirects an unauthenticated delete to login', function () {
    $schedule = Schedule::factory()->create();

    $this->delete(route('schedules.destroy', $schedule))->assertRedirect(route('login'));
});

// --- `can` UI hints ---------------------------------------------------------

it('hints schedule authoring on for a Scheduler and off for an ordinary member', function () {
    // The section lists, so the per-Schedule hints ride on the list row.
    $group = authoringGroup();
    Schedule::factory()->published()->create(['group_id' => $group->id]);
    $section = ['group' => $group, 'section' => 'scheduling'];

    $this->actingAs(scheduleOfficerOf($group, Role::Scheduler))
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createSchedule', true)
            ->where('scheduling.schedules.0.can.update', true)
            ->where('scheduling.schedules.0.can.unpublish', true)
            ->where('scheduling.schedules.0.can.delete', true));

    $this->actingAs(scheduleMemberOf($group))
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createSchedule', false)
            ->where('scheduling.schedules.0.can.update', false)
            ->where('scheduling.schedules.0.can.delete', false));
});
