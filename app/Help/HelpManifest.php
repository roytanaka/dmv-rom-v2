<?php

namespace App\Help;

use App\Enums\ArticleStatus;
use App\Enums\HelpSection;
use App\Enums\Role;
use Illuminate\Support\Collection;

/**
 * The help manifest (ADR-0025) — one ordered list that is both the reader's index
 * and (with later tickets) the engineer's ledger. Sections appear in the order
 * their articles first appear; within a section the overview article leads, then
 * the task articles in listed order.
 *
 * Resolved from the container, so a test can bind a fixture set of articles the
 * same way the renderer takes a fixture root. Production uses {@see self::catalog()}.
 */
final class HelpManifest
{
    /**
     * @param  list<HelpArticle>|null  $articles  A fixture set for tests; null uses the real catalog.
     */
    public function __construct(private readonly ?array $articles = null) {}

    /**
     * Every article, in display order. The overview of a section is listed first so
     * grouping preserves "overview then tasks" without a second sort.
     *
     * @return list<HelpArticle>
     */
    public function all(): array
    {
        return $this->articles ?? self::catalog();
    }

    /** The article for a slug, or null when nothing maps it (a 404 at the seam). */
    public function find(string $slug): ?HelpArticle
    {
        return $this->collect()->firstWhere('slug', $slug);
    }

    /**
     * The sections that have at least one article, in display order.
     *
     * @return list<HelpSection>
     */
    public function sections(): array
    {
        return $this->collect()
            ->map(fn (HelpArticle $article) => $article->section)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The articles in a section, in display order (overview first).
     *
     * @return list<HelpArticle>
     */
    public function articlesIn(HelpSection $section): array
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->section === $section)
            ->values()
            ->all();
    }

    /**
     * The sections that have at least one published article — the index's grouping,
     * so a section of only drafts never shows an empty heading.
     *
     * @return list<HelpSection>
     */
    public function publishedSections(): array
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->status === ArticleStatus::Published)
            ->map(fn (HelpArticle $article) => $article->section)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The published articles in a section, in display order — the index omits drafts.
     *
     * @return list<HelpArticle>
     */
    public function publishedIn(HelpSection $section): array
    {
        return $this->collect()
            ->filter(fn (HelpArticle $article) => $article->section === $section && $article->status === ArticleStatus::Published)
            ->values()
            ->all();
    }

    /**
     * The published article a route name maps to, or null. Drives the top-bar "?"
     * (ADR-0025): a match yields the article, anything else the index. Drafts never
     * match, so a route mapped only by a draft resolves to null.
     */
    public function publishedForRoute(string $routeName): ?HelpArticle
    {
        return $this->collect()
            ->first(fn (HelpArticle $article) => $article->route === $routeName
                && $article->status === ArticleStatus::Published);
    }

    /** The slug of a section's overview article — the section crumb's destination. */
    public function overviewSlug(HelpSection $section): ?string
    {
        return $this->collect()
            ->first(fn (HelpArticle $article) => $article->section === $section && $article->isOverview)
            ?->slug;
    }

    /**
     * The role tokens a manifest entry may list in `requires`: every {@see Role}
     * value, plus the two tiers that are not Group roles. The badge and its lang
     * labels draw from this set; the integrity test rejects anything outside it.
     *
     * @return list<string>
     */
    public static function requirableRoles(): array
    {
        return [
            ...array_map(fn (Role $role) => $role->value, Role::cases()),
            'super_tier',
            'support_operator',
        ];
    }

    /**
     * The real catalogue. The two Getting started articles stay draft until their
     * screenshots land (ADR-0025), so the index is empty until the backfill batches.
     *
     * @return list<HelpArticle>
     */
    private static function catalog(): array
    {
        return [
            new HelpArticle('getting-started', HelpSection::GettingStarted, isOverview: true, status: ArticleStatus::Draft, route: 'dashboard'),
            new HelpArticle('change-your-language', HelpSection::GettingStarted, status: ArticleStatus::Draft),
        ];
    }

    /** @return Collection<int, HelpArticle> */
    private function collect(): Collection
    {
        return collect($this->all());
    }
}
