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
 * The ROM-expiry notice summary (#943, ADR-0038 §9): who one daily-pass run sent the ROM-expiry
 * notice to, for the Records Audience. {@see RomExpiryNoticeWriter} writes the Deliveries; the
 * Drain rebuilds this Mailable from the payload in the recipient's locale.
 */
class RomExpirySummary extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminator the Drain picks this Mailable by. */
    public const NOTICE_TYPE = 'rom_expiry_summary';

    /**
     * @param  list<string>  $mailed  full names
     */
    public function __construct(public array $mailed) {}

    /**
     * @param  list<string>  $mailed
     * @return array<string, mixed>
     */
    public static function snapshot(array $mailed): array
    {
        return ['notice' => self::NOTICE_TYPE, 'mailed' => $mailed];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload['mailed']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('rom_expiry.from')),
            subject: __('rom_expiry.summary.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rom-expiry-summary');
    }
}
