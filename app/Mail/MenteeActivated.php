<?php

namespace App\Mail;

use App\Models\Member;
use App\Support\Notices\MenteeActivatedNoticeWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The mentor Notice (#920, ADR-0038 §4): the Records office moved a Member from Provisional or
 * Pre-active to Active, and their mentor is told, as the Legacy app's "Member Now Active" mail.
 * {@see MenteeActivatedNoticeWriter} writes the Delivery; the Drain rebuilds this Mailable from
 * the payload snapshot and renders it in the mentor's locale. From is the app address wearing the
 * Records name; no Reply-To.
 */
class MenteeActivated extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminator the Drain picks this Mailable by. */
    public const NOTICE_TYPE = 'mentee_activated';

    public function __construct(public Member $mentee) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self(new Member([
            'first_name' => $payload['member']['first_name'],
            'last_name' => $payload['member']['last_name'],
        ]));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('notices.mentee_activated.from')),
            subject: __('notices.mentee_activated.subject', ['member' => $this->mentee->fullName()]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.mentee-activated');
    }
}
