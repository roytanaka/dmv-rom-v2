<?php

namespace App\Help;

/**
 * A rendered Help article (ADR-0025): the title read from the source's first
 * level-one heading, and the body as sanitized HTML ready for the page.
 */
final class RenderedArticle
{
    public function __construct(
        public readonly string $title,
        public readonly string $html,
    ) {}
}
