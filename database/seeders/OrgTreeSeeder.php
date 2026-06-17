<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\StewardshipFunction;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
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
    public const ROOT = 'dmv';

    public const COMMITTEE = 'membership-committee';

    public const RECORDS = 'records-group';

    public const PROGRAM = 'docents-program';

    public const COHORT = 'vikings-exhibition-cohort';

    public const WORKING_GROUP = 'digital-working-group';

    public const PROJECT = 'oral-history-project';

    public const SCHEDULER_EMAIL = 'scheduler@dmv.test';

    /**
     * Build the miniature org tree end-to-end.
     */
    public function run(): void
    {
        $root = $this->group(self::ROOT, fn () => Group::factory()->standingCommittee()->create([
            'slug' => self::ROOT,
            'name' => 'Department of Museum Volunteers',
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

        // Memberships + roles + varied statuses + an LOA window.
        $this->membership($root, $president, MembershipStatus::Full, [Role::Chair]);
        $this->membership($committee, $secretary, MembershipStatus::Full, [Role::Secretary]);
        $this->membership($records, $clerk, MembershipStatus::Full);
        // The capability-backed Scheduler role lands on the scheduling program.
        $this->membership($program, $scheduler, MembershipStatus::Full, [Role::Scheduler]);
        $this->membership($program, $trainee, MembershipStatus::Trainee);
        $this->loaMembership($program, $onLeave);
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
