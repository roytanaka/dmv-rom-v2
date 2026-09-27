<?php

namespace App\Http\Requests;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\FeedbackTriageController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Support-operator sets a Feedback item's status (#679, ADR-0029 §7). Who may do it is
 * checked before this request resolves, by {@see FeedbackTriageController}'s
 * middleware, so a refused Member gets 403 before any validation. The status is the whole
 * whitelist.
 */
class UpdateFeedbackStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FeedbackStatus::class)],
        ];
    }
}
