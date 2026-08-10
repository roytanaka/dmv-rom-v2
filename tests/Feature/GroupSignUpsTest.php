<?php

use App\Enums\Category;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\SignUp;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Taking and dropping a Shift (#357, PRD #352, ADR-0021 §Sign-up). Two seams: the HTTP write
 * seam (Form Request → SignUpPolicy) for who may take, drop, and the state rules; and the
 * Inertia prop seam on the Schedule read for the Sign-up names, taken-count, and the take/
 * drop affordance. Also lands the zero-Sign-up guards now that there are real Sign-ups to
 * assert them against. Prior art: GroupSchedulingTest, MeetingsTest.
 */

/**
 * A Group that runs scheduling and is listed org-wide (so a non-member can read — and take
 * an `open` seat on — its published Schedules).
 */
function signUpGroup(): Group
{
    return Group::factory()->program()->publicListing()->create();
}

/**
 * Make a Member of the given Group with a chosen DMV-wide Category and per-Group standing,
 * optionally carrying a role.
 */
function signUpMemberOf(
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

/**
 * A Shift on the given Schedule with a free seat by default; override `capacity` / `audience`
 * / times as needed. Placed inside this month's published-Schedule range.
 */
function shiftOn(Schedule $schedule, array $overrides = []): Shift
{
    return Shift::factory()->create([
        'schedule_id' => $schedule->id,
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(10, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(13, 0),
        ...$overrides,
    ]);
}

// --- The DMV-wide and per-Group floors --------------------------------------

it('lets a Full-standing member take a group Shift with a free seat', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $member = signUpMemberOf($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertRedirect();

    expect(SignUp::where(['shift_id' => $shift->id, 'member_id' => $member->id])->exists())->toBeTrue();
});

it('lets a Provisional trainee sign up — signing up is how a trainee trains', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $member = signUpMemberOf($group, MembershipStatus::Trainee, Category::Provisional);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertRedirect();

    expect($shift->signUps()->count())->toBe(1);
});

it('bars a member on DMV-wide LOA from signing up while keeping their read access', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    // LOA is Full view (accessTier) but no sign-up — the orthogonality is the point.
    $member = signUpMemberOf($group, MembershipStatus::Full, Category::Loa);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertForbidden();

    // Read access is untouched: the Schedule still opens.
    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertOk();

    expect($shift->signUps()->count())->toBe(0);
});

it('bars a member whose per-Group standing is inactive from that Group’s Shifts', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $member = signUpMemberOf($group, MembershipStatus::Inactive);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});

it('keeps per-Group standing per-Group — inactive in one Group does not touch another', function () {
    $home = signUpGroup();
    $other = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $home->id]);
    $shift = shiftOn($schedule);

    // Full in the owning Group, inactive somewhere else entirely.
    $member = signUpMemberOf($home, MembershipStatus::Full);
    GroupMember::factory()->create(['group_id' => $other->id, 'member_id' => $member->id, 'status' => MembershipStatus::Inactive]);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertRedirect();

    expect($shift->signUps()->count())->toBe(1);
});

// --- The audience field -------------------------------------------------------

it('refuses a non-member on a group-audience Shift', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $outsider = Member::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertForbidden();

    expect($shift->signUps()->count())->toBe(0);
});

it('lets any Member take an open-audience Shift, wherever they belong', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule, ['audience' => 'open']);
    $outsider = Member::factory()->create();

    $this->actingAs($outsider)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertRedirect();

    expect($shift->signUps()->count())->toBe(1);
});

// --- Capacity and the one-seat rule ------------------------------------------

it('refuses a Sign-up on a Shift at capacity', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule, ['capacity' => 1]);
    SignUp::factory()->create(['shift_id' => $shift->id]);
    $member = signUpMemberOf($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertSessionHasErrors('shift');

    expect($shift->signUps()->count())->toBe(1);
});

it('blocks a second Sign-up by the same Member on one Shift', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule, ['capacity' => 5]);
    $member = signUpMemberOf($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift->id]))
        ->assertSessionHasErrors('shift');

    expect($shift->signUps()->where('member_id', $member->id)->count())->toBe(1);
});

it('allows one Member to hold Sign-ups on two overlapping Shifts', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $morning = shiftOn($schedule);
    $overlapping = shiftOn($schedule, [
        'starts_at' => now()->startOfMonth()->addDays(9)->setTime(11, 0),
        'ends_at' => now()->startOfMonth()->addDays(9)->setTime(14, 0),
    ]);
    $member = signUpMemberOf($group);

    $this->actingAs($member)->post(route('sign-ups.store', ['shift' => $morning->id]))->assertRedirect();
    $this->actingAs($member)->post(route('sign-ups.store', ['shift' => $overlapping->id]))->assertRedirect();

    expect(SignUp::where('member_id', $member->id)->count())->toBe(2);
});

// --- Dropping a Shift ---------------------------------------------------------

it('lets a Member drop the seat they hold', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $member = signUpMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);

    $this->actingAs($member)
        ->delete(route('sign-ups.destroy', ['signUp' => $signUp->id]))
        ->assertRedirect();

    expect(SignUp::find($signUp->id))->toBeNull();
});

it('forbids a Member from dropping someone else’s seat', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $owner = signUpMemberOf($group);
    $other = signUpMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $owner->id]);

    $this->actingAs($other)
        ->delete(route('sign-ups.destroy', ['signUp' => $signUp->id]))
        ->assertForbidden();

    expect(SignUp::find($signUp->id))->not->toBeNull();
});

// --- The read seam: names, taken-count, and the affordance -------------------

it('exposes Sign-up names and the taken-count to every reader, non-members included', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule, ['audience' => 'open', 'capacity' => 3]);
    $seated = Member::factory()->create(['first_name' => 'Dana', 'last_name' => 'Okafor']);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $seated->id]);

    // A non-member reader still sees who is on the floor (a roster, no more exposing than
    // the Directory), routed through MemberResource so contact PII stays gated.
    $this->actingAs(Member::factory()->create())
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.taken', 1)
            ->has('scheduling.open.shifts.0.signups', 1)
            ->where('scheduling.open.shifts.0.signups.0.first_name', 'Dana')
            ->where('scheduling.open.shifts.0.signups.0.last_name', 'Okafor')
            ->missing('scheduling.open.shifts.0.signups.0.email'));
});

it('offers the Sign-up affordance to an eligible member and withholds it on a full Shift', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $free = shiftOn($schedule, ['capacity' => 2]);
    $full = shiftOn($schedule, ['capacity' => 1, 'starts_at' => now()->startOfMonth()->addDays(9)->setTime(15, 0), 'ends_at' => now()->startOfMonth()->addDays(9)->setTime(17, 0)]);
    SignUp::factory()->create(['shift_id' => $full->id]);
    $member = signUpMemberOf($group);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.can.signUp', true)
            ->where('scheduling.open.shifts.1.can.signUp', false));
});

it('carries the viewer’s own seat id so a drop is one click from where they signed up', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $member = signUpMemberOf($group);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.signup_id', $signUp->id));

    // A different reader, holding no seat here, gets null — not someone else's id.
    $this->actingAs(signUpMemberOf($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.signup_id', null));
});

// --- The zero-Sign-up guards, now against real Sign-ups ----------------------

it('blocks un-publishing a Schedule that holds a Sign-up', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    SignUp::factory()->create(['shift_id' => $shift->id]);
    $scheduler = signUpMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('schedules.update', ['schedule' => $schedule->id]), ['state' => 'draft'])
        ->assertForbidden();

    expect($schedule->fresh()->state->value)->toBe('published');
});

it('blocks deleting a Schedule that holds a Sign-up', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    SignUp::factory()->create(['shift_id' => $shift->id]);
    $scheduler = signUpMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->delete(route('schedules.destroy', ['schedule' => $schedule->id]))
        ->assertForbidden();

    expect(Schedule::find($schedule->id))->not->toBeNull();
});

it('blocks deleting a Shift that holds a Sign-up but allows it once empty', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule);
    $signUp = SignUp::factory()->create(['shift_id' => $shift->id]);
    $scheduler = signUpMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->delete(route('shifts.destroy', ['shift' => $shift->id]))
        ->assertForbidden();
    expect(Shift::find($shift->id))->not->toBeNull();

    $signUp->delete();

    $this->actingAs($scheduler)
        ->delete(route('shifts.destroy', ['shift' => $shift->id]))
        ->assertRedirect();
    expect(Shift::find($shift->id))->toBeNull();
});

it('blocks lowering a Shift’s capacity below its Sign-up count but allows raising it', function () {
    $group = signUpGroup();
    $schedule = Schedule::factory()->published()->create(['group_id' => $group->id]);
    $shift = shiftOn($schedule, ['capacity' => 3]);
    SignUp::factory()->count(2)->sequence(fn () => ['member_id' => Member::factory()])->create(['shift_id' => $shift->id]);
    $scheduler = signUpMemberOf($group, role: Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('shifts.update', ['shift' => $shift->id]), ['capacity' => 1])
        ->assertSessionHasErrors('capacity');
    expect($shift->fresh()->capacity)->toBe(3);

    $this->actingAs($scheduler)
        ->patch(route('shifts.update', ['shift' => $shift->id]), ['capacity' => 6])
        ->assertRedirect();
    expect($shift->fresh()->capacity)->toBe(6);
});
