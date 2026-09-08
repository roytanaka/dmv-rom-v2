<?php

namespace Database\Factories;

use App\Enums\ShiftAudience;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Schedule, so a Shift can be made standalone; pass `schedule_id` to
     * attach it to an existing one. A single-seat, kind-less, group-audience morning
     * slot today by default — override `starts_at` / `ends_at` to place it within a
     * particular Schedule's range. Kind is null (Reception's shape); pass `shift_kind_id`
     * or use {@see ShiftKindFactory} for a kinded Shift.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'starts_at' => now()->startOfDay()->addHours(10),
            'ends_at' => now()->startOfDay()->addHours(13),
            'capacity' => 1,
            'shift_kind_id' => null,
            'audience' => ShiftAudience::Group,
        ];
    }

    /**
     * A Shift opened to the whole org — any Member who can see the Schedule.
     */
    public function open(): static
    {
        return $this->state(fn () => [
            'audience' => ShiftAudience::Open,
        ]);
    }
}
