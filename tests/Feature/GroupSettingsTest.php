<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\HandlingObject;
use App\Models\Member;
use App\Models\ShiftKind;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Group Settings tab (#604, spec #603, ADR-0027 §1). A section of the Group page, shown
 * only to a viewer holding a Group-scoped configuration right, holding one page of settings
 * cards. It carries the Reminders, Empty-desk alert, Self-serve shifts, Shift kinds and Objects
 * cards, moved off the Scheduling tab (#604, #606, #607). Asserted at the Inertia prop seam per
 * actor. Prior art: GroupPageTest, GroupSchedulingTest, ReminderSettingsTest, EmptyDeskSettingsTest,
 * SelfServeSettingsTest, ShiftKindManagementTest, ObjectManagementTest.
 */

/** A Member of $group carrying an optional role. */
function settingsMemberOf(Group $group, ?Role $role = null): Member
{
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);

    if ($role !== null) {
        GroupMemberRole::factory()->create(['group_member_id' => $membership->id, 'role' => $role]);
    }

    return $member;
}

/** A scheduling Group, listed org-wide, with known Reminder settings. */
function settingsGroup(): Group
{
    return Group::factory()->program()->publicListing()->create([
        'reminders_enabled' => true,
        'reminder_lead_days' => 5,
    ]);
}

// --- Who reaches the section ------------------------------------------------

it('opens the Settings section to a Scheduler with the Reminders payload', function () {
    $group = settingsGroup();

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'settings')
            ->where('can.manageSettings', true)
            ->where('can.manageReminders', true)
            ->where('settings.reminders', ['enabled' => true, 'leadDays' => 5]));
});

it('opens the Settings section to a Chair who holds no Scheduler role', function () {
    $group = settingsGroup();

    $this->actingAs(settingsMemberOf($group, role: Role::Chair))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', true)
            ->where('settings.reminders.leadDays', 5));
});

it('forbids the Settings section to an ordinary Member', function () {
    $group = settingsGroup();

    $this->actingAs(settingsMemberOf($group))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertForbidden();
});

it('forbids the Settings section to a non-member', function () {
    $group = settingsGroup();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertForbidden();
});

it('opens the Settings section to a super-tier Member without membership', function () {
    // The super-tier passes every gate (the Gate::before short-circuit), so it sees the tab.
    $group = settingsGroup();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', true)
            ->where('settings.reminders.leadDays', 5));
});

it('gives a super-tier Member an empty Settings section on a Group that runs no scheduling', function () {
    // The tab appears with authority, not with data (ADR-0027 §1). The Reminders card is a
    // scheduling card, so it has no payload here.
    $group = Group::factory()->publicListing()->create(['has_scheduling' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', true)
            ->where('settings.reminders', null));
});

it('forbids the Settings section to the Chair of a Group that runs no scheduling', function () {
    // Today every configuration right is a scheduling one, so a non-scheduling Group has none.
    $group = Group::factory()->publicListing()->create(['has_scheduling' => false]);

    $this->actingAs(settingsMemberOf($group, role: Role::Chair))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertForbidden();
});

// --- The Empty-desk and Self-serve cards (#606) ------------------------------

it('carries the Empty-desk payload with its watched shift kinds on the Settings section', function () {
    $group = settingsGroup();
    $group->update(['empty_desk_alert_enabled' => true, 'empty_desk_days_ahead' => 4]);
    $desk = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Desk', 'sort_order' => 1, 'alert_when_empty' => true]);
    $tour = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Tour', 'sort_order' => 2, 'alert_when_empty' => false]);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageEmptyDesk', true)
            ->where('settings.emptyDesk', [
                'enabled' => true,
                'daysAhead' => 4,
                'shiftKinds' => [
                    ['id' => $desk->id, 'name' => 'Desk', 'watched' => true],
                    ['id' => $tour->id, 'name' => 'Tour', 'watched' => false],
                ],
            ]));
});

it('carries the Self-serve payload on the Settings section', function () {
    $group = settingsGroup();
    $group->update(['self_serve_shifts' => true, 'self_serve_unit_minutes' => 45]);

    $this->actingAs(settingsMemberOf($group, role: Role::Chair))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSelfServe', true)
            ->where('settings.selfServe', ['enabled' => true, 'unitMinutes' => 45]));
});

it('gives no Empty-desk or Self-serve payload on a Group that runs no scheduling', function () {
    $group = Group::factory()->publicListing()->create(['has_scheduling' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.emptyDesk', null)
            ->where('settings.selfServe', null));
});

// --- The Shift kinds and Objects cards (#607) ---------------------------------

it('carries the full shift-kind list, retired kinds too, on the Settings section', function () {
    $group = settingsGroup();
    $active = ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Desk', 'sort_order' => 0]);
    $retired = ShiftKind::factory()->inactive()->create(['group_id' => $group->id, 'name' => 'Old tour', 'sort_order' => 1, 'off_site' => true]);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageShiftKinds', true)
            ->where('settings.shiftKinds', [
                ['id' => $active->id, 'name' => 'Desk', 'active' => true, 'offSite' => false, 'sortOrder' => 0],
                ['id' => $retired->id, 'name' => 'Old tour', 'active' => false, 'offSite' => true, 'sortOrder' => 1],
            ]));
});

it('carries the full Object list, retired Objects too, on the Settings section', function () {
    $group = settingsGroup();
    $active = HandlingObject::factory()->create(['group_id' => $group->id, 'name' => 'Ammonite', 'sort_order' => 0]);
    $retired = HandlingObject::factory()->inactive()->create(['group_id' => $group->id, 'name' => 'Old fossil', 'sort_order' => 1]);

    $this->actingAs(settingsMemberOf($group, role: Role::Chair))
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageObjects', true)
            ->where('settings.objects', [
                ['id' => $active->id, 'name' => 'Ammonite', 'active' => true, 'sortOrder' => 0],
                ['id' => $retired->id, 'name' => 'Old fossil', 'active' => false, 'sortOrder' => 1],
            ]));
});

it('gives no shift-kind or Object list on a Group that runs no scheduling', function () {
    $group = Group::factory()->publicListing()->create(['has_scheduling' => false]);

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.shiftKinds', null)
            ->where('settings.objects', null));
});

// --- The hint and the payload on other sections -----------------------------

it('carries the manage-Settings hint on every section, and the card payloads only on Settings', function () {
    $group = settingsGroup();
    $group->update(['self_serve_unit_minutes' => 45]);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', true)
            ->where('settings.reminders', null)
            ->where('settings.emptyDesk', null)
            ->where('settings.selfServe', null)
            ->where('settings.shiftKinds', null)
            ->where('settings.objects', null)
            ->missing('group.reminders')
            ->missing('group.emptyDesk')
            ->missing('group.selfServe')
            // The self-serve write dialog on the Scheduling tab still derives a Shift's end.
            ->where('group.selfServeUnitMinutes', 45));
});

it('withholds the manage-Settings hint from an ordinary Member', function () {
    $group = settingsGroup();

    $this->actingAs(settingsMemberOf($group))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', false)
            ->where('settings.reminders', null)
            ->where('settings.emptyDesk', null)
            ->where('settings.selfServe', null)
            ->where('settings.shiftKinds', null)
            ->where('settings.objects', null));
});

// --- Saving returns to the Settings tab -------------------------------------

it('returns the Scheduler to the Settings tab after saving the Reminder settings', function () {
    $group = settingsGroup();
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => false,
            'reminder_lead_days' => 3,
        ])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasNoErrors();
});

it('returns a validation error to the Settings tab', function () {
    $group = settingsGroup();
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->patch(route('groups.reminders.update', ['group' => $group]), [
            'reminders_enabled' => true,
            'reminder_lead_days' => 0,
        ])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasErrors('reminder_lead_days');
});

it('returns the Scheduler to the Settings tab after saving the Empty-desk settings', function () {
    $group = settingsGroup();
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 3,
            'watched_shift_kinds' => [],
        ])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasNoErrors();
});

it('returns an Empty-desk validation error to the Settings tab', function () {
    $group = settingsGroup();
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->patch(route('groups.empty-desk.update', ['group' => $group]), [
            'empty_desk_alert_enabled' => true,
            'empty_desk_days_ahead' => 0,
            'watched_shift_kinds' => [],
        ])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasErrors('empty_desk_days_ahead');
});

it('returns the Scheduler to the Settings tab after saving the Self-serve settings', function () {
    $group = settingsGroup();
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 45,
        ])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasNoErrors();
});

it('returns a Self-serve validation error to the Settings tab', function () {
    $group = settingsGroup();
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->patch(route('groups.self-serve.update', ['group' => $group]), [
            'self_serve_shifts' => true,
            'self_serve_unit_minutes' => 5,
        ])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasErrors('self_serve_unit_minutes');
});

it('returns the Scheduler to the Settings tab after every shift-kind write', function () {
    $group = settingsGroup();
    $kind = ShiftKind::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $other = ShiftKind::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);
    $scheduler = settingsMemberOf($group, role: Role::Scheduler);
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $writes = [
        ['post', route('groups.shift-kinds.store', ['group' => $group]), ['name' => 'Highlights tour']],
        ['patch', route('shift-kinds.update', ['shiftKind' => $kind]), ['name' => 'Front desk']],
        ['patch', route('shift-kinds.update', ['shiftKind' => $kind]), ['active' => false]],
        ['patch', route('shift-kinds.update', ['shiftKind' => $kind]), ['active' => true]],
        ['patch', route('shift-kinds.update', ['shiftKind' => $kind]), ['off_site' => true]],
        ['patch', route('groups.shift-kinds.reorder', ['group' => $group]), ['ids' => [$other->id, $kind->id]]],
    ];

    foreach ($writes as [$method, $url, $body]) {
        $this->actingAs($scheduler)
            ->from($settingsUrl)
            ->{$method}($url, $body)
            ->assertRedirect($settingsUrl)
            ->assertSessionHasNoErrors();
    }
});

it('returns a shift-kind validation error to the Settings tab', function () {
    $group = settingsGroup();
    ShiftKind::factory()->create(['group_id' => $group->id, 'name' => 'Desk']);
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->post(route('groups.shift-kinds.store', ['group' => $group]), ['name' => 'Desk'])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasErrors('name');
});

it('returns the Scheduler to the Settings tab after every Object write', function () {
    $group = settingsGroup();
    $object = HandlingObject::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $other = HandlingObject::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);
    $scheduler = settingsMemberOf($group, role: Role::Scheduler);
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $writes = [
        ['post', route('groups.objects.store', ['group' => $group]), ['name' => 'Trilobite fossil']],
        ['patch', route('objects.update', ['object' => $object]), ['name' => 'Ammonite shell']],
        ['patch', route('objects.update', ['object' => $object]), ['active' => false]],
        ['patch', route('objects.update', ['object' => $object]), ['active' => true]],
        ['patch', route('groups.objects.reorder', ['group' => $group]), ['ids' => [$other->id, $object->id]]],
    ];

    foreach ($writes as [$method, $url, $body]) {
        $this->actingAs($scheduler)
            ->from($settingsUrl)
            ->{$method}($url, $body)
            ->assertRedirect($settingsUrl)
            ->assertSessionHasNoErrors();
    }
});

it('returns an Object validation error to the Settings tab', function () {
    $group = settingsGroup();
    HandlingObject::factory()->create(['group_id' => $group->id, 'name' => 'Meteorite']);
    $settingsUrl = route('groups.show', ['group' => $group, 'section' => 'settings']);

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->from($settingsUrl)
        ->post(route('groups.objects.store', ['group' => $group]), ['name' => 'Meteorite'])
        ->assertRedirect($settingsUrl)
        ->assertSessionHasErrors('name');
});

// --- The French path ----------------------------------------------------------

it('resolves the Settings section under its French path segment /fr/groupes/{group}/parametres', function () {
    $group = settingsGroup();
    $scheduler = settingsMemberOf($group, role: Role::Scheduler);
    $plain = settingsMemberOf($group);

    $this->withLocaleRoutes('fr', function () use ($group, $scheduler, $plain) {
        $this->actingAs($scheduler)
            ->get("/fr/groupes/{$group->slug}/parametres")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('section', 'settings')
                ->where('settings.reminders.leadDays', 5));

        $this->actingAs($plain)
            ->get("/fr/groupes/{$group->slug}/parametres")
            ->assertForbidden();
    });
});
