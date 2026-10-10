<?php

use App\Enums\DeliveryKind;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Mail\SignUpCancelled;
use App\Mail\SignUpSubstituted;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Delivery;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HoursRecord;
use App\Models\Member;
use App\Models\Qualification;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use App\Models\SignUp;
use App\Models\Tour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Signing up and substituting on a Booking (#798, ADR-0032 §8, §9, §11). A Member takes a
 * Booking's Shift when a seat is open and they may give its Tour. A seat-holder cannot drop it;
 * they hand it to a qualified substitute instead, and everyone concerned is told.
 */

/**
 * A Group running bookings and scheduling, with a group-tour kind, one Tour and one type.
 *
 * @return array{Group, Tour, BookingType}
 */
function bookingSignUpGroup(array $attributes = []): array
{
    $group = Group::factory()->program()->publicListing()->create([
        'has_bookings' => true,
        'has_vetting' => true,
        'group_tour_label' => 'Group tours',
        ...$attributes,
    ]);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Group Tour']);
    $group->update(['group_tour_shift_kind_id' => $kind->id]);
    $tour = Tour::factory()->create(['group_id' => $group->id, 'name' => 'Ancient Egypt', 'open_to_all' => false]);
    $type = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid']);

    return [$group->fresh(), $tour, $type];
}

/** A Member of $group, with an optional role and, when $qualifiedFor is given, an active qualification. */
function bookingSignUpMember(Group $group, ?Role $role = null, ?Tour $qualifiedFor = null, array $attributes = [], MembershipStatus $status = MembershipStatus::Full): Member
{
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

/** A Booking on 20 October 2026, 10:00 to 11:30, with $docents seats. */
function bookingSignUpBooking(Group $group, Tour $tour, BookingType $type, int $docents = 2, string $day = '2026-10-20'): Booking
{
    $zone = config('app.org_timezone');

    return Booking::book(
        $group,
        CarbonImmutable::parse("{$day} 10:00", $zone),
        CarbonImmutable::parse("{$day} 11:30", $zone),
        $docents,
        ['tour_id' => $tour->id, 'booking_type_id' => $type->id, 'client' => 'Bayview Public School', 'visitors' => 28],
    );
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', config('app.org_timezone')));
});

// --- Self sign-up -------------------------------------------------------------

it('lets a Member qualified for the Booking Tour sign up, recording that Tour', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    // A second Tour on the kind, so only the Booking override narrows the pick to one.
    $kindTour = Tour::factory()->create(['group_id' => $group->id, 'open_to_all' => true]);
    ShiftKind::find($group->group_tour_shift_kind_id)->tours()->attach([$tour->id, $kindTour->id]);
    $booking = bookingSignUpBooking($group, $tour, $type);
    $docent = bookingSignUpMember($group, qualifiedFor: $tour);

    $this->actingAs($docent)
        ->post(route('sign-ups.store', ['shift' => $booking->shift_id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $signUp = SignUp::sole();
    expect($signUp->member_id)->toBe($docent->id)
        ->and($signUp->tour_id)->toBe($tour->id);
});

it('lets any Member of the Group sign up when the Booking Tour is open to all', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $tour->update(['open_to_all' => true]);
    $booking = bookingSignUpBooking($group, $tour, $type);
    $docent = bookingSignUpMember($group);

    $this->actingAs($docent)
        ->post(route('sign-ups.store', ['shift' => $booking->shift_id]))
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($tour->id);
});

it('refuses a Member without an active qualification for the Booking Tour', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $unqualified = bookingSignUpMember($group);
    $lapsed = bookingSignUpMember($group);
    Qualification::factory()->inactive()->create([
        'group_member_id' => $lapsed->membershipIn($group)->id,
        'tour_id' => $tour->id,
    ]);

    $this->actingAs($unqualified)->post(route('sign-ups.store', ['shift' => $booking->shift_id]))->assertForbidden();
    $this->actingAs($lapsed)->post(route('sign-ups.store', ['shift' => $booking->shift_id]))->assertForbidden();

    expect(SignUp::count())->toBe(0);
});

it('refuses a qualified Member when the Booking is full or they are past the sign-up floors', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type, docents: 1);
    $first = bookingSignUpMember($group, qualifiedFor: $tour);
    $second = bookingSignUpMember($group, qualifiedFor: $tour);
    $onLeave = bookingSignUpMember($group, qualifiedFor: $tour, status: MembershipStatus::Loa);

    $this->actingAs($first)->post(route('sign-ups.store', ['shift' => $booking->shift_id]))->assertSessionHasNoErrors();
    $this->actingAs($second)->post(route('sign-ups.store', ['shift' => $booking->shift_id]))->assertSessionHasErrors('shift');
    $this->actingAs($onLeave)->post(route('sign-ups.store', ['shift' => $booking->shift_id]))->assertForbidden();

    expect(SignUp::pluck('member_id')->all())->toBe([$first->id]);
});

// --- Placement ----------------------------------------------------------------

it('lets a Booker or Scheduler place any Member of the Group, qualified or not', function (Role $role) {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $officer = bookingSignUpMember($group, $role);
    $unqualified = bookingSignUpMember($group);

    $this->actingAs($officer)
        ->post(route('assignments.store', ['shift' => $booking->shift_id]), ['member_id' => $unqualified->id, 'tour_id' => $tour->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $signUp = SignUp::sole();
    expect($signUp->member_id)->toBe($unqualified->id)
        ->and($signUp->tour_id)->toBe($tour->id);
})->with([
    'Booker' => [Role::Booker],
    'Scheduler' => [Role::Scheduler],
]);

it('records the Booking Tour on a placement that names no Tour', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $booker = bookingSignUpMember($group, Role::Booker);
    $docent = bookingSignUpMember($group);

    $this->actingAs($booker)
        ->post(route('assignments.store', ['shift' => $booking->shift_id]), ['member_id' => $docent->id])
        ->assertSessionHasNoErrors();

    expect(SignUp::sole()->tour_id)->toBe($tour->id);
});

it('refuses a Booker placing on a Shift that is not a Booking, and a plain Member placing anyone', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $daily = Shift::factory()->create([
        'schedule_id' => Schedule::factory()->published()->create(['group_id' => $group->id])->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $booker = bookingSignUpMember($group, Role::Booker);
    $plain = bookingSignUpMember($group);
    $other = bookingSignUpMember($group);

    $this->actingAs($booker)->post(route('assignments.store', ['shift' => $daily->id]), ['member_id' => $other->id])->assertForbidden();
    $this->actingAs($plain)->post(route('assignments.store', ['shift' => $booking->shift_id]), ['member_id' => $other->id])->assertForbidden();

    expect(SignUp::count())->toBe(0);
});

it('sends a Booker the placement roster and seat ids on a Booking Shift, but no Shift authoring', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $booker = bookingSignUpMember($group, Role::Booker);
    $seated = bookingSignUpMember($group, qualifiedFor: $tour);
    $signUp = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $seated->id, 'tour_id' => $tour->id]);

    $this->actingAs($booker)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.roster', fn ($roster) => collect($roster)->pluck('id')->contains($seated->id))
            ->where('scheduling.open.shifts.0.can.assign', true)
            ->where('scheduling.open.shifts.0.can.update', false)
            ->where('scheduling.open.shifts.0.signups.0.signup_id', $signUp->id)
            ->where('scheduling.open.shifts.0.signups.0.can_change_tour', false));
});

it('lets a Booker or Scheduler remove any seat on a Booking Shift', function (Role $role) {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $officer = bookingSignUpMember($group, $role);
    $seat = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => bookingSignUpMember($group)->id]);

    $this->actingAs($officer)
        ->delete(route('sign-ups.destroy', ['signUp' => $seat->id]))
        ->assertRedirect();

    expect(SignUp::count())->toBe(0);
})->with([
    'Booker' => [Role::Booker],
    'Scheduler' => [Role::Scheduler],
]);

// --- No self-drop -------------------------------------------------------------

it('refuses a Member dropping their own seat on a Booking Shift, and offers no Drop', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $holder = bookingSignUpMember($group, qualifiedFor: $tour);
    $seat = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $holder->id, 'tour_id' => $tour->id]);

    $this->actingAs($holder)
        ->delete(route('sign-ups.destroy', ['signUp' => $seat->id]))
        ->assertForbidden();

    expect(SignUp::count())->toBe(1);

    $this->actingAs($holder)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $booking->shift->schedule_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.signup_id', $seat->id)
            ->where('scheduling.open.shifts.0.can.drop', false)
            ->where('scheduling.open.shifts.0.can.substitute', true));
});

it('still offers Drop on an ordinary Shift', function () {
    [$group] = bookingSignUpGroup();
    $shift = Shift::factory()->create([
        'schedule_id' => Schedule::factory()->published()->create(['group_id' => $group->id])->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $holder = bookingSignUpMember($group);
    SignUp::factory()->create(['shift_id' => $shift->id, 'member_id' => $holder->id]);

    $this->actingAs($holder)
        ->get(route('groups.scheduling.show', ['group' => $group, 'schedule' => $shift->schedule_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.open.shifts.0.can.drop', true)
            ->where('scheduling.open.shifts.0.can.substitute', false));
});

// --- Substitution -------------------------------------------------------------

/**
 * A Booking with a qualified holder seated on it.
 *
 * @return array{Group, Tour, Booking, Member, SignUp}
 */
function bookingWithHolder(int $docents = 2): array
{
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type, $docents);
    $holder = bookingSignUpMember($group, qualifiedFor: $tour, attributes: ['first_name' => 'Ada', 'last_name' => 'Holder']);
    $seat = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $holder->id, 'tour_id' => $tour->id]);

    return [$group, $tour, $booking, $holder, $seat];
}

it('moves the seat to a qualified substitute in one write, keeping the Booking Tour', function () {
    [$group, $tour, , $holder, $seat] = bookingWithHolder();
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour);

    $this->actingAs($holder)
        ->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $moved = SignUp::sole();
    expect($moved->id)->toBe($seat->id)
        ->and($moved->member_id)->toBe($substitute->id)
        ->and($moved->tour_id)->toBe($tour->id);
});

it('refuses a substitute who could not take the seat themselves', function (string $who) {
    [$group, $tour, $booking, $holder, $seat] = bookingWithHolder();
    $candidate = match ($who) {
        'unqualified' => bookingSignUpMember($group),
        'outside the Group' => Member::factory()->create(),
        'on leave' => bookingSignUpMember($group, qualifiedFor: $tour, status: MembershipStatus::Loa),
        'already on the Shift' => tap(
            bookingSignUpMember($group, qualifiedFor: $tour),
            fn (Member $member) => SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $member->id]),
        ),
        'the holder' => $holder,
    };

    $this->actingAs($holder)
        ->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $candidate->id])
        ->assertSessionHasErrors('member_id');

    expect($seat->fresh()->member_id)->toBe($holder->id);
})->with(['unqualified', 'outside the Group', 'on leave', 'already on the Shift', 'the holder']);

it('accepts a substitute when the Booking Tour is open to all', function () {
    [$group, $tour, , $holder, $seat] = bookingWithHolder();
    $tour->update(['open_to_all' => true]);
    $substitute = bookingSignUpMember($group);

    $this->actingAs($holder)
        ->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id])
        ->assertSessionHasNoErrors();

    expect($seat->fresh()->member_id)->toBe($substitute->id);
});

it('refuses a substitution by anyone but the holder, after the start, or on an ordinary Shift', function () {
    [$group, $tour, $booking, $holder, $seat] = bookingWithHolder();
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour);
    $booker = bookingSignUpMember($group, Role::Booker);

    $this->actingAs($booker)
        ->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id])
        ->assertForbidden();

    $ordinary = SignUp::factory()->create([
        'shift_id' => Shift::factory()->create([
            'schedule_id' => Schedule::factory()->published()->create(['group_id' => $group->id])->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
        ])->id,
        'member_id' => $holder->id,
    ]);
    $this->actingAs($holder)
        ->patch(route('sign-ups.substitute', ['signUp' => $ordinary->id]), ['member_id' => $substitute->id])
        ->assertForbidden();

    $this->travelTo($booking->shift->starts_at->addMinute());
    $this->actingAs($holder)
        ->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id])
        ->assertForbidden();

    expect($seat->fresh()->member_id)->toBe($holder->id)
        ->and($ordinary->fresh()->member_id)->toBe($holder->id);
});

it('lists the holder the Members who may take the seat, and nobody else', function () {
    [$group, $tour, $booking, $holder, $seat] = bookingWithHolder();
    $qualified = bookingSignUpMember($group, qualifiedFor: $tour, attributes: ['first_name' => 'Zoe', 'last_name' => 'Able']);
    bookingSignUpMember($group);
    bookingSignUpMember($group, qualifiedFor: $tour, status: MembershipStatus::Loa);
    $seated = bookingSignUpMember($group, qualifiedFor: $tour);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $seated->id]);

    $this->actingAs($holder)
        ->getJson(route('sign-ups.substitutes', ['signUp' => $seat->id]))
        ->assertOk()
        ->assertExactJson(['members' => [['id' => $qualified->id, 'name' => 'Zoe Able']]]);

    $this->actingAs($qualified)
        ->getJson(route('sign-ups.substitutes', ['signUp' => $seat->id]))
        ->assertForbidden();
});

// --- Substitution notice ------------------------------------------------------

it('queues one substitution Notice each to the old and new Member and the Bookers, skipping no-email', function () {
    Mail::fake();
    [$group, $tour, , $holder, $seat] = bookingWithHolder();
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour, attributes: ['first_name' => 'Ben', 'last_name' => 'Sub']);
    $booker = bookingSignUpMember($group, Role::Booker);
    $chair = bookingSignUpMember($group, Role::Chair);
    bookingSignUpMember($group, Role::Booker, attributes: ['no_email' => true]);
    $scheduler = bookingSignUpMember($group, Role::Scheduler);

    $this->actingAs($holder)
        ->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id])
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();

    $rows = Delivery::all();
    expect($rows->pluck('member_id')->all())->toEqualCanonicalizing([$holder->id, $substitute->id, $booker->id, $chair->id])
        ->and($rows->pluck('member_id'))->not->toContain($scheduler->id)
        ->and($rows->every(fn (Delivery $row) => $row->kind === DeliveryKind::Notice && $row->shift_id === null))->toBeTrue();

    $payload = $rows->first()->payload;
    expect($payload['notice'])->toBe(SignUpSubstituted::NOTICE_TYPE)
        ->and($payload['previous']['first_name'])->toBe('Ada')
        ->and($payload['substitute']['first_name'])->toBe('Ben')
        ->and($payload['booking']['tour'])->toBe('Ancient Egypt');
});

it('copies the substitution Notice once to the Group copy address when set, and sends it', function () {
    Mail::fake();
    [$group, $tour, , $holder, $seat] = bookingWithHolder();
    $group->update(['booking_copy_email' => 'groupsales@example.test']);
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour);

    $this->actingAs($holder)->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id]);

    $copy = Delivery::where('email', 'groupsales@example.test')->sole();
    expect($copy->member_id)->toBeNull()
        ->and($copy->payload['notice'])->toBe(SignUpSubstituted::NOTICE_TYPE);

    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpSubstituted::class, fn (SignUpSubstituted $mail) => $mail->hasTo('groupsales@example.test'));
});

it('writes no copy when the Group has no copy address', function () {
    [$group, $tour, , $holder, $seat] = bookingWithHolder();
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour);

    $this->actingAs($holder)->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id]);

    expect(Delivery::whereNull('member_id')->count())->toBe(0);
});

it('tells a holder who is also a Booker once', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $booking = bookingSignUpBooking($group, $tour, $type);
    $holder = bookingSignUpMember($group, Role::Booker, qualifiedFor: $tour);
    $seat = SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $holder->id]);
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour);

    $this->actingAs($holder)->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id]);

    expect(Delivery::pluck('member_id')->all())->toEqualCanonicalizing([$holder->id, $substitute->id]);
});

it('sends the substitution Notice through the Drain in each recipient’s locale, naming both Members', function () {
    Mail::fake();
    [$group, $tour, , $holder, $seat] = bookingWithHolder();
    $substitute = bookingSignUpMember($group, qualifiedFor: $tour, attributes: ['locale' => 'fr', 'first_name' => 'Ben', 'last_name' => 'Sub']);

    $this->actingAs($holder)->patch(route('sign-ups.substitute', ['signUp' => $seat->id]), ['member_id' => $substitute->id]);
    $this->artisan('mail:drain')->assertSuccessful();

    Mail::assertSent(SignUpSubstituted::class, 2);
    Mail::assertSent(SignUpSubstituted::class, fn (SignUpSubstituted $mail) => $mail->hasTo($substitute->email) && $mail->locale === 'fr');
    Mail::assertNotSent(SignUpCancelled::class);

    $payload = Delivery::first()->payload;
    SignUpSubstituted::fromSnapshot($payload)->locale('en')
        ->assertSeeInText('Ben Sub is taking the place of Ada Holder')
        ->assertSeeInText('Ancient Egypt')
        ->assertSeeInText('Bayview Public School')
        ->assertSeeInHtml("/groups/{$group->slug}/scheduling/");
    SignUpSubstituted::fromSnapshot($payload)->locale('fr')
        ->assertSeeInText('Ben Sub remplace Ada Holder')
        ->assertSeeInHtml("/fr/groupes/{$group->slug}/horaire/");
});

// --- Hours, My sign-ups, Reminder ---------------------------------------------

it('counts Sign-ups on Booking Shifts in Recalculate this month', function () {
    [$group, $tour, $type] = bookingSignUpGroup();
    $zone = config('app.org_timezone');
    $booking = Booking::book(
        $group,
        CarbonImmutable::parse('2026-10-05 10:00', $zone),
        CarbonImmutable::parse('2026-10-05 12:00', $zone),
        2,
        ['tour_id' => $tour->id, 'booking_type_id' => $type->id, 'client' => 'Bayview Public School', 'visitors' => 28],
    );
    $docent = bookingSignUpMember($group, qualifiedFor: $tour);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $docent->id]);
    $scheduler = bookingSignUpMember($group, Role::Scheduler);

    $this->actingAs($scheduler)
        ->post(route('hours.recalculate', $group), ['year_month' => '202610'])
        ->assertSessionHasNoErrors();

    // A worked two-hour group tour.
    expect(HoursRecord::where('member_id', $docent->id)->sole()->scheduled_hours)->toBe(2);
});

it('shows a Booking Sign-up in My sign-ups', function () {
    [$group, $tour, $type] = bookingSignUpGroup(['collects_visitor_count' => true]);
    $booking = bookingSignUpBooking($group, $tour, $type);
    $docent = bookingSignUpMember($group, qualifiedFor: $tour);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $docent->id]);

    $this->actingAs($docent)
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduling.mine.0.id', $booking->shift_id)
            ->where('scheduling.mine.0.booking.tour', 'Ancient Egypt')
            ->where('scheduling.mine.0.can.substitute', true)
            ->where('scheduling.mine.0.can.drop', false));
});

it('writes the usual Reminder for a Booking Sign-up', function () {
    [$group, $tour, $type] = bookingSignUpGroup(['reminders_enabled' => true, 'reminder_lead_days' => 2]);
    $booking = bookingSignUpBooking($group, $tour, $type, day: '2026-10-11');
    $docent = bookingSignUpMember($group, qualifiedFor: $tour);
    SignUp::factory()->create(['shift_id' => $booking->shift_id, 'member_id' => $docent->id]);

    $this->artisan('mail:daily-pass')->assertSuccessful();

    $row = Delivery::sole();
    expect($row->kind)->toBe(DeliveryKind::Reminder)
        ->and($row->member_id)->toBe($docent->id)
        ->and($row->shift_id)->toBe($booking->shift_id);
});
