<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Mail\InvitationFull;
use App\Models\Delivery;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Guest limit (#906, spec #901, ADR-0034 §9). A Host sets an optional limit on Accepted
 * Replies, and the server holds Guests to it. Acts through the HTTP routes as a Member and
 * checks Inertia props and the database; the Drain tests check the mail the Host gets.
 */

/**
 * An Open Invitation with this Guest limit and Guests already accepted.
 */
function limitedInvitation(?int $limit, int $accepted = 0, ?Member $host = null): Invitation
{
    $invitation = Invitation::factory()->create([
        'guest_limit' => $limit,
        'host_id' => ($host ?? Member::factory()->create())->id,
    ]);
    $invitation->state = InvitationState::Open;
    $invitation->save();

    Guest::factory()->count($accepted)->create(['invitation_id' => $invitation->id, 'reply' => Reply::Accepted]);

    return $invitation;
}

function guestAwaitingReply(Invitation $invitation): Guest
{
    return Guest::factory()->create(['invitation_id' => $invitation->id]);
}

function replyFor(Guest $guest, string $reply): TestResponse
{
    $url = route('invitations.show', ['invitation' => $guest->invitation_id]);

    return test()->actingAs($guest->member)
        ->from($url)
        ->put(route('invitations.reply', ['invitation' => $guest->invitation_id]), ['reply' => $reply]);
}

/**
 * @return array<string, mixed>
 */
function invitationFields(array $overrides = []): array
{
    return [
        'name' => 'Spring outing',
        'date' => now()->addMonth()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'description' => 'A walk in the galleries.',
        'options' => [],
        ...$overrides,
    ];
}

// --- A Host sets the limit -----------------------------------------------------

it('lets a Host set a limit when creating an Invitation', function () {
    $host = Member::factory()->create();

    $this->actingAs($host)
        ->post(route('invitations.store'), invitationFields(['guest_limit' => 12]))
        ->assertRedirect(route('invitations.hosting'));

    expect(Invitation::sole()->guest_limit)->toBe(12);
});

it('creates an Invitation with no limit by default', function () {
    $this->actingAs(Member::factory()->create())
        ->post(route('invitations.store'), invitationFields());

    expect(Invitation::sole()->guest_limit)->toBeNull();
});

it('shows the Host the limit on the edit form, and lets them change or clear it', function (mixed $cleared) {
    $host = Member::factory()->create();
    $invitation = Invitation::factory()->create(['host_id' => $host->id, 'guest_limit' => 12]);

    $this->actingAs($host)
        ->get(route('invitations.edit', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.guest_limit', 12));

    $this->patch(route('invitations.update', ['invitation' => $invitation->id]), invitationFields(['guest_limit' => 20]));
    expect($invitation->fresh()->guest_limit)->toBe(20);

    $this->patch(route('invitations.update', ['invitation' => $invitation->id]), invitationFields(['guest_limit' => $cleared]))
        ->assertSessionHasNoErrors();
    expect($invitation->fresh()->guest_limit)->toBeNull();
})->with([null, '']);

it('refuses a limit that is not a whole number of at least one', function (mixed $limit) {
    $this->actingAs(Member::factory()->create())
        ->post(route('invitations.store'), invitationFields(['guest_limit' => $limit]))
        ->assertSessionHasErrors('guest_limit');

    expect(Invitation::count())->toBe(0);
})->with([0, -1, 2.5, 'many', 70000]);

// --- Guests are held to it -----------------------------------------------------

it('lets a Guest take the last spot, then refuses the next Guest', function () {
    $invitation = limitedInvitation(2, accepted: 1);
    $last = guestAwaitingReply($invitation);
    $late = guestAwaitingReply($invitation);

    replyFor($last, 'accepted')->assertSessionHasNoErrors();
    replyFor($late, 'accepted')->assertSessionHasErrors('reply');

    expect($last->fresh()->reply)->toBe(Reply::Accepted)
        ->and($late->fresh()->reply)->toBe(Reply::NoReply);
});

it('leaves a Guest with no limit free to accept', function () {
    $invitation = limitedInvitation(null, accepted: 3);
    $guest = guestAwaitingReply($invitation);

    replyFor($guest, 'accepted')->assertSessionHasNoErrors();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('does not count Options toward the limit', function () {
    $invitation = limitedInvitation(2);
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $tour = $invitation->options()->create(['name' => 'Tour']);
    $first = guestAwaitingReply($invitation);
    $first->setReply(Reply::Accepted, [$lunch->id, $tour->id]);
    $second = guestAwaitingReply($invitation);

    replyFor($second, 'accepted')->assertSessionHasNoErrors();

    expect($second->fresh()->reply)->toBe(Reply::Accepted);
});

it('counts only Accepted Replies, not Declined or No reply', function () {
    $invitation = limitedInvitation(1);
    guestAwaitingReply($invitation)->setReply(Reply::Declined);
    guestAwaitingReply($invitation);
    $guest = guestAwaitingReply($invitation);

    replyFor($guest, 'accepted')->assertSessionHasNoErrors();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('lets a Guest decline a full Invitation', function () {
    $guest = guestAwaitingReply(limitedInvitation(1, accepted: 1));

    replyFor($guest, 'declined')->assertSessionHasNoErrors();

    expect($guest->fresh()->reply)->toBe(Reply::Declined);
});

it('frees a spot when a Guest declines', function () {
    $invitation = limitedInvitation(1);
    $holder = guestAwaitingReply($invitation);
    $waiting = guestAwaitingReply($invitation);

    replyFor($holder, 'accepted');
    replyFor($waiting, 'accepted')->assertSessionHasErrors('reply');

    replyFor($holder, 'declined');
    replyFor($waiting, 'accepted')->assertSessionHasNoErrors();

    expect($waiting->fresh()->reply)->toBe(Reply::Accepted);
});

it('lets an Accepted Guest on a full Invitation change their Options', function () {
    $invitation = limitedInvitation(1);
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $guest = guestAwaitingReply($invitation);
    $guest->setReply(Reply::Accepted);

    $this->actingAs($guest->member)
        ->put(route('invitations.reply', ['invitation' => $invitation->id]), ['reply' => 'accepted', 'options' => [$lunch->id]])
        ->assertSessionHasNoErrors();

    expect($guest->options()->pluck('options.id')->all())->toBe([$lunch->id]);
});

it('lets a Host-set Reply go over the limit, which keeps Guests out', function () {
    $host = Member::factory()->create();
    $invitation = limitedInvitation(1, accepted: 1, host: $host);
    $exception = guestAwaitingReply($invitation);
    $guest = guestAwaitingReply($invitation);

    $this->actingAs($host)
        ->put(route('invitations.replies', ['invitation' => $invitation->id]), ['member' => $exception->member_id, 'reply' => 'accepted'])
        ->assertSessionHasNoErrors();

    expect($exception->fresh()->reply)->toBe(Reply::Accepted);
    replyFor($guest, 'accepted')->assertSessionHasErrors('reply');
});

// --- A Guest sees that it is full ----------------------------------------------

it('tells a Guest a full Invitation is full and offers Decline, not Accept', function () {
    $guest = guestAwaitingReply(limitedInvitation(1, accepted: 1));

    $this->actingAs($guest->member)
        ->get(route('invitations.show', ['invitation' => $guest->invitation_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitation.full', true)
            ->where('can.reply', true)
            ->where('can.accept', false));
});

it('still offers Accept below the limit, and to a Guest who holds a spot', function () {
    $invitation = limitedInvitation(1);
    $holder = guestAwaitingReply($invitation);

    $this->actingAs($holder->member)
        ->get(route('invitations.show', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.full', false)->where('can.accept', true));

    $holder->setReply(Reply::Accepted);

    $this->get(route('invitations.show', ['invitation' => $invitation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.full', true)->where('can.accept', true));
});

it('gives a Guest no limit or count to read', function () {
    $guest = guestAwaitingReply(limitedInvitation(37, accepted: 1));

    $this->actingAs($guest->member)
        ->get(route('invitations.show', ['invitation' => $guest->invitation_id]))
        ->assertInertia(fn (Assert $page) => $page->missing('invitation.guest_limit'));
});

it('serves the full refusal in French', function () {
    $guest = guestAwaitingReply(limitedInvitation(1, accepted: 1));

    $this->withLocaleRoutes('fr', function () use ($guest) {
        $this->actingAs($guest->member)
            ->from("/fr/invitations/{$guest->invitation_id}")
            ->put("/fr/invitations/{$guest->invitation_id}/reponse", ['reply' => 'accepted'])
            ->assertSessionHasErrors(['reply' => 'Cette invitation est complète.']);
    });
});

// --- The Host hears when it fills ----------------------------------------------

it('queues one Notice to the Host when a Guest takes the last spot, and sends nothing in the request', function () {
    Mail::fake();
    $host = Member::factory()->create();
    $invitation = limitedInvitation(2, accepted: 1, host: $host);

    replyFor(guestAwaitingReply($invitation), 'accepted');

    Mail::assertNothingSent();
    $notice = Delivery::sole();
    expect($notice->kind)->toBe(DeliveryKind::Notice)
        ->and($notice->member_id)->toBe($host->id)
        ->and($notice->email)->toBe($host->email);
});

it('queues the Notice when a Host-set Reply reaches the limit, and none when it goes over', function () {
    $host = Member::factory()->create();
    $invitation = limitedInvitation(1, host: $host);

    foreach ([1, 1] as $notices) {
        $member = guestAwaitingReply($invitation)->member_id;
        $this->actingAs($host)
            ->put(route('invitations.replies', ['invitation' => $invitation->id]), ['member' => $member, 'reply' => 'accepted']);

        expect(Delivery::count())->toBe($notices);
    }

    expect(Delivery::sole()->member_id)->toBe($host->id);
});

it('queues no Notice below the limit, with no limit, or on a refused accept', function () {
    $roomy = limitedInvitation(3, accepted: 1);
    replyFor(guestAwaitingReply($roomy), 'accepted');

    replyFor(guestAwaitingReply(limitedInvitation(null, accepted: 5)), 'accepted');

    replyFor(guestAwaitingReply(limitedInvitation(1, accepted: 1)), 'accepted');

    expect(Delivery::count())->toBe(0);
});

it('queues no Notice when a Guest who holds a spot changes their Reply details', function () {
    $invitation = limitedInvitation(1);
    $holder = guestAwaitingReply($invitation);

    replyFor($holder, 'accepted');
    replyFor($holder, 'accepted');

    expect(Delivery::count())->toBe(1);
});

it('queues another Notice each time the count reaches the limit again', function () {
    $invitation = limitedInvitation(1);
    $first = guestAwaitingReply($invitation);
    $second = guestAwaitingReply($invitation);

    replyFor($first, 'accepted');
    replyFor($first, 'declined');
    expect(Delivery::count())->toBe(1);

    replyFor($second, 'accepted');
    expect(Delivery::count())->toBe(2);
});

it('queues no Notice when a Decline brings the count back down to the limit', function () {
    $invitation = limitedInvitation(1, accepted: 1);
    $over = guestAwaitingReply($invitation);
    $over->setReply(Reply::Accepted);

    replyFor($over, 'declined');

    expect(Delivery::count())->toBe(0);
});

it('writes no Notice for a Host with the no-email flag, and still fills the Invitation', function () {
    $host = Member::factory()->create();
    $host->no_email = true;
    $host->save();
    $invitation = limitedInvitation(1, host: $host);
    $guest = guestAwaitingReply($invitation);

    replyFor($guest, 'accepted')->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(0)
        ->and($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('drains the Notice in the Host\'s locale, naming the Invitation and linking to its Replies', function (string $locale, string $subject, string $link) {
    Mail::fake();
    $host = Member::factory()->create(['locale' => $locale]);
    $invitation = limitedInvitation(1, host: $host);
    $invitation->update(['name' => 'Spring outing']);

    replyFor(guestAwaitingReply($invitation), 'accepted');
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(InvitationFull::class, function (InvitationFull $mail) use ($host, $locale, $subject, $link, $invitation) {
        $mail->assertHasSubject($subject);
        $mail->assertSeeInHtml('Spring outing');
        $mail->assertSeeInHtml(str_replace('{id}', (string) $invitation->id, $link));

        return $mail->hasTo($host->email) && $mail->locale === $locale && $mail->replyTo === [];
    });

    expect(Delivery::sole()->state)->toBe(DeliveryState::Sent);
})->with([
    'English' => ['en', 'Guest limit reached: Spring outing', '/invitations/{id}/replies'],
    'French' => ['fr', "Limite d'invités atteinte : Spring outing", '/fr/invitations/{id}/reponses'],
]);

it('still drains the Notice after the Invitation is deleted', function () {
    Mail::fake();
    $host = Member::factory()->create();
    $invitation = limitedInvitation(1, host: $host);

    replyFor(guestAwaitingReply($invitation), 'accepted');
    $invitation->delete();
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(InvitationFull::class, 1);
});
