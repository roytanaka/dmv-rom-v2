<?php

namespace App\Support\Mail;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Holds a Broadcast's attachments for the life of the queue (spec #479, ADR-0024 §4). A send
 * writes each uploaded file to the private disk under a random UUID name — never the user's
 * filename as a path, a hard rule — and records the manifest the composer showed (the original
 * name and the size) beside the private path. The Drain attaches from those paths and, once the
 * sender's copy has gone out, {@see delete()}s them: attachments are never a permanent store.
 */
class BroadcastAttachmentStorage
{
    /**
     * The private-disk directory every Broadcast attachment lands in.
     */
    public const DIRECTORY = 'broadcast-attachments';

    /**
     * Store the uploaded files and return the manifest to persist on the Broadcast row: one
     * entry per file with the display `name`, the `size` in bytes, and the private-disk `path`.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{name: string, size: int, path: string}>
     */
    public function store(array $files): array
    {
        return array_map(function (UploadedFile $file): array {
            $path = $file->storeAs(
                self::DIRECTORY,
                Str::uuid().'.'.$file->getClientOriginalExtension(),
                'local',
            );

            return [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'path' => $path,
            ];
        }, array_values($files));
    }

    /**
     * Delete the files behind a manifest — called once the sender's copy has been sent, so the
     * private disk does not keep a copy of every Broadcast ever sent.
     *
     * @param  list<array{name: string, size: int, path: string}>  $attachments
     */
    public function delete(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            Storage::disk('local')->delete($attachment['path']);
        }
    }
}
