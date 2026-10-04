<?php

namespace Database\Factories;

use App\Enums\AudienceKey;
use App\Enums\BroadcastKind;
use App\Models\Broadcast;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * A Group Broadcast sent record with no attachments, ready for a test to hang Delivery rows
     * off. Override `group_id` (or null it) and `attachments` for the surface under test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => BroadcastKind::Broadcast,
            'sender_id' => Member::factory(),
            'group_id' => null,
            'audience_key' => AudienceKey::WholeGroup->value,
            'audience_label' => 'Whole group',
            'edited' => false,
            'recipient_count' => 0,
            'subject' => $this->faker->sentence(),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
            'attachments' => [],
            'queued_at' => now(),
        ];
    }
}
