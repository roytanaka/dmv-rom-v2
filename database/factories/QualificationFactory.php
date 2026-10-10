<?php

namespace Database\Factories;

use App\Models\GroupMember;
use App\Models\Qualification;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Qualification>
 */
class QualificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Pass `group_member_id` and `tour_id` to tie it to a Membership and a Tour of the same
     * Group; the defaults build each standalone. Active, with no Last vet date.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_member_id' => GroupMember::factory(),
            'tour_id' => Tour::factory(),
            'active' => true,
            'last_vet_date' => null,
        ];
    }

    /**
     * A qualification kept on file but counting for nothing.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
