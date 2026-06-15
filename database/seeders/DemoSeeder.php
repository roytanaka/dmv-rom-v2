<?php

namespace Database\Seeders;

use App\Enums\Category;
use App\Enums\Kind;
use App\Enums\LifecycleState;
use App\Enums\MembershipStatus;
use App\Enums\Scope;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The curated demo data (PRD #139): a believable slice of the real DMV org used
 * to populate staging by hand before a board pitch. Unlike {@see OrgTreeSeeder}
 * — the test-only fixture built from per-slice factories — this seeder is
 * faker-free: every row is created with plain Eloquent, because the factories
 * call `fake()`, a dev-only dependency absent from the `--no-dev` staging build
 * (mirroring how {@see DatabaseSeeder} creates its known login directly).
 *
 * This first slice is the spine — the thin end-to-end path proven before the full
 * org tree is transcribed: the root `DMV` Group, one standing committee, one
 * program, two members, and their memberships. Every row is keyed on a
 * deterministic identifier (Group slug, Member email, the (group, member)
 * membership pair) so re-running heals rather than duplicates.
 *
 * It is deliberately NOT wired into {@see DatabaseSeeder} and not part of any
 * deploy step — it runs only by hand. The public constants below are stable
 * handles for tests, mirroring {@see OrgTreeSeeder}.
 */
class DemoSeeder extends Seeder
{
    public const ROOT = 'dmv';

    public const COMMITTEE = 'governance-operations-committee';

    public const PROGRAM = 'docents';

    public const CHAIR_EMAIL = 'demo.chair@dmv.test';

    public const COORDINATOR_EMAIL = 'demo.coordinator@dmv.test';

    public function run(): void
    {
        $root = $this->group(self::ROOT, [
            'name' => 'Department of Museum Volunteers',
            'description' => 'The DMV at large — the root of the org tree.',
            'kind' => Kind::StandingCommittee,
            'scope' => Scope::Organization,
            'display_order' => 0,
        ]);

        $committee = $this->group(self::COMMITTEE, [
            'parent_id' => $root->id,
            'name' => 'Governance & Operations Committee',
            'kind' => Kind::StandingCommittee,
            'scope' => Scope::Organization,
            'display_order' => 1,
        ]);

        $program = $this->group(self::PROGRAM, [
            'parent_id' => $root->id,
            'name' => 'Docents',
            'kind' => Kind::Program,
            'scope' => Scope::Program,
            'display_order' => 2,
        ]);

        $chair = $this->member(self::CHAIR_EMAIL, 'Demo Chair');
        $coordinator = $this->member(self::COORDINATOR_EMAIL, 'Demo Coordinator');

        $this->membership($committee, $chair, MembershipStatus::Full);
        $this->membership($program, $coordinator, MembershipStatus::Full);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function group(string $slug, array $attributes): Group
    {
        return Group::firstOrCreate(['slug' => $slug], $attributes + [
            'lifecycle_state' => LifecycleState::Active,
        ]);
    }

    /** Not via the factory: factories call fake(), absent from the --no-dev build. */
    private function member(string $email, string $name): Member
    {
        return Member::firstOrCreate(['email' => $email], [
            'name' => $name,
            'email_verified_at' => now(),
            'category' => Category::Active,
            'super_tier' => false,
            'password' => Hash::make('password'),
        ]);
    }

    private function membership(Group $group, Member $member, MembershipStatus $status): GroupMember
    {
        return GroupMember::firstOrCreate(
            ['group_id' => $group->id, 'member_id' => $member->id],
            ['status' => $status],
        );
    }
}
