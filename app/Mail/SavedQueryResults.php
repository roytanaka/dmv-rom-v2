<?php

namespace App\Mail;

use App\Http\Controllers\SavedQueryOutputController;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Saved query results Notice (#924, ADR-0038 §7), the Legacy app's "Results of DMV Query":
 * the sender's note and a link to the run. The link is a plain page link, not a token: the run
 * opens only behind sign-in and member administration. {@see SavedQueryOutputController} writes the
 * Delivery; the Drain rebuilds this Mailable from the payload in the recipient's locale.
 */
class SavedQueryResults extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminator the Drain picks this Mailable by. */
    public const NOTICE_TYPE = 'saved_query_results';

    public function __construct(
        public int $queryId,
        public string $queryName,
        public ?string $note,
        public string $sender,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload['query']['id'], $payload['query']['name'], $payload['note'] ?? null, $payload['sender']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), __('notices.saved_query_results.from')),
            subject: __('notices.saved_query_results.subject', ['query' => $this->queryName]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.saved-query-results');
    }
}
