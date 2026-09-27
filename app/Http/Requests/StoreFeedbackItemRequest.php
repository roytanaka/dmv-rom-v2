<?php

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sending a Feedback item (#676, ADR-0029). Any logged-in Member may send one (§4), so
 * `authorize()` needs only the signed-in user the `auth` middleware already guarantees.
 *
 * `rules()` is a whitelist of what the Tester types (name, type, message) and the client
 * context only the browser knows (page URL, user agent, viewport). The context is optional
 * and length-capped, and the server trusts it for display only. Status and every
 * server-captured field (route, locale, Member, impersonator, version) are not in the
 * whitelist, so a request cannot set them.
 */
class StoreFeedbackItemRequest extends FormRequest
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
            'type' => ['required', Rule::enum(FeedbackType::class)],
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'user_agent' => ['nullable', 'string', 'max:512'],
            'viewport_width' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'viewport_height' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
