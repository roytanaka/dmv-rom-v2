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
 * The Other Groups zone (ADR-0020 §C): the browse view of the org, reshaped server-side
 * into the four organization-scope container peers — Governance & Operations, Programs,
 * Special Projects, Friends — each an expandable node that explodes one level to its
 * members-facing Groups. This reverses ADR-0018's "Option C" (which folded Governance &
 * Operations into a DMV root and promoted programs to the top level). Peer labels are
 * chrome (i18n keys); the Groups nested beneath render their names verbatim. Visible to
 * every signed-in Member.
 */

/**
 * Build a small active org tree shaped like the curated DMV one: a DMV root with the
 * four organization-scope containers (Governance & Operations, Programs, Special
 * Projects, Friends), each carrying children, so the four-peer reshape has every branch
 * to act on. Returns nothing — assertions read the rail prop, not the models.
 */
function seedFourPeerTree(): void
{
    // Every node is Public so these assertions isolate the four-peer *shape* transform
    // from listing-visibility pruning (its own tests below). The factory fail-closes
    // to Group visibility (ADR-0019), so shape fixtures must opt into Public to be seen
    // by a plain Member with no memberships.
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);

    // Governance & Operations peer — carries the governance/operations committees.
    $governance = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'governance-operations', 'name' => 'Governance & Operations', 'display_order' => 0]);
    Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $governance->id, 'slug' => 'awards', 'name' => 'Awards', 'display_order' => 0]);
    Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $governance->id, 'slug' => 'communications', 'name' => 'Communications', 'display_order' => 1]);

    // Programs peer — explodes one level to its programs (no longer promoted to top level).
    $programs = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 1]);
    $docents = Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 0]);
    Group::factory()->workingGroup()->publicListing()->create(['parent_id' => $docents->id, 'slug' => 'docents-library', 'name' => 'Library', 'display_order' => 0]);
    // A French-named program — its name is content, rendered verbatim in both locales.
    Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'guides-du-rom', 'name' => 'Guides du ROM', 'display_order' => 1]);

    // Special Projects peer.
    $special = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'special-projects', 'name' => 'Special Projects', 'display_order' => 2]);
    Group::factory()->project()->publicListing()->create(['parent_id' => $special->id, 'slug' => 'archive-inventory', 'name' => 'DMV Archive Inventory', 'display_order' => 0]);

    // Friends peer — the structural container grouping the Friends-of committees (#276).
    $friends = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'friends', 'name' => 'Friends', 'display_order' => 3]);
    $fop = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $friends->id, 'slug' => 'friends-of-palaeontology', 'name' => 'Friends of Palaeontology (FOP)', 'display_order' => 0]);
    Group::factory()->workingGroup()->publicListing()->create(['parent_id' => $fop->id, 'slug' => 'vertebrate-palaeontology', 'name' => 'Vertebrate Palaeontology', 'display_order' => 0]);
}

it('reshapes the active tree into four container peers — Governance & Operations / Programs / Special Projects / Friends, each exploding one level', function () {
    seedFourPeerTree();

    // A plain Member with no memberships still sees Other Groups (it is for everyone).
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.labelKey', 'nav.rail.other_groups')
            // Governance & Operations peer — a chrome-labelled container (no verbatim name),
            // carrying the governance/operations committees as its children.
            ->where('rail.otherGroups.items.0.labelKey', 'nav.rail.peers.governance_operations')
            ->missing('rail.otherGroups.items.0.name')
            ->where('rail.otherGroups.items.0.children.0.groupId', 'awards')
            ->where('rail.otherGroups.items.0.children.1.groupId', 'communications')
            ->count('rail.otherGroups.items.0.children', 2)
            // Programs are nested UNDER the Programs peer, not promoted to the top level,
            // each retaining its own subcommittees.
            ->where('rail.otherGroups.items.1.labelKey', 'nav.rail.peers.programs')
            ->where('rail.otherGroups.items.1.children.0.groupId', 'docents')
            ->where('rail.otherGroups.items.1.children.0.children.0.groupId', 'docents-library')
            ->where('rail.otherGroups.items.1.children.1.groupId', 'guides-du-rom')
            ->count('rail.otherGroups.items.1.children', 2)
            // Special Projects and Friends peers, each exploding one level.
            ->where('rail.otherGroups.items.2.labelKey', 'nav.rail.peers.special_projects')
            ->where('rail.otherGroups.items.2.children.0.groupId', 'archive-inventory')
            ->where('rail.otherGroups.items.3.labelKey', 'nav.rail.peers.friends')
            ->where('rail.otherGroups.items.3.children.0.groupId', 'friends-of-palaeontology')
            ->where('rail.otherGroups.items.3.children.0.children.0.groupId', 'vertebrate-palaeontology')
            // Exactly four peers — the old All-Groups "DMV" root node no longer renders.
            ->count('rail.otherGroups.items', 4));
});

it('never shows archived or stale Groups in Other Groups', function () {
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $programs = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 0]);

    $docents = Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 0]);
    // An archived cohort and an active-but-stale (lapsed window) cohort under Docents —
    // both excluded by Group::active(), so Docents renders as a childless leaf.
    Group::factory()->cohort()->archived()->publicListing()->create(['parent_id' => $docents->id, 'slug' => 'pompeii', 'name' => 'Pompeii', 'display_order' => 0]);
    Group::factory()->cohort()->publicListing()->create(['parent_id' => $docents->id, 'slug' => 'osiris', 'name' => 'Osiris', 'display_order' => 1, 'end_date' => now()->subMonth()->toDateString()]);
    // An archived sibling program — never appears under the Programs peer.
    Group::factory()->program()->archived()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'retired-program', 'name' => 'Retired Program', 'display_order' => 1]);

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.items.0.labelKey', 'nav.rail.peers.programs')
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            ->missing('rail.otherGroups.items.0.children.0.children')
            ->count('rail.otherGroups.items.0.children', 1)
            ->count('rail.otherGroups.items', 1));
});

it('emits Other Groups names verbatim and hrefs as French twins under /fr/', function () {
    seedFourPeerTree();

    $this->actingAs(Member::factory()->create());

    $this->withLocaleRoutes('fr', function () {
        $this->get('/fr/groupes/dmv')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                // Peer hrefs point at the /fr/ twin; the peer label stays a chrome key.
                ->where('rail.otherGroups.items.0.href', '/fr/groupes/governance-operations')
                ->where('rail.otherGroups.items.0.children.0.href', '/fr/groupes/awards')
                // A French-named program: name verbatim, href its localized twin.
                ->where('rail.otherGroups.items.1.children.1.name', 'Guides du ROM')
                ->where('rail.otherGroups.items.1.children.1.href', '/fr/groupes/guides-du-rom'));
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
 * Listing-visibility pruning of Other Groups (#271, PRD #268, ADR-0019). The
 * server-pruned rail also prunes on `listing_visibility`, composing beneath the
 * active-tree prune: a `Group`-visibility node is emitted only to members of its
 * parent Group; a `Private` node only to its own members; `Public` to everyone;
 * super-tier sees all. Visibility resolves from current participation (Full / LOA);
 * departed standings contribute nothing — the same standing rule My Groups applies.
 * This is the flag-driven depth of ADR-0020 §D — a peer bottoms out because its
 * working groups are pruned, not from a positional cap. Asserted at the shared-prop
 * seam; the Dashboard launcher reads this same `rail` prop, so it cannot disagree.
 */

/**
 * A Public program (Docents, nested under the Programs peer) carrying two restricted
 * subcommittees: a `Group`-visibility Training team (visible to Docents members) and a
 * `Private` Events team (visible only to Events members). Returns the three Groups so
 * tests can enrol Members precisely.
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
            ->where('rail.otherGroups.items.0.labelKey', 'nav.rail.peers.programs')
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            ->missing('rail.otherGroups.items.0.children.0.children')
            ->count('rail.otherGroups.items', 1));
});

it('shows a Group-visibility subcommittee to a member of its parent Group, but not the Private sibling', function () {
    ['docents' => $docents] = seedVisibilityTree();

    $member = Member::factory()->create();
    GroupMember::factory()->status(MembershipStatus::Full)->create(['member_id' => $member->id, 'group_id' => $docents->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            // Training (Group) is in; Events (Private) is not — parent membership is
            // not membership of the Private child.
            ->where('rail.otherGroups.items.0.children.0.children.0.groupId', 'docents-training')
            ->count('rail.otherGroups.items.0.children.0.children', 1));
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
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            // Events (Private, own member) is in; Training (Group) needs Docents
            // membership the Events-only member lacks.
            ->where('rail.otherGroups.items.0.children.0.children.0.groupId', 'docents-events')
            ->count('rail.otherGroups.items.0.children.0.children', 1));
});

it('shows every restricted subcommittee to a super-tier viewer', function () {
    seedVisibilityTree();

    $this->actingAs(Member::factory()->superTier()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            ->where('rail.otherGroups.items.0.children.0.children.0.groupId', 'docents-training')
            ->where('rail.otherGroups.items.0.children.0.children.1.groupId', 'docents-events')
            ->count('rail.otherGroups.items.0.children.0.children', 2));
});

it('still shows a Group-visibility subcommittee to a member on leave (LOA)', function () {
    ['docents' => $docents] = seedVisibilityTree();

    $member = Member::factory()->create();
    GroupMember::factory()->onLoa()->create(['member_id' => $member->id, 'group_id' => $docents->id]);

    $this->actingAs($member)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.items.0.children.0.children.0.groupId', 'docents-training')
            ->count('rail.otherGroups.items.0.children.0.children', 1));
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
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            ->missing('rail.otherGroups.items.0.children.0.children'));
});

it('prunes a Private Group from Other Groups — the launcher grid reads this same rail prop', function () {
    // The launcher and the rail both read `rail.otherGroups`, so a Group pruned here is
    // pruned from the launcher too — the two cannot disagree because they share one source.
    $root = Group::factory()->standingCommittee()->publicListing()->create(['slug' => 'dmv', 'name' => 'DMV', 'display_order' => 0]);
    $programs = Group::factory()->standingCommittee()->publicListing()->create(['parent_id' => $root->id, 'slug' => 'programs', 'name' => 'Programs', 'display_order' => 0]);
    Group::factory()->program()->publicListing()->create(['parent_id' => $programs->id, 'slug' => 'docents', 'name' => 'Docents', 'display_order' => 0]);
    Group::factory()->program()->privateListing()->create(['parent_id' => $programs->id, 'slug' => 'inner-circle', 'name' => 'Inner Circle', 'display_order' => 1]);

    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rail.otherGroups.items.0.labelKey', 'nav.rail.peers.programs')
            ->where('rail.otherGroups.items.0.children.0.groupId', 'docents')
            ->count('rail.otherGroups.items.0.children', 1)
            ->count('rail.otherGroups.items', 1));
});
