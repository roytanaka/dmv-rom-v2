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

/*
 * The comment on a post-shift entry (#655, PRD #651). Free text, optional, at most 2,000
 * characters, offered wherever the Group collects a count. Only the seat-holder writes it: an
 * officer's correction of another seat refuses a comment, so a correction never changes a
 * volunteer's words. Only the author and a schedule admin receive it in the page data.
 */
function postShiftCommentSeat(Shift $shift, Member $member, string $comment, int $count = 12): SignUp
{
    return SignUp::factory()->create([
        'shift_id' => $shift->id,
        'member_id' => $member->id,
        'visitor_count' => $count,
        'comment' => $comment,
    ]);
}

it('saves a volunteer’s comment with their count, and the comment comes back on the page', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12, 'comment' => 'A visitor asked about the whale.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->comment)->toBe('A visitor asked about the whale.');

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups.0.comment', 'A visitor asked about the whale.'));
});

it('lets a volunteer clear their comment', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftCommentSeat(postShiftShift($schedule), $me, 'Busy morning.');

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12, 'comment' => ''])
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->comment)->toBeNull();
});

it('refuses a comment with no count', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['comment' => 'Busy morning.'])
        ->assertSessionHasErrors('visitor_count');

    expect($signUp->fresh()->comment)->toBeNull();
});

it('refuses a comment over 2,000 characters and accepts one of exactly 2,000', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12, 'comment' => str_repeat('a', 2001)])
        ->assertSessionHasErrors(['comment' => trans('group.scheduling_panel.agenda.sign_out.comment_max')]);

    expect($signUp->fresh()->comment)->toBeNull();

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12, 'comment' => str_repeat('a', 2000)])
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->comment)->toHaveLength(2000);
});

it('refuses a comment where the Group collects no visitor count', function () {
    $this->travelTo(postShiftNow());

    $group = Group::factory()->program()->publicListing()->create();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['comment' => 'Busy morning.'])
        ->assertSessionHasErrors('comment');

    expect($signUp->fresh()->comment)->toBeNull();
});

it('refuses a comment on an officer’s correction of another seat', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $signUp = postShiftCommentSeat(postShiftShift($schedule), postShiftMemberOf($group), 'My own words.');

    $this->actingAs(postShiftMemberOf($group, Role::Scheduler))
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14, 'comment' => 'Rewritten.'])
        ->assertSessionHasErrors('comment');

    expect($signUp->fresh())
        ->visitor_count->toBe(12)
        ->comment->toBe('My own words.');
});

it('refuses an empty comment on an officer’s correction, so it cannot wipe the volunteer’s', function (?string $empty) {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $signUp = postShiftCommentSeat(postShiftShift($schedule), postShiftMemberOf($group), 'My own words.');

    $this->actingAs(postShiftMemberOf($group, Role::Scheduler))
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14, 'comment' => $empty])
        ->assertSessionHasErrors('comment');

    expect($signUp->fresh()->comment)->toBe('My own words.');
})->with(['null' => [null], 'empty string' => ['']]);

it('keeps the volunteer’s comment when an officer corrects the count', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $signUp = postShiftCommentSeat(postShiftShift($schedule), postShiftMemberOf($group), 'My own words.');

    $this->actingAs(postShiftMemberOf($group, Role::Scheduler))
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14])
        ->assertSessionHasNoErrors();

    expect($signUp->fresh())
        ->visitor_count->toBe(14)
        ->comment->toBe('My own words.');
});

it('lets a schedule admin comment on their own seat', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $scheduler = postShiftMemberOf($group, Role::Scheduler);
    $signUp = postShiftSeat(postShiftShift($schedule), $scheduler);

    $this->actingAs($scheduler)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12, 'comment' => 'Quiet day.'])
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->comment)->toBe('Quiet day.');
});

it('sends the author their own comment and keeps it from a co-volunteer', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftCommentSeat($shift, $me, 'Mine.');
    postShiftCommentSeat($shift, $peer, 'Theirs.', 9);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($me, $peer) {
            $peerSeat = postShiftSeatOf($seats, $peer);

            return postShiftSeatOf($seats, $me)['comment'] === 'Mine.'
                && $peerSeat['visitor_count'] === 9
                && ! array_key_exists('comment', $peerSeat);
        }));
});

it('sends a schedule admin every seat’s comment', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $first = postShiftMemberOf($group);
    $second = postShiftMemberOf($group);
    postShiftCommentSeat($shift, $first, 'First.');
    postShiftSeat($shift, $second, 9);

    viewPostShiftReport(postShiftMemberOf($group, Role::Scheduler), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.signups', function ($seats) use ($first, $second) {
            $secondSeat = postShiftSeatOf($seats, $second);

            return postShiftSeatOf($seats, $first)['comment'] === 'First.'
                && array_key_exists('comment', $secondSeat) && $secondSeat['comment'] === null;
        }));
});

it('sends a plain reader no comment', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    postShiftCommentSeat(postShiftShift($schedule), postShiftMemberOf($group), 'Private.');

    viewPostShiftReport(postShiftMemberOf($group), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->missing('scheduling.open.shifts.0.signups.0.comment'));
});

it('sends only the viewer’s own comment in the My sign-ups panel', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $me = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    postShiftSeat($shift, $me);
    postShiftCommentSeat($shift, $peer, 'Theirs.', 9);

    $this->actingAs($me)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine.0.signups', function ($seats) use ($me, $peer) {
                return array_key_exists('comment', postShiftSeatOf($seats, $me))
                    && ! array_key_exists('comment', postShiftSeatOf($seats, $peer));
            }));
});

it('lets the author and a schedule admin read a comment, and no one else', function () {
    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $author = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    $signUp = postShiftCommentSeat($shift, $author, 'Mine.');
    postShiftSeat($shift, $peer);

    expect($author->can('viewComment', $signUp))->toBeTrue()
        ->and(postShiftMemberOf($group, Role::Scheduler)->can('viewComment', $signUp))->toBeTrue()
        ->and(postShiftMemberOf($group, Role::Chair)->can('viewComment', $signUp))->toBeTrue()
        ->and($peer->can('viewComment', $signUp))->toBeFalse()
        ->and(postShiftMemberOf($group)->can('viewComment', $signUp))->toBeFalse();
});

/*
 * Review and QA fixes (#668, PRD #651). The save route sits outside the localized route group, so
 * it takes the language from the page that sent it; the officer path names why a comment is
 * refused; the page data asks the policy who reads a comment; a first save keeps its seat in My
 * sign-ups for the next page load.
 */
it('answers a save from a French page in French', function (array $body, string $field, string $key) {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->from('/fr/tableau-de-bord')
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), $body)
        ->assertSessionHasErrors([$field => trans($key, [], 'fr')]);
})->with([
    'a decimal count' => [['visitor_count' => 1.5], 'visitor_count', 'group.scheduling_panel.agenda.sign_out.whole_number'],
    'a missing count' => [['comment' => 'Busy.'], 'visitor_count', 'group.scheduling_panel.agenda.sign_out.count_required'],
    'a comment too long' => [['visitor_count' => 3, 'comment' => str_repeat('a', 2001)], 'comment', 'group.scheduling_panel.agenda.sign_out.comment_max'],
]);

it('still answers a save from an English page in English', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $signUp = postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->from('/dashboard')
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 1.5])
        ->assertSessionHasErrors(['visitor_count' => trans('group.scheduling_panel.agenda.sign_out.whole_number', [], 'en')]);
});

it('names why a comment is refused on an officer’s correction, in English and French', function (string $from, string $locale) {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $signUp = postShiftSeat(postShiftShift($schedule), postShiftMemberOf($group), 12);

    $this->actingAs(postShiftMemberOf($group, Role::Scheduler))
        ->from($from)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 14, 'comment' => 'Rewritten.'])
        ->assertSessionHasErrors(['comment' => trans('group.scheduling_panel.agenda.sign_out.comment_officer', [], $locale)]);
})->with([
    'English' => ['/dashboard', 'en'],
    'French' => ['/fr/tableau-de-bord', 'fr'],
]);

it('sends a comment exactly where the comment policy lets the viewer read it', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftShift($schedule);
    $author = postShiftMemberOf($group);
    $peer = postShiftMemberOf($group);
    $signUp = postShiftCommentSeat($shift, $author, 'Mine.');
    postShiftSeat($shift, $peer, 9);

    $readers = [
        'author' => $author,
        'co-volunteer' => $peer,
        'schedule admin' => postShiftMemberOf($group, Role::Scheduler),
        'plain reader' => postShiftMemberOf($group),
        'super-tier' => Member::factory()->superTier()->create(),
    ];

    expect(array_map(fn (Member $viewer) => $viewer->can('viewComment', $signUp), $readers))->toBe([
        'author' => true,
        'co-volunteer' => false,
        'schedule admin' => true,
        'plain reader' => false,
        'super-tier' => true,
    ]);

    foreach ($readers as $viewer) {
        $allowed = $viewer->can('viewComment', $signUp);

        viewPostShiftReport($viewer, $group, $schedule)->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.signups', function ($seats) use ($author, $allowed) {
                $seat = postShiftSeatOf($seats, $author);

                return $allowed
                    ? ($seat['comment'] ?? null) === 'Mine.'
                    : ! array_key_exists('comment', $seat);
            }));
    }
});

it('hides the comment when a Sign-up is serialized', function () {
    $signUp = SignUp::factory()->create(['comment' => 'Private.']);

    expect($signUp->toArray())->not->toHaveKey('comment')
        ->and($signUp->toJson())->not->toContain('Private.');
});

it('keeps a first save’s seat in My sign-ups for the next page load only', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $shift = postShiftShift($schedule);
    $signUp = postShiftSeat($shift, $me);
    $url = route('groups.show', ['group' => $group, 'section' => 'scheduling']);

    $this->actingAs($me)
        ->from($url)
        ->patch(route('sign-ups.record', ['signUp' => $signUp->id]), ['visitor_count' => 12])
        ->assertSessionHasNoErrors();

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.mine.0.id', $shift->id)
        ->where('scheduling.mine.0.signups.0.visitor_count', 12));

    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.mine', []));
});

it('keeps both seats in My sign-ups after two saves in a row', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    $first = postShiftSeat(postShiftShift($schedule), $me);
    $second = postShiftSeat(postShiftShift($schedule, [
        'starts_at' => CarbonImmutable::parse('2026-09-09 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-09 13:00'),
    ]), $me);
    $url = route('groups.show', ['group' => $group, 'section' => 'scheduling']);

    $this->actingAs($me)->from($url)->patch(route('sign-ups.record', ['signUp' => $first->id]), ['visitor_count' => 12]);
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', fn ($mine) => count($mine) === 2));

    $this->from($url)->patch(route('sign-ups.record', ['signUp' => $second->id]), ['visitor_count' => 7]);
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', fn ($mine) => count($mine) === 2));

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('scheduling.mine', []));
});

// --- No report on a Shift dated after today (#772) -----------------------------------------
//
// Nobody records a count before the sign-out window, so a Shift on a later date shows no
// section. "Today" is the org wall clock's date, not the server's UTC date.

/** A Shift on 2026-09-11, the day after the pinned clock. */
function postShiftTomorrowShift(Schedule $schedule): Shift
{
    return postShiftShift($schedule, [
        'starts_at' => CarbonImmutable::parse('2026-09-11 10:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-11 13:00'),
    ]);
}

it('sends no report on a Shift dated after today, in My sign-ups and on the Agenda', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = postShiftTomorrowShift($schedule);
    $me = postShiftMemberOf($group);
    postShiftSeat($shift, $me);

    $this->actingAs($me)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine.0.can.readReport', false)
            ->missing('scheduling.mine.0.signups.0.visitor_count'));

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', false)
        ->missing('scheduling.open.shifts.0.signups.0.visitor_count'));
});

it('sends a schedule admin no report on a Shift dated after today', function () {
    $this->travelTo(postShiftNow());

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    postShiftSeat(postShiftTomorrowShift($schedule), postShiftMemberOf($group));

    viewPostShiftReport(postShiftMemberOf($group, Role::Scheduler), $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', false)
        ->missing('scheduling.open.shifts.0.signups.0.can_record'));
});

it('sends the report on a Shift dated today, before and after the window opens', function () {
    // The Shift runs 10:00 to 13:00; at 08:00 the window is shut, at 12:56 it is open.
    $this->travelTo(CarbonImmutable::parse('2026-09-10 08:00'));

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    postShiftSeat(postShiftShift($schedule), $me);

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', true)
        ->where('scheduling.open.shifts.0.can.record', false));

    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:56'));

    viewPostShiftReport($me, $group, $schedule)->assertInertia(fn (Assert $page) => $page
        ->where('scheduling.open.shifts.0.can.readReport', true)
        ->where('scheduling.open.shifts.0.can.record', true));
});

it('sends the report and the form on a past Shift still owed a count in My sign-ups', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-12 14:00'));

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    postShiftSeat(postShiftShift($schedule), $me);

    $this->actingAs($me)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine.0.can.readReport', true)
            ->where('scheduling.mine.0.can.record', true));
});

it('reads today on the org wall clock, not the server’s UTC date', function (string $today, string $tomorrow) {
    // 23:00 in Toronto is already the next day in UTC. A Shift at 23:30 tonight is today; one at
    // 00:30 tomorrow is not, though both share the UTC date of the clock. Summer and winter, so a
    // fixed offset cannot pass.
    $zone = 'America/Toronto';
    $this->travelTo(CarbonImmutable::parse("{$today} 23:00", $zone));

    $group = postShiftGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $me = postShiftMemberOf($group);
    postShiftSeat(postShiftShift($schedule, [
        'starts_at' => CarbonImmutable::parse("{$today} 23:30", $zone)->utc(),
        'ends_at' => CarbonImmutable::parse("{$today} 23:50", $zone)->utc(),
    ]), $me);
    postShiftSeat(postShiftShift($schedule, [
        'starts_at' => CarbonImmutable::parse("{$tomorrow} 00:30", $zone)->utc(),
        'ends_at' => CarbonImmutable::parse("{$tomorrow} 01:30", $zone)->utc(),
    ]), $me);

    $this->actingAs($me)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine.0.can.readReport', true)
            ->where('scheduling.mine.1.can.readReport', false));
})->with([
    'summer' => ['2026-09-10', '2026-09-11'],
    'winter' => ['2027-01-14', '2027-01-15'],
]);
