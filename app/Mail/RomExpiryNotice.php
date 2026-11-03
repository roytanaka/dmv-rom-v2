<?php

namespace App\Mail;

use App\Support\Renewal\RomExpiryNoticeWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The ROM-expiry notice (#943, ADR-0038 §9). {@see RomExpiryNoticeWriter} writes the Delivery; the
 * Drain builds this Mailable in the recipient's locale. The text is translated copy, a placeholder
 * until the Records Chair confirms the wording.
 */
class RomExpiryNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('rom_expiry.from')),
            subject: __('rom_expiry.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rom-expiry-notice');
    }
}
