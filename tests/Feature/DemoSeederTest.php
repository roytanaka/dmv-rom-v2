<?php

use App\Enums\Category;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\Role;
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
