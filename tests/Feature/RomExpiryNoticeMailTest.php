<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\RomExpiryNotice;
use App\Mail\RomExpirySummary;
use App\Models\Delivery;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;

/*
 * The ROM-expiry notice mail and its summary Notice (#943, ADR-0038 §9). The Drain builds each in
 * the recipient's locale. Prior art: RenewalReminderMailTest.
 */

it('greets the Member, names the expiry date and asks for the new one in Account settings, in the render locale', function () {
    $payload = ['name' => 'Ann Active', 'membership_expiry' => '2026-03-31'];

    $english = RomExpiryNotice::fromSnapshot($payload);
    $english->assertSeeInHtml('Hello Ann Active,');
    $english->assertSeeInHtml('2026-03-31');
    $english->assertSeeInHtml('Account settings');
    expect($english->envelope()->subject)->toBe('Update your ROM membership expiry date');

    app()->setLocale('fr');
    $french = RomExpiryNotice::fromSnapshot($payload);
    $french->assertSeeInHtml('Bonjour Ann Active,');
    $french->assertSeeInHtml('2026-03-31');
    $french->assertSeeInHtml('paramètres du compte');
    app()->setLocale('en');
});

it('sends a queued notice through the Drain', function () {
    Mail::fake();
    $member = Member::factory()->create();
    Delivery::create([
        'kind' => DeliveryKind::RomExpiryNotice,
        'member_id' => $member->id,
        'email' => $member->email,
        'series' => 'rom-expiry',
        'series_year' => 202606,
        'payload' => ['name' => $member->fullName(), 'membership_expiry' => '2026-03-31'],
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ]);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(RomExpiryNotice::class, fn (RomExpiryNotice $mail) => $mail->hasTo($member->email));
    expect(Delivery::sole()->state)->toBe(DeliveryState::Sent);
});

it('lists who was mailed in the summary Notice and sends it through the Drain', function () {
    $payload = RomExpirySummary::snapshot(['Ann Active', 'Sam Sustaining']);

    $mail = RomExpirySummary::fromSnapshot($payload);
    $mail->assertSeeInHtml('ROM-expiry notices sent');
    $mail->assertSeeInHtml('Ann Active');
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

    Mail::assertSent(RomExpirySummary::class, fn (RomExpirySummary $mail) => $mail->hasTo($records->email));
});
