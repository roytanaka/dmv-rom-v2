<?php

namespace App\Rules;

use App\Support\FileResponse;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * One screenshot on a Feedback item (#678, ADR-0029 §9): a PNG, JPEG, WebP, or GIF image
 * of at most 5 MB. Each failure names the file and says why, the same words the send
 * dialog shows when it refuses the file before sending.
 *
 * The type comes from getMimeType(), which sniffs the file's contents, so a renamed file
 * is caught. The download serves that sniffed type. The allowed types are the ones the
 * image viewer shows inline ({@see FileResponse::INLINE_IMAGE_MIMES}).
 */
class FeedbackScreenshotImage implements ValidationRule
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('validation.file')->translate();

            return;
        }

        $name = ['name' => $value->getClientOriginalName()];

        // PHP refused the upload, so there are no contents to sniff. A file over the host's
        // upload limit gets the size message.
        if (! $value->isValid()) {
            $fail(in_array($value->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'feedback.screenshots.error_size'
                : 'validation.uploaded')->translate($name);

            return;
        }

        if (! in_array($value->getMimeType(), FileResponse::INLINE_IMAGE_MIMES, true)) {
            $fail('feedback.screenshots.error_type')->translate($name);

            return;
        }

        if ($value->getSize() > self::MAX_BYTES) {
            $fail('feedback.screenshots.error_size')->translate($name);
        }
    }
}
