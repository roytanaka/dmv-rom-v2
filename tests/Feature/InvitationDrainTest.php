<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Mail\BroadcastSenderCopy;
use App\Mail\InvitationMail;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;

/*
 * The Drain's handling of a Host's mail (#907, spec #901, ADR-0034 §7): From and Reply-To, the
 * link in the Guest's locale, the Host's text as written, and a queued mail that lost its reader.
 */

beforeEach(function () {
    $this->host = Member::factory()->create(['first_name' => 'Helen', 'last_name' => 'Host']);
    $this->invitation = Invitation::factory()->create(['host_id' => $this->host->id]);
    $this->invitation->state = InvitationState::Open;
    $this->invitation->save();

    $this->broadcast = Broadcast::factory()->create([
        'kind' => 'invitation',
        'sender_id' => $this->host->id,
        'invitation_id' => $this->invitation->id,
        'group_id' => null,
        'subject' => 'The Annual Meeting',
        'body' => '<p>Please join us.</p>',
    ]);
});

function queuedInvitationMail(Broadcast $broadcast, ?Invitation $invitation, array $member = []): Delivery
{
    $guest = Member::factory()->create($member);

    if ($invitation !== null) {
        Guest::factory()->create(['invitation_id' => $invitation->id, 'member_id' => $guest->id]);
    }

    return $broadcast->deliveries()->create([
        'kind' => DeliveryKind::Invitation,
        'member_id' => $guest->id,
        'email' => $guest->email,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ]);
}

it('sends each Guest their own copy, from the Host, with the Host as Reply-To', function () {
    Mail::fake();
    $row = queuedInvitationMail($this->broadcast, $this->invitation);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasTo($row->email)
        && $mail->hasFrom(config('mail.from.address'), 'Helen Host')
        && $mail->hasReplyTo($this->host->email, 'Helen Host')
        && $mail->hasSubject('The Annual Meeting'));
    expect($row->fresh()->state)->toBe(DeliveryState::Sent);
});

it('uses the Host, not the sender, when the super-tier sends for them', function () {
    Mail::fake();
    $this->broadcast->update(['sender_id' => Member::factory()->superTier()->create()->id]);
    queuedInvitationMail($this->broadcast, $this->invitation);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasReplyTo($this->host->email));
});

it('links to the Invitation in the Guest locale and keeps the Host text as written', function (string $locale, string $link, string $label) {
    Mail::fake();
    queuedInvitationMail($this->broadcast, $this->invitation, ['locale' => $locale]);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($link, $label): bool {
        $mail->assertSeeInHtml($link.$this->invitation->id, false);
        $mail->assertSeeInHtml($label);
        $mail->assertSeeInHtml('<p>Please join us.</p>', false);

        return true;
    });
})->with([
    'English' => ['en', '/invitations/', 'See the invitation and reply'],
    'French' => ['fr', '/fr/invitations/', "Voir l'invitation et répondre"],
]);

it('holds the sender copy until every Guest copy is done', function () {
    Mail::fake();
    $this->broadcast->deliveries()->create([
        'kind' => DeliveryKind::SenderCopy,
        'member_id' => $this->host->id,
        'email' => $this->host->email,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now()->subMinute(),
    ]);
    queuedInvitationMail($this->broadcast, $this->invitation);

    $this->artisan('mail:drain')->assertSuccessful();
    Mail::assertNotSent(BroadcastSenderCopy::class);

    $this->artisan('mail:drain')->assertSuccessful();
    Mail::assertSent(BroadcastSenderCopy::class, fn (BroadcastSenderCopy $mail): bool => $mail->hasFrom(config('mail.from.address'), 'Helen Host'));
});

it('sends nothing for a queued mail that lost its reader', function (Closure $change) {
    Mail::fake();
    $row = queuedInvitationMail($this->broadcast, $this->invitation);

    $change($this->invitation, $row);
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNotSent(InvitationMail::class);
    expect($row->fresh()->state)->toBe(DeliveryState::Expired);
})->with([
    'a deleted Invitation' => [fn (Invitation $invitation) => $invitation->delete()],
    'a Draft' => [function (Invitation $invitation) {
        $invitation->state = InvitationState::Draft;
        $invitation->save();
    }],
    'a removed Guest' => [fn (Invitation $invitation, Delivery $row) => $invitation->guests()->where('member_id', $row->member_id)->delete()],
]);

it('still sends the sender copy after a deleted Invitation', function () {
    Mail::fake();
    $this->broadcast->deliveries()->create([
        'kind' => DeliveryKind::SenderCopy,
        'member_id' => $this->host->id,
        'email' => $this->host->email,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ]);
    $this->invitation->delete();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BroadcastSenderCopy::class, fn (BroadcastSenderCopy $mail): bool => $mail->hasReplyTo($this->host->email));
});
