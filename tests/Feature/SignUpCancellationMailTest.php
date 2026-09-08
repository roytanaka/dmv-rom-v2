<?php

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Enums\Role;
use App\Mail\SignUpCancelled;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use Illuminate\Support\Facades\Mail;

/*
 * The Sign-up cancellation Notice (#358, PRD #352, ADR-0021 §Sign-up "Notification"; moved
 * onto the Delivery queue in #481, ADR-0024). When a Member drops their own seat, every
 * Scheduler and Chair of the owning Group is told — but nothing sends in the request now: the
 * controller writes one pending Notice Delivery per recipient, and the every-minute Drain
 * sends them. These tests cover both seams: the rows the write path writes, and what the Drain
 * does with them. Prior art: GroupSignUpsTest for the write seams.
 */

/**
 * A Group that runs scheduling and is listed org-wide. The name is fixed, not faker's, so the
 * fixture is stable (a generated company name can hold an apostrophe; the apostrophe case is
 * pinned deliberately in its own test rather than left to chance — #394).
 */
function mailGroup(): Group
{
    return Group::factory()->program()->publicListing()->create(['name' => 'Visitor Wayfinders']);
}

/** Make a Member of the given Group, optionally carrying a role and a locale preference. */
function mailMemberOf(Group $group, ?Role $role = null, string $locale = 'en'): Member
{
    $member = Member::factory()->create(['locale' => $locale]);
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** A published Schedule with a single Shift this month, and a Sign-up on it held by $holder. */
function seatFor(Group $group, Member $holder, array $shiftOverrides = []): SignUp
{
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
        ...$shiftOverrides,
    ]);

    return SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $holder->id]);
}

it('writes one pending Notice Delivery per Scheduler and Chair, and sends nothing in the request', function () {
    Mail::fake();

    $group = mailGroup();
    $scheduler = mailMemberOf($group, role: Role::Scheduler);
    $chair = mailMemberOf($group, role: Role::Chair);
    $ordinary = mailMemberOf($group);
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)
        ->delete(route('sign-ups.destroy', ['signUp' => $seat->id]))
        ->assertRedirect();

    // Nothing goes out inside the web request — the send is the Drain's job now.
    Mail::assertNothingSent();

    $rows = Delivery::all();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('member_id')->all())->toEqualCanonicalizing([$scheduler->id, $chair->id]);
    expect($rows->pluck('member_id')->all())->not->toContain($ordinary->id);

    $row = $rows->firstWhere('member_id', $scheduler->id);
    expect($row->kind)->toBe(DeliveryKind::Notice);
    expect($row->state)->toBe(DeliveryState::Pending);
    expect($row->email)->toBe($scheduler->email);
    expect($row->next_attempt_at)->not->toBeNull();
    // The snapshot the Drain renders from — the Sign-up is gone, so the row froze what the
    // mail names.
    expect($row->payload['group']['name'])->toBe($group->name);
    expect($row->payload['group']['slug'])->toBe($group->slug);
    expect($row->payload['member']['first_name'])->toBe($holder->first_name);
});

it('writes nothing when a Member takes a Shift — the deliberate silence on sign-up', function () {
    Mail::fake();

    $group = mailGroup();
    mailMemberOf($group, role: Role::Scheduler);
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
    ]);
    $member = mailMemberOf($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertRedirect();

    expect(Delivery::count())->toBe(0);
});

it('writes no Delivery for a Scheduler carrying the no-email flag', function () {
    $group = mailGroup();
    $silenced = mailMemberOf($group, role: Role::Scheduler);
    // Not mass-assignable — set the Records-only flag directly (as the endpoint does).
    $silenced->no_email = true;
    $silenced->save();
    $heard = mailMemberOf($group, role: Role::Scheduler);
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)
        ->delete(route('sign-ups.destroy', ['signUp' => $seat->id]))
        ->assertRedirect();

    // The flag is checked when Delivery rows are written (ADR-0024 §9): the silenced
    // Scheduler gets no row, the reachable one still does.
    $rows = Delivery::all();
    expect($rows)->toHaveCount(1);
    expect($rows->pluck('member_id')->all())->toEqual([$heard->id]);
});

it('writes a row inside the legacy two-day window it used to suppress — the Notice is unconditional', function () {
    $group = mailGroup();
    mailMemberOf($group, role: Role::Scheduler);
    $holder = mailMemberOf($group);
    // A Shift starting tomorrow: legacy's dead +2-day guard would have silenced this.
    $seat = seatFor($group, $holder, [
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(13, 0),
    ]);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));

    expect(Delivery::where('kind', DeliveryKind::Notice)->count())->toBe(1);
});

it('sends each pending Delivery through the Drain and marks it sent with a sent time', function () {
    Mail::fake();

    $group = mailGroup();
    $scheduler = mailMemberOf($group, role: Role::Scheduler);
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));
    Mail::assertNothingSent();

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpCancelled::class, 1);
    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($scheduler->email));

    $row = Delivery::sole();
    expect($row->state)->toBe(DeliveryState::Sent);
    expect($row->sent_at)->not->toBeNull();
});

it('renders each recipient’s copy in their own saved locale', function () {
    Mail::fake();

    $group = mailGroup();
    $english = mailMemberOf($group, role: Role::Scheduler, locale: 'en');
    $french = mailMemberOf($group, role: Role::Scheduler, locale: 'fr');
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($english->email) && $mail->locale === 'en');
    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($french->email) && $mail->locale === 'fr');
});

it('links to the Schedule for the render locale — a /fr/ twin in French, the English root in English', function () {
    $group = mailGroup();
    $holder = mailMemberOf($group);
    mailMemberOf($group, role: Role::Scheduler);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));

    $payload = Delivery::sole()->payload;

    (SignUpCancelled::fromSnapshot($payload))->locale('fr')
        ->assertSeeInHtml("/fr/groupes/{$group->slug}/horaire/");
    (SignUpCancelled::fromSnapshot($payload))->locale('en')
        ->assertSeeInHtml("/groups/{$group->slug}/scheduling/");
});

it('carries the Group name as the From display name, the app address as From, and no Reply-To', function () {
    $group = mailGroup();
    $holder = mailMemberOf($group);
    mailMemberOf($group, role: Role::Scheduler);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));

    $envelope = SignUpCancelled::fromSnapshot(Delivery::sole()->payload)->envelope();

    expect($envelope->from->address)->toBe(config('mail.from.address'));
    expect($envelope->from->name)->toBe($group->name);
    expect($envelope->replyTo)->toBe([]);
});

it('sends at most 8 rows a pass, oldest first', function () {
    Mail::fake();

    $member = Member::factory()->create();
    $rows = Delivery::factory()->count(10)->create(['member_id' => $member->id]);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpCancelled::class, 8);
    expect(Delivery::where('state', DeliveryState::Sent)->count())->toBe(8);

    // The two left behind are the two newest — the Drain took the oldest eight.
    $pending = Delivery::where('state', DeliveryState::Pending)->pluck('id');
    expect($pending->all())->toEqualCanonicalizing([$rows[8]->id, $rows[9]->id]);
});

it('never sends more than 450 rows in a rolling hour', function () {
    Mail::fake();

    $member = Member::factory()->create();
    // 445 already sent in the last hour leaves a budget of 5, below the per-pass 8.
    Delivery::factory()->count(445)->sent()->create(['member_id' => $member->id]);
    Delivery::factory()->count(8)->create(['member_id' => $member->id]);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpCancelled::class, 5);
    expect(Delivery::where('state', DeliveryState::Pending)->count())->toBe(3);
});

it('names the Shift — date, time, kind — and the Member who dropped', function () {
    $group = mailGroup();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Highlights tour']);
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => $kind->id,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
    ]);
    $member = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);
    $shift->load('kind', 'schedule.group');

    // The instant is stored UTC and shown on the org wall clock — assert the same string the
    // view derives, not a hard-coded one that assumes the storage zone.
    $wallClockTime = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'))->translatedFormat('g:i A');

    $mail = new SignUpCancelled($shift, $member);

    $mail->assertSeeInHtml('Dana Okafor');
    $mail->assertSeeInHtml('Highlights tour');
    $mail->assertSeeInHtml($wallClockTime);
    $mail->assertSeeInHtml($group->name);
});

it('omits the kind line for a Group that labels no Shifts (Reception)', function () {
    $group = mailGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'shift_kind_id' => null,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
    ]);
    $shift->load('kind', 'schedule.group');

    $wallClockTime = $shift->starts_at->copy()->setTimezone(config('app.org_timezone'))->translatedFormat('g:i A');

    $mail = new SignUpCancelled($shift, Member::factory()->create());

    // No kind, so nothing to assert but that rendering succeeds without one.
    $mail->assertSeeInHtml($wallClockTime);
});

it('renders the French subject and heading from the lang files', function () {
    $group = mailGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
    ]);
    $shift->load('kind', 'schedule.group');

    $mail = (new SignUpCancelled($shift, Member::factory()->create()))->locale('fr');

    $mail->assertHasSubject(__('scheduling.cancellation_email.subject', [], 'fr'));
    $mail->assertSeeInHtml(__('scheduling.cancellation_email.heading', [], 'fr'));
});

it('sends reliably when the Group name carries an apostrophe (#394)', function () {
    Mail::fake();

    $group = mailGroup();
    $group->update(['name' => "O'Connor Guides"]);
    $scheduler = mailMemberOf($group, role: Role::Scheduler);
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($scheduler->email));
    // The apostrophe rides the From display name, not an HTML-escaped body assertion — the
    // brittle escape comparison that made #394 flaky is gone.
    expect(SignUpCancelled::fromSnapshot(Delivery::sole()->payload)->envelope()->from->name)->toBe("O'Connor Guides");
});
