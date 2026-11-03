<?php

namespace App\Mail;

use App\Support\Notices\ApplicantNoticeWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The intake Notices (#931, ADR-0038 §8): an Applicant was accepted, placed, completed the yellow
 * card, or went Incomplete once linked. {@see ApplicantNoticeWriter} writes the Deliveries; the
 * Drain rebuilds this Mailable from the payload snapshot in the recipient's locale. The links are
 * plain page links: the pages enforce sign-in and the Applicant policy. From is the app address
 * wearing the Membership name; no Reply-To.
 */
class ApplicantNotice extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminators the Drain picks this Mailable by. */
    public const ACCEPTED = 'applicant_accepted';

    public const PLACED = 'applicant_placed';

    public const YELLOW_CARD = 'applicant_yellow_card';

    public const INCOMPLETE = 'applicant_incomplete';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload);
    }

    /** The lang key group for this Notice: `notices.applicant_accepted` and so on. */
    public function key(): string
    {
        return 'notices.'.$this->payload['notice'];
    }

    public function applicantName(): string
    {
        return trim($this->payload['applicant']['first_name'].' '.$this->payload['applicant']['last_name']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('notices.applicant.from')),
            subject: __($this->key().'.subject', ['applicant' => $this->applicantName()]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.applicant-notice', with: [
            'key' => $this->key(),
            'applicantName' => $this->applicantName(),
        ]);
    }
}
