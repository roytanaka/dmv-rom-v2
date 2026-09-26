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

            return ! array_key_exists('signup_id', $seat) && $seat['can_record'] === false;
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

// --- Who may change an entry (#653): the per-seat `can_record` verdict ----------------------

it('lets a schedule admin change every entry, with no count and before the window', function () {
    // 09:00 is before the Shift starts: a seat-holder's window is shut, an admin's never is.
    $this->travelTo(CarbonImmutable::parse('2026-09-10 09:00'));

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $recorded = postShiftMemberOf($group);
    $unrecorded = postShiftMemberOf($group);
    postShiftSeat($shift, $recorded, 9);
    postShiftSeat($shift, $unrecorded);

    viewPostShiftReport(postShiftMemberOf($group, Role::Scheduler), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($recorded, $unrecorded) {
            return postShiftSeatOf($seats, $recorded)['can_record'] === true
                && postShiftSeatOf($seats, $unrecorded)['can_record'] === true;
        }));
});

it('lets a volunteer change their own entry only, inside the window', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $me, 12);
    postShiftSeat($shift, $peer);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($me, $peer) {
            return postShiftSeatOf($seats, $me)['can_record'] === true
                && postShiftSeatOf($seats, $peer)['can_record'] === false;
        }));
});

it('holds a volunteer’s own entry shut before the window opens', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00'));

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    postShiftSeat(postShiftShift($schedule), $me);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.can_record', false));
});

it('sends no change verdict to a plain reader, or where the Group collects nothing', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    postShiftSeat(postShiftShift($schedule), postShiftMemberOf($group), 9);

    viewPostShiftReport(postShiftMemberOf($group), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->missing('scheduling.open.shifts.0.signups.0.can_record'));

    $silent = Group::factory()->program()->publicListing()->create();
    $silentSchedule = Schedule::factory()->published()->create(['group_id' => $silent->id]);
    postShiftSeat(postShiftShift($silentSchedule), postShiftMemberOf($silent));

    viewPostShiftReport(postShiftMemberOf($silent, Role::Scheduler), $silent, $silentSchedule)->assertInertia(fn (Assert $page) => $page
        ->missing('scheduling.open.shifts.0.signups.0.can_record'));
});

it('saves a schedule admin’s correction to another seat, and the report carries it', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $signUp = postShiftSeat(postShiftShift($schedule), postShiftMemberOf($group));
    $scheduler = postShiftMemberOf($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14])
        ->assertRedirect();

    viewPostShiftReport($scheduler, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.visitor_count', 14));
});

it('refuses a co-volunteer’s hand-made correction of another seat', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    postShiftSeat($shift, $me, 12);
    $peerSeat = postShiftSeat($shift, postShiftMemberOf($group));

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $peerSeat->id]), ['visitor_count' => 14])
        ->assertForbidden();

    expect($peerSeat->fresh()->visitor_count)->toBeNull();
});

/*
 * "Last edited by" (#654, PRD #651). Every save stamps the acting Member and the time on the
 * server, on the volunteer's own save and an officer's correction alike. The request body never
 * sets them. Every reader of an entry's numbers receives its stamp; an unstamped seat sends null.
 */
it('stamps a volunteer’s own save with the volunteer and the time', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12])
        ->assertRedirect();

    $signUp->refresh();
    expect($signUp->last_edited_by_id)->toBe($me->id)
        ->and($signUp->last_edited_at->equalTo(postShiftNow()))->toBeTrue();
});

it('stamps an officer’s correction with the officer, and the volunteer sees the officer’s name', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me, 12);
    $scheduler = postShiftMemberOf($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14])
        ->assertRedirect();

    expect($signUp->fresh()->last_edited_by_id)->toBe($scheduler->id);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.last_edited', [
            'name' => $scheduler->fullName(),
            'at' => postShiftNow()->toIso8601String(),
        ]));
});

it('ignores a who or when value in the request body', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);
    $other = postShiftMemberOf($group);

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), [
            'visitor_count' => 12,
            'last_edited_by_id' => $other->id,
            'last_edited_at' => '2020-01-01 00:00:00',
        ])
        ->assertRedirect();

    $signUp->refresh();
    expect($signUp->last_edited_by_id)->toBe($me->id)
        ->and($signUp->last_edited_at->equalTo(postShiftNow()))->toBeTrue();
});

it('sends a null stamp for a seat nobody has saved', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    postShiftSeat(postShiftShift($schedule), $me, 12);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.last_edited', null));
});

it('sends no stamp to a plain reader with no seat', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $signUp = postShiftSeat(postShiftShift($schedule), postShiftMemberOf($group), 12);
    $signUp->record(['visitor_count' => 12], postShiftMemberOf($group, Role::Scheduler));

    viewPostShiftReport(postShiftMemberOf($group), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->missing('scheduling.open.shifts.0.signups.0.last_edited'));
});
