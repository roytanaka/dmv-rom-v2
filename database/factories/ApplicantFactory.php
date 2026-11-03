<?php

namespace Database\Factories;

use App\Enums\ApplicantStatus;
use App\Models\Applicant;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * A New application entered by the office, unassigned.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '416-555-'.fake()->numerify('####'),
            'status' => ApplicantStatus::New,
            'status_date' => now()->toDateString(),
            'applied_on' => now()->toDateString(),
        ];
    }

    public function status(ApplicantStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function mentoredBy(Member $mentor): static
    {
        return $this->state(fn () => ['mentor_id' => $mentor->id]);
    }
}
