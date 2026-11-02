<?php

use App\Enums\Category;
use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\InvitationState;
use App\Enums\Reply;
use App\Models\Delivery;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/*
 * The Invitation half of the daily pass (#909, ADR-0034 §12). The pass writes one Reminder per
 * (Invitation, Member) for Accepted Guests on Open Invitations, from now to the end of today
 * plus 4 days, for a Member without the no-email flag. The row expires at the occasion's start.
 * Prior art: ReminderPassTest.
 */

beforeEach(function () {
    // Noon at the museum (EST) on a Tuesday, so "today plus 4" is 14 November.
    $this->travelTo(Carbon::parse('2026-11-10 17:00:00', 'UTC'));
});

/** An Open Invitation on $date at 10:00 at the museum. */
function occasionOn(string $date, array $overrides = []): Invitation
{
    return Invitation::factory()->create([
        'state' => InvitationState::Open,
        'date' => $date,
        'start_time' => '10:00',
        ...$overrides,
    ]);
}

/** A Guest on $invitation with this Reply. */
function repliedGuest(Invitation $invitation, Reply $reply = Reply::Accepted, ?Member $member = null): Guest
{
    return Guest::factory()->create([
        'invitation_id' => $invitation->id,
        'member_id' => ($member ?? Member::factory()->create())->id,
        'reply' => $reply,
    ]);
}

it('writes one pending Reminder for an Accepted Guest, expiring at the occasion’s start', function () {
    $invitation = occasionOn('2026-11-12');
    $guest = repliedGuest($invitation);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    $row = Delivery::sole();
    expect($row->kind)->toBe(DeliveryKind::InvitationReminder);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->invitation_id)->toBe($invitation->id);
    expect($row->member_id)->toBe($guest->member_id);
    expect($row->email)->toBe($guest->member->email);
    expect($row->next_attempt_at)->not->toBeNull();
    // 10:00 at the museum in November is 15:00 UTC.
    expect($row->expires_at->equalTo(Carbon::parse('2026-11-12 15:00:00', 'UTC')))->toBeTrue();
});

it('runs the window to the end of today plus 4 days, on the museum’s clock', function () {
    $lastDay = occasionOn('2026-11-14', ['start_time' => '23:30']);
    $oneTooFar = occasionOn('2026-11-15', ['start_time' => '00:30']);
    repliedGuest($lastDay);
    repliedGuest($oneTooFar);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::sole()->invitation_id)->toBe($lastDay->id);
});

it('keeps today’s later occasion in the window and leaves out one already started', function () {
    $later = occasionOn('2026-11-10', ['start_time' => '15:00']);
    $started = occasionOn('2026-11-10', ['start_time' => '11:00']);
    repliedGuest($later);
    repliedGuest($started);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::sole()->invitation_id)->toBe($later->id);
});

it('writes nothing for a Guest who has not accepted, a Draft, a deleted Invitation, or a flagged Member', function () {
    $open = occasionOn('2026-11-12');
    repliedGuest($open, Reply::NoReply);
    repliedGuest($open, Reply::Declined);

    repliedGuest(occasionOn('2026-11-12', ['state' => InvitationState::Draft]));

    $deleted = occasionOn('2026-11-12');
    repliedGuest($deleted);
    $deleted->delete();

    $flagged = Member::factory()->create();
    $flagged->no_email = true;
    $flagged->save();
    repliedGuest($open, Reply::Accepted, $flagged);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(0);
});

it('writes nothing for a Guest whose Category has stopped being current', function () {
    repliedGuest(occasionOn('2026-11-12'), Reply::Accepted, Member::factory()->create(['category' => Category::Resigned]));

    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(0);
});

it('writes no second Reminder on a later pass', function () {
    repliedGuest(occasionOn('2026-11-12'));

    $this->artisan('mail:daily-pass')->assertSuccessful();
    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::count())->toBe(1);
});

it('reminds a Guest who accepts inside the window on the next pass', function () {
    $guest = repliedGuest(occasionOn('2026-11-12'), Reply::NoReply);

    $this->artisan('mail:daily-pass')->assertSuccessful();
    expect(Delivery::count())->toBe(0);

    $guest->setReply(Reply::Accepted);
    $this->travel(1)->day();
    $this->artisan('mail:daily-pass')->assertSuccessful();

    expect(Delivery::sole()->member_id)->toBe($guest->member_id);
});

it('makes one Reminder per Invitation and Member a database fact', function () {
    $invitation = occasionOn('2026-11-12');
    $member = Member::factory()->create();
    $row = fn () => Delivery::create([
        'kind' => DeliveryKind::InvitationReminder,
        'invitation_id' => $invitation->id,
        'member_id' => $member->id,
        'email' => $member->email,
    ]);

    $row();

    expect($row)->toThrow(UniqueConstraintViolationException::class);
});
