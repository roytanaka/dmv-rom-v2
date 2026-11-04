<?php

namespace App\Http\Requests;

use App\Rules\DocumentFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Member administration saves Renewal settings (#937, ADR-0038 §9). Every field but the show flag,
 * the external addresses and the attachment is required: the reminders read the dates and texts.
 * The Sustaining attachment is optional on each save (none keeps the stored file) and is checked as
 * a Document file is, with a mail-sized cap.
 */
class UpdateRenewalSettingsRequest extends FormRequest
{
    /** The largest attachment, in kilobytes: it goes out with each Sustaining reminder. */
    public const ATTACHMENT_MAX_KB = 10 * 1024;

    public function authorize(): bool
    {
        return $this->user()->can('administer-members');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'show_renewal' => ['boolean'],
            'renewal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'active_send_date' => ['required', 'date_format:Y-m-d'],
            'sustaining_send_date' => ['required', 'date_format:Y-m-d'],
            'active_message_1' => ['required', 'string', 'max:10000'],
            'active_message_2' => ['required', 'string', 'max:10000'],
            'sustaining_message_1' => ['required', 'string', 'max:10000'],
            'sustaining_message_2' => ['required', 'string', 'max:10000'],
            // Missing reads as empty: a multipart body (a save with a file) drops an empty list.
            'external_addresses' => ['nullable', 'array', 'max:10'],
            'external_addresses.*' => ['required', 'email', 'max:255', 'distinct'],
            // DocumentFile is implicit (it stands in for `required`), so it runs only when a file is sent.
            'sustaining_attachment' => $this->hasFile('sustaining_attachment')
                ? [new DocumentFile, 'max:'.self::ATTACHMENT_MAX_KB]
                : ['prohibited'],
        ];
    }
}
