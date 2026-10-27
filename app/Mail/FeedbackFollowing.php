<?php

namespace App\Mail;

use App\Models\FeedbackFollower;
use App\Models\FeedbackItem;
use App\Support\FeedbackFollowerMail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The "You're following" receipt (#857, spec #856, ADR-0029 §15). It goes to a new Follower at
 * once, in every environment, so a Tester who does not get it knows the address is wrong.
 *
 * It renders in the Follower's stored locale. It names the item ({@see FeedbackItem::label()}),
 * links to it in that locale, and carries the stop link. From is the app address, with no
 * Reply-To. Sent through {@see FeedbackFollowerMail}, which names the `smtp` mailer.
 */
class FeedbackFollowing extends Mailable
{
    public function __construct(
        public FeedbackItem $item,
        public FeedbackFollower $follower,
    ) {
        $this->locale($follower->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('feedback.follow.receipt.subject', ['item' => $this->item->label()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.feedback-following',
            with: [
                'itemLabel' => $this->item->label(),
                'itemUrl' => $this->item->urlFor($this->follower->locale),
                'stopUrl' => $this->follower->stopUrl(),
            ],
        );
    }
}
