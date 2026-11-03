<?php

use App\Enums\BroadcastKind;
use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Sending an Invitation (#907, spec #901, ADR-0034 §7, §8). The Host's mail rides the Broadcast
 * send path: this file covers what a send writes. InvitationDrainTest covers what the Drain
 * does with it.
 */

/**
 * @param  array<string, mixed>  $overrides
 */
function sendInvitation(Member $actor, Invitation $invitation, array $overrides = []): TestResponse
{
    return test()->actingAs($actor)->postJson(route('broadcasts.store'), array_merge([
        'context' => 'invitation',
        'context_subject' => (string) $invitation->id,
        'audience' => 'invitation_guests',
        'subject' => 'The Annual Meeting',
        'body' => '<p>Please join us.</p>',
    ], $overrides));
}

function guestRow(Invitation $invitation, array $member = []): Guest
{
    return Guest::factory()->create([
        'invitation_id' => $invitation->id,
        'member_id' => Member::factory()->create($member)->id,
    ]);
}

/** @return list<int> */
function invitationRecipients(): array
{
    return Delivery::query()->where('kind', DeliveryKind::Invitation)->pluck('member_id')->sort()->values()->all();
}

beforeEach(function () {
    $this->host = Member::factory()->create();
    $this->invitation = Invitation::factory()->create(['host_id' => $this->host->id]);
});

it('writes one Delivery per Guest and a sender copy, opens the Draft, and sends nothing', function () {
    Mail::fake();
    $guests = collect([guestRow($this->invitation), guestRow($this->invitation)]);

    sendInvitation($this->host, $this->invitation)
        ->assertOk()
        ->assertJson(['queued' => 2, 'skipped' => []]);

    $broadcast = Broadcast::sole();
    expect($broadcast->kind)->toBe(BroadcastKind::Invitation)
        ->and($broadcast->invitation_id)->toBe($this->invitation->id)
        ->and($broadcast->sender_id)->toBe($this->host->id)
        ->and($broadcast->group_id)->toBeNull()
        ->and($broadcast->recipient_count)->toBe(2)
        ->and(invitationRecipients())->toBe($guests->pluck('member_id')->sort()->values()->all())
        ->and($broadcast->deliveries()->where('kind', DeliveryKind::SenderCopy)->sole()->member_id)->toBe($this->host->id)
        ->and($this->invitation->fresh()->state)->toBe(InvitationState::Open);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('leaves an Invitation untouched when the Host only previews the recipients', function () {
    guestRow($this->invitation);

    $this->actingAs($this->host)
        ->getJson(route('audiences.show', [
            'audience' => 'invitation_guests',
            'context' => 'invitation',
            'subject' => $this->invitation->id,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'recipients');

    expect($this->invitation->fresh()->state)->toBe(InvitationState::Draft)
        ->and(Broadcast::count())->toBe(0)
        ->and(Delivery::count())->toBe(0);
});

it('lets the super-tier send any Invitation', function () {
    guestRow($this->invitation);

    sendInvitation(Member::factory()->superTier()->create(), $this->invitation)->assertOk();

    expect($this->invitation->fresh()->state)->toBe(InvitationState::Open);
});

it('refuses anyone but the Host, a Guest included, and writes nothing', function () {
    $guest = guestRow($this->invitation);

    sendInvitation($guest->member, $this->invitation)->assertForbidden();

    expect($this->invitation->fresh()->state)->toBe(InvitationState::Draft)
        ->and(Broadcast::count())->toBe(0);
});

it('refuses a Host who has lost Full access', function () {
    $host = Member::factory()->category(Category::Provisional)->create();
    $invitation = Invitation::factory()->create(['host_id' => $host->id]);
    guestRow($invitation);

    sendInvitation($host, $invitation)->assertForbidden();

    expect($invitation->fresh()->state)->toBe(InvitationState::Draft);
});

it('refuses a deleted Invitation', function () {
    guestRow($this->invitation);
    $this->invitation->delete();

    sendInvitation($this->host, $this->invitation)->assertNotFound();
});

it('skips a no-email Guest and names them', function () {
    $reached = guestRow($this->invitation);
    guestRow($this->invitation, ['first_name' => 'Quiet', 'last_name' => 'Guest', 'no_email' => true]);

    sendInvitation($this->host, $this->invitation)
        ->assertOk()
        ->assertJson(['queued' => 1, 'skipped' => ['Quiet Guest']]);

    expect(invitationRecipients())->toBe([$reached->member_id]);
});

it('sends nothing to a Guest who is no longer current', function (Category $category) {
    $reached = guestRow($this->invitation);
    guestRow($this->invitation, ['category' => $category]);

    sendInvitation($this->host, $this->invitation)->assertOk()->assertJson(['queued' => 1]);

    expect(invitationRecipients())->toBe([$reached->member_id]);
})->with([
    'resigned' => [Category::Resigned],
    'provisional, who has no Directory listing' => [Category::Provisional],
]);

it('refuses a send on a Draft that would not reach every Guest, and writes nothing', function (Closure $request) {
    guestRow($this->invitation);
    $removable = guestRow($this->invitation);

    sendInvitation($this->host, $this->invitation, $request($removable))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audience');

    expect($this->invitation->fresh()->state)->toBe(InvitationState::Draft)
        ->and(Broadcast::count())->toBe(0)
        ->and(Delivery::count())->toBe(0);
})->with([
    'No reply only' => [fn () => ['audience' => 'invitation_no_reply']],
    'a Guest removed' => [fn (Guest $guest) => ['removed' => [$guest->member_id]]],
]);

it('still checks who is sending before it refuses a Draft send', function () {
    $guest = guestRow($this->invitation);

    sendInvitation($guest->member, $this->invitation, ['audience' => 'invitation_no_reply'])->assertForbidden();
});

it('lets a Host remove a Guest from a re-send', function () {
    $this->invitation->state = InvitationState::Open;
    $this->invitation->save();
    $reached = guestRow($this->invitation);
    $removed = guestRow($this->invitation);

    sendInvitation($this->host, $this->invitation, ['removed' => [$removed->member_id]])->assertOk();

    expect(invitationRecipients())->toBe([$reached->member_id]);
});

it('stays a Draft when no Guest can be reached', function () {
    guestRow($this->invitation, ['no_email' => true]);

    sendInvitation($this->host, $this->invitation)->assertUnprocessable();

    expect($this->invitation->fresh()->state)->toBe(InvitationState::Draft)
        ->and(Broadcast::count())->toBe(0);
});

it('re-sends to all Guests, whatever their Reply', function () {
    $this->invitation->state = InvitationState::Open;
    $this->invitation->save();
    $accepted = guestRow($this->invitation);
    $accepted->setReply(Reply::Accepted);
    guestRow($this->invitation);

    sendInvitation($this->host, $this->invitation)->assertOk()->assertJson(['queued' => 2]);
});

it('re-sends to Guests with No reply only', function () {
    $this->invitation->state = InvitationState::Open;
    $this->invitation->save();
    guestRow($this->invitation)->setReply(Reply::Accepted);
    guestRow($this->invitation)->setReply(Reply::Declined);
    $waiting = guestRow($this->invitation);

    sendInvitation($this->host, $this->invitation, ['audience' => 'invitation_no_reply'])
        ->assertOk()
        ->assertJson(['queued' => 1]);

    expect(invitationRecipients())->toBe([$waiting->member_id]);
});

it('refuses an Audience from outside the Invitation', function () {
    guestRow($this->invitation);

    sendInvitation($this->host, $this->invitation, ['audience' => 'all_members'])->assertForbidden();
});

it('refuses an added Member who is not a Guest', function () {
    guestRow($this->invitation);
    $stranger = Member::factory()->create();

    sendInvitation($this->host, $this->invitation, ['added' => [$stranger->id]])->assertUnprocessable();

    expect(Broadcast::count())->toBe(0);
});

it('sends nothing when the Invitation changes after sending', function (Closure $change) {
    Mail::fake();
    $guest = guestRow($this->invitation);
    sendInvitation($this->host, $this->invitation)->assertOk();
    Delivery::query()->update(['state' => DeliveryState::Sent]);
    $rows = Delivery::count();

    $change($this->actingAs($this->host), $this->invitation, $guest);

    expect(Delivery::count())->toBe($rows);
})->with([
    'a changed date' => [fn ($test, $invitation) => $test->patch(route('invitations.update', ['invitation' => $invitation->id]), [
        'name' => $invitation->name,
        'date' => now()->addMonth()->toDateString(),
        'start_time' => $invitation->start_time,
        'end_time' => $invitation->end_time,
        'description' => $invitation->description,
        'options' => [],
    ])->assertRedirect(route('invitations.hosting'))],
    'a removed Guest' => [fn ($test, $invitation, $guest) => $test->delete(route('invitations.guests.destroy', ['invitation' => $invitation->id, 'guest' => $guest->id]))->assertRedirect()],
    'a deleted Invitation' => [fn ($test, $invitation) => $test->delete(route('invitations.destroy', ['invitation' => $invitation->id]))->assertRedirect()],
    'a Guest added late' => [fn ($test, $invitation) => $test->post(route('invitations.guests.store', ['invitation' => $invitation->id]), ['member' => Member::factory()->create()->id])->assertRedirect()],
]);

it('prefills the mail from the Invitation, escaping what the Host wrote', function () {
    $invitation = Invitation::factory()->create([
        'host_id' => $this->host->id,
        'name' => 'Tea & <Cake>',
        'date' => '2027-05-14',
        'start_time' => '10:30',
        'end_time' => '12:00',
        'description' => "First line\nSecond line",
    ]);

    $this->actingAs($this->host)
        ->get(route('invitations.hosting'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitations', fn ($rows) => collect($rows)->firstWhere('id', $invitation->id)['mail'] === [
                'subject' => 'Invitation: Tea & <Cake>',
                'body' => '<p>You are invited to Tea &amp; &lt;Cake&gt; on 14 May 2027, from 10:30 to 12:00.</p><p>First line<br>'."\n".'Second line</p>',
            ]));
});
