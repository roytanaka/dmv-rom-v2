<?php

use App\Enums\BroadcastKind;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Models\Broadcast;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\Member;
use App\Support\Mail\BroadcastAttachmentStorage;
use Database\Seeders\OrgTreeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * The Broadcast send path and the sender's copy (#488, spec #479, ADR-0024 §4, §6). The server
 * side of the composer: a send re-resolves its Audience, writes the sent record and one Delivery
 * per recipient plus a sender copy, and sends nothing in the request. The Drain later carries the
 * From display name and the officer's Reply-To, holds the sender copy until every sibling is
 * terminal, names who was not reached, and deletes the attachments. Prior art: AudienceEndpointTest
 * for the resolver seam, DrainFailureTest for the Drain.
 */

beforeEach(function () {
    $this->seed(OrgTreeSeeder::class);
    $this->program = Group::where('slug', OrgTreeSeeder::PROGRAM)->firstOrFail();
    $this->scheduler = Member::where('email', OrgTreeSeeder::SCHEDULER_EMAIL)->firstOrFail();
    $this->trainee = Member::where('email', 'trainee@dmv.test')->firstOrFail();
});

/** The payload of a well-formed Group Broadcast to the program's whole group. */
function broadcastPayload(array $overrides = []): array
{
    return array_merge([
        'context' => 'group',
        'context_subject' => 'docents-program',
        'audience' => 'whole_group',
        'subject' => 'A note to the group',
        'body' => '<p>Please read the update.</p>',
    ], $overrides);
}

it('requires authentication', function () {
    $this->postJson(route('broadcasts.store'), broadcastPayload())->assertUnauthorized();
});

it('writes the record, a Delivery per recipient, and a sender copy, sending nothing', function () {
    Mail::fake();

    $response = $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload())
        ->assertOk()
        ->assertJson(['queued' => 2, 'skipped' => []]);

    $broadcast = Broadcast::sole();
    expect($broadcast->kind)->toBe(BroadcastKind::Broadcast)
        ->and($broadcast->sender_id)->toBe($this->scheduler->id)
        ->and($broadcast->group_id)->toBe($this->program->id)
        ->and($broadcast->audience_key)->toBe('whole_group')
        ->and($broadcast->recipient_count)->toBe(2)
        ->and($broadcast->subject)->toBe('A note to the group')
        ->and($broadcast->edited)->toBeFalse();

    expect($broadcast->deliveries()->where('kind', DeliveryKind::Broadcast)->count())->toBe(2)
        ->and($broadcast->deliveries()->where('kind', DeliveryKind::SenderCopy)->count())->toBe(1);

    $senderCopy = $broadcast->deliveries()->where('kind', DeliveryKind::SenderCopy)->sole();
    expect($senderCopy->member_id)->toBe($this->scheduler->id)
        ->and($senderCopy->state)->toBe(DeliveryState::Pending);

    Mail::assertNothingSent();
});

it('refuses a forbidden Audience with 403', function () {
    // The Trainee is a member of the program but not an officer, so may not pick its officers.
    $this->actingAs($this->trainee)
        ->postJson(route('broadcasts.store'), broadcastPayload(['audience' => 'group_officers']))
        ->assertForbidden();

    expect(Broadcast::count())->toBe(0);
});

it('rejects an edit id outside the resolved set with 422', function () {
    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload(['removed' => [999999]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('removed');
});

it('sets the edited flag and the "N removed" label when a chip is removed', function () {
    Mail::fake();

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload(['removed' => [$this->trainee->id]]))
        ->assertOk()
        ->assertJson(['queued' => 1]);

    $broadcast = Broadcast::sole();
    expect($broadcast->edited)->toBeTrue()
        ->and($broadcast->audience_label)->toBe('Whole group, 1 removed')
        ->and($broadcast->recipient_count)->toBe(1)
        ->and($broadcast->deliveries()->where('kind', DeliveryKind::Broadcast)->count())->toBe(1);
});

it('skips a no-email flagged Member, writing no row and naming them', function () {
    Mail::fake();
    $this->trainee->forceFill(['no_email' => true])->save();

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload())
        ->assertOk()
        ->assertJson(['queued' => 1, 'skipped' => [$this->trainee->fullName()]]);

    $broadcast = Broadcast::sole();
    expect($broadcast->recipient_count)->toBe(1)
        ->and($broadcast->deliveries()->where('member_id', $this->trainee->id)->exists())->toBeFalse();
});

it('stores the body sanitized against the allowlist', function () {
    Mail::fake();

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload([
            'body' => '<p onclick="steal()">Hi</p><script>alert(1)</script>',
        ]))
        ->assertOk();

    expect(Broadcast::sole()->body)->toBe('<p>Hi</p>');
});

it('rejects three attachments', function () {
    Storage::fake('local');

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload([
            'attachments' => [
                UploadedFile::fake()->create('a.pdf', 10),
                UploadedFile::fake()->create('b.pdf', 10),
                UploadedFile::fake()->create('c.pdf', 10),
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachments');
});

it('rejects two attachments over 10 MB together', function () {
    Storage::fake('local');

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload([
            'attachments' => [
                UploadedFile::fake()->create('a.pdf', 6 * 1024),
                UploadedFile::fake()->create('b.pdf', 6 * 1024),
            ],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachments');
});

it('stores valid attachments on the private disk under UUID names', function () {
    Mail::fake();
    Storage::fake('local');

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload([
            'attachments' => [UploadedFile::fake()->create('report.pdf', 200)],
        ]))
        ->assertOk();

    $attachments = Broadcast::sole()->attachments;
    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]['name'])->toBe('report.pdf')
        ->and($attachments[0]['path'])->toStartWith(BroadcastAttachmentStorage::DIRECTORY.'/')
        ->and($attachments[0]['path'])->not->toContain('report.pdf');

    Storage::disk('local')->assertExists($attachments[0]['path']);
});

it('marks a recipient with an invalid address failed on write', function () {
    Mail::fake();
    $this->trainee->update(['email' => 'not-an-address']);

    $this->actingAs($this->scheduler)
        ->postJson(route('broadcasts.store'), broadcastPayload())
        ->assertOk();

    $row = Delivery::where('member_id', $this->trainee->id)
        ->where('kind', DeliveryKind::Broadcast)
        ->sole();

    expect($row->state)->toBe(DeliveryState::Failed)
        ->and($row->failed_at)->not->toBeNull();
});
