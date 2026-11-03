<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\RenewalReminder;
use App\Mail\RenewalReminderSummary;
use App\Models\Delivery;
use App\Models\Member;
use App\Models\RenewalSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * The Renewal reminder mail and its summary Notice (#942, ADR-0038 §9). The Drain renders each from
 * its payload snapshot in the recipient's locale: the subject is translated chrome, the body is the
 * member admin's text as authored. A Sustaining reminder carries the Sustaining attachment. Prior
 * art: EmptyDeskMailTest.
 */

function renewalReminderRow(Member $member, string $series, string $message = 'Please renew.'): Delivery
{
    return Delivery::create([
        'kind' => DeliveryKind::RenewalReminder,
        'member_id' => $member->id,
        'email' => $member->email,
        'series' => $series,
        'series_year' => 2027,
        'payload' => ['series' => $series, 'renewal_year' => 2027, 'message' => $message],
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ]);
}

it('renders the settings text as authored, line breaks kept and markdown inert', function () {
    $mail = new RenewalReminder('active-1', 2027, "Dear Member,\nPlease renew [here](https://evil.example).");

    $mail->assertSeeInHtml('Dear Member,<br>', false);
    $mail->assertDontSeeInHtml('href="https://evil.example"', false);
});

it('carries the series and year in the subject, in the render locale', function () {
    $english = (new RenewalReminder('active-2', 2027, 'x'))->envelope()->subject;
    expect($english)->toBe('2nd Reminder to complete DMV Membership Renewal for 2027/2028 - Active');

    app()->setLocale('fr');
    $french = (new RenewalReminder('sustaining-1', 2027, 'x'))->envelope()->subject;
    app()->setLocale('en');
    expect($french)->toContain('2027/2028')->not->toContain('Reminder');
});

it('sends a queued reminder through the Drain, attaching the Sustaining file to Sustaining only', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::disk('local')->put('renewal/abc.pdf', '%PDF-1.4');
    $row = RenewalSetting::current();
    $row->forceFill([
        'show_renewal' => true,
        'renewal_year' => 2027,
        'sustaining_attachment_path' => 'renewal/abc.pdf',
        'sustaining_attachment_filename' => 'Sustaining renewal form.pdf',
        'sustaining_attachment_mime_type' => 'application/pdf',
    ])->save();

    $active = Member::factory()->create();
    $sustaining = Member::factory()->create();
    renewalReminderRow($active, 'active-1');
    renewalReminderRow($sustaining, 'sustaining-1');

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(RenewalReminder::class, fn (RenewalReminder $mail) => $mail->hasTo($active->email)
        && $mail->attachments() === []);
    Mail::assertSent(RenewalReminder::class, fn (RenewalReminder $mail) => $mail->hasTo($sustaining->email)
        && collect($mail->attachments())->sole()->as === 'Sustaining renewal form.pdf'
        && collect($mail->attachments())->sole()->mime === 'application/pdf');
    expect(Delivery::where('state', DeliveryState::Sent)->count())->toBe(2);
});

it('sends a Sustaining reminder without an attachment when none is stored', function () {
    Mail::fake();
    renewalReminderRow(Member::factory()->create(), 'sustaining-2');

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(RenewalReminder::class, fn (RenewalReminder $mail) => $mail->attachments() === []);
});

it('lists who was mailed in the summary Notice, by series, and sends it through the Drain', function () {
    $payload = RenewalReminderSummary::snapshot(['active-1' => ['Ann Active', 'Bob Active'], 'sustaining-2' => ['Sam Sustaining']], 2027);

    $mail = RenewalReminderSummary::fromSnapshot($payload);
    $mail->assertSeeInHtml('1st reminder, Active Members');
    $mail->assertSeeInHtml('Bob Active');
    $mail->assertSeeInHtml('2nd reminder, Sustaining Members');
    $mail->assertSeeInHtml('Sam Sustaining');

    Mail::fake();
    $records = Member::factory()->create();
    Delivery::create([
        'kind' => DeliveryKind::Notice,
        'member_id' => $records->id,
        'email' => $records->email,
        'payload' => $payload,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ]);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(RenewalReminderSummary::class, fn (RenewalReminderSummary $mail) => $mail->hasTo($records->email));
});
