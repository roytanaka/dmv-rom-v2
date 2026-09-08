<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\ScheduleState;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;

/*
 * The daily pass (#486, spec #479, ADR-0024 §7). At 06:00 org time it writes one Reminder
 * Delivery for every Sign-up whose Shift starts after now and before the end of today plus the
 * Group's lead days, on a published Schedule, in a Group with Reminders on, for a Member without
 * the no-email flag, where no Reminder Delivery exists yet for that (Shift, Member). The row is
 * keyed on the pair, so it is idempotent — a missed day catches up, a re-taken seat stays one
 * Reminder. Prior art: SignUpCancellationMailTest for the Delivery shape; GroupSchedulingTest
 * for the scheduling fixtures.
 */

/** A scheduling Group with Reminders on and the given lead days. */
function reminderGroup(int $leadDays = 3): Group
{
    return Group::factory()->program()->create([
        'reminders_enabled' => true,
        'reminder_lead_days' => $leadDays,
    ]);
}

/** A Sign-up by $member on a Shift starting at $startsAt, on a Schedule in the given state. */
function reminderSeatOn(Group $group, Member $member, DateTimeInterface $startsAt, ScheduleState $state = ScheduleState::Published): SignUp
{
    $schedule = Schedule::factory()->create(['group_id' => $group->id, 'state' => $state]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => $startsAt,
        'ends_at' => (clone $startsAt)->modify('+3 hours'),
    ]);

    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);
}

it('writes one pending Reminder for a Sign-up inside the window, keyed to the Shift and Member', function () {
    $group = reminderGroup();
    $member = Member::factory()->create();
    $seat = reminderSeatOn($group, $member, now()->addDays(2)->setTime(10, 0));

    $this->artisan('mail:daily-pass')->assertSuccessful();

    $row = Delivery::sole();
    expect($row->kind)->toBe(DeliveryKind::Reminder);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->member_id)->toBe($member->id);
    expect($row->email)->toBe($member->email);
    expect($row->shift_id)->toBe($seat->shift_id);
    expect($row->next_attempt_at)->not->toBeNull();
    // Expires at the Shift's start — a Reminder past due is worthless.
    expect($row->expires_at->equalTo($seat->shift->starts_at))->toBeTrue();
});

it('writes nothing for a Sign-up outside the window, a started Shift, a draft Schedule, Reminders off, or a flagged Member', function () {
    // Too far ahead — past end of today plus the 3 lead days.
    reminderSeatOn(reminderGroup(), Member::factory()->create(), now()->addDays(10));

    // Already started — before now.
    reminderSeatOn(reminderGroup(), Member::factory()->create(), now()->subHour());

    // On a draft Schedule — not yet public.
    reminderSeatOn(reminderGroup(), Member::factory()->create(), now()->addDays(2), ScheduleState::Draft);

    // In a Group with Reminders off.
    reminderSeatOn(Group::factory()->program()->create(['reminders_enabled' => false]), Member::factory()->create(), now()->addDays(2));

    // For a Member carrying the no-email flag.
    $flagged = Member::factory()->create();
    $flagged->no_email = true;
    $flagged->save();
    reminderSeatOn(reminderGroup(), $flagged, now()->addDays(2));

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(0);
});

it('writes nothing new on a second run the same day', function () {
    $group = reminderGroup();
    $member = Member::factory()->create();
    reminderSeatOn($group, $member, now()->addDays(2));

    $this->artisan('mail:daily-pass')->assertSuccessful();
    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(1);
});

it('catches up a Sign-up that entered the window on a missed day — a window, not an exact-date match', function () {
    // Legacy matches Shifts dated exactly today plus lead days, so a Shift one day out — a day
    // the cron missed — would never be reminded. The window covers it.
    $group = reminderGroup(3);
    $member = Member::factory()->create();
    reminderSeatOn($group, $member, now()->addDay()->setTime(9, 0));

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::where('kind', DeliveryKind::Reminder)->count())->toBe(1);
});

it('yields one Reminder for a seat dropped and re-taken by the same Member', function () {
    $group = reminderGroup();
    $member = Member::factory()->create();
    $seat = reminderSeatOn($group, $member, now()->addDays(2));
    $shiftId = $seat->shift_id;

    $this->artisan('mail:daily-pass')->assertSuccessful();
    expect(Delivery::count())->toBe(1);

    // Drop and re-take the same seat.
    $seat->delete();
    SignUp::factory()->create(['shift_id' => $shiftId, 'member_id' => $member->id]);

    $this->artisan('mail:daily-pass')->assertSuccessful();
    expect(Delivery::count())->toBe(1);
});

it('yields two Reminders for two Sign-ups on two overlapping Shifts', function () {
    $group = reminderGroup();
    $member = Member::factory()->create();
    reminderSeatOn($group, $member, now()->addDays(2)->setTime(10, 0));
    reminderSeatOn($group, $member, now()->addDays(2)->setTime(11, 0));

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::where('kind', DeliveryKind::Reminder)->where('member_id', $member->id)->count())->toBe(2);
});

it('does not send a Reminder for a Shift deleted before the Drain runs', function () {
    $group = reminderGroup();
    $member = Member::factory()->create();
    $seat = reminderSeatOn($group, $member, now()->addDays(2));

    $this->artisan('mail:daily-pass')->assertSuccessful();
    expect(Delivery::count())->toBe(1);

    // The seat is dropped and the Shift deleted — the Reminder must not go out.
    $seat->delete();
    $seat->shift->delete();

    expect(Delivery::where('kind', DeliveryKind::Reminder)->count())->toBe(0);
});
