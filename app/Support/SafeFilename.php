<?php

namespace App\Support;

/**
 * The safe form of a user's original filename, for the database only (docs/conventions.md
 * § Documents). The disk name is always a UUID; this is the name a download hands back.
 *
 * Letters (accented too), numbers, spaces, and `-_.()'` stay. Control characters, slashes,
 * and every other character go, and a run of dots becomes one dot, so no `..` survives. A
 * reserved Windows name (`CON`, `COM1`, …) gets a `_` suffix. The result is at most 200
 * characters, cut from the name part so the extension stays.
 */
class SafeFilename
{
    public const MAX_LENGTH = 200;

    /**
     * An extension longer than this is not an extension; it stays part of the name.
     */
    private const MAX_EXTENSION_LENGTH = 20;

    private const RESERVED = '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/i';

    public static function from(string $name): string
    {
        $clean = preg_replace("/[^\\p{L}\\p{M}\\p{N} \\-_.()']/u", '', $name) ?? '';
        $clean = preg_replace('/\.{2,}/', '.', $clean) ?? '';
        $clean = preg_replace('/ {2,}/', ' ', $clean) ?? '';
        $clean = ltrim($clean, '. ');

        [$stem, $extension] = self::split($clean);

        $stem = trim($stem, '. ');
        if ($stem === '') {
            $stem = 'file';
        }
        if (preg_match(self::RESERVED, $stem) === 1) {
            $stem .= '_';
        }

        $suffix = $extension === '' ? '' : '.'.$extension;

        return rtrim(mb_substr($stem, 0, self::MAX_LENGTH - mb_strlen($suffix)), ' ').$suffix;
    }

    /**
     * The name part and the extension (without its dot), split at the last dot.
     *
     * @return array{string, string}
     */
    private static function split(string $name): array
    {
        $dot = mb_strrpos($name, '.');
        $extension = $dot === false ? '' : trim(mb_substr($name, $dot + 1));

        if ($dot === false || $extension === '' || mb_strlen($extension) > self::MAX_EXTENSION_LENGTH) {
            return [$name, ''];
        }

        return [mb_substr($name, 0, $dot), $extension];
    }
}
