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
 * The Post-shift report section on the shift card (#652, PRD #651, ADR-0023 §5 amendment).
 * What each viewer receives decides what the section shows, so these tests pin the page data:
 *
 * - a seat-holder and every co-volunteer on the same Shift receive each seat's numbers, so a
 *   double count is visible to the people who made it;
 * - a schedule admin receives them on every seat, as before (#450);
 * - a reader with no seat and no admin role receives no seat numbers, as before;
 * - a Group that collects nothing sends no numbers and no report at all.
 *
 * `can.readReport` is the server's verdict that the section renders; `can.record` is the verdict
 * that the viewer's own seat is inside its sign-out window, so the form may open on it.
 */
function postShiftNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-09-10 14:00');
}

function postShiftGroup(): Group
{
    return Group::factory()->program()->publicListing()->collectsVisitorCount()->create();
}

function postShiftMemberOf(Group $group, ?Role $role = null): Member
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

/** A Shift ending at 13:00 org time on 2026-09-10 — past, at the pinned clock. */
function postShiftShift(Schedule $schedule, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => CarbonImmutable::parse('2026-09-10 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-10 13:00'),
        'capacity' => 3,
        ...$overrides,
    ]);
}

function postShiftSeat(Shift $shift, Member $member, ?int $count = null): SignUp
{
    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id, 'visitor_count' => $count]);
}

/** The seat payload for one Member, from the Shift's `signups` list. */
function postShiftSeatOf(iterable $seats, Member $member): array
{
    return collect($seats)->map(fn ($seat) => (array) $seat)->firstWhere('id', $member->id);
}

function viewPostShiftReport(Member $viewer, Group $group, Schedule $schedule)
{
    return test()->actingAs($viewer)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]));
}

it('sends the seat-holder every seat’s numbers on their Shift', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $me, 12);
    postShiftSeat($shift, $peer, 9);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', true)
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($me, $peer) {
            return postShiftSeatOf($seats, $me)['visitor_count'] === 12
                && postShiftSeatOf($seats, $peer)['visitor_count'] === 9;
        }));
});

it('sends a co-volunteer null where a seat has no count yet', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $me, 12);
    postShiftSeat($shift, $peer);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($peer) {
            $seat = postShiftSeatOf($seats, $peer);

            return array_key_exists('visitor_count', $seat) && $seat['visitor_count'] === null;
        }));
});

it('keeps the officer-only seat fields from a co-volunteer', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $me, 12);
    postShiftSeat($shift, $peer, 9);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($peer) {
            $seat = postShiftSeatOf($seats, $peer);

            return ! array_key_exists('signup_id', $seat) && ! array_key_exists('can_record', $seat);
        }));
});

it('sends a schedule admin every seat’s numbers without a seat of their own', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $peer, 9);

    viewPostShiftReport(postShiftMemberOf($group, Role::Scheduler), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', true)
        ->where('scheduling.open.shifts.0.signups.0.visitor_count', 9));
});

it('sends a plain reader with no seat no seat numbers', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    postShiftSeat($shift, postShiftMemberOf($group), 9);

    viewPostShiftReport(postShiftMemberOf($group), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', false)
        ->missing('scheduling.open.shifts.0.signups.0.visitor_count')
        ->missing('scheduling.open.shifts.0.signups.0.extra_interaction_count')
        ->missing('scheduling.open.shifts.0.signups.0.visitors_toronto'));
});

it('sends no report and no seat numbers where the Group collects nothing', function () {
    $this->travelTo(postShiftNow());

    $group = Group::factory()->program()->publicListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    postShiftSeat($shift, $me);
    postShiftSeat($shift, postShiftMemberOf($group));

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', false)
        ->missing('scheduling.open.shifts.0.signups.0.visitor_count')
        ->missing('scheduling.open.shifts.0.signups.1.visitor_count'));
});

it('sends co-volunteer numbers in the My sign-ups panel too', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $me);
    postShiftSeat($shift, $peer, 9);

    $this->actingAs($me)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine.0.can.readReport', true)
            ->where('scheduling.mine.0.signups', fn ($seats) => postShiftSeatOf($seats, $peer)['visitor_count'] === 9));
});

it('opens the own-seat form only inside the sign-out window, for a schedule admin too', function () {
    // 12:00 is an hour before the 13:00 end: the seat-holder's window is still shut. A schedule
    // admin may write at any time (the correction path), but their own seat's form still waits.
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00'));

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $scheduler = postShiftMemberOf($group, Role::Scheduler);
    postShiftSeat($shift, $scheduler);

    viewPostShiftReport($scheduler, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.record', false));

    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:56'));

    viewPostShiftReport($scheduler, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.record', true));
});
