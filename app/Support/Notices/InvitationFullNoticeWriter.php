<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\InvitationFull;
use App\Models\Delivery;
use App\Models\Invitation;

/**
 * The limit-reached Notice writer (#906, ADR-0034 §9). When a Guest takes the last spot,
 * the Host is told. It writes one Notice {@see Delivery} and nothing else; the Drain sends
 * it, from a payload snapshot, because the Invitation may be renamed or deleted by then.
 */
class InvitationFullNoticeWriter
{
    public function write(Invitation $invitation): void
    {
        $host = $invitation->host;

        // The no-email flag silences every mail, checked here, where the Notice is written
        // (ADR-0024 §9).
        if ($host->no_email) {
            return;
        }

        Delivery::create([
            'kind' => DeliveryKind::Notice,
            'member_id' => $host->getKey(),
            'email' => $host->email,
            'payload' => [
                'notice' => InvitationFull::NOTICE_TYPE,
                'invitation' => ['id' => $invitation->id, 'name' => $invitation->name],
            ],
            'state' => DeliveryState::Pending,
            'next_attempt_at' => now(),
        ]);
    }
}
