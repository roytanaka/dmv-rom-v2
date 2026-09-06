<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Officer's correction (#450, PRD #443, ADR-0023 §5) — a Group Officer sets or corrects the
 * visitor numbers on *any* seat, so a volunteer who left without filing is not a permanent hole
 * in the Group's total. One write seam (the same PATCH #445 built), one policy verdict extended:
 * the seat-holder's own write is time-bound, the Officer's correction is not.
 *
 * The security point is the reason this ticket exists: legacy's `_remoteSignIn` takes a row id
 * from the request and updates it with no ownership check, so any logged-in Member can set any
 * other Member's count. Here the affordance (the pencil) is a hint; the policy is the rule, and
 * an ordinary Member's write against a peer's seat is refused whether or not they saw a control.
 * Prior art: SignUpVisitorCountTest (the seat-holder's own write), OfficerAssignmentTest (the
 * schedule-admin gate and Chair-implication).
 *
 * The Officer's verdict is the schedule-admin gate, so times need not sit inside any window; the
 * clock is pinned only so the seat-holder-window cases below are unambiguous.
 */
function officerNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-10 14:00');
}

/** A Group that runs scheduling, is listed org-wide, and collects a per-shift visitor count. */
function officerGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create();
}

/** A GDR-shaped Group — collects the visitor count and the five-origin provenance split. */
function officerGdrGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorProvenance()->create();
}

/** A Member of the given Group in good standing, optionally carrying a role. */
function officerMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => MembershipStatus::Full,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** The Group's Scheduler — the Officer who corrects any seat. */
function correctingOfficerOf(Group $group): Member
{
    return officerMemberOf($group, Role::Scheduler);
}

/** A Shift on the Schedule, 10:00–13:00 org time on 2026-09-10 by default. */
function officerShift(Schedule $schedule, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-10 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-10 13:00'),
        ...$overrides,
    ]);
}

/** Seat the Member on the Shift, returning their Sign-up. */
function officerSeatOn(Shift $shift, Member $member): SignUp
{
    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
}

// --- The Officer sets or corrects any seat, with no deadline ---------------------

it('lets an Officer set a number on another Member’s seat', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    $this->actingAs($officer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(14);
});

it('lets an Officer correct a number a seat already carries', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));
    $signUp->update(['visitor_count' => 3]);

    $this->actingAs($officer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 9])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(9);
});

it('lets an Officer correct a seat months after the Shift, with no deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-10 09:00'));

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    $this->actingAs($officer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 6])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(6);
});

it('lets an Officer correct a seat before the five-minute window the seat-holder is held to', function () {
    // The Shift ends at 13:00; the seat-holder's own window opens at 12:55. Sit well before it.
    $this->travelTo(CarbonImmutable::parse('2026-09-10 08:00'));

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    $this->actingAs($officer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 2])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(2);
});

it('lets a Chair correct a seat — the schedule-admin gate folds in Chair-implication', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $chair = officerMemberOf($group, Role::Chair);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    $this->actingAs($chair)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 11])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(11);
});

// --- The affordance is a hint; the policy is the rule ---------------------------

it('refuses an ordinary Member’s correction of a peer’s seat', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule, ['capacity' => 2]);
    $owner = officerMemberOf($group);
    $peer = officerMemberOf($group); // an ordinary Member — no Scheduler role
    $signUp = officerSeatOn($shift, $owner);

    $this->actingAs($peer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 9])
        ->assertForbidden();

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('refuses a Scheduler of another Group correcting a seat on this Group’s Shift', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    $outsider = correctingOfficerOf(officerGroup()); // a Scheduler, but of a different Group

    $this->actingAs($outsider)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 9])
        ->assertForbidden();

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

// --- The correction offers the same boxes, under the same validation ------------

it('holds GDR’s five-to-count sum rule on an Officer’s correction', function () {
    $this->travelTo(officerNow());

    $group = officerGdrGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    // The five origins add up to 9, not the 10 the officer typed — the server refuses the write.
    $this->actingAs($officer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 10,
            'visitors_france_europe' => 2,
            'visitors_quebec' => 2,
            'visitors_toronto' => 2,
            'visitors_rest_of_canada' => 2,
            'visitors_other_countries' => 1,
        ])
        ->assertSessionHasErrors('visitors_france_europe');

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('records an Officer’s GDR correction whose five origins sum to the count', function () {
    $this->travelTo(officerNow());

    $group = officerGdrGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));

    $this->actingAs($officer)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 10,
            'visitors_france_europe' => 2,
            'visitors_quebec' => 2,
            'visitors_toronto' => 2,
            'visitors_rest_of_canada' => 2,
            'visitors_other_countries' => 2,
        ])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(10)
        ->and($signUp->fresh()->visitors_toronto)->toBe(2);
});

// --- The read surface: a pencil on every seat, for the Officer alone ------------

it('carries a per-seat correction verdict and the seat’s numbers to an Officer', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $officer = correctingOfficerOf($group);
    $signUp = officerSeatOn($shift, officerMemberOf($group));
    $signUp->update(['visitor_count' => 15]);

    $this->actingAs($officer)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.signups.0.signup_id', $signUp->id)
            ->where('scheduling.open.shifts.0.signups.0.can_record', true)
            ->where('scheduling.open.shifts.0.signups.0.visitor_count', 15));
});

it('withholds the correction verdict and the seat’s numbers from an ordinary Member', function () {
    $this->travelTo(officerNow());

    $group = officerGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = officerShift($schedule);
    $seated = officerMemberOf($group);
    $signUp = officerSeatOn($shift, $seated);
    $signUp->update(['visitor_count' => 15]);

    // A different ordinary Member on the same Group holds no seat and no Scheduler role.
    $this->actingAs(officerMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('scheduling.open.shifts.0.signups.0.can_record')
            ->missing('scheduling.open.shifts.0.signups.0.signup_id')
            ->missing('scheduling.open.shifts.0.signups.0.visitor_count'));
});
