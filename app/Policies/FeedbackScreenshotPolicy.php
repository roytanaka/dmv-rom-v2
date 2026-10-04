<?php

namespace App\Policies;

use App\Models\FeedbackScreenshot;
use App\Models\Member;

/**
 * Authorization for Feedback screenshots (#678, ADR-0029). Every logged-in Tester reads
 * every Feedback item (§4), so every logged-in Member may download every screenshot. The
 * check still exists because every download routes through a policy (the hard rules);
 * narrowing who may download later changes only this method.
 */
class FeedbackScreenshotPolicy
{
    public function download(Member $actor, FeedbackScreenshot $screenshot): bool
    {
        return true;
    }
}
