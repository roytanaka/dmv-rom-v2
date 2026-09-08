<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to the SkillCategory factory so a skill can be made standalone; pass
     * `category_id` (or use `->for($category)`) to attach to an existing category.
     * Use `retired()` for the `active=false` case the catalog filters out.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 999999),
            'category_id' => SkillCategory::factory(),
            'name' => ucfirst($name),
            'active' => true,
        ];
    }

    /**
     * Retire the skill — dropped from the active catalog, rows preserved.
     */
    public function retired(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
