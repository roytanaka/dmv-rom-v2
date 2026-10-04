<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Commenting on a Feedback item (#677, ADR-0029 §8). Any logged-in Member may comment
 * (§4), so `authorize()` needs only the signed-in user the `auth` middleware already
 * guarantees. The Tester's typed name and the body are the whole whitelist.
 */
class StoreFeedbackCommentRequest extends FormRequest
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
            'tester_name' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
