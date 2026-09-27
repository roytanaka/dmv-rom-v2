<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * One screenshot on a Feedback item (#678, ADR-0029 §9): a PNG, JPEG, WebP, or GIF image
 * of at most 5 MB. Each failure names the file and says why, the same words the send
 * dialog shows when it refuses the file before sending.
 *
 * The type comes from getMimeType(), which sniffs the file's contents, so a renamed file
 * is caught. The download serves that sniffed type.
 */
class FeedbackScreenshotImage implements ValidationRule
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $name = ['name' => $value->getClientOriginalName()];

        if (! in_array($value->getMimeType(), self::ALLOWED_MIMES, true)) {
            $fail('feedback.screenshots.error_type')->translate($name);

            return;
        }

        if ($value->getSize() > self::MAX_BYTES) {
            $fail('feedback.screenshots.error_size')->translate($name);
        }
    }
}
