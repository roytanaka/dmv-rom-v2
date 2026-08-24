<?php

use App\Enums\Category;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\HoursAdjustment;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Support\OrgTime;

/*
 * Extra-hours entry write seam (#408, PRD #406, ADR-0022 §2, §4). Exercises the additive
 * write through every layer — route → auth middleware → StoreHoursRecordRequest → the
 * HoursRecordPolicy → Gate::before — plus the additive arithmetic, the zero floor, the
 * no-op, the decimal refusal, the two-month window, the adjustment trail, the
 * never-trust-the-body rule, and the create authorization matrix.
 */

/** A Group listed org-wide, so a non-member can still open it and enter hours there. */
function hoursGroup(): Group
{
    return Group::factory()->publicListing()->create();
}

/** A member of the given Group, at the given DMV-wide Category (Active by default). */
function hoursMemberOf(Group $group, Category $category = Category::Active): Member
{
    $member = Member::factory()->create(['category' => $category]);
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    return $member;
}

/** The current entry month on the org wall clock. */
function currentMonth(): string
{
    return OrgTime::entryMonths()[0];
}

// --- Additive arithmetic and the zero floor ---------------------------------

it('records extra hours additively, creating the record on first entry', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 5])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $record = HoursRecord::sole();
    expect($record->member_id)->toBe($member->id)
        ->and($record->group_id)->toBe($group->id)
        ->and($record->year_month)->toBe(currentMonth())
        ->and($record->scheduled_hours)->toBe(0)
        ->and($record->extra_hours)->toBe(5)
        ->and($record->total_hours)->toBe(5);
});

it('adds the entered number to the hours already on file (3 onto 5 leaves 8)', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    HoursRecord::enterExtra($member, $group, currentMonth(), 5, $member);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 3])
        ->assertRedirect();

    expect(HoursRecord::sole()->extra_hours)->toBe(8);
});

it('subtracts a negative correction (-2 against 5 leaves 3)', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    HoursRecord::enterExtra($member, $group, currentMonth(), 5, $member);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => -2])
        ->assertRedirect();

    expect(HoursRecord::sole()->extra_hours)->toBe(3);
});

it('floors a bad correction at zero (-9 against 5 leaves 0, never negative)', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    HoursRecord::enterExtra($member, $group, currentMonth(), 5, $member);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => -9])
        ->assertRedirect();

    $record = HoursRecord::sole();
    expect($record->extra_hours)->toBe(0)
        ->and($record->total_hours)->toBe(0);
});

// --- The no-op: a blank or zero leaves no trace -----------------------------

it('writes no row for a zero entry', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 0])
        ->assertRedirect();

    expect(HoursRecord::count())->toBe(0)
        ->and(HoursAdjustment::count())->toBe(0);
});

it('writes no row for a blank entry', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => null])
        ->assertRedirect();

    expect(HoursRecord::count())->toBe(0);
});

it('leaves no trace when a negative correction is swallowed by the floor', function () {
    // -3 against an empty record floors to 0: nothing changed, so no row and no adjustment.
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => -3])
        ->assertRedirect();

    expect(HoursRecord::count())->toBe(0)
        ->and(HoursAdjustment::count())->toBe(0);
});

// --- Whole hours only -------------------------------------------------------

it('refuses a decimal with a whole-hours message and writes nothing', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => '3.5'])
        ->assertSessionHasErrors(['hours' => trans('hours.entry.whole_hours')]);

    expect(HoursRecord::count())->toBe(0);
});

// --- The two-month window ---------------------------------------------------

it('accepts the previous month', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    $previous = OrgTime::entryMonths()[1];

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => $previous, 'hours' => 4])
        ->assertSessionHasNoErrors();

    expect(HoursRecord::sole()->year_month)->toBe($previous);
});

it('refuses a month older than the previous one', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    // Two months before the current one — outside the agreed reporting window.
    $tooOld = OrgTime::now()->subMonthsWithoutOverflow(2)->format('Ym');

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => $tooOld, 'hours' => 4])
        ->assertSessionHasErrors('year_month');

    expect(HoursRecord::count())->toBe(0);
});

// --- The adjustment trail ---------------------------------------------------

it('appends an Hours adjustment carrying the delta and its author on every write', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 5]);

    $adjustment = HoursAdjustment::sole();
    expect($adjustment->delta)->toBe(5)
        ->and($adjustment->created_by)->toBe($member->id)
        ->and($adjustment->hours_record_id)->toBe(HoursRecord::sole()->id);
});

it('records the applied delta after the floor, so adjustments sum to extra hours', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    HoursRecord::enterExtra($member, $group, currentMonth(), 5, $member);

    // -9 floors 5 to 0, an applied change of -5.
    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => -9]);

    $deltas = HoursAdjustment::query()->orderBy('id')->pluck('delta');
    expect($deltas->all())->toBe([5, -5])
        ->and($deltas->sum())->toBe(HoursRecord::sole()->extra_hours);
});

// --- Never trust the body: the writer is the session -----------------------

it('writes against the authenticated Member even when the body names another', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    $other = Member::factory()->create();

    $this->actingAs($member)
        ->post(route('hours.store', $group), [
            'year_month' => currentMonth(),
            'hours' => 5,
            'member_id' => $other->id,
        ])
        ->assertSessionHasNoErrors();

    $record = HoursRecord::sole();
    expect($record->member_id)->toBe($member->id)
        ->and(HoursAdjustment::sole()->created_by)->toBe($member->id);
});

// --- The total identity holds after every write -----------------------------

it('keeps total equal to scheduled plus extra after a write', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);
    // A record already carrying scheduled hours (as a recalculation would leave it).
    HoursRecord::factory()->create([
        'member_id' => $member->id,
        'group_id' => $group->id,
        'year_month' => currentMonth(),
        'meeting_id' => HoursRecord::NO_MEETING,
        'scheduled_hours' => 6,
        'extra_hours' => 0,
        'total_hours' => 6,
    ]);

    $this->actingAs($member)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 4]);

    $record = HoursRecord::sole();
    expect($record->scheduled_hours)->toBe(6)
        ->and($record->extra_hours)->toBe(4)
        ->and($record->total_hours)->toBe(10);
});

// --- Uniqueness: one row per (Member, Group, month, no-meeting) --------------

it('keeps one row per Member, Group and month across repeated no-meeting writes', function () {
    $group = hoursGroup();
    $member = hoursMemberOf($group);

    $this->actingAs($member)->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 2]);
    $this->actingAs($member)->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 3]);

    expect(HoursRecord::count())->toBe(1)
        ->and(HoursRecord::sole()->extra_hours)->toBe(5);
});

// --- A sub-Group's record is its own -----------------------------------------

it('records against a sub-Group independently of its parent', function () {
    $parent = hoursGroup();
    $child = Group::factory()->publicListing()->create(['parent_id' => $parent->id]);
    $member = hoursMemberOf($child);

    $this->actingAs($member)
        ->post(route('hours.store', $child), ['year_month' => currentMonth(), 'hours' => 5]);

    $record = HoursRecord::sole();
    expect($record->group_id)->toBe($child->id);
});

// --- The create authorization matrix ----------------------------------------

it('lets an ordinary member enter hours on their Group', function () {
    $group = hoursGroup();

    $this->actingAs(hoursMemberOf($group))
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 5])
        ->assertSessionHasNoErrors();

    expect(HoursRecord::count())->toBe(1);
});

it('lets a Member enter hours on a Group they do not belong to', function () {
    $group = hoursGroup();
    $stranger = Member::factory()->create();

    $this->actingAs($stranger)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 5])
        ->assertSessionHasNoErrors();

    expect(HoursRecord::sole()->member_id)->toBe($stranger->id);
});

it('forbids a departed Category from entering hours', function (Category $category) {
    $group = hoursGroup();

    $this->actingAs(hoursMemberOf($group, $category))
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 5])
        ->assertForbidden();

    expect(HoursRecord::count())->toBe(0);
})->with([
    'resigned' => Category::Resigned,
    'withdrawn' => Category::Withdrawn,
    'deceased' => Category::Deceased,
]);

it('forbids a non-member from entering hours on a Private Group', function () {
    $group = Group::factory()->privateListing()->create();
    $stranger = Member::factory()->create();

    $this->actingAs($stranger)
        ->post(route('hours.store', $group), ['year_month' => currentMonth(), 'hours' => 5])
        ->assertForbidden();

    expect(HoursRecord::count())->toBe(0);
});

it('redirects an unauthenticated entry to login', function () {
    $this->post(route('hours.store', hoursGroup()), ['year_month' => currentMonth(), 'hours' => 5])
        ->assertRedirect(route('login'));
});
