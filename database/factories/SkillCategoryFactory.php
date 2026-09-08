<?php

namespace Database\Factories;

use App\Models\SkillCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillCategory>
 */
class SkillCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A plain active category with a unique `code`; use `retired()` for the
     * `active=false` case the catalog filters out.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => ucfirst($name),
            'display_order' => 0,
            'active' => true,
        ];
    }

    /**
     * Retire the category — dropped from the active catalog, rows preserved.
     */
    public function retired(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
