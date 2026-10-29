<?php

use App\Models\Group;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A super-tier Member's capability flags on the Group page (#872). The super-tier `Gate::before`
 * grant skips each policy's capability guard, so the controller checks the Group's capability
 * itself: the super-tier gets a capability's flags only on a Group that runs it.
 */

it('gives a super-tier Member no Tour flags on a Group without vetting', function () {
    $group = Group::factory()->program()->create(['has_vetting' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTours', false)
            ->where('can.manageTourRules', false));
});

it('gives a super-tier Member the Tour flags on a vetting Group', function () {
    $group = Group::factory()->program()->create(['has_vetting' => true]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageTours', true)
            ->where('can.manageTourRules', true));
});

it('gives a super-tier Member no Tours tab on a Group without vetting', function () {
    $group = Group::factory()->program()->create(['has_vetting' => false]);
    $super = Member::factory()->superTier()->create();

    $this->actingAs($super)
        ->get(route('groups.show', ['group' => $group]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.capabilities.vetting', false));

    $this->actingAs($super)
        ->get(route('groups.show', ['group' => $group, 'section' => 'tours']))
        ->assertNotFound();
});

it('gives a super-tier Member no capability flags on a Group that runs none', function () {
    $group = Group::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createMeeting', false)
            ->where('can.createSchedule', false)
            ->where('can.viewPastSchedules', false)
            ->where('can.recalculateHours', false)
            ->where('can.manageReminders', false)
            ->where('can.manageEmptyDesk', false)
            ->where('can.manageSelfServe', false)
            ->where('can.manageShiftKinds', false)
            ->where('can.manageObjects', false)
            ->where('can.manageTours', false)
            ->where('can.manageTourRules', false)
            ->where('can.manageBookings', false)
            ->where('can.manageDocuments', false)
            ->where('can.manageSettings', false)
            // Rights with no capability behind them stay granted.
            ->where('can.update', true)
            ->where('can.viewReports', true));
});

it('gives a super-tier Member no Scheduling or Settings section on a Group that runs none', function (string $section, int $status) {
    $group = Group::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => $section]))
        ->assertStatus($status);
})->with([
    'scheduling' => ['scheduling', 404],
    'settings' => ['settings', 403],
]);

it('gives a super-tier Member every capability flag on a Group that runs them all', function () {
    $group = Group::factory()->program()->create(['has_vetting' => true, 'has_bookings' => true]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createMeeting', true)
            ->where('can.createSchedule', true)
            ->where('can.viewPastSchedules', true)
            ->where('can.recalculateHours', true)
            ->where('can.manageReminders', true)
            ->where('can.manageTours', true)
            ->where('can.manageBookings', true)
            ->where('can.manageDocuments', true)
            ->where('can.manageSettings', true));
});
