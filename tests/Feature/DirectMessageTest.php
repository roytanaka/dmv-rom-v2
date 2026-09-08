<?php

use App\Enums\BroadcastKind;
use App\Enums\DeliveryKind;
use App\Mail\BroadcastMail;
use App\Mail\BroadcastSenderCopy;
use App\Models\Broadcast;
use App\Models\Member;
use Database\Seeders\OrgTreeSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * Direct messages from a Member's profile (#491, spec #479, ADR-0024 §6). Any Member may write to
 * one other without ever seeing their address: the send re-uses the Broadcast path but resolves the
 * OneMember Audience, records the send with kind direct and a null Group, and sends nothing in the
 * request. The Drain later carries the sender's own name as the From display and the sender's
 * address as Reply-To, and — when the one recipient could not be reached — names them in the
 * sender's copy. A flagged recipient is refused outright: no rows are written. Prior art:
 * BroadcastSendTest for the send seam, BroadcastDrainTest for the From line and the sender copy.
 */

beforeEach(function () {
    $this->seed(OrgTreeSeeder::class);
    $this->sender = Member::where('email', 'trainee@dmv.test')->firstOrFail();
    $this->recipient = Member::where('email', OrgTreeSeeder::SCHEDULER_EMAIL)->firstOrFail();
});

/** The payload of a well-formed Direct message to the given Member. */
function directPayload(Member $to, array $overrides = []): array
{
    return array_merge([
        'context' => 'member',
        'context_subject' => (string) $to->id,
        'audience' => 'one_member',
        'subject' => 'A quick question',
        'body' => '<p>Are you on the desk Saturday?</p>',
    ], $overrides);
}

it('writes a direct-message record, one recipient Delivery, and a sender copy, sending nothing', function () {
    Mail::fake();

    $this->actingAs($this->sender)
        ->postJson(route('broadcasts.store'), directPayload($this->recipient))
        ->assertOk()
        ->assertJson(['queued' => 1, 'skipped' => []]);

    $broadcast = Broadcast::sole();
    expect($broadcast->kind)->toBe(BroadcastKind::DirectMessage)
        ->and($broadcast->sender_id)->toBe($this->sender->id)
        ->and($broadcast->group_id)->toBeNull()
        ->and($broadcast->audience_key)->toBe('one_member')
        ->and($broadcast->recipient_count)->toBe(1);

    $recipientRow = $broadcast->deliveries()->where('kind', DeliveryKind::Broadcast)->sole();
    expect($recipientRow->member_id)->toBe($this->recipient->id)
        ->and($broadcast->deliveries()->where('kind', DeliveryKind::SenderCopy)->count())->toBe(1);

    Mail::assertNothingSent();
});

it('refuses a Direct message to a flagged Member and writes no rows', function () {
    Mail::fake();
    $this->recipient->forceFill(['no_email' => true])->save();

    $this->actingAs($this->sender)
        ->postJson(route('broadcasts.store'), directPayload($this->recipient))
        ->assertStatus(422)
        ->assertJsonValidationErrors('audience');

    expect(Broadcast::count())->toBe(0);
    Mail::assertNothingSent();
});

it('carries the sender name as the From display and the sender as Reply-To', function () {
    $sender = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $broadcast = Broadcast::factory()
        ->create(['kind' => BroadcastKind::DirectMessage, 'group_id' => null, 'sender_id' => $sender->id])
        ->load('sender', 'group');

    $envelope = (new BroadcastMail($broadcast))->envelope();

    expect($envelope->from->address)->toBe(config('mail.from.address'))
        ->and($envelope->from->name)->toBe('Ada Lovelace')
        ->and($envelope->replyTo[0]->address)->toBe($sender->email);
});

it('names the one missed recipient in the sender copy of a failed Direct message', function () {
    $broadcast = Broadcast::factory()
        ->create(['kind' => BroadcastKind::DirectMessage, 'group_id' => null])
        ->load('sender', 'group');

    $rendered = (new BroadcastSenderCopy($broadcast, ['Grace Hopper']))->render();

    expect($rendered)->toContain('Could not deliver to Grace Hopper.')
        ->and($rendered)->not->toContain('Could not be delivered to:');
});
