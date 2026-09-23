<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use App\Models\ShiftKind;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Group Settings tab (#604, spec #603, ADR-0027 §1). A section of the Group page, shown
 * only to a viewer holding a Group-scoped configuration right, holding one page of settings
 * cards. So far it carries the Reminders, Empty-desk alert and Self-serve shifts cards, moved off
 * the Scheduling tab (#604, #606). Asserted at the Inertia prop seam per actor. Prior art:
 * GroupPageTest, GroupSchedulingTest, ReminderSettingsTest, EmptyDeskSettingsTest,
 * SelfServeSettingsTest.
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
            ->where('settings.selfServe', null));
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
