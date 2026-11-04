<?php

namespace App\Mail;

use App\Actions\SaveRenewalForm;
use App\Enums\Category;
use App\Models\FeeCategory;
use App\Support\Notices\RenewalSubmittedNoticeWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The renewal Notice (#940, ADR-0038 §9): a Member saved the renewal form, with the fields they
 * changed and any Category change. {@see RenewalSubmittedNoticeWriter} writes the Deliveries; the
 * Drain rebuilds this Mailable from the payload in the recipient's locale.
 */
class RenewalSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    /** The payload discriminator the Drain picks this Mailable by. */
    public const NOTICE_TYPE = 'renewal_submitted';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSnapshot(array $payload): self
    {
        return new self($payload);
    }

    public function memberName(): string
    {
        return trim($this->payload['member']['first_name'].' '.$this->payload['member']['last_name']);
    }

    /** A Category choice by name: the fee row's, Resign's, or the Member's Category's. */
    public static function categoryLabel(string $code): string
    {
        if ($code === SaveRenewalForm::RESIGN) {
            return __('renewal.form.resign');
        }

        $category = Category::tryFrom($code);

        return $category !== null
            ? __('member.standing.'.$category->value)
            : (FeeCategory::query()->where('code', $code)->value('name') ?? $code);
    }

    public function envelope(): Envelope
    {
        $key = $this->payload['update'] ? 'subject_update' : 'subject';

        return new Envelope(
            from: new Address(config('mail.from.address'), __('notices.renewal_submitted.from')),
            subject: __('notices.renewal_submitted.'.$key, ['member' => $this->memberName(), 'year' => $this->payload['year']]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.renewal-submitted', with: ['memberName' => $this->memberName()]);
    }
}
