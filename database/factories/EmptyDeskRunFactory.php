<?php

namespace Database\Factories;

use App\Models\EmptyDeskRun;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmptyDeskRun>
 */
class EmptyDeskRunFactory extends Factory
{
    /**
     * A run today for a fresh Group, with no unstaffed Shifts found — override `run_date` and
     * `open_shift_count` when a test asserts on a specific cadence day or count.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'run_date' => now()->toDateString(),
            'open_shift_count' => 0,
        ];
    }
}
