<?php

use App\Enums\GroupLogo;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The grouping rail (PRD #209), built server-side and server-pruned in
 * HandleInertiaRequests alongside chromeNav, and shared as the `rail` Inertia prop.
 * This slice (#210) wires one zone end-to-end — My Groups: the Groups the signed-in
 * Member belongs to with Full or LOA standing, flat and alphabetical, omitted whole
 * when the Member belongs to none. Asserted at the shared-prop seam (mirrors
 * GlobalTopBarTest), including the /fr/ twin hrefs.
 */

it('lists the Member\'s Full-standing Groups, flat and alphabetical by name', function () {
    $member = Member::factory()->create();
    // Created out of alphabetical order to prove the rail sorts by name.
    $zoo = Group::factory()->create(['slug' => 'zoology', 'name' => 'Zoology']);
    $arch = Group::factory()->create(['slug' => 'archaeology', 'name' => 'Archaeology']);
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $zoo->id]);
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $arch->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.myGroups.labelKey', 'nav.rail.my_groups')
            ->where('rail.myGroups.items.0', ['groupId' => 'archaeology', 'name' => 'Archaeology', 'href' => '/groups/archaeology', 'logo' => null])
            ->where('rail.myGroups.items.1', ['groupId' => 'zoology', 'name' => 'Zoology', 'href' => '/groups/zoology', 'logo' => null])
            ->count('rail.myGroups.items', 2));
});

it('keeps a Group in My Groups when the Member is on leave (LOA)', function () {
    $member = Member::factory()->create();
    $group = Group::factory()->create(['slug' => 'docents', 'name' => 'Docents']);
    GroupMember::factory()->onLoa()->create(['member_id' => $member->id, 'group_id' => $group->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.myGroups.items.0.groupId', 'docents')
            ->count('rail.myGroups.items', 1));
});

it('omits the whole My Groups section when the Member has only departed standings', function () {
    $member = Member::factory()->create();
    $resigned = Group::factory()->create(['name' => 'Resigned Group']);
    $deceased = Group::factory()->create(['name' => 'Deceased Group']);
    GroupMember::factory()->status(MembershipStatus::Resigned)->create(['member_id' => $member->id, 'group_id' => $resigned->id]);
    GroupMember::factory()->status(MembershipStatus::Deceased)->create(['member_id' => $member->id, 'group_id' => $deceased->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('rail.myGroups'));
});

it('omits the whole My Groups section when the Member belongs to no Group', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('rail.myGroups'));
});

it('emits My Groups names verbatim and hrefs as French twins under /fr/', function () {
    $member = Member::factory()->create();
    // A French-named Group: its name is content, rendered verbatim in both locales.
    $group = Group::factory()->create(['slug' => 'guides-du-rom', 'name' => 'Guides du ROM']);
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $group->id]);

    $this->actingAs($member);

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/groupes/guides-du-rom')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('rail.myGroups.items.0', [
                    'groupId' => 'guides-du-rom',
                    'name' => 'Guides du ROM',
                    'href' => '/fr/groupes/guides-du-rom',
                    'logo' => null,
                ]));
    });
});

/*
 * Group logos on the launcher (PRD #253): each launcher row carries its Group's
 * `logo_key` verbatim so the tile can resolve it to an identity mark client-side. A
 * Group with a logo carries its key; a Group without one carries null and the tile
 * falls back to the generic mark. Asserted at the same shared-prop seam as the rest of
 * the launcher, alongside the banner-style curated-key assertions.
 */

it('carries a Group\'s logo key on its launcher row, and null when it has none', function () {
    $member = Member::factory()->create();
    // Docents has its own identity mark; Zoology has none (the common case).
    $docents = Group::factory()->create(['slug' => 'docents', 'name' => 'Docents', 'logo_key' => GroupLogo::Docents]);
    $zoo = Group::factory()->create(['slug' => 'zoology', 'name' => 'Zoology', 'logo_key' => null]);
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $docents->id]);
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $zoo->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Alphabetical by name: Docents (with logo) then Zoology (fallback).
            ->where('rail.myGroups.items.0.groupId', 'docents')
            ->where('rail.myGroups.items.0.logo', 'docents')
            ->where('rail.myGroups.items.1.groupId', 'zoology')
            ->where('rail.myGroups.items.1.logo', null));
});

/*
 * The All Groups zone (#211): the whole active organization tree, reshaped server-side
 * by the curated Option C transform that reproduces the live rail's shape — Governance
 * & Operations folded into the root DMV node, the Programs container dissolved with its
 * programs promoted to the top level, Special Projects and Friends-of committees kept
 * as ordinary top-level expandable nodes. Visible to every signed-in Member.
 */

/**
 * Build a small active org tree shaped like the curated DMV one: a DMV root with the
 * three named containers (Governance & Operations, Programs, Special Projects) plus a
 * Friends-of committee, each carrying children, so the Option C transform has every
 * branch to act on. Returns nothing — assertions read the rail prop, not the models.
 */
function seedOptionCTree(): void
{
    // Every node is Public so these assertions isolate the Option C *shape* transform
    // from listing-visibility pruning (its own tests below). The factory fail-closes
    // to Group visibility (ADR-0019), so shape fixtures must opt into Public to be seen
    // by a plain Member with no memberships.
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);

    $governance = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'governance-operations', 'name' => 'Governance & Operations', 'display_order' => 0]);
    Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $governance->id, 'slug' => 'awards', 'name' => 'Awards', 'display_order' => 0]);
    Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $governance->id, 'slug' => 'communications', 'name' => 'Communications', 'display_order' => 1]);

    $programs = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 1]);
    $docents = Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 0]);
    Group::factory()->workingGroup()->publicListing()->create(['parent_id' => $docents->id, 'slug' => 'docents-library', 'name' => 'Library', 'display_order' => 0]);
    // A French-named program — its name is content, rendered verbatim in both locales.
    Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'guides-du-rom', 'name' => 'Guides du ROM', 'display_order' => 1]);

    $special = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'special-projects', 'name' => 'Special Projects', 'display_order' => 2]);
    Group::factory()->project()->publicListing()->create(['parent_id' => $special->id, 'slug' => 'archive-inventory', 'name' => 'DMV Archive Inventory', 'display_order' => 0]);

    $friends = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'friends-of-palaeontology', 'name' => 'Friends of Palaeontology (FOP)', 'display_order' => 3]);
    Group::factory()->workingGroup()->publicListing()->create(['parent_id' => $friends->id, 'slug' => 'vertebrate-palaeontology', 'name' => 'Vertebrate Palaeontology', 'display_order' => 0]);
}

it('reshapes the active tree with the Option C transform — DMV carries governance, programs flattened, special projects & friends top-level', function () {
    seedOptionCTree();

    // A plain Member with no memberships still sees All Groups (it is for everyone).
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.labelKey', 'nav.rail.all_groups')
            // Root DMV node carries the Governance & Operations committees as its children.
            ->where('rail.allGroups.items.0.groupId', 'dmv')
            ->where('rail.allGroups.items.0.children.0.groupId', 'awards')
            ->where('rail.allGroups.items.0.children.1.groupId', 'communications')
            ->count('rail.allGroups.items.0.children', 2)
            // Programs container is gone; its programs are promoted to the top level,
            // each retaining its own subcommittees.
            ->where('rail.allGroups.items.1.groupId', 'docents')
            ->where('rail.allGroups.items.1.children.0.groupId', 'docents-library')
            ->where('rail.allGroups.items.2.groupId', 'guides-du-rom')
            // Special Projects and Friends-of stay top-level expandable nodes.
            ->where('rail.allGroups.items.3.groupId', 'special-projects')
            ->where('rail.allGroups.items.3.children.0.groupId', 'archive-inventory')
            ->where('rail.allGroups.items.4.groupId', 'friends-of-palaeontology')
            ->where('rail.allGroups.items.4.children.0.groupId', 'vertebrate-palaeontology')
            // Five top-level rows: DMV + the two promoted programs + Special Projects +
            // the Friends-of committee. The Programs container itself never appears.
            ->count('rail.allGroups.items', 5));
});

it('never shows archived or stale Groups in All Groups', function () {
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $programs = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 0]);

    $docents = Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 0]);
    // An archived cohort and an active-but-stale (lapsed window) cohort under Docents —
    // both excluded by Group::active(), so Docents renders as a childless leaf.
    Group::factory()->cohort()->archived()->publicListing()->create(['parent_id' => $docents->id, 'slug' => 'pompeii', 'name' => 'Pompeii', 'display_order' => 0]);
    Group::factory()->cohort()->publicListing()->create(['parent_id' => $docents->id, 'slug' => 'osiris', 'name' => 'Osiris', 'display_order' => 1, 'end_date' => now()->subMonth()->toDateString()]);
    // An archived sibling program — never promoted to the top level.
    Group::factory()->program()->archived()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'retired-program', 'name' => 'Retired Program', 'display_order' => 1]);

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.0.groupId', 'dmv')
            ->where('rail.allGroups.items.1.groupId', 'docents')
            ->missing('rail.allGroups.items.1.children')
            ->count('rail.allGroups.items', 2));
});

it('emits All Groups names verbatim and hrefs as French twins under /fr/', function () {
    seedOptionCTree();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/groupes/dmv')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('rail.allGroups.items.0.href', '/fr/groupes/dmv')
                ->where('rail.allGroups.items.0.children.0.href', '/fr/groupes/awards')
                // A French-named program: name verbatim, href its localized twin.
                ->where('rail.allGroups.items.2.name', 'Guides du ROM')
                ->where('rail.allGroups.items.2.href', '/fr/groupes/guides-du-rom'));
    });
});

/*
 * The Officer Tools zone (#212): the org-wide administration cluster, each item gated
 * by a real authority resolved server-side. The cluster (heading included) is emitted
 * only when at least one item survives for the viewing Member. "Officer Tools" denotes
 * org-wide administration — deliberately distinct from a Group's officers (Chair /
 * Secretary / Treasurer). Asserted at the shared-prop seam, per actor authority.
 */

/** A Member who stewards Records (the member-administration Group). */
function recordsSteward(): Member
{
    $records = Group::factory()->create();
    GroupStewardship::factory()->stewarding(StewardshipFunction::MemberAdmin)->create(['group_id' => $records->id]);

    $member = Member::factory()->create();
    GroupMember::factory()->create(['group_id' => $records->id, 'member_id' => $member->id]);

    return $member;
}

/** A Member holding the news-editor role in an announcements-on Group. */
function newsEditor(): Member
{
    $group = Group::factory()->create(['has_announcements' => true]);
    $member = Member::factory()->create();
    $membership = GroupMember::factory()->create(['group_id' => $group->id, 'member_id' => $member->id]);
    GroupMemberRole::factory()->role(Role::NewsEditor)->create(['group_member_id' => $membership->id]);

    return $member;
}

it('omits the whole Officer Tools cluster for a plain Member with no authority', function () {
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('rail.officer'));
});

it('gives a Records steward Officer Tools with Members only', function () {
    $this->actingAs(recordsSteward())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.officer.labelKey', 'nav.rail.officer')
            ->where('rail.officer.items.0', ['key' => 'members', 'labelKey' => 'nav.officer.members', 'href' => '/officer/members'])
            ->count('rail.officer.items', 1));
});

it('gives a news-editor Officer Tools with Communications only', function () {
    $this->actingAs(newsEditor())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.officer.items.0', ['key' => 'communications', 'labelKey' => 'nav.officer.communications', 'href' => '/officer/communications'])
            ->count('rail.officer.items', 1));
});

it('gives a super-tier officer all five Officer Tools items', function () {
    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.officer.items.0.key', 'members')
            ->where('rail.officer.items.1.key', 'communications')
            ->where('rail.officer.items.2.key', 'reports')
            ->where('rail.officer.items.3.key', 'flash-messages')
            ->where('rail.officer.items.4.key', 'settings')
            ->count('rail.officer.items', 5));
});

it('emits Officer Tools hrefs as French twins under /fr/', function () {
    $this->actingAs(Member::factory()->superTier()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/tableau-de-bord')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('rail.officer.items.0.href', '/fr/officier/membres')
                ->where('rail.officer.items.2.href', '/fr/officier/rapports'));
    });
});

/*
 * Listing-visibility pruning of All Groups (#271, PRD #268, ADR-0019). The
 * server-pruned rail also prunes on `listing_visibility`, composing beneath the
 * active-tree prune: a `Group`-visibility node is emitted only to members of its
 * parent Group; a `Private` node only to its own members; `Public` to everyone;
 * super-tier sees all. Visibility resolves from current participation (Full / LOA);
 * departed standings contribute nothing — the same standing rule My Groups applies.
 * Asserted at the shared-prop seam; the Dashboard launcher reads this same `rail`
 * prop, so it cannot disagree.
 */

/**
 * A Public program (Docents, promoted to the rail's top level) carrying two
 * restricted subcommittees: a `Group`-visibility Training team (visible to Docents
 * members) and a `Private` Events team (visible only to Events members). Returns the
 * three Groups so tests can enrol Members precisely.
 *
 * @return array{docents: Group, training: Group, events: Group}
 */
function seedVisibilityTree(): array
{
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $programs = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 0]);
    $docents = Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 0]);

    // Group-visibility: shown to members of the parent Group (Docents), pruned from
    // outsiders. Its page stays reachable by direct link — tidiness, not confidentiality.
    $training = Group::factory()->workingGroup()->create(['parent_id' => $docents->id, 'slug' => 'docents-training', 'name' => 'Training', 'display_order' => 0]);

    // Private-visibility: shown only to its own members; existence hidden from everyone
    // else, including Docents members who are not in Events (the #262 seed case).
    $events = Group::factory()->workingGroup()->privateListing()->create(['parent_id' => $docents->id, 'slug' => 'docents-events', 'name' => 'Events', 'display_order' => 1]);

    return ['docents' => $docents, 'training' => $training, 'events' => $events];
}

it('prunes Group- and Private-visibility subcommittees from a Member who belongs to neither', function () {
    seedVisibilityTree();

    // A plain Member: sees Docents (Public), but neither restricted child.
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.0.groupId', 'dmv')
            ->where('rail.allGroups.items.1.groupId', 'docents')
            ->missing('rail.allGroups.items.1.children')
            ->count('rail.allGroups.items', 2));
});

it('shows a Group-visibility subcommittee to a member of its parent Group, but not the Private sibling', function () {
    ['docents' => $docents] = seedVisibilityTree();

    $member = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $docents->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.1.groupId', 'docents')
            // Training (Group) is in; Events (Private) is not — parent membership is
            // not membership of the Private child.
            ->where('rail.allGroups.items.1.children.0.groupId', 'docents-training')
            ->count('rail.allGroups.items.1.children', 1));
});

it('shows a Private subcommittee to its own member, but not the parent-Group sibling', function () {
    ['events' => $events] = seedVisibilityTree();

    // Enrolled in Events only — not in Docents.
    $member = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $events->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.1.groupId', 'docents')
            // Events (Private, own member) is in; Training (Group) needs Docents
            // membership the Events-only member lacks.
            ->where('rail.allGroups.items.1.children.0.groupId', 'docents-events')
            ->count('rail.allGroups.items.1.children', 1));
});

it('shows every restricted subcommittee to a super-tier viewer', function () {
    seedVisibilityTree();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.1.groupId', 'docents')
            ->where('rail.allGroups.items.1.children.0.groupId', 'docents-training')
            ->where('rail.allGroups.items.1.children.1.groupId', 'docents-events')
            ->count('rail.allGroups.items.1.children', 2));
});

it('still shows a Group-visibility subcommittee to a member on leave (LOA)', function () {
    ['docents' => $docents] = seedVisibilityTree();

    $member = Member::factory()->create();
    GroupMember::factory()->onLoa()->create(['member_id' => $member->id, 'group_id' => $docents->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.1.children.0.groupId', 'docents-training')
            ->count('rail.allGroups.items.1.children', 1));
});

it('grants no visibility from a departed membership', function () {
    ['docents' => $docents] = seedVisibilityTree();

    // A resigned Docents membership: departed, so it grants no visibility of the
    // Group-visibility Training team.
    $member = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Resigned)->create(['member_id' => $member->id, 'group_id' => $docents->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.1.groupId', 'docents')
            ->missing('rail.allGroups.items.1.children'));
});

it('prunes a Private top-level node — the launcher grid reads this same rail prop', function () {
    // A Private top-level node (a launcher-eligible row, not a nested subcommittee).
    // The launcher reads `rail.allGroups.items`, so pruning here prunes the tile too —
    // the two cannot disagree because they share one source.
    Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    Group::factory()->standingCommittee()->privateListing()->create(['slug' => 'inner-circle', 'name' => 'Inner Circle', 'display_order' => 1]);

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.allGroups.items.0.groupId', 'dmv')
            ->count('rail.allGroups.items', 1));
});
