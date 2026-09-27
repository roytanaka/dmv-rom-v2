<?php

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Rules\FeedbackScreenshotImage;
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
 *
 * Screenshots (#678, §9) are optional: at most 3, each a PNG, JPEG, WebP, or GIF image of at
 * most 5 MB, with a message that names the file and the reason.
 */
class StoreFeedbackItemRequest extends FormRequest
{
    public const MAX_SCREENSHOTS = 3;

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
            'screenshots' => ['array', 'max:'.self::MAX_SCREENSHOTS],
            // No `file` rule: with one present, Laravel answers a file PHP refused as too
            // large with its generic "failed to upload" before this rule can name the size.
            'screenshots.*' => [new FeedbackScreenshotImage],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'screenshots.max' => __('feedback.screenshots.error_count', ['max' => self::MAX_SCREENSHOTS]),
        ];
    }
}
