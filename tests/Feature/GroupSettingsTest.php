<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Group Settings tab (#604, spec #603, ADR-0027 §1). A section of the Group page, shown
 * only to a viewer holding a Group-scoped configuration right, holding one page of settings
 * cards. This slice carries the Reminders card, moved off the Scheduling tab. Asserted at the
 * Inertia prop seam per actor. Prior art: GroupPageTest, GroupSchedulingTest,
 * ReminderSettingsTest.
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

// --- The hint and the payload on other sections -----------------------------

it('carries the manage-Settings hint on every section, and the Reminders payload only on Settings', function () {
    $group = settingsGroup();

    $this->actingAs(settingsMemberOf($group, role: Role::Scheduler))
        ->get(route('groups.show', ['group' => $group, 'section' => 'scheduling']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', true)
            ->where('settings.reminders', null)
            ->missing('group.reminders'));
});

it('withholds the manage-Settings hint from an ordinary Member', function () {
    $group = settingsGroup();

    $this->actingAs(settingsMemberOf($group))
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageSettings', false)
            ->where('settings.reminders', null));
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
