<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Shift;
use App\Models\SignUp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignUp>
 */
class SignUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Shift and a Member so a Sign-up can be made standalone; pass
     * `shift_id` / `member_id` to seat an existing Member on an existing Shift.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'member_id' => Member::factory(),
        ];
    }
}
