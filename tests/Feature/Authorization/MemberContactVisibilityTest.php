<?php

use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Models\Skill;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Field-level contact visibility (#154, ADR-0017). The centralized MemberResource
 * exposes an allowlist — name/photo/Groups-roles to every logged-in member, contact
 * PII (email, phone) only when the viewer passes the `viewContact` ability. Asserted
 * at the HTTP seam as a per-actor matrix: own-Group contact-need officer and
 * Records/super see contact; non-officer and wrong-Group officer do not.
 */

/** A target member who belongs to the given Group. */
function targetInGroup(Group $group): Member
{
    $target = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $target->id]);

    return $target;
}

/** A member holding the given role in the given Group. */
function officerOf(Group $group, Role $role): Member
{
    $officer = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $officer->id]);
    GroupMemberRole::factory()->role($role)->create(['group_member_id' => $membership->id]);

    return $officer;
}

/** A member holding member-administration authority via the Records stewardship. */
function recordsContactOfficer(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()
        ->stewarding(StewardshipFunction::MemberAdmin)
        ->create(['group_id' => $records->id]);

    $officer = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $officer->id]);

    return $officer;
}

// --- Always-public allowlist ------------------------------------------------

it('always exposes name and Groups-roles to any logged-in member', function () {
    $group = Group::factory()->create();
    $target = targetInGroup($group);
    GroupMemberRole::factory()->role(Role::Chair)
        ->create(['group_member_id' => $target->memberships()->first()->id]);

    $this->actingAs(Member::factory()->create())
        ->get(route('members.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.first_name', $target->first_name)
            ->where('member.last_name', $target->last_name)
            ->has('member.groups', 1)
            ->where('member.groups.0.name', $group->name)
            ->where('member.groups.0.roles.0', Role::Chair->value)
            ->has('member.photo'));
});

it('omits fields that are not on the allowlist', function () {
    $target = Member::factory()->create();

    $this->actingAs(recordsContactOfficer())
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('member.password')
            ->missing('member.category')
            ->missing('member.super_tier'));
});

// --- Contact granted --------------------------------------------------------

it('exposes contact to an own-Group officer holding a contact-need role', function () {
    $group = Group::factory()->create();
    $target = targetInGroup($group);

    $this->actingAs(officerOf($group, Role::Chair))
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.email', $target->email)
            ->where('member.phone', $target->phone));
});

it('exposes all three phones to a contact-need officer', function () {
    $group = Group::factory()->create();
    $target = targetInGroup($group);
    $target->update([
        'alternate_phone' => '416-555-0101',
        'business_phone' => '416-555-0102',
    ]);

    $this->actingAs(officerOf($group, Role::Chair))
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.phone', $target->phone)
            ->where('member.alternate_phone', '416-555-0101')
            ->where('member.business_phone', '416-555-0102'));
});

it('exposes contact to a Records officer org-wide', function () {
    $target = Member::factory()->create();

    $this->actingAs(recordsContactOfficer())
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.email', $target->email)
            ->where('member.phone', $target->phone));
});

it('exposes contact to a super-tier member org-wide', function () {
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.email', $target->email)
            ->where('member.phone', $target->phone));
});

// --- Contact denied (required deny rows) ------------------------------------

it('hides contact from a non-officer member', function () {
    $target = Member::factory()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('members.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.first_name', $target->first_name)
            ->where('member.last_name', $target->last_name)
            ->missing('member.email')
            ->missing('member.phone'));
});

it('hides contact from an officer of an unrelated Group', function () {
    $target = targetInGroup(Group::factory()->create());
    $unrelated = Group::factory()->create();

    $this->actingAs(officerOf($unrelated, Role::Chair))
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('member.email')
            ->missing('member.phone'));
});

it('hides contact from an own-Group officer whose role is not contact-need', function () {
    $group = Group::factory()->create();
    $target = targetInGroup($group);

    $this->actingAs(officerOf($group, Role::Treasurer))
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('member.email')
            ->missing('member.phone'));
});

// --- Home address: Records-only, never peer-visible --------------------------

it('exposes the home address to a Records officer', function () {
    $target = Member::factory()->create(['address_street' => '100 Queens Park']);

    $this->actingAs(recordsContactOfficer())
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.address.street', '100 Queens Park'));
});

it('hides the home address from a peer contact-need officer even when contact is granted', function () {
    $group = Group::factory()->create();
    $target = targetInGroup($group);
    $target->update(['address_street' => '100 Queens Park']);

    // A Chair passes viewContact — so the phones are present — yet the address is a
    // stricter Records-only tier and must be wholly absent (#232, ADR-0017).
    $this->actingAs(officerOf($group, Role::Chair))
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.phone', $target->phone)
            ->missing('member.address'));
});

it('exposes the home address to the member themselves', function () {
    $member = Member::factory()->create(['address_street' => '100 Queens Park']);

    $this->actingAs($member)
        ->get(route('members.show', $member))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.address.street', '100 Queens Park'));
});

it('hides the home address from a non-officer member', function () {
    $target = Member::factory()->create(['address_street' => '100 Queens Park']);

    $this->actingAs(Member::factory()->create())
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('member.address'));
});

// --- Skills: Records-only, never peer-visible --------------------------------

/** A single active catalog skill, attached to the given member. */
function skillHeldBy(Member $member): Skill
{
    $skill = Skill::factory()->create();
    $member->skills()->attach($skill);

    return $skill;
}

it('never exposes a member\'s skills in any peer-visible payload', function () {
    $group = Group::factory()->create();
    $target = targetInGroup($group);
    skillHeldBy($target);

    // A Chair passes viewContact — the phones are present — yet skills are a
    // stricter Records-only tier and must be wholly absent, even here (#247).
    $peer = officerOf($group, Role::Chair);

    // Profile show.
    $this->actingAs($peer)
        ->get(route('members.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('member.phone', $target->phone)
            ->missing('member.skills'));

    // Directory list.
    $this->actingAs($peer)
        ->get(route('directory'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', function ($members) use ($target) {
                $row = collect($members)->firstWhere('id', $target->id);

                return $row !== null && ! array_key_exists('skills', $row);
            }));

    // Group roster.
    $this->actingAs($peer)
        ->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roster', fn ($roster) => ! array_key_exists('skills', collect($roster)->firstWhere('id', $target->id))));
});

it('exposes the owner\'s own skill selections on their Skills settings page', function () {
    $member = Member::factory()->create();
    $skill = skillHeldBy($member);

    $this->actingAs($member)
        ->get(route('settings.skills'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected', fn ($selected) => collect($selected)->contains($skill->id)));
});

it('redirects an unauthenticated request to login', function () {
    $this->get(route('members.show', Member::factory()->create()))
        ->assertRedirect(route('login'));
});
