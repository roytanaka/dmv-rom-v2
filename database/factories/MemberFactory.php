<?php

namespace Database\Factories;

use App\Enums\Category;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'category' => Category::Active,
            'locale' => 'en',
            'super_tier' => false,
            'support_operator' => false,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the Member holds the org-wide "all-DMV" access grant.
     */
    public function superTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'super_tier' => true,
        ]);
    }

    /**
     * Indicate that the Member holds support-operator access — the maintainer power
     * to impersonate for support/QA, held independently of super-tier (ADR-0009).
     */
    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'support_operator' => true,
        ]);
    }

    /**
     * Indicate that the Member carries the no-email flag — a Records-set switch that
     * silences every mail to them (#483, ADR-0024 §9).
     */
    public function noEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'no_email' => true,
        ]);
    }

    public function category(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }
}
