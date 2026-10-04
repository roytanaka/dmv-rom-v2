<?php

namespace Database\Factories;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * A pending Notice row due now, addressed to a fresh Member, with the payload a Sign-up
     * cancellation snapshot expects. Override `payload` for a specific Shift/Group.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => DeliveryKind::Notice,
            'member_id' => Member::factory(),
            // The snapshotted recipient address; override alongside member_id when a test
            // asserts on where the mail was sent.
            'email' => $this->faker->safeEmail(),
            'payload' => [
                'member' => ['first_name' => $this->faker->firstName(), 'last_name' => $this->faker->lastName()],
                'group' => ['name' => 'Visitor Wayfinders', 'slug' => 'visitor-wayfinders'],
                'schedule' => ['id' => 1, 'name' => $this->faker->word()],
                'shift' => [
                    'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
                    'ends_at' => now()->addDay()->setTime(13, 0)->toIso8601String(),
                    'kind' => null,
                ],
            ],
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ];
    }

    /** A row already sent, at the given time — for exercising the rolling-hour budget. */
    public function sent(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'state' => DeliveryState::Sent,
            'sent_at' => $at ?? now(),
        ]);
    }
}
