<?php

namespace App\Policies;

use App\Models\FeedbackCommentImage;
use App\Models\Member;

/**
 * Authorization for images on Feedback comments (#778, ADR-0029). A Member may download a
 * comment image when they may view the comment's item ({@see FeedbackItemPolicy::view()}):
 * every item on staging, their own on Beta (ADR-0035 §8). Every download routes through a
 * policy (the hard rules), as for {@see FeedbackScreenshotPolicy}.
 */
class FeedbackCommentImagePolicy
{
    public function download(Member $actor, FeedbackCommentImage $image): bool
    {
        return $actor->can('view', $image->feedbackComment->feedbackItem);
    }
}
