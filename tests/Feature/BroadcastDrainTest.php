<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\BroadcastMail;
use App\Mail\BroadcastSenderCopy;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * The Drain's Broadcast handling (#488, spec #479, ADR-0024 §4). The send path (BroadcastSendTest)
 * writes the rows; this file covers what the Drain does with them: the From display name and the
 * officer's Reply-To on each recipient copy, the sender copy held until every sibling is terminal,
 * the footer naming who was not reached, and the attachments deleted once the copy is out. Prior
 * art: DrainFailureTest for the Drain's failure classes.
 */

/** A Broadcast sent record for the given Group (null for an org-wide send), with its sender. */
function drainBroadcast(?Group $group, array $overrides = []): Broadcast
{
    $sender = Member::factory()->create(['first_name' => 'Officer', 'last_name' => 'One']);

    return Broadcast::factory()->create(array_merge([
        'sender_id' => $sender->id,
        'group_id' => $group?->id,
        'subject' => 'Season opening',
    ], $overrides));
}

/** A pending recipient Delivery on the Broadcast, addressed to a fresh Member. */
function recipientRow(Broadcast $broadcast, array $overrides = []): Delivery
{
    $member = Member::factory()->create();

    return $broadcast->deliveries()->create(array_merge([
        'kind' => DeliveryKind::Broadcast,
        'member_id' => $member->id,
        'email' => $member->email,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ], $overrides));
}

/** The one sender-copy Delivery on the Broadcast, addressed to its sender. */
function senderCopyRow(Broadcast $broadcast): Delivery
{
    return $broadcast->deliveries()->create([
        'kind' => DeliveryKind::SenderCopy,
        'member_id' => $broadcast->sender_id,
        'email' => $broadcast->sender->email,
        'state' => DeliveryState::Pending,
        'next_attempt_at' => now(),
    ]);
}

it('carries the Group name as the From display and the officer as Reply-To', function () {
    $group = Group::factory()->create(['name' => 'Docents']);
    $broadcast = drainBroadcast($group)->load('sender', 'group');

    $envelope = (new BroadcastMail($broadcast))->envelope();

    expect($envelope->from->address)->toBe(config('mail.from.address'))
        ->and($envelope->from->name)->toBe('Docents')
        ->and($envelope->replyTo[0]->address)->toBe($broadcast->sender->email);
});

it('uses the app name as the From display for an org-wide Broadcast', function () {
    $broadcast = drainBroadcast(null)->load('sender', 'group');

    expect((new BroadcastMail($broadcast))->envelope()->from->name)->toBe(config('app.name'));
});

it('sends a recipient copy to the recipient', function () {
    Mail::fake();
    $broadcast = drainBroadcast(Group::factory()->create(['name' => 'Docents']));
    $row = recipientRow($broadcast);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $mail): bool => $mail->hasTo($row->email));
    expect($row->fresh()->state)->toBe(DeliveryState::Sent);
});

it('holds the sender copy while a recipient is still pending', function () {
    Mail::fake();
    $broadcast = drainBroadcast(Group::factory()->create());
    // A recipient still pending but not yet due — retrying next attempt in ten minutes.
    recipientRow($broadcast, ['next_attempt_at' => now()->addMinutes(10)]);
    $copy = senderCopyRow($broadcast);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertNotSent(BroadcastSenderCopy::class);
    expect($copy->fresh()->state)->toBe(DeliveryState::Pending);
});

it('sends the sender copy once siblings are terminal, names the failed, and deletes attachments', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::disk('local')->put('broadcast-attachments/file.pdf', 'data');

    $broadcast = drainBroadcast(Group::factory()->create(), [
        'attachments' => [['name' => 'agenda.pdf', 'size' => 4, 'path' => 'broadcast-attachments/file.pdf']],
    ]);
    $unreached = Member::factory()->create(['first_name' => 'Unreached', 'last_name' => 'Member']);
    $broadcast->deliveries()->create([
        'kind' => DeliveryKind::Broadcast,
        'member_id' => $unreached->id,
        'email' => $unreached->email,
        'state' => DeliveryState::Failed,
        'failed_at' => now(),
    ]);
    $copy = senderCopyRow($broadcast);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BroadcastSenderCopy::class, function (BroadcastSenderCopy $mail) use ($broadcast): bool {
        return $mail->hasTo($broadcast->sender->email) && $mail->failedNames === ['Unreached Member'];
    });
    expect($copy->fresh()->state)->toBe(DeliveryState::Sent);
    Storage::disk('local')->assertMissing('broadcast-attachments/file.pdf');
});
