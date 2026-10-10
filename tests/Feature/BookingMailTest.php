<?php

use App\Enums\DeliveryKind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\BookingMail;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\Qualification;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use App\Support\Notices\BookingMailWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/*
 * The Booking Request and Confirmation mails (#799, ADR-0032 §9). Adding a Booking sends a
 * Request to the Members who may give its Tour, or a Confirmation to the Members on it when it is
 * already full; a Booker, the Chair or super-tier can send either again. Each mail goes through the
 * Delivery queue, Reply-To the sending Booker, and is copied to the Group's optional copy address.
 */

/**
 * A Group running bookings with a group-tour kind, one Tour and one booking type.
 *
 * @return array{Group, Tour, BookingType}
 */
function mailBookingGroup(array $attributes = []): array
{
    $group = Group::factory()->program()->create([
        'name' => 'Docents',
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

/** A Member of $group with an optional role, standing and qualification on $qualifiedFor. */
function mailBookingMember(
    Group $group,
    ?Role $role = null,
    ?Tour $qualifiedFor = null,
    MembershipStatus $status = MembershipStatus::Full,
    array $attributes = [],
): Member {
    $member = Member::factory()->create($attributes);
    $membership = GroupMember::factory()->status($status)->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    if ($qualifiedFor !== null) {
        Qualification::factory()->create(['group_member_id' => $membership->id, 'tour_id' => $qualifiedFor->id]);
    }

    return $member;
}

function mailBookingPayload(Tour $tour, BookingType $type, array $overrides = []): array
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

/** A Booking on $group for $tour, written straight through Booking::book. */
function mailBooking(Group $group, Tour $tour, BookingType $type, int $docentsNeeded = 2): Booking
{
    return Booking::book(
        $group,
        CarbonImmutable::parse('2026-11-12 10:00', config('app.org_timezone'))->utc(),
        CarbonImmutable::parse('2026-11-12 11:30', config('app.org_timezone'))->utc(),
        $docentsNeeded,
        [
            'tour_id' => $tour->id,
            'booking_type_id' => $type->id,
            'client' => 'Bayview Public School',
            'visitors' => 28,
            'leader' => 'Ms. Okafor',
            'comments' => 'Grade 5, two classes.',
        ],
    );
}

/** The addresses the queue holds, sorted. */
function queuedAddresses(): array
{
    return Delivery::query()->pluck('email')->sort()->values()->all();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', config('app.org_timezone')));
});

// --- On create ---------------------------------------------------------------

it('sends a Request on create to the Members qualified for the Tour', function () {
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker, attributes: ['email' => 'booker@example.test']);
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'qualified@example.test']);
    mailBookingMember($group, attributes: ['email' => 'unqualified@example.test']);
    mailBookingMember($group, qualifiedFor: $tour, status: MembershipStatus::Loa, attributes: ['email' => 'leave@example.test']);
    $other = Tour::factory()->create(['group_id' => $group->id]);
    mailBookingMember($group, qualifiedFor: $other, attributes: ['email' => 'other-tour@example.test']);

    $inactive = mailBookingMember($group, attributes: ['email' => 'inactive-qual@example.test']);
    Qualification::factory()->inactive()->create([
        'group_member_id' => $inactive->membershipIn($group)->id,
        'tour_id' => $tour->id,
    ]);

    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), mailBookingPayload($tour, $type))
        ->assertSessionHasNoErrors();

    expect(queuedAddresses())->toBe(['qualified@example.test'])
        ->and(Delivery::sole()->kind)->toBe(DeliveryKind::Notice)
        ->and(Delivery::sole()->payload['notice'])->toBe(BookingMail::REQUEST);
});

it('sends the Request to the whole current roster when the Tour is open to all', function () {
    [$group, $tour, $type] = mailBookingGroup();
    $tour->update(['open_to_all' => true]);
    $booker = mailBookingMember($group, Role::Booker, attributes: ['email' => 'booker@example.test']);
    mailBookingMember($group, attributes: ['email' => 'a@example.test']);
    mailBookingMember($group, status: MembershipStatus::Trainee, attributes: ['email' => 'b@example.test']);
    mailBookingMember($group, status: MembershipStatus::Resigned, attributes: ['email' => 'gone@example.test']);

    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), mailBookingPayload($tour, $type))
        ->assertSessionHasNoErrors();

    expect(queuedAddresses())->toBe(['a@example.test', 'b@example.test', 'booker@example.test']);
});

it('sends a Confirmation instead when the Booking is already full', function () {
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker);
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'qualified@example.test']);
    $docent = mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'docent@example.test']);
    $booking = mailBooking($group, $tour, $type, docentsNeeded: 1);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $docent->id, 'tour_id' => $tour->id]);

    app(BookingMailWriter::class)->onCreate($booking, $booker);

    expect(queuedAddresses())->toBe(['docent@example.test'])
        ->and(Delivery::sole()->payload['notice'])->toBe(BookingMail::CONFIRMATION);
});

// --- The buttons ---------------------------------------------------------------

it('lets the Booker, the Chair and super-tier send a Request and a Confirmation again', function (string $who) {
    [$group, $tour, $type] = mailBookingGroup();
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'qualified@example.test']);
    $docent = mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'docent@example.test']);
    $booking = mailBooking($group, $tour, $type);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $docent->id, 'tour_id' => $tour->id]);

    $actor = match ($who) {
        'booker' => mailBookingMember($group, Role::Booker),
        'chair' => mailBookingMember($group, Role::Chair),
        'super' => Member::factory()->superTier()->create(),
    };

    $this->actingAs($actor)
        ->post(route('bookings.request', ['booking' => $booking]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(queuedAddresses())->toBe(['docent@example.test', 'qualified@example.test']);

    Delivery::query()->delete();

    $this->actingAs($actor)
        ->post(route('bookings.confirmation', ['booking' => $booking]))
        ->assertRedirect();

    expect(queuedAddresses())->toBe(['docent@example.test'])
        ->and(Delivery::sole()->payload['notice'])->toBe(BookingMail::CONFIRMATION);
})->with(['booker', 'chair', 'super']);

it('refuses anyone else the buttons', function (?Role $role) {
    [$group, $tour, $type] = mailBookingGroup();
    mailBookingMember($group, qualifiedFor: $tour);
    $booking = mailBooking($group, $tour, $type);
    $actor = mailBookingMember($group, $role);

    $this->actingAs($actor)->post(route('bookings.request', ['booking' => $booking]))->assertForbidden();
    $this->actingAs($actor)->post(route('bookings.confirmation', ['booking' => $booking]))->assertForbidden();

    expect(Delivery::count())->toBe(0);
})->with([
    'a Member' => [null],
    'a Scheduler' => [Role::Scheduler],
    'a Statistician' => [Role::Statistician],
]);

it('tells the Schedule which viewers get the buttons', function () {
    [$group, $tour, $type] = mailBookingGroup();
    $booking = mailBooking($group, $tour, $type);
    $booker = mailBookingMember($group, Role::Booker);
    $member = mailBookingMember($group);
    $url = route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule_id]);

    $this->actingAs($booker)->get($url)->assertInertia(fn ($page) => $page
        ->where('scheduling.open.shifts.0.booking.can_send_mails', true));

    $this->actingAs($member)->get($url)->assertInertia(fn ($page) => $page
        ->where('scheduling.open.shifts.0.booking.can_send_mails', false));
});

// --- What the mail says -----------------------------------------------------------

it('renders the Request with the Booking details and Reply-To the sending Booker', function () {
    Mail::fake();
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker, attributes: [
        'first_name' => 'Bea', 'last_name' => 'Booker', 'email' => 'bea@example.test',
    ]);
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'qualified@example.test']);
    $booking = mailBooking($group, $tour, $type, docentsNeeded: 3);

    $this->actingAs($booker)->post(route('bookings.request', ['booking' => $booking]));
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BookingMail::class, function (BookingMail $mail) {
        $html = $mail->render();

        return $mail->hasTo('qualified@example.test')
            && $mail->hasReplyTo('bea@example.test', 'Bea Booker')
            && str_contains($html, 'Ancient Egypt')
            && str_contains($html, 'Thursday, 12 November 2026')
            && str_contains($html, '10:00 AM')
            && str_contains($html, '11:30 AM')
            && str_contains($html, 'Bayview Public School')
            && str_contains($html, 'Ms. Okafor')
            && str_contains($html, 'Grade 5, two classes.')
            && str_contains($html, '28')
            && str_contains($html, '3 docents still needed');
    });
});

it('lists everyone on the Booking in the Confirmation', function () {
    Mail::fake();
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker);
    $first = mailBookingMember($group, qualifiedFor: $tour, attributes: ['first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana@example.test']);
    $second = mailBookingMember($group, qualifiedFor: $tour, attributes: ['first_name' => 'Ben', 'last_name' => 'Ito', 'email' => 'ben@example.test']);
    $booking = mailBooking($group, $tour, $type);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $first->id, 'tour_id' => $tour->id]);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $second->id, 'tour_id' => $tour->id]);

    $this->actingAs($booker)->post(route('bookings.confirmation', ['booking' => $booking]));
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BookingMail::class, 2);
    Mail::assertSent(BookingMail::class, function (BookingMail $mail) {
        $html = $mail->render();

        return $mail->hasTo('ana@example.test')
            && str_contains($html, 'Ana Silva')
            && str_contains($html, 'Ben Ito')
            && str_contains($html, 'Bayview Public School');
    });
});

it('renders in the recipient locale and leaves the Booking content as written', function () {
    Mail::fake();
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker);
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['locale' => 'fr', 'email' => 'fr@example.test']);
    $booking = mailBooking($group, $tour, $type);

    $this->actingAs($booker)->post(route('bookings.request', ['booking' => $booking]));
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BookingMail::class, function (BookingMail $mail) {
        $html = $mail->render();

        return $mail->locale === 'fr'
            && str_contains($html, 'jeudi 12 novembre 2026')
            && str_contains($html, 'Grade 5, two classes.')
            && str_contains($mail->withLocale('fr', fn () => $mail->envelope()->subject), 'Demande de visite de groupe');
    });
});

// --- No-email and the copy address -------------------------------------------------

it('skips a Member with the no-email flag', function () {
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker);
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'yes@example.test']);
    $silent = mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'no@example.test']);
    $silent->no_email = true;
    $silent->save();
    $booking = mailBooking($group, $tour, $type);

    $this->actingAs($booker)->post(route('bookings.request', ['booking' => $booking]));

    expect(queuedAddresses())->toBe(['yes@example.test']);
});

it('copies every Booking mail to the Group copy address when one is set', function () {
    Mail::fake();
    [$group, $tour, $type] = mailBookingGroup(['booking_copy_email' => 'groupsales@example.test']);
    $booker = mailBookingMember($group, Role::Booker, attributes: ['email' => 'bea@example.test', 'locale' => 'fr']);
    mailBookingMember($group, qualifiedFor: $tour, attributes: ['email' => 'qualified@example.test']);

    $this->actingAs($booker)
        ->post(route('bookings.store', ['group' => $group]), mailBookingPayload($tour, $type))
        ->assertSessionHasNoErrors();
    $booking = Booking::sole();
    $this->actingAs($booker)->post(route('bookings.confirmation', ['booking' => $booking]));

    expect(queuedAddresses())->toBe(['groupsales@example.test', 'groupsales@example.test', 'qualified@example.test']);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('groupsales@example.test')
        && $mail->hasReplyTo('bea@example.test')
        && $mail->locale === 'fr');
    Mail::assertSent(BookingMail::class, 3);
});

it('writes no copy when the Group has no copy address', function () {
    [$group, $tour, $type] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker);
    $booking = mailBooking($group, $tour, $type);

    $this->actingAs($booker)->post(route('bookings.request', ['booking' => $booking]));

    expect(Delivery::count())->toBe(0);
});

// --- The copy-address setting ------------------------------------------------------

it('lets a Booker set and clear the copy address on the Group tours card', function () {
    [$group] = mailBookingGroup();
    $booker = mailBookingMember($group, Role::Booker);
    $settings = fn (?string $email) => [
        'group_tour_shift_kind_id' => $group->group_tour_shift_kind_id,
        'group_tour_label' => 'Group tours',
        'booking_copy_email' => $email,
    ];

    $this->actingAs($booker)
        ->patch(route('groups.group-tours.update', ['group' => $group]), $settings('groupsales@example.test'))
        ->assertSessionHasNoErrors();
    expect($group->fresh()->booking_copy_email)->toBe('groupsales@example.test');

    $this->actingAs($booker)
        ->patch(route('groups.group-tours.update', ['group' => $group]), $settings('not an address'))
        ->assertSessionHasErrors('booking_copy_email');

    $this->actingAs($booker)
        ->patch(route('groups.group-tours.update', ['group' => $group]), $settings(null))
        ->assertSessionHasNoErrors();
    expect($group->fresh()->booking_copy_email)->toBeNull();
});

it('carries the copy address to the Group tours card', function () {
    [$group] = mailBookingGroup(['booking_copy_email' => 'groupsales@example.test']);
    $booker = mailBookingMember($group, Role::Booker);

    $this->actingAs($booker)
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn ($page) => $page->where('settings.bookings.copyEmail', 'groupsales@example.test'));
});
