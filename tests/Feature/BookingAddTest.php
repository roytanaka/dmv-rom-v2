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
 * Adding a Booking and seeing it on the Schedule (#795, ADR-0032 §1, §4, §5, §10). A Booker, the
 * Chair or super-tier adds a group tour; the app writes its one Shift on the month's group-tour
 * Schedule, created and published on the month's first Booking. Who sees which fields follows §5.
 */

/**
 * A Group running bookings, scheduling and vetting, with a group-tour kind, label, one active
 * Tour and one booking type. Returns the Group, Tour and type.
 *
 * @return array{Group, Tour, BookingType}
 */
function addBookingGroup(array $attributes = []): array
{
    $group = Group::factory()->program()->create([
        'has_bookings' => true,
        'has_vetting' => true,
        'group_tour_label' => 'Group tours',
        ...$attributes,
    ]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Group Tour']);
    $group->update(['group_tour_shift_kind_id' => $kind->id]);
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Ancient Egypt']);
    $type = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid']);

    return [$group->fresh(), $tour, $type];
}

/** A Member of $group carrying an optional role. */
function addBookingMember(Group $group, ?Role $role = null, MembershipStatus $status = MembershipStatus::Full): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->status($status)->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** A valid add-Booking payload. */
function addBookingPayload(Tour $tour, BookingType $type, array $overrides = []): array
{
    return [
        'date' => '2026-11-12',
        'starts_time' => '10:00',
        'ends_time' => '11:30',
        'docents_needed' => 2,
        'tour_id' => $tour->id,
        'booking_type_id' => $type->id,
        'client' => 'Bayview Public School',
        'visitors' => 28,
        'leader' => 'Ms. Okafor',
        'order_number' => '418820',
        'order_date' => '2026-10-02',
        'comments' => 'Grade 5, two classes.',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', config('app.org_timezone')));
});

// --- Adding -----------------------------------------------------------------

it('lets a Booker add a Booking with its one Shift on a new published group-tour Schedule', function () {
    [$group, $tour, $type] = addBookingGroup();
    $booker = addBookingMember($group, Role::Booker);

    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $booking = Booking::sole();
    expect($booking->group_id)->toBe($group->id)
        ->and($booking->tour_id)->toBe($tour->id)
        ->and($booking->booking_type_id)->toBe($type->id)
        ->and($booking->client)->toBe('Bayview Public School')
        ->and($booking->visitors)->toBe(28)
        ->and($booking->leader)->toBe('Ms. Okafor')
        ->and($booking->order_number)->toBe('418820')
        ->and($booking->order_date->toDateString())->toBe('2026-10-02')
        ->and($booking->comments)->toBe('Grade 5, two classes.');

    $shift = $booking->shift;
    $zone = config('app.org_timezone');
    expect($shift->shift_kind_id)->toBe($group->group_tour_shift_kind_id)
        ->and($shift->capacity)->toBe(2)
        ->and($shift->starts_at->setTimezone($zone)->format('Y-m-d H:i'))->toBe('2026-11-12 10:00')
        ->and($shift->ends_at->setTimezone($zone)->format('Y-m-d H:i'))->toBe('2026-11-12 11:30')
        ->and($shift->audience->value)->toBe('group');

    $schedule = $shift->schedule;
    expect($schedule->group_id)->toBe($group->id)
        ->and($schedule->name)->toBe('Group tours – November 2026')
        ->and($schedule->state)->toBe(ScheduleState::Published)
        ->and($schedule->starts_on->toDateString())->toBe('2026-11-01')
        ->and($schedule->ends_on->toDateString())->toBe('2026-11-30')
        ->and($schedule->isGroupTour())->toBeTrue();
});

it('reuses the month group-tour Schedule for later Bookings and opens a new one for another month', function () {
    [$group, $tour, $type] = addBookingGroup();
    $booker = addBookingMember($group, Role::Booker);

    $this->actingAs($booker)->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type));
    $this->actingAs($booker)->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type, ['date' => '2026-11-28']));
    $this->actingAs($booker)->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type, ['date' => '2026-12-03']));

    expect(Booking::count())->toBe(3)
        ->and(Schedule::where('group_id', $group->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Group tours – December 2026', 'Group tours – November 2026']);
});

it('keeps the group-tour Schedule apart from a daily Schedule over the same month', function () {
    [$group, $tour, $type] = addBookingGroup();
    $daily = Schedule::factory()->create([
        'group_id' => $group->id,
        'name' => 'November 2026',
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-11-30',
        'state' => ScheduleState::Draft,
    ]);

    $this->actingAs(addBookingMember($group, Role::Booker))
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type));

    expect(Booking::sole()->shift->schedule_id)->not->toBe($daily->id)
        ->and($daily->shifts()->count())->toBe(0);
});

it('names the month in the given locale from the Group label', function () {
    [$group] = addBookingGroup(['group_tour_label' => 'Visites de groupe']);

    $schedule = GroupTourSchedule::for($group, CarbonImmutable::parse('2026-10-20 14:00', config('app.org_timezone')), 'fr');

    expect($schedule->name)->toBe('Visites de groupe – octobre 2026')
        ->and(GroupTourSchedule::for($group, CarbonImmutable::parse('2026-10-03 09:00', config('app.org_timezone')))->id)->toBe($schedule->id);
});

it('lets the Chair and super-tier add a Booking', function () {
    [$group, $tour, $type] = addBookingGroup();

    $this->actingAs(addBookingMember($group, Role::Chair))
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertSessionHasNoErrors();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertSessionHasNoErrors();

    expect(Booking::count())->toBe(2);
});

it('refuses anyone else', function (?Role $role) {
    [$group, $tour, $type] = addBookingGroup();

    $this->actingAs(addBookingMember($group, $role))
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertForbidden();

    expect(Booking::count())->toBe(0);
})->with([
    'a plain Member' => [null],
    'a Scheduler' => [Role::Scheduler],
    'a Statistician' => [Role::Statistician],
]);

it('refuses a non-member and a Booker on a Group that runs no bookings', function () {
    [$group, $tour, $type] = addBookingGroup();

    $this->actingAs(Member::factory()->create())
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertForbidden();

    $chair = addBookingMember($group, Role::Chair);
    $group->update(['has_bookings' => false]);

    $this->actingAs($chair)
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertForbidden();
});

it('validates the form', function (array $overrides, string $field) {
    [$group, $tour, $type] = addBookingGroup();

    $this->actingAs(addBookingMember($group, Role::Booker))
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type, $overrides))
        ->assertSessionHasErrors($field);

    expect(Booking::count())->toBe(0)->and(Shift::count())->toBe(0);
})->with([
    'no client' => [['client' => ''], 'client'],
    'end before start' => [['ends_time' => '09:00'], 'ends_time'],
    'off the grid' => [['starts_time' => '10:03'], 'starts_time'],
    'no docents' => [['docents_needed' => 0], 'docents_needed'],
    'no visitors' => [['visitors' => null], 'visitors'],
    'bad date' => [['date' => 'tomorrow'], 'date'],
]);

it('refuses a retired Tour, another Group\'s Tour and a retired booking type', function () {
    [$group, $tour, $type] = addBookingGroup();
    $booker = addBookingMember($group, Role::Booker);
    $retired = Tour::factory()->inactive()->create(['group_id' => $group->id]);
    $foreign = Tour::factory()->create();
    $retiredType = BookingType::factory()->create(['group_id' => $group->id, 'active' => false]);

    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($retired, $type))
        ->assertSessionHasErrors('tour_id');
    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($foreign, $type))
        ->assertSessionHasErrors('tour_id');
    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $retiredType))
        ->assertSessionHasErrors('booking_type_id');

    expect(Booking::count())->toBe(0);
});

it('refuses while the Group has no group-tour shift kind', function () {
    [$group, $tour, $type] = addBookingGroup();
    $group->update(['group_tour_shift_kind_id' => null]);

    $this->actingAs(addBookingMember($group, Role::Booker))
        ->post(route('bookings.store', ['group' => $group]), addBookingPayload($tour, $type))
        ->assertSessionHasErrors('date');

    expect(Booking::count())->toBe(0)->and(Schedule::count())->toBe(0);
});

// --- The Shift offers the Booking's Tour -----------------------------------

it('offers only the Booking Tour on its Shift, whatever the kind maps to', function () {
    [$group, $tour, $type] = addBookingGroup();
    $kind = ShiftKind::find($group->group_tour_shift_kind_id);
    $other = Tour::factory()->create(['group_id' => $group->id]);
    $kind->tours()->attach([$tour->id, $other->id]);

    $booking = Booking::book($group, CarbonImmutable::parse('2026-11-12 15:00'), CarbonImmutable::parse('2026-11-12 16:00'), 1, [
        'tour_id' => $other->id, 'booking_type_id' => $type->id, 'client' => 'ROM Members', 'visitors' => 12,
    ]);

    expect($booking->shift->fresh()->toursOffered()->pluck('id')->all())->toBe([$other->id]);
});

// --- Who sees what (§5) -----------------------------------------------------

/** Add one Booking to the Group and return its Schedule. */
function bookedSchedule(Group $group, Tour $tour, BookingType $type): Schedule
{
    return Booking::book($group, CarbonImmutable::parse('2026-11-12 15:00'), CarbonImmutable::parse('2026-11-12 16:30'), 2, [
        'tour_id' => $tour->id,
        'booking_type_id' => $type->id,
        'client' => 'Bayview Public School',
        'visitors' => 28,
        'leader' => 'Ms. Okafor',
        'order_number' => '418820',
        'order_date' => '2026-10-02',
        'comments' => 'Grade 5, two classes.',
    ])->shift->schedule;
}

it('shows a Group Member the client half but not the order fields', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);

    $this->actingAs(addBookingMember($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.is_group_tour', true)
            ->where('scheduling.open.shifts.0.capacity', 2)
            ->where('scheduling.open.shifts.0.booking.tour', 'Ancient Egypt')
            ->where('scheduling.open.shifts.0.booking.details', [
                'client' => 'Bayview Public School',
                'visitors' => 28,
                'type' => 'Tour Paid',
                'booking_type_id' => $type->id,
                'leader' => 'Ms. Okafor',
                'comments' => 'Grade 5, two classes.',
            ])
            ->where('scheduling.open.shifts.0.booking.officer', null)
            ->where('scheduling.open.shifts.0.can.update', false)
            ->where('scheduling.open.shifts.0.can.delete', false));
});

it('shows the order number and date to the Booker, Statistician, Chair and super-tier', function (?Role $role) {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);
    $viewer = $role === null ? Member::factory()->superTier()->create() : addBookingMember($group, $role);

    $this->actingAs($viewer)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.booking.details.client', 'Bayview Public School')
            ->where('scheduling.open.shifts.0.booking.officer', ['order_number' => '418820', 'order_date' => '2026-10-02']));
})->with([
    'Booker' => [Role::Booker],
    'Statistician' => [Role::Statistician],
    'Chair' => [Role::Chair],
    'super-tier' => [null],
]);

it('shows anyone else only the time, Tour and seats', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.taken', 0)
            ->where('scheduling.open.shifts.0.capacity', 2)
            ->where('scheduling.open.shifts.0.booking.tour', 'Ancient Egypt')
            ->where('scheduling.open.shifts.0.booking.details', null)
            ->where('scheduling.open.shifts.0.booking.officer', null))
        ->assertDontSee('Bayview Public School')
        ->assertDontSee('418820');
});

it('withholds the client half from a former Member', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);

    $this->actingAs(addBookingMember($group, null, MembershipStatus::Resigned))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.booking.details', null));
});

it('sends no booking block on an ordinary Shift', function () {
    [$group] = addBookingGroup();
    $schedule = Schedule::factory()->create([
        'group_id' => $group->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'state' => ScheduleState::Published,
    ]);
    Shift::factory()->create(['schedule_id' => $schedule->id, 'starts_at' => '2026-10-12 14:00', 'ends_at' => '2026-10-12 15:00']);

    $this->actingAs(addBookingMember($group))
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.is_group_tour', false)
            ->where('scheduling.open.shifts.0.booking', null));
});

it('carries the Booking form pickers to a Booker only', function () {
    [$group, $tour, $type] = addBookingGroup();
    Tour::factory()->inactive()->create(['group_id' => $group->id]);

    $this->actingAs(addBookingMember($group, Role::Booker))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.booking_options.tours', [['id' => $tour->id, 'name' => 'Ancient Egypt']])
            ->where('scheduling.booking_options.types', [['id' => $type->id, 'name' => 'Tour Paid']]));

    $this->actingAs(addBookingMember($group, Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page->where('scheduling.booking_options', null));
});

it('renders the opened group-tour Schedule under the French route', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);
    $member = addBookingMember($group);

    $this->withLocaleRoutes('fr', function () use ($group, $schedule, $member) {
        $this->actingAs($member)
            ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $schedule]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('scheduling.open.shifts.0.booking.details.client', 'Bayview Public School'));
    });
});

// --- Shift authoring leaves Bookings alone ----------------------------------

it('refuses a Scheduler editing or deleting a Booking Shift, or adding one to the group-tour Schedule', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);
    $shift = $schedule->shifts()->sole();
    $scheduler = addBookingMember($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->patch(route('shifts.update', ['shift' => $shift]), ['capacity' => 5])
        ->assertSessionHasErrors('shift');
    $this->actingAs($scheduler)
        ->delete(route('shifts.destroy', ['shift' => $shift]))
        ->assertSessionHasErrors('shift');
    $this->actingAs($scheduler)
        ->post(route('shifts.store', ['schedule' => $schedule]), ['starts_at' => '2026-11-13T10:00', 'ends_at' => '2026-11-13T11:00'])
        ->assertSessionHasErrors('schedule');
    $this->actingAs($scheduler)
        ->patch(route('schedules.update', ['schedule' => $schedule]), ['state' => 'draft'])
        ->assertSessionHasErrors('state');

    expect($shift->fresh()->capacity)->toBe(2)
        ->and($schedule->shifts()->count())->toBe(1)
        ->and($schedule->fresh()->state)->toBe(ScheduleState::Published);
});

it('leaves a Booking Shift out of a bulk delete', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);

    $this->actingAs(addBookingMember($group, Role::Scheduler))
        ->delete(route('shifts.bulk-destroy', ['schedule' => $schedule]), [
            'times' => [['starts_time' => '15:00', 'ends_time' => '16:30']],
            'capacity' => 2,
            'shift_kind_id' => $group->group_tour_shift_kind_id,
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-30',
        ]);

    expect(Booking::count())->toBe(1)->and($schedule->shifts()->count())->toBe(1);
});

it('refuses deleting a Tour a Booking names', function () {
    [$group, $tour, $type] = addBookingGroup();
    bookedSchedule($group, $tour, $type);

    $this->actingAs(addBookingMember($group, Role::Chair))
        ->delete(route('tours.destroy', ['tour' => $tour]))
        ->assertSessionHasErrors('tour');

    expect($tour->fresh())->not->toBeNull();
});

it('deletes the Booking with its Shift', function () {
    [$group, $tour, $type] = addBookingGroup();
    $schedule = bookedSchedule($group, $tour, $type);

    $schedule->shifts()->sole()->delete();

    expect(Booking::count())->toBe(0);
});

// --- Client suggestions (§10) -----------------------------------------------

it('suggests the Group\'s past clients matching what the Booker types', function () {
    [$group, $tour, $type] = addBookingGroup();
    [$other, $otherTour, $otherType] = addBookingGroup();
    $attributes = fn (Tour $tour, BookingType $type, string $client) => ['tour_id' => $tour->id, 'booking_type_id' => $type->id, 'client' => $client, 'visitors' => 10];
    $at = CarbonImmutable::parse('2026-11-12 15:00');

    foreach (['Bayview Public School', 'Bayview Public School', 'Bloor Collegiate', 'ROM Members'] as $client) {
        Booking::book($group, $at, $at->addHour(), 1, $attributes($tour, $type, $client));
    }
    Booking::book($other, $at, $at->addHour(), 1, $attributes($otherTour, $otherType, 'Bayview Seniors'));

    $this->actingAs(addBookingMember($group, Role::Booker))
        ->getJson(route('bookings.clients', ['group' => $group, 'q' => 'b']))
        ->assertOk()
        ->assertExactJson(['clients' => ['Bayview Public School', 'Bloor Collegiate', 'ROM Members']]);

    $this->actingAs(addBookingMember($group, Role::Booker))
        ->getJson(route('bookings.clients', ['group' => $group, 'q' => 'bay']))
        ->assertExactJson(['clients' => ['Bayview Public School']]);
});

it('refuses client suggestions to anyone who cannot add a Booking', function () {
    [$group] = addBookingGroup();

    $this->actingAs(addBookingMember($group))
        ->getJson(route('bookings.clients', ['group' => $group, 'q' => 'b']))
        ->assertForbidden();
});

// --- A Booking Sign-up gives the Booking's Tour ------------------------------

it('records the Booking Tour on a Sign-up placed on its Shift', function () {
    [$group, $tour, $type] = addBookingGroup();
    $tour->update(['open_to_all' => true]);
    $schedule = bookedSchedule($group, $tour, $type);
    $shift = $schedule->shifts()->sole();
    $member = addBookingMember($group);

    $this->actingAs($member)
        ->post(route('sign-ups.store', ['shift' => $shift]))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($tour->id);
});
