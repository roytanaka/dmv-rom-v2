<?php

use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\ShiftKind;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The bookings capability, the Booker role and the Group's booking types (#794, ADR-0032 §2, §3,
 * §6). A Booker, the Chair or super-tier keeps the booking-type list and the group-tour settings
 * in Group Settings, only while the Group runs bookings. A type a Booking uses cannot be deleted;
 * it can be retired.
 */

/** A Group running bookings (scheduling on, so it has shift kinds). */
function bookingGroup(array $attributes = []): Group
{
    return Group::factory()->program()->create(['has_bookings' => true, ...$attributes]);
}

/** A Member of $group carrying an optional role. */
function bookingMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

// --- The capability and the role --------------------------------------------

it('maps the Booker role to the bookings capability', function () {
    expect(Role::Booker->requiredCapability())->toBe('has_bookings');
});

it('lets a Chair grant the Booker role from the roster on a Group running bookings', function () {
    $group = bookingGroup();
    $chair = bookingMemberOf($group, Role::Chair);
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs($chair)
        ->patch(route('group-members.update', ['membership' => $membership]), ['roles' => ['booker']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($membership->roles()->pluck('role')->all())->toBe([Role::Booker]);
});

it('refuses the Booker role on a Group that runs no bookings', function () {
    $group = bookingGroup(['has_bookings' => false]);
    $chair = bookingMemberOf($group, Role::Chair);
    $membership = GroupMember::factory()->create(['group_id' => $group->id]);

    $this->actingAs($chair)
        ->patch(route('group-members.update', ['membership' => $membership]), ['roles' => ['booker']])
        ->assertSessionHasErrors('roles.0');

    expect($membership->roles()->count())->toBe(0);
});

it('offers the Booker role on the roster only while bookings are on', function () {
    $on = bookingGroup();
    $off = bookingGroup(['has_bookings' => false]);

    $this->actingAs(bookingMemberOf($on, Role::Chair))
        ->get(route('groups.show', ['group' => $on, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.assignableRoles', fn ($roles) => collect($roles)->contains('booker')));

    $this->actingAs(bookingMemberOf($off, Role::Chair))
        ->get(route('groups.show', ['group' => $off, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.assignableRoles', fn ($roles) => ! collect($roles)->contains('booker')));
});

// --- The Settings card ------------------------------------------------------

it('opens the Settings tab to a Booker with the booking types and group-tour settings', function () {
    $group = bookingGroup(['group_tour_label' => 'Group tours']);
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Group Tour']);
    $group->update(['group_tour_shift_kind_id' => $kind->id]);
    BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Free', 'sort_order' => 1]);
    BookingType::factory()->create([
        'group_id' => $group->id,
        'name' => 'Tour Paid',
        'rate_per_visitor' => '5.00',
        'rate_per_docent_hour' => '0.00',
        'sort_order' => 0,
    ]);

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', true)
            ->where('can.manageBookings', true)
            ->where('settings.bookings.types.0.name', 'Tour Paid')
            ->where('settings.bookings.types.0.ratePerVisitor', '5.00')
            ->where('settings.bookings.types.0.ratePerDocentHour', '0.00')
            ->where('settings.bookings.types.0.active', true)
            ->where('settings.bookings.types.1.name', 'Tour Free')
            ->where('settings.bookings.shiftKindId', $kind->id)
            ->where('settings.bookings.label', 'Group tours')
            ->where('settings.bookings.shiftKinds.0.name', 'Group Tour')
            // A Booker holds no scheduling right, so the scheduling cards stay shut.
            ->where('settings.shiftKinds', null));
});

it('gives the Chair and super-tier the bookings card', function () {
    $group = bookingGroup();

    foreach ([bookingMemberOf($group, Role::Chair), Member::factory()->superTier()->create()] as $actor) {
        $this->actingAs($actor)
            ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manageBookings', true)
                ->where('settings.bookings.types', []));
    }
});

it('shows no bookings card on a Group that runs no bookings', function () {
    $group = bookingGroup(['has_bookings' => false]);

    $this->actingAs(bookingMemberOf($group, Role::Chair))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageBookings', false)
            ->where('settings.bookings', null));
});

it('forbids the Settings tab to an ordinary Member of a bookings Group', function () {
    $group = bookingGroup();

    $this->actingAs(bookingMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertForbidden();
});

// --- Adding, renaming, re-rating, retiring, reordering ---------------------

it('lets a Booker add a booking type with its rates, active and last in the order', function () {
    $group = bookingGroup();
    BookingType::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->post(route('groups.booking-types.store', ['group' => $group]), [
            'name' => 'Spot Paid',
            'rate_per_visitor' => '0',
            'rate_per_docent_hour' => '25',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $type = $group->bookingTypes()->where('name', 'Spot Paid')->sole();
    expect($type->rate_per_visitor)->toBe('0.00');
    expect($type->rate_per_docent_hour)->toBe('25.00');
    expect($type->active)->toBeTrue();
    expect($type->sort_order)->toBe(1);
});

it('lets the Chair and super-tier add a booking type', function () {
    $group = bookingGroup();
    $rates = ['rate_per_visitor' => '0', 'rate_per_docent_hour' => '0'];

    $this->actingAs(bookingMemberOf($group, Role::Chair))
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'Tour Free', ...$rates])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'Tour Internal', ...$rates])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($group->bookingTypes()->pluck('name')->sort()->values()->all())->toBe(['Tour Free', 'Tour Internal']);
});

it('refuses a duplicate name within the Group but allows it in another Group', function () {
    $group = bookingGroup();
    $other = bookingGroup();
    BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid']);
    BookingType::factory()->create(['group_id' => $other->id, 'name' => 'Tour Free']);
    $rates = ['rate_per_visitor' => '5', 'rate_per_docent_hour' => '0'];

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'Tour Paid', ...$rates])
        ->assertSessionHasErrors('name');
    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'Tour Free', ...$rates])
        ->assertSessionHasNoErrors();
});

it('refuses a negative or missing rate', function () {
    $group = bookingGroup();

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'Tour Paid', 'rate_per_visitor' => '-1'])
        ->assertSessionHasErrors(['rate_per_visitor', 'rate_per_docent_hour']);

    expect($group->bookingTypes()->count())->toBe(0);
});

it('lets a Booker rename a type, change its rates, retire and restore it', function () {
    $group = bookingGroup();
    $type = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid', 'rate_per_visitor' => '5.00']);
    $booker = bookingMemberOf($group, Role::Booker);

    $this->actingAs($booker)
        ->patch(route('booking-types.update', ['bookingType' => $type]), ['name' => 'Tour Paid (adult)', 'rate_per_visitor' => '7.50'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $type->refresh();
    expect($type->name)->toBe('Tour Paid (adult)');
    expect($type->rate_per_visitor)->toBe('7.50');

    $this->actingAs($booker)->patch(route('booking-types.update', ['bookingType' => $type]), ['active' => false]);
    expect($type->refresh()->active)->toBeFalse();

    $this->actingAs($booker)->patch(route('booking-types.update', ['bookingType' => $type]), ['active' => true]);
    expect($type->refresh()->active)->toBeTrue();
});

it('refuses a rename onto another type of the Group', function () {
    $group = bookingGroup();
    BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Free']);
    $type = BookingType::factory()->create(['group_id' => $group->id, 'name' => 'Tour Paid']);

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->patch(route('booking-types.update', ['bookingType' => $type]), ['name' => 'Tour Free'])
        ->assertSessionHasErrors('name');
});

it('lets a Booker reorder the types', function () {
    $group = bookingGroup();
    $first = BookingType::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $second = BookingType::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->patch(route('groups.booking-types.reorder', ['group' => $group]), ['ids' => [$second->id, $first->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($second->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
});

it('refuses to reorder in a type of another Group', function () {
    $group = bookingGroup();
    $foreign = BookingType::factory()->create();

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->patch(route('groups.booking-types.reorder', ['group' => $group]), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');
});

// --- Delete -----------------------------------------------------------------

it('lets a Booker delete a type no Booking uses', function () {
    $group = bookingGroup();
    $type = BookingType::factory()->create(['group_id' => $group->id]);

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->delete(route('booking-types.destroy', ['bookingType' => $type]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(BookingType::find($type->id))->toBeNull();
});

it('refuses to delete a type a Booking uses, even for the super-tier, but lets it be retired', function () {
    $group = bookingGroup();
    $type = BookingType::factory()->create(['group_id' => $group->id]);
    Booking::factory()->create(['group_id' => $group->id, 'booking_type_id' => $type->id]);
    $super = Member::factory()->superTier()->create();

    $this->actingAs($super)
        ->delete(route('booking-types.destroy', ['bookingType' => $type]))
        ->assertSessionHasErrors('booking_type');
    expect(BookingType::find($type->id))->not->toBeNull();

    $this->actingAs($super)
        ->patch(route('booking-types.update', ['bookingType' => $type]), ['active' => false])
        ->assertSessionHasNoErrors();
    expect($type->refresh()->active)->toBeFalse();
});

// --- Who is refused ---------------------------------------------------------

it('refuses every booking-type write to an ordinary Member, a Scheduler and a Statistician', function (?Role $role) {
    $group = bookingGroup();
    $type = BookingType::factory()->create(['group_id' => $group->id]);
    $actor = bookingMemberOf($group, $role);

    $this->actingAs($actor)
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'X', 'rate_per_visitor' => '0', 'rate_per_docent_hour' => '0'])
        ->assertForbidden();
    $this->actingAs($actor)
        ->patch(route('booking-types.update', ['bookingType' => $type]), ['name' => 'Y'])
        ->assertForbidden();
    $this->actingAs($actor)
        ->patch(route('groups.booking-types.reorder', ['group' => $group]), ['ids' => [$type->id]])
        ->assertForbidden();
    $this->actingAs($actor)
        ->delete(route('booking-types.destroy', ['bookingType' => $type]))
        ->assertForbidden();
    $this->actingAs($actor)
        ->patch(route('groups.group-tours.update', ['group' => $group]), ['group_tour_shift_kind_id' => null, 'group_tour_label' => 'X'])
        ->assertForbidden();
})->with([
    'ordinary Member' => [null],
    'Scheduler' => [Role::Scheduler],
    'Statistician' => [Role::Statistician],
]);

it('refuses a Booker of another Group', function () {
    $group = bookingGroup();
    $type = BookingType::factory()->create(['group_id' => $group->id]);
    $outsider = bookingMemberOf(bookingGroup(), Role::Booker);

    $this->actingAs($outsider)
        ->patch(route('booking-types.update', ['bookingType' => $type]), ['name' => 'Y'])
        ->assertForbidden();
    $this->actingAs($outsider)
        ->delete(route('booking-types.destroy', ['bookingType' => $type]))
        ->assertForbidden();
});

it('refuses the Chair once the Group stops running bookings', function () {
    $group = bookingGroup(['has_bookings' => false]);

    $this->actingAs(bookingMemberOf($group, Role::Chair))
        ->post(route('groups.booking-types.store', ['group' => $group]), ['name' => 'X', 'rate_per_visitor' => '0', 'rate_per_docent_hour' => '0'])
        ->assertForbidden();
});

// --- Group-tour settings ----------------------------------------------------

it('lets a Booker set the group-tour shift kind and Schedule label', function () {
    $group = bookingGroup();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id]);

    $this->actingAs(bookingMemberOf($group, Role::Booker))
        ->patch(route('groups.group-tours.update', ['group' => $group]), [
            'group_tour_shift_kind_id' => $kind->id,
            'group_tour_label' => 'Visites de groupe',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $group->refresh();
    expect($group->group_tour_shift_kind_id)->toBe($kind->id);
    expect($group->group_tour_label)->toBe('Visites de groupe');
});

it('refuses a shift kind of another Group and a blank label', function () {
    $group = bookingGroup();
    $foreign = ShiftKind::factory()->create();

    $this->actingAs(bookingMemberOf($group, Role::Chair))
        ->patch(route('groups.group-tours.update', ['group' => $group]), [
            'group_tour_shift_kind_id' => $foreign->id,
            'group_tour_label' => '',
        ])
        ->assertSessionHasErrors(['group_tour_shift_kind_id', 'group_tour_label']);
});
