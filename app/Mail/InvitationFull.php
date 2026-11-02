<?php

namespace App\Mail;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The limit-reached Notice (#906, ADR-0034 §9): an Invitation's Host is told that a Guest
 * took the last spot. The Drain builds this from a Notice {@see Delivery}'s payload snapshot.
 *
 * It renders in the Host's saved locale. An Invitation has no Group to name, so From is the
 * app's own default. There is no Reply-To: an automatic Notice is not a conversation.
 */
class InvitationFull extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The payload discriminator that picks this Mailable within the Notice kind.
     */
    public const NOTICE_TYPE = 'invitation_full';

    public function __construct(
        public int $invitationId,
        public string $invitationName,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload['invitation']['id'], $payload['invitation']['name']);
    }

    /**
     * The subject, resolved in the render locale. The name is as-authored.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('notices.invitation_full.subject', ['name' => $this->invitationName]),
        );
    }

    /**
     * The body: a Markdown view.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation-full',
        );
    }
}
