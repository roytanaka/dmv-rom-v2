<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Earned and the Statistician's correction (#797, ADR-0032 §7). Earned is worked out on read from
 * the booking type's rates: rate per visitor × visitors + rate per docent-hour × docent-hours, where
 * docent-hours = Sign-ups × the Shift's length in hours. The Statistician, the Chair or super-tier
 * may set a correction that replaces it until cleared. Earned goes to the Booker, Statistician,
 * Chair and super-tier only.
 */

/**
 * A Group running bookings with a group-tour kind, a Tour and a type at $2.50 a visitor and $15.00
 * a docent-hour, and one Booking for 28 visitors on a 90-minute Shift.
 *
 * @return array{Group, Booking}
 */
function earnedBooking(): array
{
    $group = Group::factory()->program()->create(['has_bookings' => true, 'group_tour_label' => 'Group tours']);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Group Tour']);
    $group->update(['group_tour_shift_kind_id' => $kind->id]);
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Ancient Egypt']);
    $type = BookingType::factory()->create([
        'group_id' => $group->id,
        'name' => 'Tour Paid',
        'rate_per_visitor' => '2.50',
        'rate_per_docent_hour' => '15.00',
    ]);

    $booking = Booking::book($group->fresh(), CarbonImmutable::parse('2026-11-12 15:00'), CarbonImmutable::parse('2026-11-12 16:30'), 2, [
        'tour_id' => $tour->id,
        'booking_type_id' => $type->id,
        'client' => 'Bayview Public School',
        'visitors' => 28,
    ]);

    return [$group->fresh(), $booking];
}

/** A Member of $group carrying an optional role. */
function earnedMember(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** Seat $count Members of the Booking's Group on its Shift. */
function seatDocents(Group $group, Booking $booking, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => earnedMember($group)->id]);
    }
}

/** The Booking's `officer` block as $viewer reads it on the Schedule. */
function officerBlockFor(Member $viewer, Group $group, Booking $booking): ?array
{
    $officer = null;

    test()->actingAs($viewer)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$officer) {
            $officer = $page->toArray()['props']['scheduling']['open']['shifts'][0]['booking']['officer'];
        });

    return $officer;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', config('app.org_timezone')));
});

// --- Worked out -------------------------------------------------------------

it('works Earned out from the type rates, visitors and docent-hours', function () {
    [$group, $booking] = earnedBooking();
    seatDocents($group, $booking, 2);

    // 2.50 × 28 + 15.00 × (2 Sign-ups × 1.5 h) = 70.00 + 45.00
    expect(officerBlockFor(earnedMember($group, Role::Booker), $group, $booking))
        ->toMatchArray(['earned' => '115.00', 'earned_is_corrected' => false]);
});

it('follows changes to visitors, type, times and Sign-ups', function () {
    [$group, $booking] = earnedBooking();
    $booker = earnedMember($group, Role::Booker);
    seatDocents($group, $booking, 1);

    // 2.50 × 28 + 15.00 × 1.5 = 70.00 + 22.50
    expect(officerBlockFor($booker, $group, $booking)['earned'])->toBe('92.50');

    $booking->update(['visitors' => 10]);
    // 2.50 × 10 + 15.00 × 1.5 = 25.00 + 22.50
    expect(officerBlockFor($booker, $group, $booking)['earned'])->toBe('47.50');

    $booking->shift->update(['ends_at' => $booking->shift->starts_at->addMinutes(45)]);
    // 2.50 × 10 + 15.00 × 0.75 = 25.00 + 11.25
    expect(officerBlockFor($booker, $group, $booking)['earned'])->toBe('36.25');

    seatDocents($group, $booking, 1);
    // 2.50 × 10 + 15.00 × (2 × 0.75) = 25.00 + 22.50
    expect(officerBlockFor($booker, $group, $booking)['earned'])->toBe('47.50');

    $flat = BookingType::factory()->create(['group_id' => $group->id, 'rate_per_visitor' => '1.00', 'rate_per_docent_hour' => '0.00']);
    $booking->update(['booking_type_id' => $flat->id]);
    // 1.00 × 10 + 0
    expect(officerBlockFor($booker, $group, $booking)['earned'])->toBe('10.00');
});

// --- The correction ---------------------------------------------------------

it('lets the Statistician, the Chair and super-tier set a correction that replaces the worked-out figure', function (?Role $role) {
    [$group, $booking] = earnedBooking();
    $viewer = $role === null ? Member::factory()->superTier()->create() : earnedMember($group, $role);

    $this->actingAs($viewer)
        ->patch(route('bookings.earned.update', ['booking' => $booking]), ['earned_correction' => '120.40'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(officerBlockFor($viewer, $group, $booking))->toMatchArray([
        'earned' => '120.40',
        'earned_is_corrected' => true,
        'earned_correction' => '120.40',
        'can_correct_earned' => true,
    ]);
})->with([
    'Statistician' => [Role::Statistician],
    'Chair' => [Role::Chair],
    'super-tier' => [null],
]);

it('refuses a correction from anyone else', function (?Role $role) {
    [$group, $booking] = earnedBooking();

    $this->actingAs(earnedMember($group, $role))
        ->patch(route('bookings.earned.update', ['booking' => $booking]), ['earned_correction' => '120.40'])
        ->assertForbidden();

    expect($booking->fresh()->earned_correction)->toBeNull();
})->with([
    'Booker' => [Role::Booker],
    'Scheduler' => [Role::Scheduler],
    'Member' => [null],
]);

it('refuses a Statistician of another Group', function () {
    [, $booking] = earnedBooking();
    [$other] = earnedBooking();

    $this->actingAs(earnedMember($other, Role::Statistician))
        ->patch(route('bookings.earned.update', ['booking' => $booking]), ['earned_correction' => '1.00'])
        ->assertForbidden();
});

it('keeps a correction of zero', function () {
    [$group, $booking] = earnedBooking();
    $statistician = earnedMember($group, Role::Statistician);

    $this->actingAs($statistician)
        ->patch(route('bookings.earned.update', ['booking' => $booking]), ['earned_correction' => '0'])
        ->assertSessionHasNoErrors();

    expect(officerBlockFor($statistician, $group, $booking))
        ->toMatchArray(['earned' => '0.00', 'earned_is_corrected' => true]);
});

it('brings the worked-out figure back when the correction is cleared', function () {
    [$group, $booking] = earnedBooking();
    $statistician = earnedMember($group, Role::Statistician);
    $booking->correctEarned('500.00');

    $this->actingAs($statistician)
        ->patch(route('bookings.earned.update', ['booking' => $booking]), ['earned_correction' => null])
        ->assertSessionHasNoErrors();

    // 2.50 × 28 + 15.00 × 0 docent-hours
    expect(officerBlockFor($statistician, $group, $booking))
        ->toMatchArray(['earned' => '70.00', 'earned_is_corrected' => false, 'earned_correction' => null]);
});

it('validates the correction', function (string $value) {
    [$group, $booking] = earnedBooking();

    $this->actingAs(earnedMember($group, Role::Statistician))
        ->patch(route('bookings.earned.update', ['booking' => $booking]), ['earned_correction' => $value])
        ->assertSessionHasErrors('earned_correction');
})->with([
    'negative' => ['-1'],
    'not a number' => ['lots'],
    'three decimals' => ['1.234'],
    'too large' => ['100000000'],
]);

it('keeps the correction through an ordinary edit of the Booking', function () {
    [$group, $booking] = earnedBooking();
    $statistician = earnedMember($group, Role::Statistician);
    $booking->correctEarned('500.00');

    $other = BookingType::factory()->create(['group_id' => $group->id, 'rate_per_visitor' => '9.00']);
    $booking->fresh()->update(['visitors' => 3, 'booking_type_id' => $other->id]);

    expect(officerBlockFor($statistician, $group, $booking))
        ->toMatchArray(['earned' => '500.00', 'earned_is_corrected' => true]);
});

it('keeps the correction out of mass assignment', function () {
    [, $booking] = earnedBooking();

    expect(fn () => $booking->update(['earned_correction' => '1.00']))
        ->toThrow(MassAssignmentException::class);
});

// --- Visibility -------------------------------------------------------------

it('sends Earned to the Booker, who may not correct it', function () {
    [$group, $booking] = earnedBooking();

    expect(officerBlockFor(earnedMember($group, Role::Booker), $group, $booking))
        ->toMatchArray(['earned' => '70.00', 'can_correct_earned' => false]);
});

it('withholds Earned from a Member, a Scheduler and an outsider', function (string $who) {
    [$group, $booking] = earnedBooking();
    $viewer = match ($who) {
        'member' => earnedMember($group),
        'scheduler' => earnedMember($group, Role::Scheduler),
        'outsider' => Member::factory()->create(),
    };

    expect(officerBlockFor($viewer, $group, $booking))->toBeNull();

    $this->actingAs($viewer)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule]))
        ->assertDontSee('earned_is_corrected');
})->with(['member', 'scheduler', 'outsider']);
