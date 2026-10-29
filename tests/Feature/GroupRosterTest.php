<?php

use App\Enums\Category;
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

it('renders the root DMV Group roster from the Directory, with no roster management', function () {
    // Root-DMV membership is derived from Category and never stored (ADR-0020 §B, ADR-0036
    // §9): the root's Roster is the Directory — listed Members A–Z, no in-Group roles — and
    // its rows cannot be managed, so even the super-tier gets no add-member scaffolding.
    $root = Group::factory()->standingCommittee()->create(['slug' => Group::ROOT_SLUG, 'name' => 'DMV']);
    Member::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Young']);
    Member::factory()->category(Category::Loa)->create(['first_name' => 'Ada', 'last_name' => 'Adams']);
    Member::factory()->category(Category::Resigned)->create(['first_name' => 'Re', 'last_name' => 'Signed']);
    Member::factory()->category(Category::Provisional)->create(['first_name' => 'Pro', 'last_name' => 'Visional']);

    $this->actingAs(Member::factory()->superTier()->create(['first_name' => 'Margaret', 'last_name' => 'Chen']))
        ->get(route('groups.show', ['group' => $root, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageRoster', false)
            ->where('rosterMeta.candidates', fn (Collection $candidates) => $candidates->isEmpty())
            ->where('roster', function (Collection $roster) {
                return $roster->pluck('last_name')->all() === ['Adams', 'Chen', 'Young']
                    && $roster->firstWhere('last_name', 'Young')['group_roles'] === []
                    && $roster->firstWhere('last_name', 'Young')['group_standing'] === MembershipStatus::Full->value
                    && $roster->firstWhere('last_name', 'Adams')['group_standing'] === MembershipStatus::Loa->value
                    && $roster->firstWhere('last_name', 'Adams')['can_hard_remove'] === false;
            }));
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

it('carries an LOA Member’s DMV-wide standing beside a Full Group standing, for a manager and a non-manager', function () {
    // The roster marks the DMV-wide standing (Category) for a roster manager only (#877); the
    // frontend reads it off `standing` and gates it on `can.manageRoster`. The Category change
    // leaves the Group standing alone: the two stay separate.
    $group = Group::factory()->create();
    $secretary = rosterMember($group, 'Sec', 'Retary', MembershipStatus::Full, [Role::Secretary]);
    $leave = rosterMember($group, 'Lou', 'Leave', MembershipStatus::Full);
    $leave->update(['category' => Category::Loa]);
    $section = ['group' => $group, 'section' => 'roster'];

    $loaRow = fn (Collection $roster) => $roster->firstWhere('id', $leave->id)['standing'] === Category::Loa->value
        && $roster->firstWhere('id', $leave->id)['group_standing'] === MembershipStatus::Full->value;

    $this->actingAs($secretary)
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageRoster', true)->where('roster', $loaRow));

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $section))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageRoster', false)->where('roster', $loaRow));
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

// --- Inactive Groups are read-only (#839, ADR-0036 §2) ------------------------

it('refuses a Chair a roster change on an archived Group while the roster still reads', function () {
    $group = Group::factory()->archived()->create();
    $chair = rosterMember($group, 'Carol', 'Chair', MembershipStatus::Full, [Role::Chair]);
    $existing = rosterMember($group, 'Ada', 'Adams');
    $membership = GroupMember::where(['group_id' => $group->id, 'member_id' => $existing->id])->sole();
    $outsider = Member::factory()->create();

    $this->actingAs($chair)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageRoster', false)
            ->where('roster', fn (Collection $roster) => $roster->contains('id', $existing->id)));

    $this->actingAs($chair)
        ->post(route('group-members.store', $group), ['member_ids' => [$outsider->id]])
        ->assertForbidden();

    $this->actingAs($chair)
        ->patch(route('group-members.update', $membership), ['status' => MembershipStatus::Inactive->value])
        ->assertForbidden();

    expect(GroupMember::where('member_id', $outsider->id)->exists())->toBeFalse()
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Full);
});

it('refuses the super-tier a roster addition on an expired time-boxed Group', function () {
    $group = Group::factory()->timeBoxed()->create(['end_date' => now()->subDay()->toDateString()]);
    $outsider = Member::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->post(route('group-members.store', $group), ['member_ids' => [$outsider->id]])
        ->assertForbidden();

    expect(GroupMember::where('member_id', $outsider->id)->exists())->toBeFalse();
});
