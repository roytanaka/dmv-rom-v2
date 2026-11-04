<?php

use App\Models\Delivery;
use App\Models\EmptyDeskRun;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Support\Facades\Log;

/*
 * The Beta mail-off switch (#938, ADR-0035 §6, ADR-0038 §9). `mail.daily_pass_off` is read once
 * at the top of the daily pass: when on, the pass writes nothing (shift Reminders, the empty-desk
 * alert, and every step added later) and logs one line saying so. Prior art: ReminderPassTest.
 */

/** A Sign-up two days out in a Group with Reminders on: the pass would write one Reminder. */
function mailOffSeat(): SignUp
{
    $group = Group::factory()->program()->create([
        'reminders_enabled' => true,
        'reminder_lead_days' => 3,
        'empty_desk_alert_enabled' => true,
    ]);
    $schedule = Schedule::factory()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(3),
    ]);

    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => Member::factory()->create()->id]);
}

it('is off by default', function () {
    expect(config('mail.daily_pass_off'))->toBeFalse();
});

it('writes no Deliveries and logs once when the switch is on', function () {
    mailOffSeat();
    config(['mail.daily_pass_off' => true]);
    Log::spy();

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(0);
    expect(EmptyDeskRun::count())->toBe(0);
    Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message) => str_contains($message, 'MAIL_DAILY_PASS_OFF'));
});

it('writes the Reminder when the switch is off', function () {
    mailOffSeat();
    config(['mail.daily_pass_off' => false]);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(1);
});
