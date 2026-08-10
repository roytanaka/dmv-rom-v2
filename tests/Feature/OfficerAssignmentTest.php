<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\SignUpCancelled;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Officer assignment and removal (#359, PRD #352, ADR-0021 §Sign-up) — a Scheduler placing a
 * named Member on a Shift directly, and removing any Sign-up on her Group's Shifts. There is
 * no second entity: placement writes an ordinary Sign-up row, and removal reuses the drop
 * seam. The asymmetry with self-service is the point — a Scheduler is bound by both floors
 * and by capacity, but *not* by the Shift's `audience` (she has already spoken to the person
 * the discovery filter is for). Neither assign nor remove sends any email. Prior art:
 * GroupSignUpsTest for the self-service write seam.
 */

/** A Group that runs scheduling and is listed org-wide. */
function assignGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

/** Make a Member of the given Group with a chosen standing, optionally carrying a role. */
function assignMemberOf(
    Group $group,
    MembershipStatus $status = MembershipStatus::Full,
    Category $category = Category::Active,
    ?Role $role = null,
): Member {
    $member = Member::factory()->category($category)->create();
    $membership = GroupMember::factory()->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
        'status' => $status,
    ]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** The Group's Scheduler — the actor who places and removes. */
function schedulerOf(Group $group): Member
{
    return assignMemberOf($group, MembershipStatus::Full, Category::Active, Role::Scheduler);
}

/** A Shift on the given Schedule with a free seat by default. */
function assignShiftOn(Schedule $schedule, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
        ...$overrides,
    ]);
}

// --- Placement — the Scheduler seats a named Member --------------------------

it('lets a Scheduler place a named roster Member on a Shift, writing an ordinary Sign-up', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertRedirect();

    expect(SignUp::where(['shift_id' => $shift->id, 'member_id' => $regular->id])->exists())->toBeTrue();
});

it('lets a Chair place a Member — the schedule-admin gate folds in Chair-implication', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $chair = assignMemberOf($group, MembershipStatus::Full, Category::Active, Role::Chair);
    $regular = assignMemberOf($group);

    $this->actingAs($chair)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertRedirect();

    expect($shift->signUps()->count())->toBe(1);
});

it('forbids an ordinary Member — placement is not a self-service verb', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $ordinary = assignMemberOf($group);
    $regular = assignMemberOf($group);

    $this->actingAs($ordinary)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});

it('forbids a Scheduler of another Group — authority never leaks across Groups', function () {
    $group = assignGroup();
    $other = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $foreignScheduler = schedulerOf($other);
    $regular = assignMemberOf($group);

    $this->actingAs($foreignScheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});

// --- The floors bind the placed Member (but not the audience) ----------------

it('bars placing a Member on DMV-wide LOA — the DMV-wide floor binds placement', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    // Full per-Group standing but LOA org-wide: the DMV-wide floor still bars placement.
    $onLeave = assignMemberOf($group, MembershipStatus::Full, Category::Loa);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $onLeave->id])
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});

it('bars placing a Member whose per-Group standing is resigned — the per-Group floor binds', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    $resigned = assignMemberOf($group, MembershipStatus::Resigned);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $resigned->id])
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});

it('places a Member the audience would not offer a button to — placement ignores audience', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    // A `group`-audience Shift: self-service offers its button only to Members of this Group.
    // The Scheduler places a regular directly — the discovery filter is not consulted.
    $shift = assignShiftOn($schedule, ['audience' => 'group']);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertRedirect();

    expect($shift->signUps()->count())->toBe(1);
});

// --- Capacity and the one-seat rule bind the Scheduler too -------------------

it('refuses placing onto a full Shift — capacity binds the Scheduler, no override', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule, ['capacity' => 1]);
    SignUp::factory()->create(['shift_id' => $shift->id]);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertSessionHasErrors('member_id');

    expect($shift->signUps()->count())->toBe(1);
});

it('refuses placing a Member already holding a seat — the one-seat rule binds', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule, ['capacity' => 5]);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $regular->id]);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertSessionHasErrors('member_id');

    expect($shift->signUps()->where('member_id', $regular->id)->count())->toBe(1);
});

// --- Removal — the Scheduler clears any seat on her Group's Shifts ------------

it('lets a Scheduler remove a Sign-up on her Group’s Shift, whoever created it', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    // A self-service Sign-up the Member made themselves — the Scheduler still clears it.
    $regular = assignMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $regular->id]);

    $this->actingAs($scheduler)
        ->delete(route('sign-ups.destroy', ['signUp' => $signUp->id]))
        ->assertRedirect();

    expect(SignUp::find($signUp->id))->toBeNull();
});

it('still forbids an ordinary Member from removing someone else’s seat', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $owner = assignMemberOf($group);
    $ordinary = assignMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $owner->id]);

    $this->actingAs($ordinary)
        ->delete(route('sign-ups.destroy', ['signUp' => $signUp->id]))
        ->assertForbidden();

    expect(SignUp::find($signUp->id))->not->toBeNull();
});

// --- The two deliberate silences: assign and remove send no email ------------

it('sends no email when a Scheduler places a Member', function () {
    Mail::fake();

    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);

    $this->actingAs($scheduler)
        ->post(route('assignments.store', ['shift' => $shift->id]), ['member_id' => $regular->id])
        ->assertRedirect();

    Mail::assertNothingSent();
});

it('sends no email when a Scheduler removes a Member’s seat', function () {
    Mail::fake();

    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $regular->id]);

    $this->actingAs($scheduler)
        ->delete(route('sign-ups.destroy', ['signUp' => $signUp->id]))
        ->assertRedirect();

    Mail::assertNotSent(SignUpCancelled::class);
});

// --- The read surface: the Member picker and the authoring hints -------------

it('gives a schedule admin the placement roster and per-Shift assign hint', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule, ['capacity' => 2]);
    $scheduler = schedulerOf($group);
    assignMemberOf($group);

    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            // The picker draws from the Group's roster — the Scheduler and the regular,
            // routed through MemberResource (name tier, contact suppressed).
            ->has('scheduling.roster', 2)
            ->missing('scheduling.roster.0.email')
            ->where('scheduling.open.shifts.0.can.assign', true));
});

it('excludes a barred Member from the placement roster — only placeable Members reach the picker', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    assignShiftOn($schedule);
    $scheduler = schedulerOf($group);
    // On leave in this Group — the per-Group floor bars placement, so the picker omits them.
    assignMemberOf($group, MembershipStatus::Loa);

    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        // Only the Scheduler (placeable) remains; the on-leave Member is filtered out.
        ->assertInertia(fn (Assert $page) => $page->has('scheduling.roster', 1));
});

it('withholds the roster and assign hint from an ordinary Member', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    assignShiftOn($schedule);
    $member = assignMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduling.roster', 0)
            ->where('scheduling.open.shifts.0.can.assign', false));
});

it('carries each seat’s Sign-up id to a schedule admin so any seat is one click to remove', function () {
    $group = assignGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = assignShiftOn($schedule, ['capacity' => 2]);
    $scheduler = schedulerOf($group);
    $regular = assignMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $regular->id]);

    // The admin sees each seat's own Sign-up id — the remove target for any seat, not just
    // their own; a plain reader never learns another seat's id.
    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.signups.0.signup_id', $signUp->id));

    $this->actingAs(assignMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->missing('scheduling.open.shifts.0.signups.0.signup_id'));
});
