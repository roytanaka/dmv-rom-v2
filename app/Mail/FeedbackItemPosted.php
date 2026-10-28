<?php

namespace App\Mail;

use App\Models\FeedbackItem;
use App\Support\FeedbackFollowerMail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * The email to the notify address for each new Feedback item (ADR-0029 §16): the Tester's typed
 * name, the page they sent it from, the full message, and the item link. Screenshots are not
 * attached; the link leads to them.
 *
 * It renders in the app's default locale (English), whatever page the item came from, with the item link built for that locale. It has no
 * stop link: the address comes from config, so clearing it stops the emails. From is the app
 * address, with no Reply-To. Sent through {@see FeedbackFollowerMail}, which names the `smtp`
 * mailer.
 */
class FeedbackItemPosted extends Mailable
{
    public function __construct(public FeedbackItem $item)
    {
        $this->locale(LaravelLocalization::getDefaultLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('feedback.new_item.subject', ['item' => $this->item->label()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.feedback-item-posted',
            with: [
                'itemLabel' => $this->item->label(),
                'testerName' => $this->item->tester_name,
                'pageUrl' => $this->item->page_url,
                'message' => $this->item->message,
                'hasScreenshots' => $this->item->screenshots()->exists(),
                'itemUrl' => $this->item->urlFor($this->locale),
            ],
        );
    }
}
