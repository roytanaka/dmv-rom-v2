<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\HandlingObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandlingObject>
 */
class HandlingObjectFactory extends Factory
{
    /**
     * The model this factory builds — the class is prefixed, so it is named explicitly.
     *
     * @var class-string<HandlingObject>
     */
    protected $model = HandlingObject::class;

    /**
     * Define the model's default state.
     *
     * Wires to a Group with scheduling on, so an Object can be made standalone against a valid
     * owner; pass `group_id` to attach it to an existing Group. Active by default — use
     * {@see inactive()} for a retired Object that still names its old Sign-ups.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory()->state(['has_scheduling' => true]),
            'name' => fake()->unique()->words(2, true),
            'active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A retired Object — no longer offered on new Sign-ups, but still naming old ones.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
