<?php

namespace App\Mail;

use App\Support\Notices\SignUpSubstitutionNoticeWriter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The substitution Notice (#798, ADR-0032 §9): a seat on a group tour has passed from one Member
 * to another. Sent to both Members and the Group's Bookers through the Delivery queue; the
 * {@see SignUpSubstitutionNoticeWriter} writes the rows and the Drain builds this from each row's
 * payload snapshot.
 *
 * It renders in the recipient's saved locale. From is the app's one address wearing the Group's
 * name; no Reply-To (an automatic Notice is not a conversation). The body names both Members, the
 * date and times, the Tour and the client, and links to the Schedule for the render locale.
 */
class SignUpSubstituted extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload `notice` discriminator the Drain switches on (ADR-0024 §8). */
    public const NOTICE_TYPE = 'sign_up_substituted';

    /**
     * @param  array{previous: string, substitute: string, group_name: string, group_slug: string, schedule_id: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, tour: ?string, client: ?string}  $notice
     */
    public function __construct(public array $notice) {}

    /**
     * Rebuild the Mailable from a Notice Delivery's payload snapshot.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self([
            'previous' => trim($payload['previous']['first_name'].' '.$payload['previous']['last_name']),
            'substitute' => trim($payload['substitute']['first_name'].' '.$payload['substitute']['last_name']),
            'group_name' => $payload['group']['name'],
            'group_slug' => $payload['group']['slug'],
            'schedule_id' => $payload['schedule']['id'],
            'starts_at' => CarbonImmutable::parse($payload['shift']['starts_at']),
            'ends_at' => CarbonImmutable::parse($payload['shift']['ends_at']),
            'tour' => $payload['booking']['tour'],
            'client' => $payload['booking']['client'],
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->notice['group_name']),
            subject: __('scheduling.substitution_email.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sign-up-substituted',
        );
    }
}
