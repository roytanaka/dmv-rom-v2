<?php

namespace App\Support\Notices;

use App\Enums\DeliveryKind;
use App\Enums\DeliveryState;
use App\Mail\SavedQueryResults;
use App\Models\Delivery;
use App\Models\Member;
use App\Models\SavedQuery;

/**
 * The Email results Notice writer (#924, ADR-0038 §7). One Notice {@see Delivery} per chosen
 * Member, carrying the note and a link to the run. A Member with the no-email flag or no address
 * is skipped.
 */
class SavedQueryResultsNoticeWriter
{
    /** @param  list<int>  $memberIds */
    public function write(SavedQuery $query, array $memberIds, ?string $note, Member $sender): void
    {
        Member::query()
            ->whereIn('id', $memberIds)
            ->where('no_email', false)
            ->whereNotNull('email')
            ->get()
            ->each(fn (Member $member) => Delivery::create([
                'kind' => DeliveryKind::Notice,
                'member_id' => $member->id,
                'email' => $member->email,
                'payload' => [
                    'notice' => SavedQueryResults::NOTICE_TYPE,
                    'query' => ['id' => $query->id, 'name' => $query->name],
                    'note' => $note,
                    'sender' => $sender->fullName(),
                ],
                'state' => DeliveryState::Pending,
                'next_attempt_at' => now(),
            ]));
    }
}
