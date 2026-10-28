<?php

namespace App\Policies;

use App\Models\FeedbackScreenshot;
use App\Models\Member;

/**
 * Authorization for Feedback screenshots (#678, ADR-0029). A Member may download a
 * screenshot when they may view its item ({@see FeedbackItemPolicy::view()}): every item on
 * staging, their own on Beta (ADR-0035 §8). Every download routes through a policy (the
 * hard rules).
 */
class FeedbackScreenshotPolicy
{
    public function download(Member $actor, FeedbackScreenshot $screenshot): bool
    {
        return $actor->can('view', $screenshot->feedbackItem);
    }
}
