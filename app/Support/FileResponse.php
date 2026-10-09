<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve a stored file from the private `local` disk under its original filename, inline or
 * as an attachment (ADR-0003 as amended for spec #773, docs/conventions.md § Documents).
 * Used by the Document download and the Feedback image routes; each caller checks the
 * policy, and writes any access log, before it calls this.
 *
 * Only a type the caller names goes inline, and never when the request asks to download
 * (`?download=1`): an SVG or HTML file shown inline could run script on this origin. Every
 * response sends `nosniff`, so the browser never second-guesses the stored type.
 */
class FileResponse
{
    /**
     * The image types every browser displays inline: the image viewer's list (#776, #779),
     * and the only types a Feedback image may be. Never an SVG.
     */
    public const INLINE_IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    /**
     * @param  list<string>  $inlineMimes  the types that may go inline
     */
    public static function make(string $path, string $filename, string $mimeType, array $inlineMimes, bool $download): StreamedResponse
    {
        $inline = ! $download && in_array($mimeType, $inlineMimes, true);

        return Storage::disk('local')->response(
            $path,
            $filename,
            ['Content-Type' => $mimeType, 'X-Content-Type-Options' => 'nosniff'],
            $inline ? 'inline' : 'attachment',
        );
    }
}
