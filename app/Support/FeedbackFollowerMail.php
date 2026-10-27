<?php

namespace App\Support;

use App\Mail\FeedbackFollowing;
use App\Models\FeedbackFollower;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one email to one Feedback Follower (#857, spec #856, ADR-0029 §15).
 *
 * - Inside the request, after the feedback transaction commits: the exception to ADR-0024 §4.
 *   Never through `deliveries`, which staging wipes on every push.
 * - Through the `smtp` mailer, named here. The default mailer stays as configured (`log` on
 *   staging), so the rest of staging's mail stays off the shared hourly cap.
 * - Each send stands alone. A failure is reported, never thrown: the item or comment is
 *   already saved, and the other Followers still get theirs.
 */
class FeedbackFollowerMail
{
    public static function send(FeedbackFollower $follower, Mailable $mail): void
    {
        try {
            Mail::mailer('smtp')->to($follower->email)->send($mail);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The "You're following" receipt, sent only when the follow made a new Follower: a second
     * follow with the same address sends nothing.
     */
    public static function receipt(FeedbackFollower $follower): void
    {
        if ($follower->wasRecentlyCreated) {
            self::send($follower, new FeedbackFollowing($follower->feedbackItem, $follower));
        }
    }
}
