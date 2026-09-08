<?php

namespace App\Mail;

use App\Models\Broadcast;
use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One recipient's copy of a Broadcast (spec #479, ADR-0024 §3, §6). The send writes one
 * Broadcast {@see Delivery} per recipient; the Drain builds this Mailable from
 * the row's {@see Broadcast} and sends it. Unlike an automatic Notice, a Broadcast is
 * content: it goes out as authored, in one language, so nothing here is re-rendered per
 * locale.
 *
 * From is the app's one address wearing the Group's name — or the app's name for an
 * org-wide send (§3). Reply-To is the sending officer, so a reply discloses the recipient's
 * own address to the sender by the recipient's choice and the app never shows an address.
 * The body is the sanitized HTML stored on the record; the attachments are the files held
 * on the private disk for the life of the queue.
 */
class BroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Broadcast $broadcast) {}

    /**
     * From the app address wearing the Broadcast's From name; Reply-To the sender; the
     * subject as authored.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->broadcast->fromName()),
            replyTo: [new Address($this->broadcast->sender->email, $this->broadcast->sender->fullName())],
            subject: $this->broadcast->subject,
        );
    }

    /**
     * The body — the sanitized HTML stored on the record, sent as authored.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->broadcast->body,
        );
    }

    /**
     * The attachments held on the private disk under UUID names for the life of the queue
     * (ADR-0024 §4), each restored to the name the composer showed.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $attachment): Attachment => Attachment::fromStorageDisk('local', $attachment['path'])
                ->as($attachment['name']),
            $this->broadcast->attachments,
        );
    }
}
