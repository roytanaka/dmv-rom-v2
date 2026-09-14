<?php

namespace App\Help;

use App\Enums\HelpSection;
use Illuminate\Support\Collection;

/**
 * The help manifest (ADR-0025) — one ordered list that is both the reader's index
 * and (with later tickets) the engineer's ledger. Sections appear in the order
 * their articles first appear; within a section the overview article leads, then
 * the task articles in listed order. This slice ships only the two Getting started
 * entries.
 */
final class HelpManifest
{
    /**
     * Every article, in display order. The overview of a section is listed first so
     * grouping preserves "overview then tasks" without a second sort.
     *
     * @return list<HelpArticle>
     */
    public static function all(): array
    {
        return [
            new HelpArticle('getting-started', HelpSection::GettingStarted, isOverview: true),
            new HelpArticle('change-your-language', HelpSection::GettingStarted),
        ];
    }

    /** The article for a slug, or null when nothing maps it (a 404 at the seam). */
    public static function find(string $slug): ?HelpArticle
    {
        return self::collect()->firstWhere('slug', $slug);
    }

    /**
     * The sections that have at least one article, in display order.
     *
     * @return list<HelpSection>
     */
    public static function sections(): array
    {
        return self::collect()
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
    public static function articlesIn(HelpSection $section): array
    {
        return self::collect()
            ->filter(fn (HelpArticle $article) => $article->section === $section)
            ->values()
            ->all();
    }

    /** The slug of a section's overview article — the section crumb's destination. */
    public static function overviewSlug(HelpSection $section): ?string
    {
        return self::collect()
            ->first(fn (HelpArticle $article) => $article->section === $section && $article->isOverview)
            ?->slug;
    }

    /** @return Collection<int, HelpArticle> */
    private static function collect(): Collection
    {
        return collect(self::all());
    }
}
