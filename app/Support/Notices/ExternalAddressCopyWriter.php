<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Models\Delivery;
use App\Models\RenewalSetting;

/**
 * The external addresses of the Renewal settings (ADR-0038 §9, §14.5): the ROM mailboxes the
 * Legacy app hard-coded, now a list Records sets, empty until the Records Chair confirms one. The
 * renewal and applicant Notice writers call {@see write} with the payload their Member rows carry.
 *
 * An address is no Member, so its row has no `member_id` and no no-email flag, and names its
 * locale in the payload, as a Group copy address does ({@see GroupCopyWriter}, #799). No schema
 * change: `member_id` has been nullable since then.
 */
class ExternalAddressCopyWriter
{
    /**
     * One Notice Delivery per external address. Nothing when the list is empty, or while the Beta
     * mail-off switch is on (ADR-0038 Beta: every Notice is off during Beta).
     *
     * @param  array<string, mixed>  $payload  the Notice payload, `notice` discriminator included
     */
    public function write(array $payload): void
    {
        if (config('mail.daily_pass_off')) {
            return;
        }

        foreach (array_unique(RenewalSetting::current()->external_addresses ?? []) as $address) {
            if (blank($address)) {
                continue;
            }

            Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => null,
                'email' => $address,
                'payload' => [...$payload, 'locale' => config('app.locale')],
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]);
        }
    }
}
