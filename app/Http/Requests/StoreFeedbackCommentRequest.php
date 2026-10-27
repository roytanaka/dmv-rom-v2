<?php

namespace App\Http\Requests;

use App\Policies\FeedbackItemPolicy;
use App\Rules\FeedbackScreenshotImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Commenting on a Feedback item (#677, ADR-0029 §8). A Member may comment on any item they
 * may view ({@see FeedbackItemPolicy::view()}): every item on staging, their own on Beta
 * (ADR-0035 §8). The Tester's typed name, the body, and the images are the whole whitelist.
 *
 * Images (#778) follow the item's screenshot rules ({@see StoreFeedbackItemRequest}): at
 * most 3, each a PNG, JPEG, WebP, or GIF of at most 5 MB. A comment needs text, an image,
 * or both.
 */
class StoreFeedbackCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('feedbackItem')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tester_name' => ['required', 'string', 'max:100'],
            'body' => ['nullable', 'required_without:images', 'string', 'max:5000'],
            'images' => ['array', 'max:'.StoreFeedbackItemRequest::MAX_SCREENSHOTS],
            // No `file` rule, for the reason in StoreFeedbackItemRequest.
            'images.*' => [new FeedbackScreenshotImage],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => __('feedback.comments.error_empty'),
            'images.max' => __('feedback.comments.error_count', ['max' => StoreFeedbackItemRequest::MAX_SCREENSHOTS]),
        ];
    }
}
