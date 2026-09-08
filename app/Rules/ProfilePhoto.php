<?php

namespace App\Rules;

use App\Support\ProfilePhotoStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Gate an uploaded profile picture at the application layer — an explicit allowlist,
 * NOT a bet on what the host's ImageMagick happens to support. The production host has
 * no HEIC delegate while the dev container does (verified 2026-07-02), so relying on
 * the decoder to throw would pass HEIC locally and 500 in prod. We reject by
 * format/size here, before the file ever reaches {@see ProfilePhotoStorage}.
 *
 * Each failure carries its own translated message so the member gets an actionable
 * reason (re-save HEIC as JPG / use a supported format / shrink an oversized file)
 * rather than a generic "invalid file".
 */
class ProfilePhoto implements ValidationRule
{
    /**
     * Upload ceiling in bytes (~5 MB) — comfortably above any phone photo, low enough
     * to keep a stray multi-megapixel upload from tying up the request.
     */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * MIME types the host can actually decode and that we re-encode from.
     */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        // getMimeType() sniffs the real file contents (finfo), so a renamed .jpg that
        // is really HEIC is still caught; extension is a fallback for that sniff.
        $mime = $value->getMimeType();
        $extension = strtolower($value->getClientOriginalExtension());

        if (in_array($mime, ['image/heic', 'image/heif'], true) || in_array($extension, ['heic', 'heif'], true)) {
            $fail('settings.profile.photo_error_heic')->translate();

            return;
        }

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            $fail('settings.profile.photo_error_unsupported')->translate();

            return;
        }

        if ($value->getSize() > self::MAX_BYTES) {
            $fail('settings.profile.photo_error_too_large')->translate([
                'max' => self::MAX_BYTES / (1024 * 1024),
            ]);
        }
    }
}
