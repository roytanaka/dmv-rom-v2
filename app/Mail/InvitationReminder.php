<?php

namespace App\Mail;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Reminder an Accepted Guest gets before an occasion (#909, ADR-0034 §12). The daily pass
 * writes one Delivery per (Invitation, Member), and the Drain builds this from live data, so
 * it always shows the Guest's current Options.
 *
 * The chrome renders in the Guest's locale; the Invitation's name, description, and Options
 * stay as written (ADR-0004). As with all Invitation mail, From is the app's one address
 * wearing the Host's name, and Reply-To is the Host (ADR-0034 §7).
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
        $host = $this->invitation->host;

        return new Envelope(
            from: new Address(config('mail.from.address'), $host->fullName()),
            replyTo: [new Address($host->email, $host->fullName())],
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
