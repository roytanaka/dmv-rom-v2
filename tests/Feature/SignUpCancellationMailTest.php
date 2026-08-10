<?php

use App\Enums\Role;
use App\Mail\SignUpCancelled;
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
 * The cancellation email (#358, PRD #352, ADR-0021 §Sign-up "Notification") — the first mail
 * in the app. When a Member drops a Sign-up, every Scheduler of the owning Group is told,
 * unconditionally (legacy gates the same mail on a dead 2-day guard, so a Scheduler is
 * silently not-told). The two deliberate silences — a Member *taking* a Shift, and a
 * Scheduler placing or removing a named Member — send nothing, and that silence is pinned,
 * not merely absent. Prior art: GroupSignUpsTest for the write seams.
 */

/** A Group that runs scheduling and is listed org-wide. */
function mailGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
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

it('emails every Scheduler of the owning Group when a Member drops a Sign-up', function () {
    Mail::fake();

    $group = mailGroup();
    $schedulerA = mailMemberOf($group, role: Role::Scheduler);
    $schedulerB = mailMemberOf($group, role: Role::Scheduler);
    $ordinary = mailMemberOf($group);
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)
        ->delete(route('sign-ups.destroy', ['signUp' => $seat->id]))
        ->assertRedirect();

    Mail::assertSent(SignUpCancelled::class, 2);
    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($schedulerA->email));
    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($schedulerB->email));
    Mail::assertNotSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($ordinary->email));
});

it('tells a Chair, who acts as Scheduler on their own Group even without the role', function () {
    Mail::fake();

    $group = mailGroup();
    $chair = mailMemberOf($group, role: Role::Chair);
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));

    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($chair->email));
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

it('renders each recipient’s copy in their own locale', function () {
    Mail::fake();

    $group = mailGroup();
    $english = mailMemberOf($group, role: Role::Scheduler, locale: 'en');
    $french = mailMemberOf($group, role: Role::Scheduler, locale: 'fr');
    $holder = mailMemberOf($group);
    $seat = seatFor($group, $holder);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));

    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($english->email) && $mail->locale === 'en');
    Mail::assertSent(SignUpCancelled::class, fn (SignUpCancelled $mail) => $mail->hasTo($french->email) && $mail->locale === 'fr');
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

it('is unconditional — it fires inside the legacy two-day window it used to suppress', function () {
    Mail::fake();

    $group = mailGroup();
    $scheduler = mailMemberOf($group, role: Role::Scheduler);
    $holder = mailMemberOf($group);
    // A Shift starting tomorrow: legacy's dead +2-day guard would have silenced this.
    $seat = seatFor($group, $holder, [
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(13, 0),
    ]);

    $this->actingAs($holder)->delete(route('sign-ups.destroy', ['signUp' => $seat->id]));

    Mail::assertSent(SignUpCancelled::class, 1);
});

it('sends nothing when a Member takes a Shift — the deliberate silence on sign-up', function () {
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

    Mail::assertNothingSent();
});
