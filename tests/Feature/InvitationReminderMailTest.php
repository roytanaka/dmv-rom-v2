<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Mail\InvitationReminder;
use App\Models\Delivery;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;

/*
 * The Invitation Reminder through the Drain (#909, ADR-0034 §12). It renders from the live
 * Invitation in the Guest's locale and names their Options, the description and the Host. It is
 * automatic mail, so it has no Reply-To. A Guest who has since declined and a deleted Invitation
 * get no mail.
 * Prior art: ReminderMailTest.
 */

/** An Accepted Guest on an Open Invitation, with a pending Reminder queued for them. */
function queuedInvitationReminder(array $memberAttributes = [], array $invitationAttributes = []): Guest
{
    $member = Member::factory()->create($memberAttributes);
    $invitation = Invitation::factory()->create([
        'state' => InvitationState::Open,
        'date' => now()->addDays(3)->toDateString(),
        ...$invitationAttributes,
    ]);
    $guest = Guest::factory()->create([
        'invitation_id' => $invitation->id,
        'member_id' => $member->id,
        'reply' => Reply::Accepted,
    ]);

    Delivery::create([
        'kind' => DeliveryKind::InvitationReminder,
        'invitation_id' => $invitation->id,
        'member_id' => $member->id,
        'email' => $member->email,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
        'expires_at' => $invitation->startsAt(),
    ]);

    return $guest;
}

it('sends a queued Reminder through the Drain in the Guest’s own locale', function () {
    Mail::fake();

    $english = queuedInvitationReminder(['locale' => 'en']);
    $french = queuedInvitationReminder(['locale' => 'fr']);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(InvitationReminder::class, 2);
    Mail::assertSent(InvitationReminder::class, fn (InvitationReminder $m) => $m->hasTo($english->member->email) && $m->locale === 'en');
    Mail::assertSent(InvitationReminder::class, fn (InvitationReminder $m) => $m->hasTo($french->member->email) && $m->locale === 'fr');
    expect(Delivery::where('state', DeliveryState::Sent)->count())->toBe(2);
});

it('sends nothing to a Guest who has since declined', function () {
    Mail::fake();

    queuedInvitationReminder()->setReply(Reply::Declined);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Delivery::sole()->state)->toBe(DeliveryState::Expired);
});

it('sends nothing for a deleted Invitation', function () {
    Mail::fake();

    queuedInvitationReminder()->invitation->delete();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Delivery::sole()->state)->toBe(DeliveryState::Expired);
});

it('sends nothing for an Invitation moved back to Draft, or to a removed Guest', function () {
    Mail::fake();

    $hidden = queuedInvitationReminder();
    $hidden->invitation->state = InvitationState::Draft;
    $hidden->invitation->save();
    queuedInvitationReminder()->delete();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Delivery::where('state', DeliveryState::Expired)->count())->toBe(2);
});

it('sends nothing to a Guest who stopped being current', function (Category $category) {
    Mail::fake();

    queuedInvitationReminder()->member->update(['category' => $category]);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Delivery::sole()->state)->toBe(DeliveryState::Expired);
})->with([
    'resigned' => [Category::Resigned],
    'provisional, who has no Directory listing' => [Category::Provisional],
]);

it('sends nothing once the occasion has started', function () {
    Mail::fake();

    queuedInvitationReminder();
    $this->travel(4)->days();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Delivery::sole()->state)->toBe(DeliveryState::Expired);
});

it('names the Guest, the occasion, their Options, the description, and the Host', function () {
    $guest = queuedInvitationReminder(
        ['first_name' => 'Dana', 'last_name' => 'Okafor'],
        ['name' => 'Annual Meeting', 'description' => 'Doors open at nine.', 'start_time' => '14:30', 'end_time' => '16:00'],
    );
    $invitation = $guest->invitation;
    $invitation->host->update(['first_name' => 'Noor', 'last_name' => 'Haddad']);
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $invitation->options()->create(['name' => 'Gallery tour']);
    $guest->setReply(Reply::Accepted, [$lunch->id]);

    $mail = new InvitationReminder($invitation, $guest);

    $mail->assertSeeInHtml('Dana Okafor');
    $mail->assertSeeInHtml('Annual Meeting');
    $mail->assertSeeInHtml('Doors open at nine.');
    $mail->assertSeeInHtml('Noor Haddad');
    $mail->assertSeeInHtml('Lunch');
    $mail->assertDontSeeInHtml('Gallery tour');
    $mail->assertSeeInHtml('2:30 PM');
    $mail->assertSeeInHtml('4:00 PM');
});

it('leaves out the Options line when the Guest chose none', function () {
    $guest = queuedInvitationReminder();

    (new InvitationReminder($guest->invitation, $guest))
        ->assertDontSeeInHtml(__('invitations.reminder_email.your_options'));
});

it('renders the date in the subject, with the default From and no Reply-To', function () {
    $guest = queuedInvitationReminder([], ['name' => 'Annual Meeting', 'date' => '2026-11-12']);

    $envelope = (new InvitationReminder($guest->invitation, $guest))->envelope();

    expect($envelope->subject)->toBe(__('invitations.reminder_email.subject', ['name' => 'Annual Meeting', 'date' => '12 November 2026']));
    expect($envelope->from)->toBeNull();
    expect($envelope->replyTo)->toBe([]);
});

it('renders in French with a /fr/ link, and the Host’s text as written', function () {
    $guest = queuedInvitationReminder([], ['name' => 'Annual Meeting', 'description' => 'Doors open at nine.', 'date' => '2026-11-12']);
    $invitation = $guest->invitation;

    $mail = (new InvitationReminder($invitation, $guest))->locale('fr');

    $mail->assertHasSubject('Rappel : Annual Meeting le 12 novembre 2026');
    $mail->assertSeeInHtml('Doors open at nine.');
    $mail->assertSeeInHtml("/fr/invitations/{$invitation->id}");
    $mail->assertDontSeeInHtml('View the invitation');
});

it('links to the Invitation at the English root in English', function () {
    $guest = queuedInvitationReminder();

    (new InvitationReminder($guest->invitation, $guest))->locale('en')
        ->assertSeeInHtml("/invitations/{$guest->invitation_id}")
        ->assertDontSeeInHtml('/fr/invitations');
});
