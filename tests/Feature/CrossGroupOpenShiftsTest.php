<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Cross-Group open Shifts (#361, PRD #352, ADR-0021 §Sign-up). A Member reading one Group's
 * Schedule discovers the `open` Shifts *other* Groups advertise, and can take one — resolved
 * as a query over Shifts whose audience includes the viewer, never a relationship. Two seams:
 * the Inertia prop seam on the read (attribution and non-interleaving of the foreign set), and
 * the HTTP write seam (a non-member of the owning Group taking an `open` Shift, subject to
 * being able to see that Group's Schedule). Prior art: GroupSignUpsTest, OfficerAssignmentTest.
 */

/**
 * A Group that runs scheduling and is listed org-wide, so its published Schedules — and the
 * `open` Shifts on them — are readable and takeable by any Member.
 */
function crossGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

/**
 * A Shift inside this month's published-Schedule range; `open` by default so it advertises
 * across Groups. Override `audience` / times / capacity as needed.
 */
function crossShift(Schedule $schedule, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'audience' => 'open',
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
        ...$overrides,
    ]);
}

/** Open the owning Group's Schedule as the given Member, returning the Inertia assertion. */
function readSchedule(Member $viewer, Group $group, Schedule $schedule): Assert
{
    $page = null;

    test()->actingAs($viewer)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(function (Assert $inertia) use (&$page) {
            $page = $inertia;
        });

    return $page;
}

// --- The Inertia prop seam: attribution and non-interleaving -----------------

it('advertises another Group’s open Shift, attributed to its owning Group', function () {
    $home = crossGroup();
    $homeSchedule = Schedule::factory()->published()->create(['group_id' => $home->id]);

    $other = crossGroup();
    $other->update(['name' => 'Visitor Wayfinders']);
    $otherSchedule = Schedule::factory()->published()->create(['group_id' => $other->id]);
    $foreign = crossShift($otherSchedule);

    $viewer = Member::factory()->create();

    readSchedule($viewer, $home, $homeSchedule)
        ->has('scheduling.open.foreign', 1)
        ->where('scheduling.open.foreign.0.id', $foreign->id)
        ->where('scheduling.open.foreign.0.group_name', 'Visitor Wayfinders');
});

it('never mixes a foreign Shift into the owning Group’s own list', function () {
    $home = crossGroup();
    $homeSchedule = Schedule::factory()->published()->create(['group_id' => $home->id]);
    $ownOpen = crossShift($homeSchedule);

    $other = crossGroup();
    $otherSchedule = Schedule::factory()->published()->create(['group_id' => $other->id]);
    $foreign = crossShift($otherSchedule);

    $viewer = Member::factory()->create();

    readSchedule($viewer, $home, $homeSchedule)
        // The Group's own open Shift is its own — it never appears as foreign …
        ->has('scheduling.open.shifts', 1)
        ->where('scheduling.open.shifts.0.id', $ownOpen->id)
        // … and the foreign one is never in the own list.
        ->has('scheduling.open.foreign', 1)
        ->where('scheduling.open.foreign.0.id', $foreign->id);
});

it('carries no authoring affordances on a foreign Shift, for anyone', function () {
    $home = crossGroup();
    $homeSchedule = Schedule::factory()->published()->create(['group_id' => $home->id]);

    $other = crossGroup();
    $otherSchedule = Schedule::factory()->published()->create(['group_id' => $other->id]);
    $foreign = crossShift($otherSchedule);
    $seated = Member::factory()->create();
    SignUp::factory()->create(['shift_id' => $foreign->id, 'member_id' => $seated->id]);

    // Even a super-tier holder — who administers every Group — gets no assign affordance and
    // no seat-removal target on a foreign Shift: it is another Group's to author.
    $super = Member::factory()->superTier()->create();

    readSchedule($super, $home, $homeSchedule)
        ->where('scheduling.open.foreign.0.can.assign', false)
        // Nor the Shift authoring affordances (#356 front end) — a foreign Shift is never
        // editable or deletable from a reader's own Group page.
        ->where('scheduling.open.foreign.0.can.update', false)
        ->where('scheduling.open.foreign.0.can.delete', false)
        ->missing('scheduling.open.foreign.0.signups.0.signup_id');
});

it('excludes a group-audience Shift on another Group from the foreign set', function () {
    $home = crossGroup();
    $homeSchedule = Schedule::factory()->published()->create(['group_id' => $home->id]);

    $other = crossGroup();
    $otherSchedule = Schedule::factory()->published()->create(['group_id' => $other->id]);
    crossShift($otherSchedule, ['audience' => 'group']);

    $viewer = Member::factory()->create();

    readSchedule($viewer, $home, $homeSchedule)
        ->has('scheduling.open.foreign', 0);
});

it('hides a Private Group’s open Shift from a non-member’s foreign set', function () {
    $home = crossGroup();
    $homeSchedule = Schedule::factory()->published()->create(['group_id' => $home->id]);

    $private = Group::factory()->program()->privateListing()->create();
    $privateSchedule = Schedule::factory()->published()->create(['group_id' => $private->id]);
    crossShift($privateSchedule);

    // A non-member cannot see the Private Group, so its open Shift is as invisible as the
    // Group itself — the confidentiality boundary is not leaked through scheduling.
    readSchedule(Member::factory()->create(), $home, $homeSchedule)
        ->has('scheduling.open.foreign', 0);

    // A member of the Private Group does discover it.
    $insider = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $private->id, 'member_id' => $insider->id, 'status' => MembershipStatus::Full]);

    readSchedule($insider, $home, $homeSchedule)
        ->has('scheduling.open.foreign', 1);
});

it('omits foreign Shifts from a viewer barred by the DMV-wide floor', function () {
    $home = crossGroup();
    $homeSchedule = Schedule::factory()->published()->create(['group_id' => $home->id]);

    $other = crossGroup();
    $otherSchedule = Schedule::factory()->published()->create(['group_id' => $other->id]);
    crossShift($otherSchedule);

    // LOA reads the Schedule but cannot sign up anywhere — so nothing is "open to" them.
    $loa = Member::factory()->category(Category::Loa)->create();

    readSchedule($loa, $home, $homeSchedule)
        ->has('scheduling.open.foreign', 0);
});

// --- The HTTP write seam: a non-member taking an open Shift ------------------

it('lets a non-member of the owning Group take its open Shift', function () {
    $owner = crossGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $owner->id]);
    $shift = crossShift($schedule);

    // No membership in the owning Group at all — admitted by the read check and the DMV floor.
    $outsider = Member::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertRedirect();

    expect(SignUp::where(['shift_id' => $shift->id, 'member_id' => $outsider->id])->exists())->toBeTrue();
});

it('refuses a non-member taking a Private Group’s open Shift they cannot see', function () {
    $private = Group::factory()->program()->privateListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $private->id]);
    $shift = crossShift($schedule);

    $outsider = Member::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});
