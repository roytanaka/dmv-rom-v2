<?php

namespace Database\Factories;

use App\Models\HoursAdjustment;
use App\Models\HoursRecord;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HoursAdjustment>
 */
class HoursAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to the HoursRecord/Member factories so an adjustment can be made standalone;
     * pass `hours_record_id` / `created_by` to attach to existing rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hours_record_id' => HoursRecord::factory(),
            'field' => HoursAdjustment::FIELD_EXTRA_HOURS,
            'delta' => fake()->numberBetween(1, 10),
            'created_by' => Member::factory(),
        ];
    }
}
