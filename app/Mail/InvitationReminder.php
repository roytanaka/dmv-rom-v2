<?php

namespace App\Mail;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Reminder an Accepted Guest gets before an occasion (#909, ADR-0034 §12). The daily pass
 * writes one Delivery per (Invitation, Member), and the Drain builds this from live data, so
 * it always shows the Guest's current Options.
 *
 * The chrome renders in the Guest's locale; the Invitation's name, description, and Options
 * stay as written (ADR-0004). It is automatic mail, so unlike the Host's own mail it has the
 * default From and no Reply-To (ADR-0024 §3). Its body names the Host.
 */
class InvitationReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public Guest $guest,
    ) {}

    /**
     * The subject names the occasion and its date in the render locale.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invitations.reminder_email.subject', [
                'name' => $this->invitation->name,
                'date' => $this->invitation->date->translatedFormat('j F Y'),
            ]),
        );
    }

    /**
     * The body, a Markdown view sharing the Invitation and Guest.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation-reminder',
        );
    }
}
