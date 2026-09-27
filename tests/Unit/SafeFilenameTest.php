<?php

use App\Support\SafeFilename;

/*
 * The Documents convention's rules for an original filename kept in the database
 * (docs/conventions.md § Documents): letters (accented too), numbers, spaces, and
 * `-_.()'` stay; everything else goes, path traversal included; a reserved Windows
 * name gets a suffix; the result is at most 200 characters.
 */

it('keeps a safe filename as it is', function () {
    expect(SafeFilename::from("Rapport d'été (v2)_final-1.png"))->toBe("Rapport d'été (v2)_final-1.png");
});

it('strips characters outside the allowlist', function (string $name, string $expected) {
    expect(SafeFilename::from($name))->toBe($expected);
})->with([
    'punctuation' => ['Écran: capture*2 <draft>?.png', 'Écran capture2 draft.png'],
    'null byte and control characters' => ["bad\0na\x07me.png", 'badname.png'],
    'slashes' => ['a/b\\c.png', 'abc.png'],
    'path traversal' => ['../../etc/passwd', 'etcpasswd'],
    'dot runs' => ['shot..png', 'shot.png'],
    'extra spaces' => ['  two   spaces  .png ', 'two spaces.png'],
]);

it('adds a suffix to a reserved Windows name', function (string $name, string $expected) {
    expect(SafeFilename::from($name))->toBe($expected);
})->with([
    'bare' => ['CON', 'CON_'],
    'with extension' => ['nul.png', 'nul_.png'],
    'numbered' => ['COM1.gif', 'COM1_.gif'],
]);

it('caps the name at 200 characters and keeps the extension', function () {
    $name = SafeFilename::from(str_repeat('é', 300).'.png');

    expect(mb_strlen($name))->toBe(200)
        ->and($name)->toEndWith('é.png');
});

it('falls back to a plain name when nothing is left', function () {
    expect(SafeFilename::from('???'))->toBe('file');
});
