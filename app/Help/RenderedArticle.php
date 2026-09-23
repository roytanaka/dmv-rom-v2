<?php

namespace App\Help;

/**
 * A rendered Help article (ADR-0025): the title read from the source's first
 * level-one heading, the body as sanitized HTML ready for the page, and the
 * body's level-two headings (text and id) in order.
 */
final class RenderedArticle
{
    public function __construct(
        public readonly string $title,
        public readonly string $html,
        /** @var list<array{text: string, id: string}> */
        public readonly array $headings,
    ) {}
}
