<?php

namespace App\Policies;

use App\Models\FeedbackCommentImage;
use App\Models\Member;

/**
 * Authorization for images on Feedback comments (#778, ADR-0029). Every logged-in Tester
 * reads every Feedback item and its comments (§4), so every logged-in Member may download
 * every comment image. The check exists because every download routes through a policy
 * (the hard rules), as for {@see FeedbackScreenshotPolicy}.
 */
class FeedbackCommentImagePolicy
{
    public function download(Member $actor, FeedbackCommentImage $image): bool
    {
        return true;
    }
}
