<?php

namespace App\Policies;

use App\Models\FeedbackItem;
use App\Models\Member;
use App\Support\FeedbackIdentity;

/**
 * Who may view a Feedback item (ADR-0029 §4, ADR-0035 §8 as amended 2026-10-27). The one
 * "may view this item" rule: it serves the list, the item page, commenting, and the
 * screenshot and comment-image downloads.
 *
 * Where the app does not use account identity (staging, local), every logged-in Member
 * views every item. Where it does (Beta), a Member views an item when its stored Member
 * email is their account's email, or they are the Support-operator. Super-tier views every
 * item through `Gate::before`, which grants them every ability.
 *
 * Status change and delete are not here: they stay a direct
 * {@see Member::isSupportOperator()} check in their Form Requests (ADR-0017 §5), because a
 * policy ability would hand them to super-tier through that same `Gate::before`.
 */
class FeedbackItemPolicy
{
    public function view(Member $actor, FeedbackItem $item): bool
    {
        if (! FeedbackIdentity::usesAccountIdentity() || $actor->isSupportOperator()) {
            return true;
        }

        return $item->member_email !== null
            && strcasecmp($item->member_email, (string) $actor->email) === 0;
    }
}
