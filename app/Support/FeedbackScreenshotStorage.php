<?php

namespace App\Support;

use App\Models\FeedbackItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores a Feedback item's screenshots (#678, ADR-0029 §9) by the Documents convention
 * (docs/conventions.md § Documents): each file goes to the private `local` disk under a
 * UUID name with no extension, never the Tester's filename as a path. The row on the
 * feedback connection keeps the safe original filename, the sniffed MIME type, and the
 * size. Deploys keep the private disk (rsync skips `storage/`), as they keep the feedback
 * database (§1).
 */
class FeedbackScreenshotStorage
{
    /**
     * The private-disk directory every Feedback screenshot lands in.
     */
    public const DIRECTORY = 'feedback-screenshots';

    /**
     * @param  list<UploadedFile>  $files  already validated as screenshots
     */
    public function store(FeedbackItem $item, array $files): void
    {
        foreach ($files as $file) {
            $item->screenshots()->create([
                'original_filename' => SafeFilename::from($file->getClientOriginalName()),
                'storage_path' => $file->storeAs(self::DIRECTORY, (string) Str::uuid(), 'local'),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }
}
