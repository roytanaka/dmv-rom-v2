<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\MembershipStatus;
use App\Enums\ScheduleState;
use App\Models\Delivery;
use App\Models\EmptyDeskRun;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use Illuminate\Support\Carbon;

/*
 * The empty-desk alert, from the daily pass (#487, spec #479, ADR-0024 §7). On days of the month
 * divisible by 3, for each Group with the alert on and no run row for today, the pass collects
 * watched Shifts on published Schedules from today through today plus days-ahead that have zero
 * Sign-ups. It writes a run row with the count even on a silent day, and — if any Shift is open —
 * one Delivery per roster Member in present standing without the no-email flag. Prior art:
 * ReminderPassTest for the daily-pass shape and the Delivery grain.
 */

/** Pin "now" to a fixed run day (the 9th — divisible by 3) at org-noon, so the cadence fires. */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-09 16:00:00', 'UTC')); // noon in America/Toronto
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A scheduling Group with the empty-desk alert on and the given look-ahead. */
function alertGroup(int $daysAhead = 3): Group
{
    return Group::factory()->program()->create([
        'empty_desk_alert_enabled' => true,
        'empty_desk_days_ahead' => $daysAhead,
    ]);
}

/** A watched ShiftKind on $group — the alert only names Shifts of a watched kind. */
function watchedKind(Group $group): ShiftKind
{
    return ShiftKind::factory()->create(['group_id' => $group->id, 'alert_when_empty' => true]);
}

/** A Shift on a Schedule in the given state, of the given kind, starting at $startsAt. */
function alertShift(Group $group, ?ShiftKind $kind, DateTimeInterface $startsAt, ScheduleState $state = ScheduleState::Published, int $capacity = 2): Shift
{
    $schedule = Schedule::factory()->create(['group_id' => $group->id, 'state' => $state]);

    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $kind?->id,
        'starts_at' => $startsAt,
        'ends_at' => (clone $startsAt)->modify('+3 hours'),
        'capacity' => $capacity,
    ]);
}

/** Put $member on $group's roster in the given within-Group standing. */
function rosterMemberOf(Group $group, Member $member, MembershipStatus $status = MembershipStatus::Full): void
{
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id, 'status' => $status]);
}

it('writes a run row and one Delivery per present-standing recipient for an unstaffed watched Shift', function () {
    $group = alertGroup();
    $kind = watchedKind($group);
    alertShift($group, $kind, now()->addDays(2)->setTime(10, 0));

    $member = Member::factory()->create();
    rosterMemberOf($group, $member);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    $run = EmptyDeskRun::sole();
    expect($run->group_id)->toBe($group->id);
    expect($run->open_shift_count)->toBe(1);

    $row = Delivery::where('kind', DeliveryKind::EmptyDesk)->sole();
    expect($row->member_id)->toBe($member->id);
    expect($row->email)->toBe($member->email);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->next_attempt_at)->not->toBeNull();
});

it('does nothing on a day of the month not divisible by 3', function () {
    // The 10th — not a cadence day. Nothing runs: no run row, no Deliveries.
    Carbon::setTestNow(Carbon::parse('2026-09-10 16:00:00', 'UTC'));

    $group = alertGroup();
    $kind = watchedKind($group);
    alertShift($group, $kind, now()->addDays(2)->setTime(10, 0));
    rosterMemberOf($group, Member::factory()->create());

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(EmptyDeskRun::count())->toBe(0);
    expect(Delivery::where('kind', DeliveryKind::EmptyDesk)->count())->toBe(0);
});

it('counts a Shift with zero Sign-ups but not one merely below capacity', function () {
    $group = alertGroup();
    $kind = watchedKind($group);
    rosterMemberOf($group, Member::factory()->create());

    // One seat taken, capacity 2 — below capacity, still not empty, so not listed.
    $partial = alertShift($group, $kind, now()->addDays(1)->setTime(9, 0), capacity: 2);
    SignUp::factory()->create(['shift_id' => $partial->id, 'member_id' => Member::factory()->create()->id]);

    // Nobody signed up — the only vacancy.
    alertShift($group, $kind, now()->addDays(1)->setTime(13, 0), capacity: 2);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(EmptyDeskRun::sole()->open_shift_count)->toBe(1);
    expect(Delivery::where('kind', DeliveryKind::EmptyDesk)->count())->toBe(1);
});

it('writes a run row on a silent day and nothing on a second run the same day', function () {
    $group = alertGroup();
    watchedKind($group); // a watched kind exists, but no open Shift — a silent day
    rosterMemberOf($group, Member::factory()->create());

    $this->artisan('mail:daily-pass')->assertSuccessful();
    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(EmptyDeskRun::sole()->open_shift_count)->toBe(0);
    expect(Delivery::where('kind', DeliveryKind::EmptyDesk)->count())->toBe(0);
});

it('ignores unwatched kinds, null-kind Shifts, draft Schedules, out-of-window Shifts, and the alert off', function () {
    $group = alertGroup(3);
    $watched = watchedKind($group);
    $unwatched = ShiftKind::factory()->create(['group_id' => $group->id, 'alert_when_empty' => false]);
    rosterMemberOf($group, Member::factory()->create());

    // Of an unwatched kind.
    alertShift($group, $unwatched, now()->addDay());
    // Kind-less — a Group without watched kinds cannot run the alert.
    alertShift($group, null, now()->addDay());
    // On a draft Schedule — not yet public.
    alertShift($group, $watched, now()->addDay(), ScheduleState::Draft);
    // Past the look-ahead window.
    alertShift($group, $watched, now()->addDays(10));

    // A whole other Group with the alert switched off and an open watched Shift.
    $off = Group::factory()->program()->create(['empty_desk_alert_enabled' => false]);
    alertShift($off, watchedKind($off), now()->addDay());

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(EmptyDeskRun::where('group_id', $group->id)->sole()->open_shift_count)->toBe(0);
    expect(Delivery::where('kind', DeliveryKind::EmptyDesk)->count())->toBe(0);
    // The alert-off Group is never even scanned, so it logs no run.
    expect(EmptyDeskRun::where('group_id', $off->id)->count())->toBe(0);
});

it('mails the roster in present standing only, skipping on-leave, departed, and flagged Members', function () {
    $group = alertGroup();
    $kind = watchedKind($group);
    alertShift($group, $kind, now()->addDay()->setTime(10, 0));

    $present = Member::factory()->create();
    rosterMemberOf($group, $present, MembershipStatus::Full);

    // On leave — cannot sign up, so no vacancy alert.
    rosterMemberOf($group, Member::factory()->create(), MembershipStatus::Loa);
    // Resigned — departed.
    rosterMemberOf($group, Member::factory()->create(), MembershipStatus::Resigned);

    // Present standing but carrying the no-email flag.
    $flagged = Member::factory()->create();
    $flagged->no_email = true;
    $flagged->save();
    rosterMemberOf($group, $flagged, MembershipStatus::Full);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    $rows = Delivery::where('kind', DeliveryKind::EmptyDesk)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->member_id)->toBe($present->id);
});
