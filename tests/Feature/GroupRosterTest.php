<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group Roster tab (#189, PRD #186). The Group-scoped instance of the Directory
 * surface, asserted at the Inertia prop seam: the roster lists the Group's members
 * A–Z with their in-Group role badge(s) and within-Group standing; the departed
 * (Resigned / Deceased) are hidden by default while Inactive shows; contact details
 * ride along only for a viewContact-authorized viewer; any logged-in Member may view.
 */

/** Attach a Member to a Group with a standing and optional roles, returning the Member. */
function rosterMember(Group $group, string $first, string $last, MembershipStatus $status = MembershipStatus::Full, array $roles = []): Member
{
    $member = Member::factory()->create(['first_name' => $first, 'last_name' => $last]);
    $membership = GroupMember::factory()->status($status)->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);
    foreach ($roles as $role) {
        $membership->roles()->create(['role' => $role]);
    }

    return $member;
}

it('lists the Group members A–Z with role and standing fields', function () {
    $group = Group::factory()->create();
    rosterMember($group, 'Zoe', 'Young', MembershipStatus::Full, [Role::Chair]);
    rosterMember($group, 'Ada', 'Adams', MembershipStatus::Inactive);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roster', function (Collection $roster) {
                $names = $roster->map(fn ($row) => $row['last_name'])->all();

                return $names === ['Adams', 'Young']
                    && $roster->firstWhere('last_name', 'Young')['group_roles'] === [Role::Chair->value]
                    && $roster->firstWhere('last_name', 'Young')['group_standing'] === MembershipStatus::Full->value
                    && $roster->firstWhere('last_name', 'Adams')['group_standing'] === MembershipStatus::Inactive->value;
            }));
});

it('hides Resigned and Deceased members but shows Inactive by default', function () {
    $group = Group::factory()->create();
    $full = rosterMember($group, 'Full', 'Active', MembershipStatus::Full);
    $inactive = rosterMember($group, 'In', 'Active', MembershipStatus::Inactive);
    $resigned = rosterMember($group, 'Re', 'Signed', MembershipStatus::Resigned);
    $deceased = rosterMember($group, 'De', 'Ceased', MembershipStatus::Deceased);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roster', function (Collection $roster) use ($full, $inactive, $resigned, $deceased) {
                $ids = $roster->pluck('id')->all();

                return in_array($full->id, $ids, true)
                    && in_array($inactive->id, $ids, true)
                    && ! in_array($resigned->id, $ids, true)
                    && ! in_array($deceased->id, $ids, true);
            }));
});

it('exposes contact details only to a viewContact-authorized viewer', function () {
    $group = Group::factory()->create();
    rosterMember($group, 'Tara', 'Target', MembershipStatus::Full);

    // A Chair of the same Group holds a contact-need role and may see contact.
    $chair = rosterMember($group, 'Cara', 'Chair', MembershipStatus::Full, [Role::Chair]);

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roster', function (Collection $roster) {
                $target = $roster->firstWhere('last_name', 'Target');

                return array_key_exists('email', $target) && $target['email'] !== null;
            }));
});

it('withholds contact details from an ordinary member', function () {
    $group = Group::factory()->create();
    rosterMember($group, 'Tara', 'Target', MembershipStatus::Full);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roster', fn (Collection $roster) => ! array_key_exists('email', $roster->firstWhere('last_name', 'Target'))
                && ! array_key_exists('phone', $roster->firstWhere('last_name', 'Target'))));
});

it('lets any logged-in Member view an active Group roster', function () {
    $group = Group::factory()->create();
    rosterMember($group, 'Sam', 'Stone');

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('roster', 1));
});

// --- Officer affordances (#192) --------------------------------------------

it('hints roster management on for an officer and off for an ordinary member', function () {
    $group = Group::factory()->create();
    $secretary = rosterMember($group, 'Sec', 'Retary', MembershipStatus::Full, [Role::Secretary]);
    $section = ['group' => $group, 'section' => 'roster'];

    $this->actingAs($secretary)
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageRoster', true));

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageRoster', false));
});

it('reveals Resigned to an officer who shows past members, never Deceased', function () {
    $group = Group::factory()->create();
    $secretary = rosterMember($group, 'Sec', 'Retary', MembershipStatus::Full, [Role::Secretary]);
    $resigned = rosterMember($group, 'Re', 'Signed', MembershipStatus::Resigned);
    $deceased = rosterMember($group, 'De', 'Ceased', MembershipStatus::Deceased);

    $this->actingAs($secretary)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster', 'past' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.showingPast', true)
            ->where('roster', function (Collection $roster) use ($resigned, $deceased) {
                $ids = $roster->pluck('id')->all();

                return in_array($resigned->id, $ids, true) && ! in_array($deceased->id, $ids, true);
            }));
});

it('ignores the show-past toggle for an ordinary member', function () {
    $group = Group::factory()->create();
    rosterMember($group, 'Re', 'Signed', MembershipStatus::Resigned);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster', 'past' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.showingPast', false)
            ->where('roster', fn (Collection $roster) => $roster->isEmpty()));
});

it('offers add-member candidates and capability-valid roles to an officer only', function () {
    $group = Group::factory()->program()->create(); // scheduling on, vetting off
    $secretary = rosterMember($group, 'Sec', 'Retary', MembershipStatus::Full, [Role::Secretary]);
    $outsider = Member::factory()->create(['first_name' => 'Out', 'last_name' => 'Sider']);

    $this->actingAs($secretary)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.candidates', fn (Collection $candidates) => $candidates->contains('id', $outsider->id)
                && ! $candidates->contains('id', $secretary->id))
            ->where('rosterMeta.assignableRoles', fn (Collection $roles) => $roles->contains(Role::Scheduler->value)
                && ! $roles->contains(Role::Vetting->value)));

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rosterMeta.candidates', fn (Collection $candidates) => $candidates->isEmpty())
            ->where('rosterMeta.assignableRoles', fn (Collection $roles) => $roles->isEmpty()));
});
