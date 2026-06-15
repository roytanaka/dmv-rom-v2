<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMember>
 */
class GroupMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to the existing Member/Group factories so a membership can be made
     * standalone; pass `group_id` / `member_id` to attach to existing rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'member_id' => Member::factory(),
            'status' => MembershipStatus::Full,
            'loa_start' => null,
            'loa_end' => null,
        ];
    }

    /**
     * Set the within-Group standing.
     */
    public function status(MembershipStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }

    /**
     * Put the membership on leave with an open LOA window (started a month ago,
     * ends a month from now). Status flips to LOA to match.
     */
    public function onLoa(): static
    {
        return $this->state(fn () => [
            'status' => MembershipStatus::Loa,
            'loa_start' => now()->subMonth()->toDateString(),
            'loa_end' => now()->addMonth()->toDateString(),
        ]);
    }
}
