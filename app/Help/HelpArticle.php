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
 * to readers. The mapped route is added by a later ticket (ADR-0025).
 */
final class HelpArticle
{
    /**
     * @param  list<string>  $requires  Role tokens the task needs; empty means every Member.
     */
    public function __construct(
        public readonly string $slug,
        public readonly HelpSection $section,
        public readonly bool $isOverview = false,
        public readonly array $requires = [],
        public readonly ArticleStatus $status = ArticleStatus::Published,
        public readonly FrenchState $fr = FrenchState::MachineTranslated,
    ) {}
}
