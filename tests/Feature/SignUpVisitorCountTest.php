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
 * Recording a visitor count on your own Sign-up after the shift (#445, PRD #443, ADR-0023 §2,
 * §5). One write seam — PATCH on a Sign-up (Form Request → SignUpPolicy) — and one read prop:
 * the seat-holder's own count and the `can.record` verdict on the Scheduling tab. Prior art:
 * GroupSignUpsTest, HoursRecalculationTest (time travel).
 *
 * A fixed clock so the five-minute sign-out window is exact. `ends_at` is a stored UTC instant
 * (the org wall clock is a display concern, not a comparison one), so every time here is UTC —
 * matching how the rest of the scheduling suite builds Shift times. The Shift below ends at
 * 13:00 UTC; `visitorNow()` sits an hour past its end, inside the window.
 */
function visitorNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-10 14:00');
}

/** A Group that runs scheduling, is listed org-wide, and collects a per-shift visitor count. */
function collectingGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create();
}

/** A Member of the given Group in good standing. */
function seatMemberOf(Group $group, ?Role $role = null): Member
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

/** A Shift on the Schedule, 10:00–13:00 org time on 2026-09-10 by default. */
function endedShift(Schedule $schedule, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-10 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-10 13:00'),
        ...$overrides,
    ]);
}

/** Seat the Member on the Shift, returning their Sign-up. */
function seatOn(Shift $shift, Member $member): SignUp
{
    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
}

it('records a visitor count on the seat-holder’s own Sign-up', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(12);
});

it('stores a recorded zero as zero, distinct from an unrecorded null', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 0])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(0)
        ->and($signUp->fresh()->visitor_count)->not->toBeNull();
});

it('overwrites a previously recorded number on a re-file', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);
    $signUp->update(['visitor_count' => 5]);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 20])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(20);
});

it('refuses a blank on the server, not only at the button', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [])
        ->assertSessionHasErrors('visitor_count');

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('refuses a decimal with a whole-number message', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 3.5])
        ->assertSessionHasErrors(['visitor_count' => trans('group.scheduling_panel.agenda.sign_out.whole_number')]);

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('refuses a negative count', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => -1])
        ->assertSessionHasErrors('visitor_count');

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

// --- Whose seat: the route binding, never the body -------------------------------

it('forbids a Member from writing another Member’s Sign-up', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $owner = seatMemberOf($group);
    $other = seatMemberOf($group);
    $signUp = seatOn($shift, $owner);

    $this->actingAs($other)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 9])
        ->assertForbidden();

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('writes the route-bound Sign-up regardless of a member id in the body', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule, ['capacity' => 2]);
    $member = seatMemberOf($group);
    $bystander = seatMemberOf($group);
    $signUp = seatOn($shift, $member);
    $otherSeat = seatOn($shift, $bystander);

    // The body names another Member (and their seat) — both are ignored; the route wins.
    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 7,
            'member_id' => $bystander->id,
            'sign_up_id' => $otherSeat->id,
        ])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(7)
        ->and($otherSeat->fresh()->visitor_count)->toBeNull();
});

// --- The five-minute window -----------------------------------------------------

it('refuses a write before the five-minute window opens', function () {
    // The Shift ends at 13:00 UTC; the window opens at 12:55. Sit at 12:00, before it opens.
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00'));

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 4])
        ->assertForbidden();

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

it('still accepts a write a month after the Shift ended', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00'));

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 6])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(6);
});

// --- The Group's collecting switch ----------------------------------------------

it('refuses the write on a Group that collects no visitor count', function () {
    $this->travelTo(visitorNow());

    $group = Group::factory()->program()->publicListing()->create(); // collects_visitor_count false
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 8])
        ->assertSessionHasErrors('visitor_count');

    expect($signUp->fresh()->visitor_count)->toBeNull();
});

// --- The read surface: the sign-out panel's props -------------------------------

it('exposes the collecting switch as a Group capability', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $member = seatMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.collectsVisitorCount', true));
});

it('carries the seat-holder’s own recorded count and a record verdict on their Shift', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    $signUp = seatOn($shift, $member);
    $signUp->update(['visitor_count' => 15]);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.visitor_count', 15)
            ->where('scheduling.open.shifts.0.can.record', true));
});

it('withholds the count and record verdict from a reader holding no seat', function () {
    $this->travelTo(visitorNow());

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $seated = seatMemberOf($group);
    $signUp = seatOn($shift, $seated);
    $signUp->update(['visitor_count' => 15]);

    // A different reader on the same Group holds no seat on this Shift.
    $this->actingAs(seatMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.visitor_count', null)
            ->where('scheduling.open.shifts.0.can.record', false));
});

it('closes the record verdict before the window opens', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00'));

    $group = collectingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = endedShift($schedule);
    $member = seatMemberOf($group);
    seatOn($shift, $member);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.can.record', false));
});

it('makes collecting a Group setting — two Groups running the same shift may differ', function () {
    $this->travelTo(visitorNow());

    $collecting = collectingGroup();
    $notCollecting = Group::factory()->program()->publicListing()->create();

    foreach ([$collecting, $notCollecting] as $group) {
        $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
        $shift = endedShift($schedule);
        $member = seatMemberOf($group);
        $signUp = seatOn($shift, $member);

        $response = $this->actingAs($member)
            ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 3]);

        if ($group->collects_visitor_count) {
            $response->assertRedirect();
            expect($signUp->fresh()->visitor_count)->toBe(3);
        } else {
            $response->assertSessionHasErrors('visitor_count');
            expect($signUp->fresh()->visitor_count)->toBeNull();
        }
    }
});
