<?php

namespace App\Http\Requests;

use App\Enums\FeedbackStatus;
use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Support-operator sets a Feedback item's status (#679, ADR-0029 §7). The mutation is
 * authorized structurally here (ADR-0017 §4) by {@see Member::isSupportOperator()}, read
 * directly and never through a Gate (§5), so a super-tier executive is refused. `authorize()`
 * runs before `rules()`, so a refused Member gets 403 before any validation. The status is
 * the whole whitelist.
 */
class UpdateFeedbackStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSupportOperator();
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
