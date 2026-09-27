<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Support-operator deletes a Feedback item with its comments and screenshots (#679,
 * ADR-0029 §7). Authorized structurally here (ADR-0017 §4) by
 * {@see Member::isSupportOperator()}, read directly and never through a Gate (§5). There is
 * no body to validate.
 */
class DeleteFeedbackItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSupportOperator();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
