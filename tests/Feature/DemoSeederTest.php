<?php

use App\Enums\Category;
use App\Enums\GroupLogo;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\ListingVisibility;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\Scope;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use App\Personas\PersonaCatalogue;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The curated demo data (PRD #139, slice 2 / #141): the faker-free, idempotent
 * full DMV org tree seeded into staging by hand before a board pitch. These
 * assertions check external, observable invariants — the tree resolves at real
 * depth, every Group Kind is present, archived/stale nodes drop out of the
 * active filter, and re-seeding heals rather than duplicates — rather than
 * specific curated content, so the suite survives the org list changing.
 */

// Demo seeding fetches best-effort DiceBear avatars; fake the HTTP client so these
// org-tree assertions never touch the network. An empty 200 leaves every Member on
// the initials fallback, which these tests don't assert against.
beforeEach(function () {
    Http::fake();
    $this->seed(DemoSeeder::class);
});

it('builds a tree whose relationships resolve from the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->with(['parent', 'children.parent'])->firstOrFail();

    expect($root->parent)->toBeNull()
        ->and($root->children)->not->toBeEmpty()
        ->and($root->children->every(fn (Group $child) => $child->parent->is($root)))->toBeTrue();
});

it('anchors stable handles for a committee and a program under the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();
    $committee = Group::where('slug', DemoSeeder::COMMITTEE)->firstOrFail();
    $program = Group::where('slug', DemoSeeder::PROGRAM)->firstOrFail();

    expect($committee->parent->is($root))->toBeTrue()
        ->and($program->kind)->toBe(Kind::Program)
        ->and($program->parent)->not->toBeNull()
        ->and($program->parent->parent->is($root))->toBeTrue();
});

it('represents every Group Kind in the curated tree', function () {
    foreach (Kind::cases() as $kind) {
        expect(Group::where('kind', $kind)->exists())->toBeTrue();
    }
});

it('resolves working groups and cohorts up to their program at depth', function () {
    $workingGroupUnderProgram = Group::where('kind', Kind::WorkingGroup)->with('parent')->get()
        ->first(fn (Group $g) => $g->parent?->kind === Kind::Program);

    $cohortUnderProgram = Group::where('kind', Kind::Cohort)->with('parent')->get()
        ->first(fn (Group $g) => $g->parent?->kind === Kind::Program);

    expect($workingGroupUnderProgram)->not->toBeNull()
        ->and($cohortUnderProgram)->not->toBeNull();
});

it('excludes archived and stale groups from the active scope but keeps live ones', function () {
    $activeIds = Group::active()->pluck('id');

    $archived = Group::where('lifecycle_state', LifecycleState::Archived)->get();
    expect($archived)->not->toBeEmpty();
    $archived->each(fn (Group $g) => expect($activeIds->contains($g->id))->toBeFalse());

    // An active-but-stale pool: lifecycle Active, but its time-boxed window lapsed.
    $stale = Group::where('lifecycle_state', LifecycleState::Active)
        ->where('time_boxed', true)
        ->whereDate('end_date', '<', now())
        ->get();
    expect($stale)->not->toBeEmpty();
    $stale->each(fn (Group $g) => expect($activeIds->contains($g->id))->toBeFalse());

    $liveProgram = Group::where('kind', Kind::Program)
        ->where('lifecycle_state', LifecycleState::Active)
        ->firstOrFail();
    expect($activeIds->contains($liveProgram->id))->toBeTrue();
});

it('groups the five Friends-of committees under a rosterless Friends container peer', function () {
    // Explicit structure, not a naming heuristic — Bishop White (FEA) wouldn't
    // match a "Friends of …" name filter (PRD #275, ADR-0020 §G).
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();
    $friends = Group::where('slug', 'friends')->with('children')->firstOrFail();

    expect($friends->parent_id)->toBe($root->id)
        ->and($friends->kind)->toBe(Kind::Container);

    $expected = [
        'bishop-white-fea',
        'friends-of-global-south-asia-fsa',
        'friends-of-textiles-costume',
        'friends-of-palaeontology-fop',
        'friends-of-earth-space-fes',
    ];

    expect($friends->children->pluck('slug')->sort()->values()->all())
        ->toBe(collect($expected)->sort()->values()->all());

    expect(GroupMember::where('group_id', $friends->id)->count())->toBe(0);

    // Associated Friends is a distinct coordinating committee and stays under
    // Governance & Operations — it is not the container and is not re-parented.
    $associated = Group::where('slug', 'associated-friends')->firstOrFail();
    expect($associated->parent->slug)->toBe(DemoSeeder::COMMITTEE);
});

it('tags the three org-level section peers as containers with zero members', function () {
    // The container marker (PRD #289, #292): pure scaffolding, no roster, no
    // leadership — clicking one shows the Groups it organizes, not people.
    foreach (['governance-operations', 'programs', 'friends'] as $slug) {
        $container = Group::where('slug', $slug)->firstOrFail();

        expect($container->kind)->toBe(Kind::Container)
            ->and($container->scope)->toBe(Scope::Organization)
            ->and($container->description)->toBeNull()
            ->and($container->has_meetings)->toBeFalse()
            ->and($container->has_documents)->toBeFalse()
            ->and($container->has_scheduling)->toBeFalse()
            ->and(GroupMember::where('group_id', $container->id)->count())->toBe(0)
            ->and(GroupMemberRole::whereRelation('groupMember', 'group_id', $container->id)->count())->toBe(0);
    }
});

it('keeps special-projects a real Group with a roster and leadership', function () {
    // special-projects is promoted out of the structural set (PRD #289, #292): it
    // stays a standing committee with a page, members, and a Chair + Secretary.
    $specialProjects = Group::where('slug', 'special-projects')->firstOrFail();

    expect($specialProjects->kind)->toBe(Kind::StandingCommittee)
        ->and(GroupMember::where('group_id', $specialProjects->id)->count())->toBeGreaterThan(0);

    $officerRoles = GroupMemberRole::whereRelation('groupMember', 'group_id', $specialProjects->id)
        ->pluck('role');

    expect($officerRoles)->toContain(Role::Chair)
        ->and($officerRoles)->toContain(Role::Secretary);
});

it('hand-assigns curated logo keys per node, leaving groups without a mark on the fallback', function () {
    // A representative program, a Friends-of committee, and a "filename ≠ key"
    // rename (special.svg → Visitor Wayfinders) — the per-node assignment (#257).
    expect(Group::where('slug', 'docents')->firstOrFail()->logo_key)->toBe(GroupLogo::Docents)
        ->and(Group::where('slug', 'bishop-white-fea')->firstOrFail()->logo_key)->toBe(GroupLogo::BishopWhiteFea)
        ->and(Group::where('slug', 'visitor-wayfinders')->firstOrFail()->logo_key)->toBe(GroupLogo::VisitorWayfinders);

    // ROMForYou deliberately ships no mark, and the structural root never does —
    // so the generic fallback is visibly exercised on staging (at least one null).
    expect(Group::where('slug', 'romforyou')->firstOrFail()->logo_key)->toBeNull()
        ->and(Group::where('slug', DemoSeeder::ROOT)->firstOrFail()->logo_key)->toBeNull()
        ->and(Group::whereNull('logo_key')->exists())->toBeTrue();
});

it('hand-assigns listing visibility per node so staging exercises all three tiers', function () {
    // The recruiting/scaffold tree is Public (org root, a program, a committee), an
    // internal working subgroup falls to the fail-closed Group default, and one
    // subgroup is hand-set Private (PRD #268, ADR-0019).
    expect(Group::where('slug', DemoSeeder::ROOT)->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        ->and(Group::where('slug', 'docents')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        ->and(Group::where('slug', 'membership')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        // A working group hand-flipped to Public so the Public path is exercised on a
        // nested subgroup, not just the recruiting/scaffold tree.
        ->and(Group::where('slug', 'donor-friends')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Public)
        ->and(Group::where('slug', 'pr-committee')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Group)
        ->and(Group::where('slug', 'gallery-interpreters-events')->firstOrFail()->listing_visibility)
        ->toBe(ListingVisibility::Private);
});

it('shows a non-empty Other Groups to an ordinary Member with no memberships', function () {
    // Regression (PRD #268): the visibility prune must not swallow the whole Other
    // Groups zone. A plain Member — no memberships, not super-tier — still browses the
    // Public recruiting tree, now surfaced as the four organization-scope container
    // peers (ADR-0020 §C). The bug left the root and its containers at the Group
    // default, pruning every non-member's tree to nothing.
    $this->actingAs(Member::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The first peer is Governance & Operations, a chrome-labelled container.
            ->where('rail.otherGroups.items.0.labelKey', 'nav.rail.peers.governance_operations')
            // Index 1 existing proves ≥2 container peers survived the prune — the
            // tree did not collapse to nothing for a non-member.
            ->has('rail.otherGroups.items.1'));
});

it('represents varied membership statuses including Full, Trainee and LOA', function () {
    $present = GroupMember::query()->distinct()->pluck('status');

    foreach ([MembershipStatus::Full, MembershipStatus::Trainee, MembershipStatus::Loa] as $status) {
        expect($present->contains($status))->toBeTrue();
    }
});

it('sets an LOA window on every membership on leave', function () {
    $onLeave = GroupMember::where('status', MembershipStatus::Loa)->get();

    expect($onLeave)->not->toBeEmpty();
    $onLeave->each(function (GroupMember $membership) {
        expect($membership->loa_start)->not->toBeNull()
            ->and($membership->loa_end)->not->toBeNull();
    });
});

it('resolves a capability-backed role on a Group whose capability is enabled', function () {
    $role = GroupMemberRole::with('groupMember.group')->get()
        ->first(fn (GroupMemberRole $role) => $role->role->requiredCapability() !== null);

    expect($role)->not->toBeNull();

    $capability = $role->role->requiredCapability();
    expect($role->groupMember->group->{$capability})->toBeTrue();
});

it('resolves a Group stewarding an org-wide function via the stewardship helper', function () {
    $steward = Group::stewardOf(StewardshipFunction::MemberAdmin);

    expect($steward)->not->toBeNull()
        ->and($steward->stewardships()->where('function', StewardshipFunction::MemberAdmin)->exists())->toBeTrue();
});

it('leaves no membership orphaned — every one references a real Group and Member', function () {
    GroupMember::with(['group', 'member'])->get()->each(function (GroupMember $membership) {
        expect($membership->group)->not->toBeNull()
            ->and($membership->member)->not->toBeNull();
    });
});

it('is idempotent — re-seeding leaves row counts unchanged', function () {
    $counts = fn () => [
        'groups' => Group::count(),
        'members' => Member::count(),
        'memberships' => GroupMember::count(),
        'roles' => GroupMemberRole::count(),
        'stewardships' => GroupStewardship::count(),
    ];
    $before = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});

/*
 * Persona catalogue + seeding (PRD #220 / #221, ADR-0009 dev half). The switcher
 * stands on a code catalogue that is the single source of truth for the curated
 * Personas; these assert the seeded outcomes the switcher and its gates depend on,
 * through observable model/gate behaviour — not the catalogue's internals.
 */

it('seeds exactly the catalogued Personas', function () {
    foreach (PersonaCatalogue::all() as $persona) {
        expect(Member::where('email', $persona->email)->exists())->toBeTrue();
    }
});

it('seeds a super-tier trio in the Executive Group', function () {
    $executive = Group::where('slug', 'executive')->firstOrFail();

    $supers = Member::where('super_tier', true)->with('memberships')->get();

    expect($supers)->toHaveCount(3);
    $supers->each(fn (Member $member) => expect($member->membershipIn($executive))->not->toBeNull());
});

it('seeds the support operator with operator access split cleanly from super-tier', function () {
    // The operator/authority split: exactly one support operator, holding no
    // super-tier org authority; and none of the super-tier executives is an operator.
    $operators = Member::where('support_operator', true)->get();

    expect($operators)->toHaveCount(1);
    expect($operators->first()->isAllDmv())->toBeFalse();
    expect(Member::where('super_tier', true)->where('support_operator', true)->exists())->toBeFalse();
});

it('satisfies canPostNews for the news-editor Persona on an announcements Group', function () {
    $editor = Member::where('email', 'nadia.haddad@dmv.test')
        ->with('memberships.roles', 'memberships.group')
        ->firstOrFail();

    $communications = Group::where('slug', 'communications')->firstOrFail();

    expect($communications->has_announcements)->toBeTrue()
        ->and($editor->canActAs(Role::NewsEditor, $communications))->toBeTrue()
        ->and($editor->canPostNews())->toBeTrue();
});

it('excludes the departed Persona from the Directory and My-Groups sources', function () {
    $departed = Member::where('email', 'sven.larsson@dmv.test')
        ->with('memberships')
        ->firstOrFail();

    expect($departed->category)->toBe(Category::Resigned);

    // Directory keys on DMV-wide Category — the departed Persona is not listed.
    expect(Member::inDirectory()->whereKey($departed->id)->exists())->toBeFalse();

    // My-Groups keys on per-Group standing — no Full/on-leave membership qualifies,
    // and the root-as-Full enrolment exempts departed Members, so nothing shows.
    $qualifying = $departed->memberships->filter(fn (GroupMember $m) => in_array(
        $m->status,
        [MembershipStatus::Full, MembershipStatus::Loa],
        true,
    ));
    expect($qualifying)->toBeEmpty();
});

it('gives the multi-group no-office Persona Full standing in several programs', function () {
    $member = Member::where('email', 'amara.abara@dmv.test')
        ->with('memberships.roles', 'memberships.group')
        ->firstOrFail();

    $programs = $member->memberships
        ->filter(fn (GroupMember $m) => $m->group->kind === Kind::Program && $m->status === MembershipStatus::Full);

    expect($programs->count())->toBeGreaterThanOrEqual(2);
    $member->memberships->each(fn (GroupMember $m) => expect($m->roles)->toBeEmpty());
});

it('demotes test@example.com to an ordinary member with no authority', function () {
    $this->seed(DatabaseSeeder::class);

    $member = Member::where('email', 'test@example.com')
        ->with('memberships.roles', 'memberships.group')
        ->firstOrFail();

    expect($member->super_tier)->toBeFalse()
        ->and($member->isAllDmv())->toBeFalse()
        ->and($member->canPostNews())->toBeFalse()
        ->and($member->hasMemberAdminAuthority())->toBeFalse();

    // Only the one root-DMV membership every Member gets, carrying no roles.
    expect($member->memberships)->toHaveCount(1)
        ->and($member->memberships->first()->group->slug)->toBe(DemoSeeder::ROOT)
        ->and($member->memberships->first()->roles)->toBeEmpty();
});

it('allowlists exactly the catalogued Personas', function () {
    expect(PersonaCatalogue::has('nadia.haddad@dmv.test'))->toBeTrue()
        ->and(PersonaCatalogue::has('test@example.com'))->toBeFalse()
        ->and(PersonaCatalogue::emails())->toContain(PersonaCatalogue::CHAIR_EMAIL);
});
