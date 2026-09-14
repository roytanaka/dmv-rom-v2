<?php

namespace App\Help;

use App\Enums\ArticleStatus;
use App\Enums\FrenchState;
use App\Enums\HelpSection;
use App\Enums\Role;

/**
 * One entry in the help manifest (ADR-0025). The slug is a stable identifier — the
 * same string in both locales and the name of the Markdown file and public
 * screenshot folder. The section places it in the index; `isOverview` marks the one
 * article that leads its section. The article's title is not stored here: it is the
 * first level-one heading of the Markdown file, read at render time.
 *
 * `requires` is the article's Required-role badge (ADR-0025 §6): each string is a
 * role token — a {@see Role} value, or `super_tier` / `support_operator`.
 * An empty array means every Member. `status` hides drafts from the index; `fr`
 * tracks whether the French copy has been reviewed. Both are ledger state, invisible
 * to readers.
 *
 * `route` is the name of the page route this article documents (ADR-0025). It maps
 * the top-bar "?" to the article for the page the Member is on. Parameterized routes
 * match on name alone, so one article covers every instance (a Group's schedule page
 * for every Group). Null means the article maps to no page; a draft never matches.
 */
final class HelpArticle
{
    /**
     * @param  list<string>  $requires  Role tokens the task needs; empty means every Member.
     * @param  string|null  $route  The route name this article documents, or null.
     */
    public function __construct(
        public readonly string $slug,
        public readonly HelpSection $section,
        public readonly bool $isOverview = false,
        public readonly array $requires = [],
        public readonly ArticleStatus $status = ArticleStatus::Published,
        public readonly FrenchState $fr = FrenchState::MachineTranslated,
        public readonly ?string $route = null,
    ) {}
}
