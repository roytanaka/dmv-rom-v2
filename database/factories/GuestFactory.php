<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * Define the model's default state: a Guest with No reply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'member_id' => Member::factory(),
        ];
    }
}
