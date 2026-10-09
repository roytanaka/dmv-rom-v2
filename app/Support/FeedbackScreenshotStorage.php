<?php

namespace App\Support;

use App\Models\FeedbackCommentImage;
use App\Models\FeedbackScreenshot;
use App\Rules\FeedbackScreenshotImage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores and serves Feedback images (ADR-0029 §9): an item's screenshots (#678) and a
 * comment's images (#778). Each file goes to the private `local` disk under a UUID name
 * with no extension, never the Tester's filename as a path (docs/conventions.md
 * § Documents). The row on the feedback connection keeps the safe original filename, the
 * sniffed MIME type, and the size. Deploys keep the private disk (rsync skips `storage/`),
 * as they keep the feedback database (§1).
 */
class FeedbackScreenshotStorage
{
    /**
     * The private-disk directory every Feedback image lands in.
     */
    public const DIRECTORY = 'feedback-screenshots';

    /**
     * @param  HasMany<FeedbackScreenshot|FeedbackCommentImage, *>  $images  `$item->screenshots()` or `$comment->images()`
     * @param  list<UploadedFile>  $files  already validated by {@see FeedbackScreenshotImage}
     */
    public function store(HasMany $images, array $files): void
    {
        foreach ($files as $file) {
            $images->create([
                'original_filename' => SafeFilename::from($file->getClientOriginalName()),
                'storage_path' => $file->storeAs(self::DIRECTORY, (string) Str::uuid(), 'local'),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }

    /**
     * Serve one stored image. Inline for the page and the image viewer (#776), or as an
     * attachment with its original filename when `$download` is set. Only a PNG, JPEG,
     * WebP, or GIF goes inline, with `nosniff` ({@see FileResponse}). The caller has
     * already checked the policy.
     */
    public function response(FeedbackScreenshot|FeedbackCommentImage $image, bool $download): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($image->storage_path), 404);

        return FileResponse::make(
            $image->storage_path,
            $image->original_filename,
            $image->mime_type,
            FileResponse::INLINE_IMAGE_MIMES,
            $download,
        );
    }
}
