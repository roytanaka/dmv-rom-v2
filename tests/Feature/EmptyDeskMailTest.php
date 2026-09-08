<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\EmptyDeskAlert;
use App\Models\Delivery;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * The empty-desk alert mail (#487, spec #479, ADR-0024 §7). Every third day, a Group tells its
 * roster which watched Shifts still have nobody. Like a Notice it renders from a payload snapshot
 * (the Shifts may be filled or gone by drain time), in the recipient's own locale: it names each
 * open Shift with date, times, and kind, marks the ones dated today, and links to the Group's
 * schedules. From is the app's one address wearing the Group's name; no Reply-To. Prior art:
 * ReminderMailTest and the twin snapshot render in the standing-change Notice.
 */

/** A snapshot payload for the empty-desk alert, run today, over the given Shift rows. */
function emptyDeskSnapshot(array $shifts, string $group = 'Visitor Guides', string $slug = 'visitor-guides'): array
{
    return [
        'group' => ['name' => $group, 'slug' => $slug],
        'run_date' => Carbon::now(config('app.org_timezone'))->toDateString(),
        'shifts' => $shifts,
    ];
}

/** One Shift row for the snapshot — instants ISO-8601, kind as-authored. */
function emptyDeskShift(DateTimeInterface $startsAt, string $kind): array
{
    $start = Carbon::instance(Carbon::parse($startsAt));

    return [
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->copy()->addHours(3)->toIso8601String(),
        'kind' => $kind,
    ];
}

it('names each open Shift with its date, times, and kind', function () {
    $today = Carbon::now(config('app.org_timezone'))->setTime(10, 0);
    $payload = emptyDeskSnapshot([emptyDeskShift($today, 'Level 1 Desk')], group: 'Visitor Guides');

    $mail = EmptyDeskAlert::fromSnapshot($payload);
    $wallClockTime = $today->copy()->translatedFormat('g:i A');

    $mail->assertSeeInHtml('Visitor Guides');
    $mail->assertSeeInHtml('Level 1 Desk');
    $mail->assertSeeInHtml($wallClockTime);
});

it('marks a Shift dated the run day as today', function () {
    $today = Carbon::now(config('app.org_timezone'))->setTime(9, 0);
    $payload = emptyDeskSnapshot([emptyDeskShift($today, 'Desk')]);

    EmptyDeskAlert::fromSnapshot($payload)->assertSeeInHtml(__('notices.empty_desk.today'));
});

it('does not mark a Shift dated later than the run day', function () {
    $later = Carbon::now(config('app.org_timezone'))->addDays(2)->setTime(9, 0);
    $payload = emptyDeskSnapshot([emptyDeskShift($later, 'Desk')]);

    EmptyDeskAlert::fromSnapshot($payload)->assertDontSeeInHtml(__('notices.empty_desk.today'));
});

it('carries the Group as the From display name and sets no Reply-To', function () {
    $today = Carbon::now(config('app.org_timezone'))->setTime(10, 0);
    $envelope = EmptyDeskAlert::fromSnapshot(emptyDeskSnapshot([emptyDeskShift($today, 'Desk')], group: 'Visitor Guides'))->envelope();

    expect($envelope->from->address)->toBe(config('mail.from.address'));
    expect($envelope->from->name)->toBe('Visitor Guides');
    expect($envelope->replyTo)->toBe([]);
});

it('links to the Group schedules for the render locale — a /fr/ twin in French, the English root in English', function () {
    $today = Carbon::now(config('app.org_timezone'))->setTime(10, 0);
    $payload = emptyDeskSnapshot([emptyDeskShift($today, 'Desk')], slug: 'visitor-guides');

    EmptyDeskAlert::fromSnapshot($payload)->locale('en')
        ->assertSeeInHtml('/groups/visitor-guides/scheduling');
    EmptyDeskAlert::fromSnapshot($payload)->locale('fr')
        ->assertSeeInHtml('/fr/groupes/visitor-guides/scheduling');
});

it('renders the French subject and heading from the lang files', function () {
    $today = Carbon::now(config('app.org_timezone'))->setTime(10, 0);
    $mail = EmptyDeskAlert::fromSnapshot(emptyDeskSnapshot([emptyDeskShift($today, 'Desk')], group: 'Guides du ROM'))->locale('fr');

    $mail->assertHasSubject(__('notices.empty_desk.subject', ['group' => 'Guides du ROM'], 'fr'));
    $mail->assertSeeInHtml(__('notices.empty_desk.heading', [], 'fr'));
});

it('sends a queued empty-desk alert through the Drain in the recipient’s own locale', function () {
    Mail::fake();

    $today = Carbon::now(config('app.org_timezone'))->setTime(10, 0);
    $payload = emptyDeskSnapshot([emptyDeskShift($today, 'Desk')]);

    $english = Member::factory()->create(['locale' => 'en']);
    $french = Member::factory()->create(['locale' => 'fr']);

    foreach ([$english, $french] as $member) {
        Delivery::create([
            'kind' => DeliveryKind::EmptyDesk,
            'member_id' => $member->id,
            'email' => $member->email,
            'payload' => $payload,
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ]);
    }

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(EmptyDeskAlert::class, 2);
    Mail::assertSent(EmptyDeskAlert::class, fn (EmptyDeskAlert $m) => $m->hasTo($english->email) && $m->locale === 'en');
    Mail::assertSent(EmptyDeskAlert::class, fn (EmptyDeskAlert $m) => $m->hasTo($french->email) && $m->locale === 'fr');
    expect(Delivery::where('state', DeliveryState::Sent)->count())->toBe(2);
});
