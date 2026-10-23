<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use App\Support\Scheduling\GroupTourSchedule;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Changing, moving and deleting a Booking (#796, ADR-0032 §1, §3, §4). A Booker, the Chair, the
 * Statistician or super-tier changes any field; a date in another month moves the Shift to that
 * month's group-tour Schedule. Deleting removes the Booking, its Shift and its Sign-ups.
 */

/**
 * A Group running bookings and scheduling, with a group-tour kind, one active Tour and one booking
 * type.
 *
 * @return array{Group, Tour, BookingType}
 */
function changeBookingGroup(): array
{
    $group = Group::factory()->program()->create([
        'has_bookings' => true,
        'has_vetting' => true,
        'group_tour_label' => 'Group tours',
    ]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Group Tour']);
    $group->update(['group_tour_shift_kind_id' => $kind->id]);
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Ancient Egypt']);
    $type = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid']);

    return [$group->fresh(), $tour, $type];
}

/** A Member of $group carrying an optional role. */
function changeBookingMember(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** A Booking on 12 November 2026, 10:00–11:30, two docents needed. */
function changeBookingBooked(Group $group, Tour $tour, BookingType $type): Booking
{
    $zone = config('app.org_timezone');

    return Booking::book(
        $group,
        CarbonImmutable::parse('2026-11-12 10:00', $zone)->utc(),
        CarbonImmutable::parse('2026-11-12 11:30', $zone)->utc(),
        2,
        [
            'tour_id' => $tour->id,
            'booking_type_id' => $type->id,
            'client' => 'Bayview Public School',
            'visitors' => 28,
            'leader' => 'Ms. Okafor',
            'order_number' => '418820',
            'order_date' => '2026-10-02',
            'comments' => null,
        ],
    );
}

/** A full change payload matching the booked Booking, with overrides. */
function changeBookingPayload(Booking $booking, array $overrides = []): array
{
    return [
        'date' => '2026-11-12',
        'starts_time' => '10:00',
        'ends_time' => '11:30',
        'docents_needed' => 2,
        'tour_id' => $booking->tour_id,
        'booking_type_id' => $booking->booking_type_id,
        'client' => 'Bayview Public School',
        'visitors' => 28,
        'leader' => 'Ms. Okafor',
        'order_number' => '418820',
        'order_date' => '2026-10-02',
        'comments' => null,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', config('app.org_timezone')));
});

// --- Changing ----------------------------------------------------------------

it('lets a Booker change any field of a Booking', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $otherTour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Dinosaurs']);
    $otherType = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'School']);

    $this->actingAs(changeBookingMember($group, Role::Booker))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, [
            'date' => '2026-11-20',
            'starts_time' => '13:00',
            'ends_time' => '14:00',
            'docents_needed' => 3,
            'tour_id' => $otherTour->id,
            'booking_type_id' => $otherType->id,
            'client' => 'Lakeshore Collegiate',
            'visitors' => 40,
            'leader' => null,
            'order_number' => '500001',
            'order_date' => '2026-10-05',
            'comments' => 'Bring stools.',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $booking->refresh();
    $zone = config('app.org_timezone');
    expect($booking->tour_id)->toBe($otherTour->id)
        ->and($booking->booking_type_id)->toBe($otherType->id)
        ->and($booking->client)->toBe('Lakeshore Collegiate')
        ->and($booking->visitors)->toBe(40)
        ->and($booking->leader)->toBeNull()
        ->and($booking->order_number)->toBe('500001')
        ->and($booking->order_date->toDateString())->toBe('2026-10-05')
        ->and($booking->comments)->toBe('Bring stools.')
        ->and($booking->shift->capacity)->toBe(3)
        ->and($booking->shift->starts_at->setTimezone($zone)->format('Y-m-d H:i'))->toBe('2026-11-20 13:00')
        ->and($booking->shift->ends_at->setTimezone($zone)->format('Y-m-d H:i'))->toBe('2026-11-20 14:00');
});

it('lets the Chair, the Statistician and super-tier change a Booking', function (?Role $role) {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $actor = $role === null ? Member::factory()->superTier()->create() : changeBookingMember($group, $role);

    $this->actingAs($actor)
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['client' => 'Changed']))
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->client)->toBe('Changed');
})->with([
    'the Chair' => [Role::Chair],
    'the Statistician' => [Role::Statistician],
    'super-tier' => [null],
]);

it('refuses anyone else a change', function (?Role $role) {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);

    $this->actingAs(changeBookingMember($group, $role))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['client' => 'Changed']))
        ->assertForbidden();

    expect($booking->fresh()->client)->toBe('Bayview Public School');
})->with([
    'a plain Member' => [null],
    'a Scheduler' => [Role::Scheduler],
]);

it('refuses a Booker of another Group and a non-member', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    [$other] = changeBookingGroup();

    foreach ([changeBookingMember($other, Role::Booker), Member::factory()->create()] as $actor) {
        $this->actingAs($actor)
            ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking))
            ->assertForbidden();
        $this->actingAs($actor)
            ->delete(route('bookings.destroy', ['booking' => $booking]))
            ->assertForbidden();
    }

    expect(Booking::count())->toBe(1);
});

it('moves the Booking to the new month group-tour Schedule, creating it, Sign-ups and all', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $november = $booking->shift->schedule;
    $signUp = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'tour_id' => $tour->id]);

    $this->actingAs(changeBookingMember($group, Role::Booker))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['date' => '2026-12-03']))
        ->assertSessionHasNoErrors();

    $shift = $booking->fresh()->shift;
    $december = $shift->schedule;
    expect($december->id)->not->toBe($november->id)
        ->and($december->name)->toBe('Group tours – December 2026')
        ->and($december->state)->toBe(ScheduleState::Published)
        ->and($december->isGroupTour())->toBeTrue()
        ->and($shift->starts_at->setTimezone(config('app.org_timezone'))->format('Y-m-d H:i'))->toBe('2026-12-03 10:00')
        ->and($signUp->fresh()->shift_id)->toBe($shift->id)
        // The month it left stays, empty, under the ordinary Schedule rules.
        ->and($november->fresh())->not->toBeNull()
        ->and($november->shifts()->count())->toBe(0);
});

it('moves the Booking onto an existing month group-tour Schedule', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $december = GroupTourSchedule::for($group, CarbonImmutable::parse('2026-12-01 12:00', config('app.org_timezone')));

    $this->actingAs(changeBookingMember($group, Role::Booker))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['date' => '2026-12-03']))
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->shift->schedule_id)->toBe($december->id)
        ->and(Schedule::where('group_id', $group->id)->count())->toBe(2);
});

it('refuses docents needed below the current Sign-ups', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    SignUp::factory()->count(2)->create(['shift_id' => $booking->shift_id, 'tour_id' => $tour->id]);

    $this->actingAs(changeBookingMember($group, Role::Booker))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['docents_needed' => 1]))
        ->assertSessionHasErrors(['docents_needed' => trans('group.scheduling_panel.capacity_below_signups')]);

    expect($booking->fresh()->shift->capacity)->toBe(2);
});

it('writes a new Tour onto the Shift Sign-ups', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $signUp = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'tour_id' => $tour->id]);
    $otherTour = Tour::factory()->create(['group_id' => $group->id]);

    $this->actingAs(changeBookingMember($group, Role::Booker))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['tour_id' => $otherTour->id]))
        ->assertSessionHasNoErrors();

    expect($signUp->fresh()->tour_id)->toBe($otherTour->id);
});

it('keeps the Booking own retired Tour and type valid but refuses moving onto another retired one', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $tour->update(['active' => false]);
    $type->update(['active' => false]);
    $booker = changeBookingMember($group, Role::Booker);

    $this->actingAs($booker)
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['client' => 'Changed']))
        ->assertSessionHasNoErrors();

    $retired = Tour::factory()->inactive()->create(['group_id' => $group->id]);
    $this->actingAs($booker)
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['tour_id' => $retired->id]))
        ->assertSessionHasErrors('tour_id');

    expect($booking->fresh()->tour_id)->toBe($tour->id);
});

it('never touches the Earned correction', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $booking->forceFill(['earned_correction' => '12.50'])->save();

    $this->actingAs(changeBookingMember($group, Role::Statistician))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['earned_correction' => '99.00']))
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->earned_correction)->toBe('12.50');
});

it('validates the change form', function (array $overrides, string $field) {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);

    $this->actingAs(changeBookingMember($group, Role::Booker))
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'no client' => [['client' => ''], 'client'],
    'end before start' => [['ends_time' => '09:00'], 'ends_time'],
    'no date' => [['date' => ''], 'date'],
    'zero docents' => [['docents_needed' => 0], 'docents_needed'],
]);

// --- Deleting ----------------------------------------------------------------

it('deletes a Booking with its Shift and Sign-ups', function (?Role $role) {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $schedule = $booking->shift->schedule;
    SignUp::factory()->count(2)->create(['shift_id' => $booking->shift_id, 'tour_id' => $tour->id]);
    $actor = $role === null ? Member::factory()->superTier()->create() : changeBookingMember($group, $role);

    $this->actingAs($actor)
        ->delete(route('bookings.destroy', ['booking' => $booking]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Booking::count())->toBe(0)
        ->and(Shift::count())->toBe(0)
        ->and(SignUp::count())->toBe(0)
        ->and($schedule->fresh())->not->toBeNull();
})->with([
    'a Booker' => [Role::Booker],
    'the Chair' => [Role::Chair],
    'the Statistician' => [Role::Statistician],
    'super-tier' => [null],
]);

it('refuses anyone else a delete', function (?Role $role) {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);

    $this->actingAs(changeBookingMember($group, $role))
        ->delete(route('bookings.destroy', ['booking' => $booking]))
        ->assertForbidden();

    expect(Booking::count())->toBe(1);
})->with([
    'a plain Member' => [null],
    'a Scheduler' => [Role::Scheduler],
]);

// --- The group-tour Schedule -------------------------------------------------

it('refuses deleting a group-tour Schedule that still holds Bookings, even to super-tier', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $schedule = changeBookingBooked($group, $tour, $type)->shift->schedule;

    foreach ([changeBookingMember($group, Role::Scheduler), Member::factory()->superTier()->create()] as $actor) {
        $this->actingAs($actor)
            ->delete(route('schedules.destroy', ['schedule' => $schedule]))
            ->assertSessionHasErrors('schedule');
    }

    expect($schedule->fresh())->not->toBeNull()->and(Booking::count())->toBe(1);
});

it('lets a Scheduler delete a group-tour Schedule left empty', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $schedule = $booking->shift->schedule;
    $booking->shift->delete();
    $scheduler = changeBookingMember($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.can.delete', true));

    $this->actingAs($scheduler)
        ->delete(route('schedules.destroy', ['schedule' => $schedule]))
        ->assertSessionHasNoErrors();

    expect($schedule->fresh())->toBeNull();
});

it('hides the Schedule delete control while it holds Bookings', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $schedule = changeBookingBooked($group, $tour, $type)->shift->schedule;

    $this->actingAs(changeBookingMember($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.can.delete', false));
});

// --- The Schedule payload ----------------------------------------------------

it('sends the change form values and pickers to whoever may change the Booking', function (Role $role) {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    Tour::factory()->inactive()->create(['group_id' => $group->id]);

    $this->actingAs(changeBookingMember($group, $role))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.booking.can_delete', true)
            ->where('scheduling.open.shifts.0.booking.edit', [
                'date' => '2026-11-12',
                'starts_time' => '10:00',
                'ends_time' => '11:30',
                'docents_needed' => 2,
                'tour_id' => $tour->id,
                'booking_type_id' => $type->id,
                'booking_type' => 'Tour Paid',
                'client' => 'Bayview Public School',
                'visitors' => 28,
                'leader' => 'Ms. Okafor',
                'order_number' => '418820',
                'order_date' => '2026-10-02',
                'comments' => null,
            ])
            ->where('scheduling.booking_change_options.tours', [['id' => $tour->id, 'name' => 'Ancient Egypt']])
            ->where('scheduling.booking_change_options.types', [['id' => $type->id, 'name' => 'Tour Paid']]));
})->with([
    'a Booker' => [Role::Booker],
    'the Statistician' => [Role::Statistician],
]);

it('sends no change form to anyone else', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);

    $this->actingAs(changeBookingMember($group, Role::Scheduler))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.booking.can_delete', false)
            ->where('scheduling.open.shifts.0.booking.edit', null)
            ->where('scheduling.booking_change_options', null));
});

it('renders the change form under the French route and spells a new month in French', function () {
    [$group, $tour, $type] = changeBookingGroup();
    $booking = changeBookingBooked($group, $tour, $type);
    $booker = changeBookingMember($group, Role::Booker);

    $this->withLocaleRoutes('fr', function () use ($group, $booking, $booker) {
        $this->actingAs($booker)
            ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.booking.edit.client', 'Bayview Public School'));
    });

    app()->setLocale('fr');
    $this->actingAs($booker)
        ->patch(route('bookings.update', ['booking' => $booking]), changeBookingPayload($booking, ['date' => '2027-01-14']))
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->shift->schedule->name)->toBe('Group tours – janvier 2027');
});
