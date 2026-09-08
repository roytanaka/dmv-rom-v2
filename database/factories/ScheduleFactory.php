<?php

namespace Database\Factories;

use App\Enums\ScheduleState;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Group with scheduling on, so a Schedule can be made standalone
     * against a valid owner; pass `group_id` to attach it to an existing Group.
     * Published and current (this month) by default — use {@see draft()} for an
     * admin-only draft and {@see past()} for a Schedule whose range has closed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_scheduling' => true]),
            'name' => fake()->monthName().' '.fake()->year(),
            'starts_on' => now()->startOfMonth()->toDateString(),
            'ends_on' => now()->endOfMonth()->toDateString(),
            'state' => ScheduleState::Published,
            'description' => null,
        ];
    }

    /**
     * A draft Schedule — visible only to the Group's schedule admins.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'state' => ScheduleState::Draft,
        ]);
    }

    /**
     * A published Schedule — visible to the Group's listing-visibility audience.
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'state' => ScheduleState::Published,
        ]);
    }

    /**
     * A past Schedule — its range has closed, but it stays listed and openable
     * (nothing is hidden by date, ADR-0021 §1).
     */
    public function past(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'ends_on' => now()->subMonths(2)->endOfMonth()->toDateString(),
        ]);
    }
}
