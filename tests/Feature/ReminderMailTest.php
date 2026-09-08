<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\ShiftReminder;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use Illuminate\Support\Facades\Mail;

/*
 * The Reminder mail (#486, spec #479, ADR-0024 §7). A Member with an upcoming Sign-up is
 * reminded a few days ahead. Unlike a Notice, a Reminder renders from the *live* Shift — the
 * row carries a `shift_id`, and the Shift is still there at drain time (a deleted Shift expires
 * the row). One bilingual chrome template covers every Group: subject "Reminder: your <Group>
 * shift on <date>", the Member's name, the Group, the Schedule name, start and end with AM/PM,
 * the shift kind if any, and a plain link to the Schedule, in the recipient's own language, no
 * Reply-To. Prior art: SignUpCancellationMailTest, which renders the twin Shift chrome.
 */

/** A published Schedule + Shift on $group, and a pending Reminder Delivery to $member for it. */
function reminderDeliveryFor(Group $group, Member $member, array $shiftOverrides = []): Delivery
{
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id, 'name' => 'October 2026']);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->addDays(2)->setTime(10, 0),
        'ends_at' => now()->addDays(2)->setTime(13, 0),
        ...$shiftOverrides,
    ]);

    return Delivery::create([
        'kind' => DeliveryKind::Reminder,
        'member_id' => $member->id,
        'email' => $member->email,
        'shift_id' => $shift->id,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
        'expires_at' => $shift->starts_at,
    ]);
}

it('sends a queued Reminder through the Drain in the recipient’s own locale', function () {
    Mail::fake();

    $group = Group::factory()->program()->create(['name' => 'Visitor Guides']);
    $english = Member::factory()->create(['locale' => 'en']);
    $french = Member::factory()->create(['locale' => 'fr']);
    reminderDeliveryFor($group, $english);
    reminderDeliveryFor($group, $french);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(ShiftReminder::class, 2);
    Mail::assertSent(ShiftReminder::class, fn (ShiftReminder $m) => $m->hasTo($english->email) && $m->locale === 'en');
    Mail::assertSent(ShiftReminder::class, fn (ShiftReminder $m) => $m->hasTo($french->email) && $m->locale === 'fr');
    expect(Delivery::where('state', DeliveryState::Sent)->count())->toBe(2);
});

it('names the Member, the Group, the Schedule, the AM/PM times, and the kind', function () {
    $group = Group::factory()->program()->create(['name' => 'Docents']);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights tour']);
    $member = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);
    $delivery = reminderDeliveryFor($group, $member, ['shift_kind_id' => $kind->id]);

    $shift = $delivery->shift()->with(['schedule.group', 'kind'])->first();
    $wallClockTime = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'))->translatedFormat('g:i A');

    $mail = new ShiftReminder($shift, $member);

    $mail->assertSeeInHtml('Dana Okafor');
    $mail->assertSeeInHtml('Docents');
    $mail->assertSeeInHtml('October 2026');
    $mail->assertSeeInHtml('Highlights tour');
    $mail->assertSeeInHtml($wallClockTime);
});

it('carries the date in the subject and the Group as the From display name, with no Reply-To', function () {
    $group = Group::factory()->program()->create(['name' => 'Reception']);
    $member = Member::factory()->create();
    $delivery = reminderDeliveryFor($group, $member, ['shift_kind_id' => null]);
    $shift = $delivery->shift()->with(['schedule.group', 'kind'])->first();

    $envelope = (new ShiftReminder($shift, $member))->envelope();
    $date = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'))->translatedFormat('j F Y');

    expect($envelope->subject)->toBe(__('scheduling.reminder_email.subject', ['group' => 'Reception', 'date' => $date]));
    expect($envelope->from->address)->toBe(config('mail.from.address'));
    expect($envelope->from->name)->toBe('Reception');
    expect($envelope->replyTo)->toBe([]);
});

it('links to the Schedule for the render locale — a /fr/ twin in French, the English root in English', function () {
    $group = Group::factory()->program()->create();
    $member = Member::factory()->create();
    $delivery = reminderDeliveryFor($group, $member);
    $shift = $delivery->shift()->with(['schedule.group', 'kind'])->first();

    (new ShiftReminder($shift, $member))->locale('fr')
        ->assertSeeInHtml("/fr/groupes/{$group->slug}/horaire/{$shift->schedule->id}");
    (new ShiftReminder($shift, $member))->locale('en')
        ->assertSeeInHtml("/groups/{$group->slug}/scheduling/{$shift->schedule->id}");
});

it('renders the French subject and heading from the lang files', function () {
    $group = Group::factory()->program()->create(['name' => 'Guides du ROM']);
    $member = Member::factory()->create();
    $delivery = reminderDeliveryFor($group, $member);
    $shift = $delivery->shift()->with(['schedule.group', 'kind'])->first();
    $date = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'))->locale('fr')->translatedFormat('j F Y');

    $mail = (new ShiftReminder($shift, $member))->locale('fr');

    $mail->assertHasSubject(__('scheduling.reminder_email.subject', ['group' => 'Guides du ROM', 'date' => $date], 'fr'));
    $mail->assertSeeInHtml(__('scheduling.reminder_email.heading', [], 'fr'));
});
