<?php

namespace App\Mail;

use App\Support\Renewal\RenewalReminderWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Renewal reminder summary Notice (#942, ADR-0038 §9): who one daily-pass run mailed, by
 * series, for the Records Audience. {@see RenewalReminderWriter} writes the Deliveries;
 * the Drain rebuilds this Mailable from the payload in the recipient's locale.
 */
class RenewalReminderSummary extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminator the Drain picks this Mailable by. */
    public const NOTICE_TYPE = 'renewal_reminder_summary';

    /**
     * @param  array<string, list<string>>  $mailed  full names, keyed by series
     */
    public function __construct(public array $mailed, public int $seriesYear) {}

    /**
     * @param  array<string, list<string>>  $mailed
     * @return array<string, mixed>
     */
    public static function snapshot(array $mailed, int $seriesYear): array
    {
        return ['notice' => self::NOTICE_TYPE, 'mailed' => $mailed, 'series_year' => $seriesYear];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload['mailed'], $payload['series_year']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('notices.renewal_reminder_summary.from')),
            subject: __('notices.renewal_reminder_summary.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.renewal-reminder-summary');
    }
}
