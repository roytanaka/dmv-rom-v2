<?php

namespace App\Http\Requests;

use App\Enums\AudienceKey;
use App\Enums\ContextType;
use App\Support\Audiences\AudienceResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A Broadcast send (spec #479, ADR-0024 §5, §6). The request names its surface, the Audience
 * to resolve there, the per-Member picker edits, the subject and body, and up to two
 * attachments. It never posts a recipient list, a From alias, or an exclusion — the server
 * re-resolves the Audience from the Group model, the fix for the largest legacy defect.
 *
 * Authorization is deliberately open here: the picker rule is the {@see AudienceResolver}'s
 * to enforce, so a forbidden Audience is a 403 raised as the recipients are resolved, not a
 * blanket refusal before validation. What this class guards is shape — a known context and
 * Audience, integer edit ids, a subject and body, and the attachment ceiling (two files, 10 MB
 * together, ADR-0024 §4).
 */
class SendBroadcastRequest extends FormRequest
{
    /**
     * The most attachments a Broadcast may carry, and their combined ceiling in bytes
     * (ADR-0024 §4): two files, 10 MB together.
     */
    public const MAX_ATTACHMENTS = 2;

    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'context' => ['required', Rule::enum(ContextType::class)],
            // The surface the Audience is resolved for — a Group slug, a Schedule/Shift/Member
            // id; absent for the Directory. Named apart from the email `subject` (ADR-0024 §6).
            'context_subject' => ['nullable', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['required', Rule::enum(AudienceKey::class)],
            'parameter' => ['nullable', 'string'],
            'removed' => ['array'],
            'removed.*' => ['integer'],
            'added' => ['array'],
            'added.*' => ['integer'],
            'attachments' => ['array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file'],
        ];
    }

    /**
     * The combined attachment size is a cross-field rule: two files each under the ceiling can
     * still break it together, so the sum is checked here after the per-file rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $files = array_filter(
                (array) $this->file('attachments', []),
                fn ($file): bool => $file instanceof UploadedFile,
            );

            $total = array_sum(array_map(fn (UploadedFile $file): int => $file->getSize(), $files));

            if ($total > self::MAX_ATTACHMENT_BYTES) {
                $validator->errors()->add('attachments', __('validation.max.file', [
                    'attribute' => 'attachments',
                    'max' => self::MAX_ATTACHMENT_BYTES / 1024,
                ]));
            }
        });
    }
}
