<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\MenteeActivated;
use App\Models\Delivery;
use App\Models\Member;

/**
 * The mentor Notice writer (#920, ADR-0038 §4). When a Member moves from Provisional or Pre-active
 * to Active, their mentor gets one Notice {@see Delivery}. The mentor is known only once an
 * Applicant links to the Member (#914); without one, or with a mentor carrying the no-email flag,
 * it writes nothing.
 */
class MenteeActivatedNoticeWriter
{
    public function write(Member $mentee, ?Member $mentor): void
    {
        if ($mentor === null || $mentor->no_email) {
            return;
        }

        Delivery::create([
            'kind' => DeliveryKind::Notice,
            'member_id' => $mentor->getKey(),
            'email' => $mentor->email,
            'payload' => [
                'notice' => MenteeActivated::NOTICE_TYPE,
                'member' => [
                    'first_name' => $mentee->first_name,
                    'last_name' => $mentee->last_name,
                ],
            ],
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ]);
    }
}
