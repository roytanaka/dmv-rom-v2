<?php

use App\Enums\Category;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use App\Support\OrgTime;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Host's Replies page (#905, spec #901, ADR-0034 §10, §11): who said what, with
 * totals, and a Reply set for a Guest. Acts through the HTTP routes as a Member and checks
 * Inertia props.
 */

function repliesOf(array $attributes = []): Invitation
{
    $invitation = Invitation::factory()->create($attributes);
    $invitation->state = InvitationState::Open;
    $invitation->save();

    return $invitation;
}

function guestWithReply(Invitation $invitation, Reply $reply, array $member = []): Guest
{
    return Guest::factory()->create([
        'invitation_id' => $invitation->id,
        'member_id' => Member::factory()->create($member)->id,
        'reply' => $reply,
    ]);
}

function repliesUrl(Invitation $invitation): string
{
    return route('invitations.replies', ['invitation' => $invitation->id]);
}

// --- The page -----------------------------------------------------------------

it('shows totals for Accepted, Declined, No reply and each Option', function () {
    $invitation = repliesOf();
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $tour = $invitation->options()->create(['name' => 'Tour']);
    guestWithReply($invitation, Reply::Accepted)->options()->attach([$lunch->id, $tour->id]);
    guestWithReply($invitation, Reply::Accepted)->options()->attach($lunch->id);
    guestWithReply($invitation, Reply::Accepted);
    guestWithReply($invitation, Reply::Declined);
    guestWithReply($invitation, Reply::NoReply);
    guestWithReply($invitation, Reply::NoReply);

    $this->actingAs($invitation->host)
        ->get(repliesUrl($invitation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/Replies')
            ->where('totals', [
                'accepted' => 3,
                'declined' => 1,
                'no_reply' => 2,
                'options' => [
                    ['id' => $lunch->id, 'name' => 'Lunch', 'count' => 2],
                    ['id' => $tour->id, 'name' => 'Tour', 'count' => 1],
                ],
            ]));
});

it('lists every Guest with their Reply and Options, Accepted first and then by surname', function () {
    $invitation = repliesOf();
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $silent = guestWithReply($invitation, Reply::NoReply, ['first_name' => 'Ann', 'last_name' => 'Abel']);
    $declined = guestWithReply($invitation, Reply::Declined, ['first_name' => 'Bo', 'last_name' => 'Bell']);
    $later = guestWithReply($invitation, Reply::Accepted, ['first_name' => 'Di', 'last_name' => 'Dunn']);
    $sooner = guestWithReply($invitation, Reply::Accepted, ['first_name' => 'Cy', 'last_name' => 'Cole']);
    $later->options()->attach($lunch->id);

    $this->actingAs($invitation->host)
        ->get(repliesUrl($invitation))
        ->assertInertia(fn (Assert $page) => $page->where('guests', [
            ['id' => $sooner->id, 'member' => $sooner->member_id, 'name' => 'Cy Cole', 'reply' => 'accepted', 'options' => []],
            ['id' => $later->id, 'member' => $later->member_id, 'name' => 'Di Dunn', 'reply' => 'accepted', 'options' => [$lunch->id]],
            ['id' => $declined->id, 'member' => $declined->member_id, 'name' => 'Bo Bell', 'reply' => 'declined', 'options' => []],
            ['id' => $silent->id, 'member' => $silent->member_id, 'name' => 'Ann Abel', 'reply' => 'no_reply', 'options' => []],
        ]));
});

it('gives the print view what it heads the list with', function () {
    $invitation = repliesOf(['name' => 'Annual Meeting', 'date' => '2030-05-01', 'start_time' => '10:00', 'end_time' => '12:00']);

    $this->actingAs($invitation->host)
        ->get(repliesUrl($invitation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitation.name', 'Annual Meeting')
            ->where('invitation.date', '2030-05-01')
            ->where('invitation.start_time', '10:00')
            ->where('invitation.end_time', '12:00'));
});

it('holds no email address in the payload', function () {
    $host = Member::factory()->create(['email' => 'host-address@example.test']);
    $invitation = repliesOf(['host_id' => $host->id]);
    $guest = guestWithReply($invitation, Reply::Accepted, ['email' => 'guest-address@example.test']);
    $candidate = Member::factory()->create(['email' => 'candidate-address@example.test']);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(repliesUrl($invitation))
        ->assertInertia(fn (Assert $page) => $page->has('guests', 1))
        ->assertDontSee('host-address@example.test')
        ->assertDontSee($guest->member->email)
        ->assertDontSee($candidate->email);
});

it('offers current Members not on the list, so a Host can reply for them', function () {
    $invitation = repliesOf(['host_id' => Member::factory()->create(['first_name' => 'Hal', 'last_name' => 'Host'])->id]);
    guestWithReply($invitation, Reply::NoReply);
    $candidate = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);
    Member::factory()->category(Category::Provisional)->create();

    $this->actingAs($invitation->host)
        ->get(repliesUrl($invitation))
        ->assertInertia(fn (Assert $page) => $page->where('candidates', [
            ['id' => $invitation->host_id, 'name' => 'Hal Host'],
            ['id' => $candidate->id, 'name' => 'Ada Lee'],
        ]));
});

// --- A Host sets a Reply -----------------------------------------------------------

it('lets a Host accept for a Guest with Options', function () {
    $invitation = repliesOf();
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $guest = guestWithReply($invitation, Reply::NoReply);

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'accepted', 'options' => [$lunch->id]])
        ->assertRedirect(repliesUrl($invitation));

    $guest->refresh();
    expect($guest->reply)->toBe(Reply::Accepted)
        ->and($guest->options->pluck('id')->all())->toBe([$lunch->id]);
});

it('clears Options when a Host declines for a Guest', function () {
    $invitation = repliesOf();
    $lunch = $invitation->options()->create(['name' => 'Lunch']);
    $guest = guestWithReply($invitation, Reply::Accepted);
    $guest->options()->attach($lunch->id);

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'declined', 'options' => [$lunch->id]])
        ->assertRedirect();

    expect($guest->fresh()->reply)->toBe(Reply::Declined)
        ->and($guest->options()->count())->toBe(0);
});

it('lets a Host set a Reply after Closed', function () {
    $invitation = repliesOf(['date' => OrgTime::today()->subDay()->toDateString()]);
    $guest = guestWithReply($invitation, Reply::NoReply);

    expect($invitation->isClosed())->toBeTrue();

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'accepted'])
        ->assertRedirect();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('adds a Member who is not on the list as a Guest with the Reply', function () {
    $invitation = repliesOf();
    $member = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lee']);

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $member->id, 'reply' => 'accepted'])
        ->assertRedirect();

    $this->actingAs($invitation->host)
        ->get(repliesUrl($invitation))
        ->assertInertia(fn (Assert $page) => $page
            ->has('guests', 1)
            ->where('guests.0.name', 'Ada Lee')
            ->where('guests.0.reply', 'accepted'));
});

it('adds no Guest when their Reply cannot be written', function () {
    $invitation = Invitation::factory()->create();
    $member = Member::factory()->create();

    expect(fn () => $invitation->setReplyFor($member, Reply::Accepted, [999999]))->toThrow(QueryException::class);

    expect($invitation->guests()->count())->toBe(0);
});

it('still lets a Host answer for a Guest who is no longer current', function () {
    $invitation = repliesOf();
    $guest = guestWithReply($invitation, Reply::NoReply, ['category' => Category::Resigned]);

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'declined'])
        ->assertRedirect();

    expect($guest->fresh()->reply)->toBe(Reply::Declined);
});

it('refuses to add a Member who is not current', function (Category $category) {
    $invitation = repliesOf();
    $member = Member::factory()->category($category)->create();

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $member->id, 'reply' => 'accepted'])
        ->assertSessionHasErrors('member');

    expect($invitation->guests()->count())->toBe(0);
})->with([
    'resigned' => [Category::Resigned],
    'provisional, who has no Directory listing' => [Category::Provisional],
]);

it('refuses an Option from another Invitation and a Reply that is not an answer', function () {
    $invitation = repliesOf();
    $guest = guestWithReply($invitation, Reply::NoReply);
    $elsewhere = repliesOf()->options()->create(['name' => 'Tour']);

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'accepted', 'options' => [$elsewhere->id]])
        ->assertSessionHasErrors('options.0');

    $this->actingAs($invitation->host)
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'maybe'])
        ->assertSessionHasErrors('reply');

    expect($guest->fresh()->reply)->toBe(Reply::NoReply);
});

it('lets the super-tier set a Reply on any Invitation', function () {
    $invitation = repliesOf();
    $guest = guestWithReply($invitation, Reply::NoReply);

    $this->actingAs(Member::factory()->superTier()->create())
        ->put(repliesUrl($invitation), ['member' => $guest->member_id, 'reply' => 'accepted'])
        ->assertRedirect();

    expect($guest->fresh()->reply)->toBe(Reply::Accepted);
});

it('refuses a Guest who tries to set a Reply for another Guest', function () {
    $invitation = repliesOf();
    $actor = guestWithReply($invitation, Reply::Accepted);
    $other = guestWithReply($invitation, Reply::NoReply);

    $this->actingAs($actor->member)
        ->put(repliesUrl($invitation), ['member' => $other->member_id, 'reply' => 'declined'])
        ->assertForbidden();

    expect($other->fresh()->reply)->toBe(Reply::NoReply);
});

// --- Who may see it -------------------------------------------------------------

it('lets the super-tier see the Replies of any Invitation', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get(repliesUrl(repliesOf()))
        ->assertOk();
});

it('refuses a Guest, a stranger and a Host who lost Full access', function () {
    $invitation = repliesOf();
    $guest = guestWithReply($invitation, Reply::Accepted);

    $this->actingAs($guest->member)->get(repliesUrl($invitation))->assertForbidden();
    $this->actingAs(Member::factory()->create())->get(repliesUrl($invitation))->assertForbidden();

    $host = Member::factory()->category(Category::Provisional)->create();
    $this->actingAs($host)->get(repliesUrl(repliesOf(['host_id' => $host->id])))->assertForbidden();
});

it('sends a signed-out visitor to the login page', function () {
    $this->get(repliesUrl(repliesOf()))->assertRedirect();
});

it('serves the page and the write at their French address', function () {
    $invitation = repliesOf();
    $guest = guestWithReply($invitation, Reply::NoReply);

    $this->withLocaleRoutes('fr', function () use ($invitation, $guest) {
        $this->actingAs($invitation->host);
        $page = "/fr/invitations/{$invitation->id}/reponses";

        $this->get($page)->assertOk();
        $this->put($page, ['member' => $guest->member_id, 'reply' => 'accepted'])->assertRedirect($page);
    });
});

it('links a Host to the Replies from Hosting, and marks a past Invitation Closed', function () {
    $host = Member::factory()->create();
    $past = repliesOf(['host_id' => $host->id, 'date' => OrgTime::today()->subDay()->toDateString()]);
    repliesOf(['host_id' => $host->id, 'date' => OrgTime::today()->addDay()->toDateString()]);

    $this->actingAs($host)
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitations.0.closed', false)
            ->where('invitations.1.id', $past->id)
            ->where('invitations.1.closed', true)
            ->where('invitations.1.can.guests', true));
});
