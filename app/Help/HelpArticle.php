<?php

namespace App\Help;

use App\Enums\HelpSection;

/**
 * One entry in the help manifest (ADR-0025). The slug is a stable identifier — the
 * same string in both locales and the name of the Markdown file and public
 * screenshot folder. The section places it in the index; `isOverview` marks the one
 * article that leads its section. The article's title is not stored here: it is the
 * first level-one heading of the Markdown file, read at render time.
 *
 * The manifest ships only slug, section, and overview flag in this slice; role,
 * status, French state, and mapped route are added by later tickets (ADR-0025).
 */
final class HelpArticle
{
    public function __construct(
        public readonly string $slug,
        public readonly HelpSection $section,
        public readonly bool $isOverview = false,
    ) {}
}
