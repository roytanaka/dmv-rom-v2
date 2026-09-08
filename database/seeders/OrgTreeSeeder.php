<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScheduleState;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftKind;
use Closure;
use Illuminate\Database\Seeder;

/**
 * The canonical spine fixture (PRD #126, slice 6 / #132): a seeded miniature org
 * tree that exercises every spine table together and is the shared starting point
 * downstream feature tests build against. Seed it explicitly in a test with
 * `$this->seed(OrgTreeSeeder::class)`, then reach the rows by the public slug /
 * email constants below.
 *
 * The tree covers the full shape — a root Group, a standing committee under it, a
 * scheduling program, a time-boxed exhibition cohort, the Records Group stewarding
 * `member_admin`, plus a working group and an archived project so every Group Kind
 * and both sides of the active-Groups filter are represented. Members include a
 * super-tier holder; memberships carry varied within-Group statuses and an LOA
 * window; roles include a capability-backed Scheduler on the scheduling program and
 * the core Chair / Secretary.
 *
 * It composes the per-slice factories (never raw inserts) and is idempotent: every
 * row is keyed on a deterministic identifier, so re-running against the same DB
 * heals rather than duplicates. It is intentionally not wired into
 * {@see DatabaseSeeder} — the factories it relies on call faker, a dev-only
 * dependency absent from the `--no-dev` deploy build.
 */
class OrgTreeSeeder extends Seeder
{
    public const ROOT = Group::ROOT_SLUG;

    public const COMMITTEE = 'membership-committee';

    public const RECORDS = 'records-group';

    public const PROGRAM = 'docents-program';

    public const COHORT = 'vikings-exhibition-cohort';

    public const WORKING_GROUP = 'digital-working-group';

    public const PROJECT = 'oral-history-project';

    // The DMV Executive Group (ADR-0024 §5) — a top-level standing committee under
    // the root, the roster the Board-of-Directors Audience resolves to. Keyed on the
    // well-known {@see Group::EXECUTIVE_SLUG} so the resolver finds it the same way in
    // the fixture as in the curated demo tree.
    public const EXECUTIVE = Group::EXECUTIVE_SLUG;

    public const SCHEDULER_EMAIL = 'scheduler@dmv.test';

    public const EXECUTIVE_CHAIR_EMAIL = 'exec-chair@dmv.test';

    /**
     * Build the miniature org tree end-to-end.
     */
    public function run(): void
    {
        $root = $this->group(self::ROOT, fn () => Group::factory()->standingCommittee()->create([
            'slug' => self::ROOT,
            'name' => 'DMV',
            'description' => 'The DMV at large — the root of the org tree.',
            'display_order' => 0,
        ]));

        $committee = $this->group(self::COMMITTEE, fn () => Group::factory()->standingCommittee()->create([
            'parent_id' => $root->id,
            'slug' => self::COMMITTEE,
            'name' => 'Membership Committee',
            'display_order' => 1,
        ]));

        $records = $this->group(self::RECORDS, fn () => Group::factory()->standingCommittee()->create([
            'parent_id' => $root->id,
            'slug' => self::RECORDS,
            'name' => 'Records Group',
            'display_order' => 2,
        ]));
        $this->steward($records, StewardshipFunction::MemberAdmin);
        // Records is also an org-wide sender (ADR-0024 §5): a member of it may pick the
        // org-wide Broadcast Audiences.
        $this->steward($records, StewardshipFunction::OrgMail);

        $program = $this->group(self::PROGRAM, fn () => Group::factory()->program()->create([
            'parent_id' => $root->id,
            'slug' => self::PROGRAM,
            'name' => 'Docents Program',
            'display_order' => 3,
        ]));

        $this->group(self::COHORT, fn () => Group::factory()->cohort()->create([
            'parent_id' => $program->id,
            'slug' => self::COHORT,
            'name' => 'Vikings Exhibition Cohort',
            'display_order' => 4,
        ]));

        $this->group(self::WORKING_GROUP, fn () => Group::factory()->workingGroup()->create([
            'parent_id' => $committee->id,
            'slug' => self::WORKING_GROUP,
            'name' => 'Digital Working Group',
            'display_order' => 5,
        ]));

        // A completed project — archived, so the active-Groups filter excludes it.
        $this->group(self::PROJECT, fn () => Group::factory()->project()->archived()->create([
            'parent_id' => $committee->id,
            'slug' => self::PROJECT,
            'name' => 'Oral History Project',
            'display_order' => 6,
        ]));

        // The DMV Executive — a top-level standing committee stewarding `org_mail`
        // (ADR-0024 §5). Its roster is the Board-of-Directors Audience, and its Chair
        // is a Committee Chair (a top-level Chair, distinct from the root's own Chair,
        // who is an All-Chair only). A member of it is an org-wide sender.
        $executive = $this->group(self::EXECUTIVE, fn () => Group::factory()->standingCommittee()->create([
            'parent_id' => $root->id,
            'slug' => self::EXECUTIVE,
            'name' => 'DMV Executive',
            'display_order' => 7,
        ]));
        $this->steward($executive, StewardshipFunction::OrgMail);

        // Members — a super-tier holder plus the people who fill the roles below.
        $president = $this->member('president@dmv.test', fn () => Member::factory()->superTier()->create([
            'first_name' => 'DMV',
            'last_name' => 'President',
            'email' => 'president@dmv.test',
        ]));
        $secretary = $this->member('secretary@dmv.test', fn () => Member::factory()->create([
            'first_name' => 'Committee',
            'last_name' => 'Secretary',
            'email' => 'secretary@dmv.test',
        ]));
        $clerk = $this->member('clerk@dmv.test', fn () => Member::factory()->create([
            'first_name' => 'Records',
            'last_name' => 'Clerk',
            'email' => 'clerk@dmv.test',
        ]));
        $scheduler = $this->member(self::SCHEDULER_EMAIL, fn () => Member::factory()->create([
            'first_name' => 'Program',
            'last_name' => 'Scheduler',
            'email' => self::SCHEDULER_EMAIL,
        ]));
        $trainee = $this->member('trainee@dmv.test', fn () => Member::factory()->create([
            'first_name' => 'Trainee',
            'last_name' => 'Docent',
            'email' => 'trainee@dmv.test',
        ]));
        $onLeave = $this->member('onleave@dmv.test', fn () => Member::factory()->create([
            'first_name' => 'Docent',
            'last_name' => 'On Leave',
            'email' => 'onleave@dmv.test',
        ]));
        $executiveChair = $this->member(self::EXECUTIVE_CHAIR_EMAIL, fn () => Member::factory()->create([
            'first_name' => 'Executive',
            'last_name' => 'Chair',
            'email' => self::EXECUTIVE_CHAIR_EMAIL,
        ]));

        // Memberships + roles + varied statuses + an LOA window.
        $this->membership($root, $president, MembershipStatus::Full, [Role::Chair]);
        $this->membership($executive, $executiveChair, MembershipStatus::Full, [Role::Chair]);
        $this->membership($committee, $secretary, MembershipStatus::Full, [Role::Secretary]);
        $this->membership($records, $clerk, MembershipStatus::Full);
        // The capability-backed Scheduler role lands on the scheduling program.
        $this->membership($program, $scheduler, MembershipStatus::Full, [Role::Scheduler]);
        $this->membership($program, $trainee, MembershipStatus::Trainee);
        $this->loaMembership($program, $onLeave);

        // A published, current Schedule on the scheduling program (#353), so feature
        // tests have a realistic hook. Keyed on (group, name) so the seed stays
        // idempotent.
        $schedule = $this->schedule($program, self::SCHEDULE_NAME);

        // The program uses kinds: a small seeded vocabulary and a handful of Shifts
        // across the month, so the Agenda has something real to read (#355). One Shift
        // carries no kind — the Reception ("Desk") shape — so both cases are present.
        $this->shiftKinds($program);
        $this->shifts($program, $schedule);
    }

    /**
     * The current-month Schedule name on the program — a stable key so re-seeding
     * heals rather than duplicates.
     */
    public const SCHEDULE_NAME = 'Current Season';

    /**
     * Ensure the Group owns a published, current Schedule with the given name, without
     * duplicating on re-seed.
     */
    private function schedule(Group $group, string $name): Schedule
    {
        return Schedule::where('group_id', $group->id)->where('name', $name)->first()
            ?? Schedule::factory()->create([
                'group_id' => $group->id,
                'name' => $name,
                'state' => ScheduleState::Published,
                'starts_on' => now()->startOfMonth()->toDateString(),
                'ends_on' => now()->endOfMonth()->toDateString(),
            ]);
    }

    /**
     * The program's seeded ShiftKind vocabulary — kept small, idempotent on (group,
     * name). Rows are seeded because the maintenance CRUD screen is deferred
     * (ADR-0021 §3).
     */
    private function shiftKinds(Group $group): void
    {
        foreach (['Highlights tour', 'Level 1 Oslo gate'] as $order => $name) {
            if (! $group->shiftKinds()->where('name', $name)->exists()) {
                ShiftKind::factory()->create([
                    'group_id' => $group->id,
                    'name' => $name,
                    'sort_order' => $order,
                ]);
            }
        }
    }

    /**
     * Seed a few Shifts across the Schedule's month — two kinded, one kind-less (the
     * Reception "Desk" shape) — so the Agenda reads at more than one day. Each Shift is
     * placed on a distinct day inside the range and keyed on (schedule, starts_at) so
     * re-running heals rather than duplicates.
     */
    private function shifts(Group $group, Schedule $schedule): void
    {
        $day = fn (int $offset, int $hour) => $schedule->starts_on->copy()->addDays($offset)->setTime($hour, 0);

        $highlights = $group->shiftKinds()->where('name', 'Highlights tour')->first();
        $oslo = $group->shiftKinds()->where('name', 'Level 1 Oslo gate')->first();

        $plan = [
            ['starts_at' => $day(2, 10), 'ends_at' => $day(2, 13), 'capacity' => 3, 'shift_kind_id' => $highlights?->id],
            ['starts_at' => $day(2, 14), 'ends_at' => $day(2, 17), 'capacity' => 2, 'shift_kind_id' => $oslo?->id],
            ['starts_at' => $day(5, 9), 'ends_at' => $day(5, 12), 'capacity' => 1, 'shift_kind_id' => null],
        ];

        foreach ($plan as $attributes) {
            if (! $schedule->shifts()->where('starts_at', $attributes['starts_at'])->exists()) {
                Shift::factory()->create(['schedule_id' => $schedule->id] + $attributes);
            }
        }
    }

    /**
     * Look up a Group by slug, or build it via the given factory closure. Keying
     * on the unique slug is what makes the seed idempotent.
     *
     * @param  Closure(): Group  $make
     */
    private function group(string $slug, Closure $make): Group
    {
        return Group::where('slug', $slug)->first() ?? $make();
    }

    /**
     * Look up a Member by email, or build them via the given factory closure.
     *
     * @param  Closure(): Member  $make
     */
    private function member(string $email, Closure $make): Member
    {
        return Member::where('email', $email)->first() ?? $make();
    }

    /**
     * Ensure the Group stewards the given org-wide function, without duplicating.
     */
    private function steward(Group $group, StewardshipFunction $function): void
    {
        if (! $group->stewardships()->where('function', $function)->exists()) {
            $group->stewardships()->create(['function' => $function]);
        }
    }

    /**
     * Ensure a membership exists for the Member in the Group with the given status,
     * carrying the given roles (each attached once). Keyed on the (Group, Member)
     * pair so re-running heals rather than duplicates.
     *
     * @param  list<Role>  $roles
     */
    private function membership(Group $group, Member $member, MembershipStatus $status, array $roles = []): GroupMember
    {
        $membership = $this->findMembership($group, $member)
            ?? GroupMember::factory()->status($status)->create([
                'group_id' => $group->id,
                'member_id' => $member->id,
            ]);

        foreach ($roles as $role) {
            if (! $membership->roles()->where('role', $role)->exists()) {
                $membership->roles()->create(['role' => $role]);
            }
        }

        return $membership;
    }

    /**
     * Ensure a membership on an open LOA window exists for the Member in the Group.
     */
    private function loaMembership(Group $group, Member $member): GroupMember
    {
        return $this->findMembership($group, $member)
            ?? GroupMember::factory()->onLoa()->create([
                'group_id' => $group->id,
                'member_id' => $member->id,
            ]);
    }

    private function findMembership(Group $group, Member $member): ?GroupMember
    {
        return GroupMember::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->first();
    }
}
