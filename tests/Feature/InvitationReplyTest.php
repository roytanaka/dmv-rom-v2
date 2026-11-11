<?php

use App\Enums\Category;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\Option;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Opening an Invitation and replying to it (#904, spec #901, ADR-0034 §6, §10, §11). A
 * Host moves an Invitation between Draft and Open, and a Guest answers for themselves.
 * Acts through the HTTP routes as a Member and checks Inertia props and the database.
 */

function openInvitation(array $attributes = []): Invitation
{
    $invitation = Invitation::factory()->create($attributes);
    $invitation->state = InvitationState::Open;
    $invitation->save();

    return $invitation;
}

function guestOn(Invitation $invitation, ?Member $member = null): Guest
{
    return Guest::factory()->create([
        'invitation_id' => $invitation->id,
        'member_id' => ($member ?? Member::factory()->create())->id,
    ]);
}

function option(Invitation $invitation, string $name = 'Lunch'): Option
{
    return $invitation->options()->create(['name' => $name]);
}

/**
 * @param  array<string, mixed>  $data
 */
function replyAs(Member $member, Invitation $invitation, array $data): TestResponse
{
    return test()->actingAs($member)
        ->from(route('invitations.show', ['invitation' => $invitation->id]))
        ->put(route('invitations.reply', ['invitation' => $invitation->id]), $data);
}

// --- A Host moves an Invitation between Draft and Open -----------------------

it('lets a Host move an Open Invitation back to Draft', function () {
    $host = Member::factory()->create();
    $invitation = openInvitation(['host_id' => $host->id]);

    $this->actingAs($host)
        ->post(route('invitations.draft', ['invitation' => $invitation->id]))
        ->assertRedirect(route('invitations.hosting'));

    expect($invitation->fresh()->state)->toBe(InvitationState::Draft);
});

it('refuses drafting to anyone but the Host', function () {
    $invitation = openInvitation();

    $this->actingAs(Member::factory()->create())
        ->post(route('invitations.draft', ['invitation' => $invitation->id]))
        ->assertForbidden();
});

it('refuses a Host who has lost Full access', function () {
    $host = Member::factory()->category(Category::Provisional)->create();
    $invitation = openInvitation(['host_id' => $host->id]);

    $this->actingAs($host)
        ->post(route('invitations.draft', ['invitation' => $invitation->id]))
        ->assertForbidden();

    expect($invitation->fresh()->state)->toBe(InvitationState::Open);
});

it('keeps Replies when an Invitation goes back to Draft', function () {
    $host = Member::factory()->create();
    $invitation = openInvitation(['host_id' => $host->id]);
    $guest = guestOn($invitation);
    $guest->setReply(Reply::Accepted);

    $this->actingAs($host)->post(route('invitations.draft', ['invitation' => $invitation->id]));

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

// --- My invitations ----------------------------------------------------------

it('lists the Open Invitations the Member is a Guest on, soonest first, with their Reply', function () {
    $member = Member::factory()->create();
    $later = openInvitation(['name' => 'Later', 'date' => now()->addMonths(2)->toDateString()]);
    $sooner = openInvitation(['name' => 'Sooner', 'date' => now()->addWeek()->toDateString()]);
    guestOn($later, $member)->setReply(Reply::Declined);
    guestOn($sooner, $member);

    $this->actingAs($member)
        ->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/Mine')
            ->has('invitations', 2)
            ->where('invitations.0.name', 'Sooner')
            ->where('invitations.0.reply', 'no_reply')
            ->where('invitations.1.name', 'Later')
            ->where('invitations.1.reply', 'declined'));
});

it('leaves Drafts, Closed, deleted and other Members\' Invitations off My invitations', function () {
    $member = Member::factory()->create();
    guestOn(Invitation::factory()->create(), $member);
    guestOn(openInvitation(['date' => now()->subWeek()->toDateString()]), $member);
    $deleted = openInvitation();
    guestOn($deleted, $member);
    $deleted->delete();
    guestOn(openInvitation());

    $this->actingAs($member)
        ->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));
});

// --- Reading one Invitation --------------------------------------------------

it('shows a Guest the Invitation with their own Reply and Options', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation(['name' => 'Annual Meeting']);
    $lunch = option($invitation, 'Lunch');
    option($invitation, 'Tour');
    guestOn($invitation, $member)->setReply(Reply::Accepted, [$lunch->id]);

    $this->actingAs($member)
        ->get(route('invitations.show', ['invitation' => $invitation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/Show')
            ->where('invitation.name', 'Annual Meeting')
            ->where('invitation.closed', false)
            ->has('invitation.options', 2)
            ->where('guest.reply', 'accepted')
            ->where('guest.options', [$lunch->id])
            ->where('can.reply', true));
});

it('holds no other Guest, Reply or email address in what a Guest reads', function () {
    $host = Member::factory()->create();
    $invitation = openInvitation(['host_id' => $host->id]);
    $member = Member::factory()->create();
    guestOn($invitation, $member);
    $other = Member::factory()->create(['first_name' => 'Zebedee', 'last_name' => 'Quillfeather']);
    guestOn($invitation, $other)->setReply(Reply::Declined);

    foreach ([route('invitations.show', ['invitation' => $invitation->id]), route('invitations')] as $url) {
        $this->actingAs($member)->get($url)
            ->assertDontSee('Quillfeather')
            ->assertDontSee('declined')
            ->assertDontSee($other->email)
            ->assertDontSee($host->email);
    }
});

it('refuses a Member who is not a Guest', function () {
    $this->actingAs(Member::factory()->create())
        ->get(route('invitations.show', ['invitation' => openInvitation()->id]))
        ->assertForbidden();
});

it('refuses a Guest a Draft', function () {
    $member = Member::factory()->create();
    $invitation = Invitation::factory()->create();
    guestOn($invitation, $member);

    $this->actingAs($member)
        ->get(route('invitations.show', ['invitation' => $invitation->id]))
        ->assertForbidden();
});

it('lets a Guest read a Closed Invitation but offers no Reply', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation(['date' => now()->subWeek()->toDateString()]);
    guestOn($invitation, $member);

    $this->actingAs($member)
        ->get(route('invitations.show', ['invitation' => $invitation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitation.closed', true)
            ->where('can.reply', false));
});

it('lets the Host read their own Draft, with no Reply to give', function () {
    $host = Member::factory()->create();
    $invitation = Invitation::factory()->create(['host_id' => $host->id]);

    $this->actingAs($host)
        ->get(route('invitations.show', ['invitation' => $invitation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('guest', null)->where('can.reply', false));
});

it('serves reading, replying and the state moves under translated French URLs', function () {
    $host = Member::factory()->create();
    $member = Member::factory()->create();
    $invitation = openInvitation(['host_id' => $host->id]);
    guestOn($invitation, $member);

    $this->withLocaleRoutes('fr', function () use ($host, $member, $invitation) {
        $this->actingAs($member);
        $this->get("/fr/invitations/{$invitation->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('invitations/Show')->where('locale', 'fr'));
        $this->put("/fr/invitations/{$invitation->id}/reponse", ['reply' => 'declined'])
            ->assertRedirect("/fr/invitations/{$invitation->id}");

        $this->actingAs($host);
        $this->post("/fr/invitations/{$invitation->id}/brouillon")->assertRedirect('/fr/invitations/organisation');
    });
});

// --- Replying ----------------------------------------------------------------

it('lets a Guest accept and choose Options', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    $lunch = option($invitation, 'Lunch');
    $tour = option($invitation, 'Tour');
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'accepted', 'options' => [$lunch->id, $tour->id]])
        ->assertRedirect(route('invitations.show', ['invitation' => $invitation->id]));

    expect($guest->fresh()->reply)->toBe(Reply::Accepted)
        ->and($guest->options()->pluck('options.id')->sort()->values()->all())->toBe([$lunch->id, $tour->id]);
});

it('lets a Provisional Guest find the Invitation and accept', function () {
    $member = Member::factory()->category(Category::Provisional)->create();
    $invitation = openInvitation(['name' => 'Service Awards']);
    $guest = guestOn($invitation, $member);

    $this->actingAs($member)
        ->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page->where('invitations.0.name', 'Service Awards'));
    replyAs($member, $invitation, ['reply' => 'accepted'])->assertSessionHasNoErrors();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('lets a Guest accept with no Options', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    option($invitation);
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'accepted'])->assertSessionHasNoErrors();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted)
        ->and($guest->options()->count())->toBe(0);
});

it('lets a Guest decline, with no Options kept even if some are sent', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    $lunch = option($invitation);
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'declined', 'options' => [$lunch->id]]);

    expect($guest->fresh()->reply)->toBe(Reply::Declined)
        ->and($guest->options()->count())->toBe(0);
});

it('lets a Guest change their Reply and their Options until Closed', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    $lunch = option($invitation, 'Lunch');
    $tour = option($invitation, 'Tour');
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'accepted', 'options' => [$lunch->id]]);
    replyAs($member, $invitation, ['reply' => 'accepted', 'options' => [$tour->id]]);
    expect($guest->options()->pluck('options.id')->all())->toBe([$tour->id]);

    replyAs($member, $invitation, ['reply' => 'declined']);
    expect($guest->fresh()->reply)->toBe(Reply::Declined)
        ->and($guest->options()->count())->toBe(0);

    replyAs($member, $invitation, ['reply' => 'accepted', 'options' => [$lunch->id]]);
    expect($guest->fresh()->reply)->toBe(Reply::Accepted)
        ->and($guest->options()->pluck('options.id')->all())->toBe([$lunch->id]);
});

it('refuses another Invitation\'s Option', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    $foreign = option(openInvitation());
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'accepted', 'options' => [$foreign->id]])
        ->assertSessionHasErrors('options.0');

    expect($guest->fresh()->reply)->toBe(Reply::NoReply);
});

it('refuses anything but accepting or declining', function (mixed $reply) {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => $reply])->assertSessionHasErrors('reply');
})->with(['no_reply', 'maybe', null]);

it('takes the Guest from the signed-in Member, never from the request', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    $own = guestOn($invitation, $member);
    $other = guestOn($invitation);

    replyAs($member, $invitation, [
        'reply' => 'accepted',
        'guest' => $other->id,
        'guest_id' => $other->id,
        'member' => $other->member_id,
        'member_id' => $other->member_id,
    ]);

    expect($own->fresh()->reply)->toBe(Reply::Accepted)
        ->and($other->fresh()->reply)->toBe(Reply::NoReply);
});

it('refuses a Reply from a Member who is not a Guest, and adds no row', function () {
    $invitation = openInvitation();
    guestOn($invitation);

    replyAs(Member::factory()->create(), $invitation, ['reply' => 'accepted'])->assertForbidden();

    expect($invitation->guests()->count())->toBe(1);
});

it('refuses a Reply on a Draft', function () {
    $member = Member::factory()->create();
    $invitation = Invitation::factory()->create();
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'accepted'])->assertForbidden();

    expect($guest->fresh()->reply)->toBe(Reply::NoReply);
});

it('refuses a Reply once the Invitation is Closed', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation(['date' => now()->subDays(2)->toDateString()]);
    $guest = guestOn($invitation, $member);

    replyAs($member, $invitation, ['reply' => 'accepted'])->assertForbidden();

    expect($guest->fresh()->reply)->toBe(Reply::NoReply);
});

it('refuses a removed Guest', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation();
    guestOn($invitation, $member)->delete();

    replyAs($member, $invitation, ['reply' => 'accepted'])->assertForbidden();
});

it('holds the super-tier to the same Reply rules as any Guest', function () {
    $superTier = Member::factory()->superTier()->create();
    $notOn = openInvitation();
    $closed = openInvitation(['date' => now()->subDays(2)->toDateString()]);
    $draft = Invitation::factory()->create();
    $closedGuest = guestOn($closed, $superTier);
    $draftGuest = guestOn($draft, $superTier);

    replyAs($superTier, $notOn, ['reply' => 'accepted'])->assertForbidden();
    replyAs($superTier, $closed, ['reply' => 'accepted'])->assertForbidden();
    replyAs($superTier, $draft, ['reply' => 'accepted'])->assertForbidden();

    expect($notOn->guests()->count())->toBe(0)
        ->and($closedGuest->fresh()->reply)->toBe(Reply::NoReply)
        ->and($draftGuest->fresh()->reply)->toBe(Reply::NoReply);
});

it('lets a super-tier Guest reply to an Open Invitation', function () {
    $superTier = Member::factory()->superTier()->create();
    $invitation = openInvitation();
    $guest = guestOn($invitation, $superTier);

    replyAs($superTier, $invitation, ['reply' => 'accepted'])->assertRedirect();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

// --- Closed is computed ------------------------------------------------------

it('stays Open through the occasion\'s own day on the museum\'s clock, then Closes', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation(['date' => '2027-05-14']);
    $guest = guestOn($invitation, $member);

    // 21:00 in Toronto on the 14th, already the 15th in UTC.
    $this->travelTo('2027-05-15 01:00:00');
    replyAs($member, $invitation, ['reply' => 'accepted'])->assertRedirect();
    $this->actingAs($member)->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1));

    // 00:30 in Toronto on the 15th.
    $this->travelTo('2027-05-15 04:30:00');
    replyAs($member, $invitation, ['reply' => 'declined'])->assertForbidden();
    $this->actingAs($member)->get(route('invitations'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('reopens when the Host moves the date into the future', function () {
    $member = Member::factory()->create();
    $invitation = openInvitation(['date' => now()->subWeek()->toDateString()]);
    guestOn($invitation, $member);

    $invitation->update(['date' => now()->addWeek()->toDateString()]);

    replyAs($member, $invitation, ['reply' => 'accepted'])->assertRedirect();
});
