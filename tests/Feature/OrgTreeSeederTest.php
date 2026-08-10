<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use Database\Seeders\OrgTreeSeeder;

/*
 * The canonical spine fixture (PRD #126, slice 6 / #132). Asserts the seeded
 * miniature org tree is internally valid and usable end-to-end: tree
 * relationships resolve, the scheduling program accepts a Scheduler role, the
 * Records Group resolves via the member_admin stewardship helper, and the
 * active-Groups filter behaves on the seeded set — plus the seeder is idempotent.
 */

beforeEach(fn () => $this->seed(OrgTreeSeeder::class));

it('builds a tree whose relationships resolve', function () {
    $root = Group::where('slug', OrgTreeSeeder::ROOT)->firstOrFail();
    $program = Group::where('slug', OrgTreeSeeder::PROGRAM)->firstOrFail();
    $cohort = Group::where('slug', OrgTreeSeeder::COHORT)->firstOrFail();

    expect($root->parent)->toBeNull()
        ->and($root->children->pluck('slug'))->toContain(
            OrgTreeSeeder::COMMITTEE,
            OrgTreeSeeder::RECORDS,
            OrgTreeSeeder::PROGRAM,
        )
        ->and($cohort->parent->is($program))->toBeTrue();
});

it('covers every Group Kind', function () {
    expect(Group::query()->distinct()->pluck('kind')->map->value->sort()->values()->all())
        ->toEqual(['cohort', 'program', 'project', 'standing_committee', 'working_group']);
});

it('seeds a scheduling program that accepts a Scheduler role', function () {
    $program = Group::where('slug', OrgTreeSeeder::PROGRAM)->firstOrFail();
    $scheduler = Member::where('email', OrgTreeSeeder::SCHEDULER_EMAIL)->firstOrFail();

    expect($program->has_scheduling)->toBeTrue()
        ->and($scheduler->holdsRole(Role::Scheduler, $program))->toBeTrue();

    // The capability gate admits a further Scheduler on the same program.
    $fresh = GroupMember::factory()->create([
        'group_id' => $program->id,
        'member_id' => Member::factory()->create()->id,
    ]);
    $fresh->roles()->create(['role' => Role::Scheduler]);

    expect($fresh->roles()->where('role', Role::Scheduler)->exists())->toBeTrue();
});

it('resolves the Records Group via the member_admin stewardship helper', function () {
    $records = Group::where('slug', OrgTreeSeeder::RECORDS)->firstOrFail();

    expect(Group::stewardOf(StewardshipFunction::MemberAdmin)?->is($records))->toBeTrue();
});

it('excludes archived Groups from the active-Groups filter', function () {
    $active = Group::active()->pluck('slug');

    expect($active)->toContain(
        OrgTreeSeeder::ROOT,
        OrgTreeSeeder::PROGRAM,
        OrgTreeSeeder::COHORT,
        OrgTreeSeeder::WORKING_GROUP,
    )->not->toContain(OrgTreeSeeder::PROJECT);
});

it('seeds a super-tier holder, an LOA window, and varied statuses', function () {
    expect(Member::where('super_tier', true)->exists())->toBeTrue()
        ->and(GroupMember::whereNotNull('loa_start')->whereNotNull('loa_end')->exists())->toBeTrue()
        ->and(GroupMember::query()->distinct()->pluck('status'))->toContain(
            MembershipStatus::Full,
            MembershipStatus::Trainee,
            MembershipStatus::Loa,
        );
});

it('seeds a published, current Schedule on the scheduling program', function () {
    $program = Group::where('slug', OrgTreeSeeder::PROGRAM)->firstOrFail();
    $schedule = $program->schedules()->where('name', OrgTreeSeeder::SCHEDULE_NAME)->firstOrFail();

    expect($schedule->state)->toBe(ScheduleState::Published)
        ->and($schedule->isCurrent(now()))->toBeTrue();
});

it('is idempotent when re-run against the same database', function () {
    $counts = fn () => [
        'groups' => Group::count(),
        'members' => Member::count(),
        'memberships' => GroupMember::count(),
        'schedules' => Schedule::count(),
    ];
    $before = $counts();

    $this->seed(OrgTreeSeeder::class);

    expect($counts())->toBe($before);
});
