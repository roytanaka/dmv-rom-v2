<?php

use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The second box for tour-leading Groups (#447, PRD #443, ADR-0023 §2). Beside the visitor
 * count, a tour-leading Group's Sign-up carries an `extra_interaction_count` — visitors served
 * outside the tour the volunteer led. It rides the same write seam (PATCH on a Sign-up, Form
 * Request → SignUpPolicy) and the same read prop as the visitor count. Prior art, and the
 * fixtures this file leans on: SignUpVisitorCountTest.
 *
 * A fixed clock so the five-minute sign-out window is exact; the Shift ends at 13:00 UTC and
 * `extraNow()` sits an hour past it, inside the window.
 */
function extraNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-10 14:00');
}

/** A tour-leading Group: it collects a visitor count and the extra-interaction split beside it. */
function tourLeadingGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsExtraInteractions()->create();
}

/** A Group that collects a visitor count but not the split — one box only. */
function countOnlyGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create();
}

/** A Member of the given Group in good standing. */
function extraMemberOf(Group $group): Member
{
    $member = Member::factory()->create();
    GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => MembershipStatus::Full,
    ]);

    return $member;
}

/** A Shift on the Schedule, 10:00–13:00 org time on 2026-09-10. */
function extraShift(Schedule $schedule): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-10 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-10 13:00'),
    ]);
}

/** Seat the Member on the Shift, returning their Sign-up. */
function extraSeatOn(Shift $shift, Member $member): SignUp
{
    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
}

/** Set up a tour-leading Group with one seated Member, returning [member, signUp]. */
function seatedOnTour(): array
{
    $group = tourLeadingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = extraShift($schedule);
    $member = extraMemberOf($group);

    return [$member, extraSeatOn($shift, $member)];
}

it('records an extra-interaction count in its own column beside the visitor count', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 12,
            'extra_interaction_count' => 4,
        ])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(12)
        ->and($signUp->fresh()->extra_interaction_count)->toBe(4);
});

it('does not block the Sign Out on a blank extra box — the count alone is accepted', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 9])
        ->assertRedirect();

    expect($signUp->fresh()->visitor_count)->toBe(9)
        ->and($signUp->fresh()->extra_interaction_count)->toBeNull();
});

it('stores a recorded extra zero as zero, distinct from an unrecorded null', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'extra_interaction_count' => 0,
        ])
        ->assertRedirect();

    expect($signUp->fresh()->extra_interaction_count)->toBe(0)
        ->and($signUp->fresh()->extra_interaction_count)->not->toBeNull();
});

it('overwrites a previously recorded extra count on a re-file', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();
    $signUp->update(['visitor_count' => 9, 'extra_interaction_count' => 3]);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'extra_interaction_count' => 11,
        ])
        ->assertRedirect();

    expect($signUp->fresh()->extra_interaction_count)->toBe(11);
});

it('clears a previously recorded extra count back to null on a re-file', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();
    $signUp->update(['visitor_count' => 9, 'extra_interaction_count' => 3]);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'extra_interaction_count' => null,
        ])
        ->assertRedirect();

    expect($signUp->fresh()->extra_interaction_count)->toBeNull();
});

it('refuses a decimal extra count with a whole-number message', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'extra_interaction_count' => 2.5,
        ])
        ->assertSessionHasErrors(['extra_interaction_count' => trans('group.scheduling_panel.agenda.sign_out.extra_whole_number')]);

    expect($signUp->fresh()->extra_interaction_count)->toBeNull();
});

it('refuses a negative extra count', function () {
    $this->travelTo(extraNow());
    [$member, $signUp] = seatedOnTour();

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'extra_interaction_count' => -1,
        ])
        ->assertSessionHasErrors('extra_interaction_count');

    expect($signUp->fresh()->extra_interaction_count)->toBeNull();
});

// --- The split is its own switch, separate from the visitor count ----------------

it('refuses an extra value on a Group that collects the count but not the split', function () {
    $this->travelTo(extraNow());

    $group = countOnlyGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = extraShift($schedule);
    $member = extraMemberOf($group);
    $signUp = extraSeatOn($shift, $member);

    $this->actingAs($member)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 9,
            'extra_interaction_count' => 4,
        ])
        ->assertSessionHasErrors('extra_interaction_count');

    expect($signUp->fresh()->extra_interaction_count)->toBeNull()
        ->and($signUp->fresh()->visitor_count)->toBeNull();
});

// --- The read surface: the second box's props -----------------------------------

it('exposes the split switch as a Group capability', function () {
    $this->travelTo(extraNow());

    $group = tourLeadingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $member = extraMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.collectsExtraInteractions', true));
});

it('leaves the split switch off for a Group that collects the count but not the split', function () {
    $this->travelTo(extraNow());

    $group = countOnlyGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $member = extraMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.capabilities.collectsExtraInteractions', false));
});

it('carries the seat-holder’s own extra count on their Shift', function () {
    $this->travelTo(extraNow());

    $group = tourLeadingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = extraShift($schedule);
    $member = extraMemberOf($group);
    $signUp = extraSeatOn($shift, $member);
    $signUp->update(['visitor_count' => 15, 'extra_interaction_count' => 6]);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.extra_interaction_count', 6));
});

it('withholds the extra count from a reader holding no seat', function () {
    $this->travelTo(extraNow());

    $group = tourLeadingGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = extraShift($schedule);
    $seated = extraMemberOf($group);
    $signUp = extraSeatOn($shift, $seated);
    $signUp->update(['visitor_count' => 15, 'extra_interaction_count' => 6]);

    $this->actingAs(extraMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.extra_interaction_count', null));
});
