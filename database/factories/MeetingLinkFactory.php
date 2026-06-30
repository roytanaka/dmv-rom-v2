<?php

namespace Database\Factories;

use App\Enums\MeetingLinkKind;
use App\Models\Meeting;
use App\Models\MeetingLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingLink>
 */
class MeetingLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Wires to a Meeting factory so a link can be made standalone; pass
     * `meeting_id` to attach it to an existing meeting.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'kind' => fake()->randomElement(MeetingLinkKind::cases()),
            'url' => fake()->url(),
        ];
    }

    /**
     * Set the link's kind (agenda / minutes / report).
     */
    public function kind(MeetingLinkKind $kind): static
    {
        return $this->state(fn () => [
            'kind' => $kind,
        ]);
    }
}
