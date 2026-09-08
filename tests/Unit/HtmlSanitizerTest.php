<?php

use App\Support\Mail\HtmlSanitizer;

/*
 * The composer body sanitizer (#488, ADR-0024 §6, §12.6). A Broadcast body is rich text an
 * officer types, stored against an allowlist: allowed formatting survives, active content and
 * unsafe URLs do not. Legacy shipped unsanitized bodies; this port does not inherit that.
 */

beforeEach(function () {
    $this->sanitizer = new HtmlSanitizer;
});

it('keeps allowed formatting tags', function () {
    $html = '<p>Hello <strong>there</strong> and <em>welcome</em></p><ul><li>one</li></ul>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('drops a script tag and its contents', function () {
    $clean = $this->sanitizer->sanitize('<p>hi</p><script>alert(1)</script>');

    expect($clean)->toBe('<p>hi</p>')
        ->and($clean)->not->toContain('alert');
});

it('strips event-handler and style attributes but keeps the tag', function () {
    $clean = $this->sanitizer->sanitize('<p onclick="steal()" style="color:red">text</p>');

    expect($clean)->toBe('<p>text</p>');
});

it('unwraps a disallowed tag but keeps its text', function () {
    $clean = $this->sanitizer->sanitize('<div>kept <span>inner</span></div>');

    expect($clean)->toBe('kept <span>inner</span>');
});

it('keeps a safe href but drops a javascript: one', function () {
    expect($this->sanitizer->sanitize('<a href="https://ex.test">ok</a>'))
        ->toBe('<a href="https://ex.test">ok</a>');

    expect($this->sanitizer->sanitize('<a href="javascript:steal()">x</a>'))
        ->toBe('<a>x</a>');
});

it('returns an empty string for empty input', function () {
    expect($this->sanitizer->sanitize('   '))->toBe('');
});
