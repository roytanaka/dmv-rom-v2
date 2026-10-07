<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * One file uploaded to a Document library (ADR-0030 §12): one of the allowed types, at most
 * 1.5 GB. The extension must be on the list and the sniffed content type must fit that
 * extension, so a renamed file is caught. Each failure names the file and says why.
 */
class DocumentFile implements ValidationRule
{
    /**
     * Implicit, so a missing file fails here too. The rule stands in for `required`: with
     * `required` on the attribute, Laravel answers a file PHP refused with its own generic
     * "failed to upload" before this rule can name the size limit.
     */
    public bool $implicit = true;

    public const MAX_BYTES = 1536 * 1024 * 1024;

    /**
     * Each allowed extension and the content types the server may sniff for it. Office
     * formats list the generic types older `file` magic reports (`application/zip` for the
     * OOXML formats, the OLE types for the legacy ones).
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/msword'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/msword'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'mp4' => ['video/mp4', 'application/mp4'],
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            $fail('validation.required')->translate();

            return;
        }

        if (! $value instanceof UploadedFile) {
            $fail('validation.file')->translate();

            return;
        }

        $name = ['name' => $value->getClientOriginalName()];

        // PHP refused the upload, so there are no contents to sniff. A file over the host's
        // upload limit gets the size message.
        if (! $value->isValid()) {
            $fail(in_array($value->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'documents.upload.error_size'
                : 'validation.uploaded')->translate($name);

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($value->getMimeType(), self::ALLOWED[$extension] ?? [], true)) {
            $fail('documents.upload.error_type')->translate($name);

            return;
        }

        if ($value->getSize() > self::MAX_BYTES) {
            $fail('documents.upload.error_size')->translate($name);
        }
    }
}
