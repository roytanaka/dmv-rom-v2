<?php

use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMemberRole;
use App\Models\GroupStewardship;
use App\Models\Member;
use Database\Seeders\DemoSeeder;

/*
 * The curated demo data (PRD #139, slice 2 / #141): the faker-free, idempotent
 * full DMV org tree seeded into staging by hand before a board pitch. These
 * assertions check external, observable invariants — the tree resolves at real
 * depth, every Group Kind is present, archived/stale nodes drop out of the
 * active filter, and re-seeding heals rather than duplicates — rather than
 * specific curated content, so the suite survives the org list changing.
 */

beforeEach(fn () => $this->seed(DemoSeeder::class));

it('builds a tree whose relationships resolve from the root', function () {
    $root = Group::where('slug', DemoSeeder::ROOT)->firstOrFail();

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
    $workingGroupUnderProgram = Group::where('kind', Kind::WorkingGroup)->get()
        ->first(fn (Group $g) => $g->parent?->kind === Kind::Program);

    $cohortUnderProgram = Group::where('kind', Kind::Cohort)->get()
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
