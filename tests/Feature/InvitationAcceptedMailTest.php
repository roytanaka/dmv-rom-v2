<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Mail\BroadcastSenderCopy;
use App\Mail\InvitationMail;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A Host mails Accepted Guests from the Replies page (#908, spec #901, ADR-0034 §11). It rides
 * the send path with its own Audience, opens nothing, and still reaches Guests on a Draft and
 * after Closed, because the Replies page is the Host's door tool.
 */

function acceptedInvitation(array $attributes = [], InvitationState $state = InvitationState::Open): Invitation
{
    $invitation = Invitation::factory()->create($attributes);
    $invitation->state = $state;
    $invitation->save();

    return $invitation;
}

function acceptedGuest(Invitation $invitation, Reply $reply = Reply::Accepted, array $member = []): Guest
{
    $guest = Guest::factory()->create([
        'invitation_id' => $invitation->id,
        'member_id' => Member::factory()->create($member)->id,
    ]);
    $guest->setReply($reply);

    return $guest;
}

function mailAcceptedGuests(Member $actor, Invitation $invitation): TestResponse
{
    return test()->actingAs($actor)->postJson(route('broadcasts.store'), [
        'context' => 'invitation',
        'context_subject' => (string) $invitation->id,
        'audience' => 'invitation_accepted',
        'subject' => 'Parking on the day',
        'body' => '<p>Use the east door.</p>',
    ]);
}

it('queues one mail per Accepted Guest and none for the others', function () {
    Mail::fake();
    $invitation = acceptedInvitation();
    $accepted = acceptedGuest($invitation);
    acceptedGuest($invitation, Reply::Declined);
    acceptedGuest($invitation, Reply::NoReply);

    mailAcceptedGuests($invitation->host, $invitation)
        ->assertOk()
        ->assertJson(['queued' => 1, 'skipped' => []]);

    expect(Broadcast::sole()->audience_label)->toBe('Accepted Guests')
        ->and(Delivery::where('kind', DeliveryKind::Invitation)->sole()->member_id)->toBe($accepted->member_id);
    Mail::assertNothingSent();
});

it('skips an Accepted Guest with the no-email flag and names them', function () {
    $invitation = acceptedInvitation();
    acceptedGuest($invitation);
    acceptedGuest($invitation, Reply::Accepted, ['first_name' => 'Quiet', 'last_name' => 'Guest', 'no_email' => true]);

    mailAcceptedGuests($invitation->host, $invitation)
        ->assertOk()
        ->assertJson(['queued' => 1, 'skipped' => ['Quiet Guest']]);
});

it('refuses a send when no Accepted Guest can be reached', function () {
    $invitation = acceptedInvitation();
    acceptedGuest($invitation, Reply::NoReply);

    mailAcceptedGuests($invitation->host, $invitation)->assertUnprocessable();

    expect(Broadcast::count())->toBe(0);
});

it('leaves a Draft a Draft, and the Accepted Guest still gets the mail, with no link they would be refused at', function () {
    Mail::fake();
    $invitation = acceptedInvitation(state: InvitationState::Draft);
    $guest = acceptedGuest($invitation);

    mailAcceptedGuests($invitation->host, $invitation)->assertOk();
    $this->artisan('mail:drain')->assertSuccessful();

    expect($invitation->fresh()->state)->toBe(InvitationState::Draft)
        ->and(Delivery::where('kind', DeliveryKind::Invitation)->sole()->state)->toBe(DeliveryState::Sent);
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($guest, $invitation): bool {
        $mail->assertSeeInHtml('<p>Use the east door.</p>', false);
        $mail->assertDontSeeInHtml('/invitations/'.$invitation->id, false);

        return $mail->hasTo($guest->member->email);
    });
});

it('lets a Host remove an Accepted Guest from this mail on a Draft', function () {
    $invitation = acceptedInvitation(state: InvitationState::Draft);
    $reached = acceptedGuest($invitation);
    $removed = acceptedGuest($invitation);

    test()->actingAs($invitation->host)->postJson(route('broadcasts.store'), [
        'context' => 'invitation',
        'context_subject' => (string) $invitation->id,
        'audience' => 'invitation_accepted',
        'subject' => 'Parking on the day',
        'body' => '<p>Use the east door.</p>',
        'removed' => [$removed->member_id],
    ])->assertOk();

    expect(Delivery::where('kind', DeliveryKind::Invitation)->sole()->member_id)->toBe($reached->member_id)
        ->and($invitation->fresh()->state)->toBe(InvitationState::Draft);
});

it('still expires a plain send on an Invitation moved back to Draft', function () {
    Mail::fake();
    $invitation = acceptedInvitation();
    acceptedGuest($invitation, Reply::NoReply);

    $this->actingAs($invitation->host)->postJson(route('broadcasts.store'), [
        'context' => 'invitation',
        'context_subject' => (string) $invitation->id,
        'audience' => 'invitation_guests',
        'subject' => 'The Annual Meeting',
        'body' => '<p>Please join us.</p>',
    ])->assertOk();
    $invitation->state = InvitationState::Draft;
    $invitation->save();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNotSent(InvitationMail::class);
});

it('reaches Accepted Guests after Closed, with the link', function () {
    Mail::fake();
    $invitation = acceptedInvitation(['date' => now()->subWeek()->toDateString()]);
    acceptedGuest($invitation);

    mailAcceptedGuests($invitation->host, $invitation)->assertOk();
    $this->artisan('mail:drain')->assertSuccessful();

    expect($invitation->fresh()->isClosed())->toBeTrue();
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($invitation): bool {
        $mail->assertSeeInHtml('/invitations/'.$invitation->id, false);

        return true;
    });
});

it('lets the super-tier mail Accepted Guests of any Invitation', function () {
    $invitation = acceptedInvitation();
    acceptedGuest($invitation);

    mailAcceptedGuests(Member::factory()->superTier()->create(), $invitation)
        ->assertOk()
        ->assertJson(['queued' => 1]);
});

it('refuses everyone else, an Accepted Guest and another Full access Member included', function (Closure $actor) {
    $invitation = acceptedInvitation();
    $guest = acceptedGuest($invitation);

    mailAcceptedGuests($actor($guest), $invitation)->assertForbidden();

    expect(Broadcast::count())->toBe(0);
})->with([
    'an Accepted Guest' => [fn (Guest $guest) => $guest->member],
    'another Member' => [fn () => Member::factory()->create()],
]);

it('shows the Host names and no address, on the page, the recipient list, and the send', function () {
    $invitation = acceptedInvitation(['host_id' => Member::factory()->create(['first_name' => 'Hal', 'last_name' => 'Host'])->id]);
    acceptedGuest($invitation, Reply::Accepted, ['first_name' => 'Ada', 'email' => 'ada-address@example.test']);
    acceptedGuest($invitation, Reply::Accepted, ['first_name' => 'Ned', 'email' => 'ned-address@example.test', 'no_email' => true]);

    $this->actingAs($invitation->host)
        ->get(route('invitations.replies', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.host', 'Hal Host'))
        ->assertDontSee('ada-address@example.test')
        ->assertDontSee('ned-address@example.test');

    $this->actingAs($invitation->host)
        ->getJson(route('audiences.show', ['audience' => 'invitation_accepted', 'context' => 'invitation', 'subject' => $invitation->id]))
        ->assertOk()
        ->assertJsonCount(1, 'recipients')
        ->assertJsonPath('recipients.0.first_name', 'Ada')
        ->assertDontSee('ada-address@example.test')
        ->assertDontSee('ned-address@example.test');

    mailAcceptedGuests($invitation->host, $invitation)
        ->assertOk()
        ->assertDontSee('ada-address@example.test')
        ->assertDontSee('ned-address@example.test');
});

it('names the Audience in French', function () {
    app()->setLocale('fr');
    $invitation = acceptedInvitation();
    acceptedGuest($invitation);

    mailAcceptedGuests($invitation->host, $invitation)->assertOk();

    expect(Broadcast::sole()->audience_label)->toBe('Invités ayant accepté');
});

it('sends the Host their copy when the Accepted mail is done', function () {
    Mail::fake();
    $invitation = acceptedInvitation(state: InvitationState::Draft);
    acceptedGuest($invitation);

    mailAcceptedGuests($invitation->host, $invitation)->assertOk();
    $this->artisan('mail:drain')->assertSuccessful();
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BroadcastSenderCopy::class);
});
