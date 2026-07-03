<?php

namespace App\Support;

use App\Rules\ProfilePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * The one place that turns an uploaded profile picture into a stored file, shared by
 * the self-service upload (#233), the replace/remove lifecycle (#234), and the demo
 * seeder (#235) so all three land identical files — public disk, random UUID name,
 * square WebP.
 *
 * The photo lane is deliberately PUBLIC (webserver-served via `storage:link`), unlike
 * the gated Documents lane (ADR-0003 + amendment): a face is opt-in-by-uploading and
 * needs no per-fetch authorization, so it trades the controller round-trip for a plain
 * static URL. Unguessable UUID names + no directory indexing keep it non-enumerable.
 */
class ProfilePhotoStorage
{
    /**
     * The public-disk directory that holds every processed profile photo.
     */
    public const DIRECTORY = 'profile-photos';

    /**
     * Longest edge of the stored square, in pixels. One canonical size renders crisply
     * everywhere (profile header, directory row, roster) without per-surface variants.
     */
    private const SIZE = 512;

    /**
     * Process and store an uploaded image, returning its public-disk-relative path.
     *
     * The image is center-cropped to a {@see SIZE}px square, re-encoded to WebP (which
     * drops the source EXIF — no GPS or camera metadata survives), and written under a
     * random UUID name. Callers persist the returned path to `members.photo_path`.
     *
     * Format/size validation (reject HEIC, cap ~5 MB) happens upstream in the form
     * request ({@see ProfilePhoto}); by here the file is a known-good raster.
     */
    public function store(UploadedFile $file): string
    {
        $webp = (string) (new ImageManager(new Driver))
            ->decodePath($file->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->encode(new WebpEncoder(quality: 82));

        $path = self::DIRECTORY.'/'.Str::uuid()->toString().'.webp';

        Storage::disk('public')->put($path, $webp);

        return $path;
    }

    /**
     * Unlink a previously stored photo from the public disk.
     *
     * Nulling `members.photo_path` alone would strand the bytes on disk under their
     * UUID URL; this removes them together. A null path is a no-op.
     */
    public function delete(?string $path): void
    {
        if ($path === null) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
