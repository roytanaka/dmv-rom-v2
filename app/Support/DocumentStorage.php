<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The app's one storage layer for uploaded files (ADR-0003, ADR-0030 §13,
 * docs/conventions.md § Documents). A file goes to the private `local` disk under
 * `documents/` with a UUID name and no extension, never the uploader's filename as a path.
 *
 * It knows nothing about the row that points at the file: {@see store()} returns the
 * columns to keep, so the Document library, and later Meetings and Schedules published as
 * files, each write their own row.
 */
class DocumentStorage
{
    /**
     * The private-disk directory every stored file lands in.
     */
    public const DIRECTORY = 'documents';

    private const DISK = 'local';

    /**
     * Store an already validated upload.
     *
     * @return array{original_filename: string, storage_path: string, mime_type: string, size_bytes: int}
     */
    public function store(UploadedFile $file): array
    {
        return [
            'original_filename' => SafeFilename::from($file->getClientOriginalName()),
            'storage_path' => $file->storeAs(self::DIRECTORY, (string) Str::uuid(), self::DISK),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    /**
     * Delete a stored file. A file already gone is not an error.
     */
    public function delete(string $storagePath): void
    {
        Storage::disk(self::DISK)->delete($storagePath);
    }
}
