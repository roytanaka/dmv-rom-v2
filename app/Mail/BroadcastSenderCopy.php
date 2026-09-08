<?php

namespace App\Mail;

use App\Enums\BroadcastKind;
use App\Models\Broadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The sender's copy of a Broadcast — the done signal (spec #479, ADR-0024 §4). It is the
 * last Delivery of a send and goes only once every sibling is terminal, so its arrival tells
 * the officer the queue has drained. When any recipient was not reached it carries a footer
 * naming them, up to 20 then "and N more"; the footer is chrome, rendered in the sender's own
 * locale, while the body above it is the Broadcast as authored.
 *
 * From and Reply-To match the Broadcast (the sender is their own Reply-To here); the
 * attachments ride along one last time, and the Drain deletes the files once this copy is sent.
 */
class BroadcastSenderCopy extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The most names the footer spells out before it falls back to a count (ADR-0024 §4).
     */
    public const NAMES_SHOWN = 20;

    /**
     * @param  Broadcast  $broadcast  the send this is a copy of
     * @param  list<string>  $failedNames  the full names of recipients not reached, for the footer
     */
    public function __construct(
        public Broadcast $broadcast,
        public array $failedNames = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->broadcast->fromName()),
            replyTo: [new Address($this->broadcast->sender->email, $this->broadcast->sender->fullName())],
            subject: $this->broadcast->subject,
        );
    }

    /**
     * The Broadcast body as authored, followed by the undelivered footer when anyone was not
     * reached. Both are HTML: the body is the sanitized store, the footer is a small chrome
     * block resolved in the render locale.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->broadcast->body.$this->footer(),
        );
    }

    /**
     * The attachments, one last send before the Drain deletes them (ADR-0024 §4).
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

    /**
     * The "could not be reached" footer, or an empty string when every recipient was reached.
     * Up to {@see NAMES_SHOWN} names are listed; beyond that the overflow becomes "and N more".
     */
    private function footer(): string
    {
        if ($this->failedNames === []) {
            return '';
        }

        // A Direct message has one recipient, so its footer names that one plainly rather than
        // opening a "could not be delivered to" list (ADR-0024 §6).
        if ($this->broadcast->kind === BroadcastKind::DirectMessage) {
            $line = __('broadcasts.sender_copy.undelivered_direct', ['name' => $this->failedNames[0]]);

            return '<hr><p>'.e($line).'</p>';
        }

        $shown = array_slice($this->failedNames, 0, self::NAMES_SHOWN);
        $names = implode(', ', $shown);

        $overflow = count($this->failedNames) - count($shown);

        if ($overflow > 0) {
            $names .= ' '.__('broadcasts.sender_copy.and_more', ['count' => $overflow]);
        }

        $line = __('broadcasts.sender_copy.undelivered', ['names' => $names]);

        return '<hr><p>'.e($line).'</p>';
    }
}
