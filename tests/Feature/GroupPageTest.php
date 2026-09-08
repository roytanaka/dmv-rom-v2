<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Database\Seeders\OrgTreeSeeder;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Group detail page (#188, PRD #186). The committee shell + read-only Overview tab,
 * asserted at the Inertia prop seam: the right page and Overview props render, any
 * logged-in Member sees an active Group, an archived Group is reachable directly but
 * absent from its parent's navigable child list, and an unknown slug 404s.
 */

it('redirects an unauthenticated request to login', function () {
    $group = Group::factory()->create();

    $this->get(route('groups.show', $group))->assertRedirect(route('login'));
});

it('renders the Groups/Show page with header and Overview props for any Member', function () {
    $group = Group::factory()->standingCommittee()->create([
        'name' => 'Membership Committee',
        'description' => 'Stewards the membership lifecycle.',
    ]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('group.name', 'Membership Committee')
            ->where('group.archived', false)
            ->where('section', 'overview')
            ->where('overview.description', 'Stewards the membership lifecycle.'));
});

it('defaults a bare slug to the Overview section and reads the {section} segment', function () {
    $group = Group::factory()->create();
    $this->actingAs(Member::factory()->create());

    $this->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('section', 'overview'));

    $this->get(route('groups.show', ['group' => $group, 'section' => 'roster']))
        ->assertInertia(fn (Assert $page) => $page->where('section', 'roster'));
});

it('404s an unknown slug', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/groups/no-such-group')
        ->assertNotFound();
});

/*
 * Hours section (ADR-0022 §3). Hours is always-on: every Group and sub-Group carries
 * the Hours menu, at any depth, whether or not anyone has recorded hours there — with
 * no capability flag to switch it off. The section resolves for any viewer who can
 * open the Group's page, and is withheld only where the whole page is (a Private Group).
 */

it('resolves the Hours section on a sub-Group at depth, with no capability flag', function () {
    // A plain standing committee two levels down — no hours flag exists to switch on.
    $root = Group::factory()->create();
    $parent = Group::factory()->create(['parent_id' => $root->id]);
    $child = Group::factory()->standingCommittee()->create(['parent_id' => $parent->id]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $child, 'section' => 'hours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('section', 'hours'));
});

it('resolves the Hours section on an archived Group that can still be opened', function () {
    $group = Group::factory()->archived()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.archived', true)
            ->where('section', 'hours'));
});

it('404s the Hours section on a Private Group a viewer cannot open, so it is not leaked', function () {
    $group = Group::factory()->privateListing()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', ['group' => $group, 'section' => 'hours']))
        ->assertNotFound();
});

it('resolves the Hours section under its French path segment /fr/groupes/{group}/heures', function () {
    $group = Group::factory()->create(['slug' => 'docents-program']);
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($group) {
        $this->get("/fr/groupes/{$group->slug}/heures")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('groups/Show')
                ->where('locale', 'fr'));
    });
});

it('renders an archived Group directly but flags it archived', function () {
    $group = Group::factory()->archived()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('group.archived', true));
});

it('lists active child Groups but never an archived child', function () {
    $parent = Group::factory()->create();
    $active = Group::factory()->create(['parent_id' => $parent->id, 'name' => 'Digital Working Group']);
    $archived = Group::factory()->archived()->create(['parent_id' => $parent->id, 'name' => 'Oral History Project']);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $parent))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.children', function (Collection $children) use ($active, $archived) {
                $slugs = $children->pluck('slug')->all();

                return in_array($active->slug, $slugs, true)
                    && ! in_array($archived->slug, $slugs, true);
            }));
});

it('surfaces the Group leadership by role, names and profile ids', function () {
    $group = Group::factory()->create();
    $chair = Member::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $membership = GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $group->id,
        'member_id' => $chair->id,
    ]);
    $membership->roles()->create(['role' => Role::Chair]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.leadership', function (Collection $leadership) use ($chair) {
                $entry = $leadership->firstWhere('member_id', $chair->id);

                return $entry !== null
                    && $entry['role'] === Role::Chair->value
                    && $entry['name'] === 'Ada Lovelace';
            }));
});

it('surfaces the executive as the root Group leadership instead of a Chair', function () {
    // The root DMV Group is the exception: no Chair. Its leadership is the super-tier
    // executive, surfaced by authority. A root Group with a super-tier member and,
    // to prove role rows are ignored there, an ordinary member carrying a Chair role.
    $root = Group::factory()->standingCommittee()->create(['parent_id' => null, 'name' => 'DMV']);

    $president = Member::factory()->superTier()->create(['first_name' => 'Margaret', 'last_name' => 'Chen']);
    GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $root->id,
        'member_id' => $president->id,
    ]);

    $chair = Member::factory()->create();
    $chairMembership = GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $root->id,
        'member_id' => $chair->id,
    ]);
    $chairMembership->roles()->create(['role' => Role::Chair]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $root))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.leadership', function (Collection $leadership) use ($president, $chair) {
                $roles = $leadership->pluck('role')->all();

                return $leadership->firstWhere('member_id', $president->id)['role'] === 'executive'
                    && ! in_array(Role::Chair->value, $roles, true)
                    && $leadership->firstWhere('member_id', $chair->id) === null;
            }));
});

it('counts living members in the facts card, excluding the departed', function () {
    $group = Group::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create(['group_id' => $group->id]);
    GroupMember::factory()->status(MembershipStatus::Inactive)->create(['group_id' => $group->id]);
    GroupMember::factory()->status(MembershipStatus::Resigned)->create(['group_id' => $group->id]);
    GroupMember::factory()->status(MembershipStatus::Deceased)->create(['group_id' => $group->id]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('overview.facts.member_count', 2));
});

it('renders the French twin of the Group page', function () {
    $group = Group::factory()->create(['slug' => 'docents-program']);
    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () use ($group) {
        $this->get("/fr/groupes/{$group->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('groups/Show')
                ->where('locale', 'fr'));
    });
});

it('omits contact details from the Overview surface', function () {
    $this->seed(OrgTreeSeeder::class);
    $group = Group::where('slug', OrgTreeSeeder::COMMITTEE)->firstOrFail();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview')
            ->missing('overview.leadership.0.email')
            ->missing('overview.leadership.0.phone'));
});

/*
 * Private page-gate (#270, PRD #268, ADR-0019). A Private Group's page keeps its
 * existence hidden: a viewer who is neither a member nor on the super-tier gets a
 * 404 — deliberately not a 403, so the boundary never confirms the Group exists.
 * The gate reuses the established membership / super-tier resolution and holds
 * against parentage. Public / Group visibility is unchanged (org-open).
 */

it('404s a Private Group for a logged-in non-member', function () {
    $group = Group::factory()->privateListing()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertNotFound();
});

it('404s a Private child Group for an officer of the parent Group who is not a member', function () {
    $parent = Group::factory()->create();
    $child = Group::factory()->privateListing()->create(['parent_id' => $parent->id]);

    $chair = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $parent->id,
        'member_id' => $chair->id,
    ])->roles()->create(['role' => Role::Chair]);

    $this->actingAs($chair)
        ->get(route('groups.show', $child))
        ->assertNotFound();
});

it('renders a Private Group to one of its members', function () {
    $group = Group::factory()->privateListing()->create(['name' => 'Secret Working Group']);
    $member = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create([
        'group_id' => $group->id,
        'member_id' => $member->id,
    ]);

    $this->actingAs($member)
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('group.name', 'Secret Working Group'));
});

it('renders a Private Group to the super-tier without membership', function () {
    $group = Group::factory()->privateListing()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('groups/Show'));
});

it('renders a Public Group to any logged-in Member', function () {
    $group = Group::factory()->publicListing()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk();
});

it('renders a Group-visibility Group to any logged-in Member', function () {
    $group = Group::factory()->create(); // factory default is Group visibility

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk();
});

/*
 * Container page-gate (#293, PRD #289). A Kind::Container Group has no page — it is a
 * structural section peer, not a destination. The gate is unconditional: 404 for every
 * viewer including the super-tier, because "no page exists" is a fact, not an access
 * decision (contrast the Private gate, which exempts members and the super-tier). 404
 * (not 403) so the navigation and the addressable pages agree. Special Projects — a
 * real Group despite heading a section — renders its Overview normally.
 */

it('404s a Kind::Container Group for an ordinary Member', function () {
    $group = Group::factory()->container()->create();

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertNotFound();
});

it('404s a Kind::Container Group for the super-tier — unconditional, no exemption', function () {
    $group = Group::factory()->container()->create();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get(route('groups.show', $group))
        ->assertNotFound();
});

it('renders special-projects — a real Group heading a section — like any Group', function () {
    $group = Group::factory()->standingCommittee()->create([
        'slug' => 'special-projects',
        'name' => 'Special Projects',
    ]);

    $this->actingAs(Member::factory()->create())
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/Show')
            ->where('group.name', 'Special Projects'));
});
