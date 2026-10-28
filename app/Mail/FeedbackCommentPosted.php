<?php

namespace App\Mail;

use App\Models\FeedbackComment;
use App\Models\FeedbackFollower;
use App\Models\FeedbackItem;
use App\Support\FeedbackFollowerMail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email a Follower gets for each new comment on a Feedback item (#860, spec #856,
 * ADR-0029 §15): the full comment text, the commenter's typed name, the item's current status,
 * the item link, and the stop link. Comment images are not attached; the link leads to them.
 *
 * It renders in the Follower's stored locale, with the item link built for that locale. From is
 * the app address, with no Reply-To. Sent through {@see FeedbackFollowerMail}, which names the
 * `smtp` mailer.
 */
class FeedbackCommentPosted extends Mailable
{
    public function __construct(
        public FeedbackItem $item,
        public FeedbackComment $comment,
        public FeedbackFollower $follower,
    ) {
        $this->locale($follower->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('feedback.follow.comment.subject', ['item' => $this->item->label()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.feedback-comment-posted',
            with: [
                'itemLabel' => $this->item->label(),
                'testerName' => $this->comment->tester_name,
                'body' => $this->comment->body,
                'hasImages' => $this->comment->images()->exists(),
                'status' => __($this->item->status->labelKey()),
                'itemUrl' => $this->item->urlFor($this->follower->locale),
                'stopUrl' => $this->follower->stopUrl(),
            ],
        );
    }
}
