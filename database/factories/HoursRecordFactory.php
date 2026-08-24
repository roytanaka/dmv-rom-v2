<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\HoursRecord;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HoursRecord>
 */
class HoursRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to the Member/Group factories so a record can be made standalone; pass
     * `member_id` / `group_id` to attach to existing rows. Defaults to the no-meeting
     * sentinel and a `total_hours` that already satisfies the stored identity — override
     * the two parts and pass a matching total, or use {@see HoursRecord::enterExtra()} to
     * exercise the write path.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduled = fake()->numberBetween(0, 20);
        $extra = fake()->numberBetween(0, 20);

        return [
            'member_id' => Member::factory(),
            'group_id' => Group::factory(),
            'year_month' => fake()->numberBetween(2018, 2026).str_pad((string) fake()->numberBetween(1, 12), 2, '0', STR_PAD_LEFT),
            'meeting_id' => HoursRecord::NO_MEETING,
            'scheduled_hours' => $scheduled,
            'extra_hours' => $extra,
            'total_hours' => $scheduled + $extra,
        ];
    }
}
