<?php

namespace App\Support\Mail;

/**
 * User text in a markdown email, made inert (spec #856 review). Laravel's `{{ }}` escapes HTML
 * but not markdown, so a typed `[x](https://…)` or `![](https://…)` renders as a live link or
 * image. {@see inert()} writes every character that can start markdown or HTML as a character
 * reference. CommonMark never reads a reference as markup, so the text shows as typed. The
 * plain-text part decodes the references back.
 *
 * Echo the result raw (`{!! !!}`): it is already HTML-safe.
 */
class MarkdownText
{
    private const REFERENCES = [
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
        '"' => '&quot;',
        "'" => '&#39;',
        '!' => '&#33;',
        '#' => '&#35;',
        '(' => '&#40;',
        ')' => '&#41;',
        '*' => '&#42;',
        '+' => '&#43;',
        '-' => '&#45;',
        '.' => '&#46;',
        '=' => '&#61;',
        '[' => '&#91;',
        '\\' => '&#92;',
        ']' => '&#93;',
        '_' => '&#95;',
        '`' => '&#96;',
        '{' => '&#123;',
        '|' => '&#124;',
        '}' => '&#125;',
        '~' => '&#126;',
    ];

    public static function inert(?string $text): string
    {
        return strtr($text ?? '', self::REFERENCES);
    }
}
