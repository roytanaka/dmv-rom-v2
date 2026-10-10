<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Support\Notices\BookingMailWriter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A Booking Request or Confirmation (#799, ADR-0032 §9). The {@see BookingMailWriter} writes one
 * Notice {@see Delivery} per recipient with a payload snapshot; the Drain rebuilds this Mailable
 * from it ({@see fromSnapshot}) and renders it in the recipient's locale.
 *
 * Both show the Tour, date, time, visitors, client, leader and comments. The Request asks for the
 * docents still needed; the Confirmation lists everyone on the Booking. From is the app address
 * wearing the Group's name, Reply-To the Booker who sent it. Booking content is never translated
 * (ADR-0004); only the chrome is.
 */
class BookingMail extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminator for a Request — one of the Notices on DeliveryKind::Notice. */
    public const REQUEST = 'booking_request';

    /** The payload discriminator for a Confirmation. */
    public const CONFIRMATION = 'booking_confirmation';

    /**
     * @param  string  $notice  {@see REQUEST} or {@see CONFIRMATION}
     * @param  array{name: string, slug: string}  $group
     * @param  array{name: string, email: string}  $sender  the sending Booker, for Reply-To
     * @param  array<string, mixed>  $booking  the snapshot: tour, times, visitors, client, leader, comments, seats_needed, docents
     */
    public function __construct(
        public string $notice,
        public array $group,
        public int $scheduleId,
        public array $sender,
        public array $booking,
    ) {}

    /**
     * Rebuild the Mailable from a Notice Delivery's payload snapshot.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self(
            $payload['notice'],
            $payload['group'],
            (int) $payload['schedule_id'],
            $payload['reply_to'],
            $payload['booking'],
        );
    }

    /**
     * Whether this is a Confirmation rather than a Request.
     */
    public function isConfirmation(): bool
    {
        return $this->notice === self::CONFIRMATION;
    }

    /**
     * The start on the org wall clock.
     */
    public function startsAt(): CarbonInterface
    {
        return Carbon::parse($this->booking['starts_at'])->setTimezone(config('app.org_timezone'));
    }

    /**
     * The end on the org wall clock.
     */
    public function endsAt(): CarbonInterface
    {
        return Carbon::parse($this->booking['ends_at'])->setTimezone(config('app.org_timezone'));
    }

    /**
     * From the app address wearing the Group's name; Reply-To the sending Booker; the keyed
     * subject naming the Tour and date in the render locale.
     */
    public function envelope(): Envelope
    {
        $key = $this->isConfirmation() ? 'notices.booking_confirmation' : 'notices.booking_request';

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->group['name']),
            replyTo: [new Address($this->sender['email'], $this->sender['name'])],
            subject: __($key.'.subject', [
                'tour' => $this->booking['tour'],
                'date' => $this->startsAt()->translatedFormat('j F Y'),
            ]),
        );
    }

    /**
     * The body — a Markdown view over the snapshot, with the date and times on the org clock in
     * the render locale.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking',
            with: [
                'isConfirmation' => $this->isConfirmation(),
                'date' => $this->startsAt()->translatedFormat(__('notices.booking.date_format')),
                'times' => $this->startsAt()->translatedFormat('g:i A').' – '.$this->endsAt()->translatedFormat('g:i A'),
            ],
        );
    }
}
