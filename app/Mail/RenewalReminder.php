<?php

namespace App\Mail;

use App\Models\RenewalSetting;
use App\Support\Renewal\RenewalReminderWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * One Renewal reminder (#942, ADR-0038 §9). {@see RenewalReminderWriter} writes the Delivery with
 * the series and the settings text frozen in its payload; the Drain rebuilds this Mailable in the
 * recipient's locale, so the sender and subject are translated chrome and the body is the member
 * admin's text as authored. A Sustaining reminder carries the Sustaining attachment from Renewal
 * settings, read when the mail is sent; a missing file sends the reminder without it.
 */
class RenewalReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $series, public int $renewalYear, public string $body) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload['series'], $payload['renewal_year'], $payload['message']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('renewal_reminders.from')),
            subject: __('renewal_reminders.subject.'.$this->series, [
                'year' => $this->renewalYear,
                'next' => $this->renewalYear + 1,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.renewal-reminder');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! str_starts_with($this->series, 'sustaining-')) {
            return [];
        }

        $settings = RenewalSetting::current();
        $path = $settings->sustaining_attachment_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return [];
        }

        $attachment = Attachment::fromStorageDisk('local', $path)
            ->as($settings->sustaining_attachment_filename ?? basename($path));

        return [$settings->sustaining_attachment_mime_type === null
            ? $attachment
            : $attachment->withMime($settings->sustaining_attachment_mime_type)];
    }
}
