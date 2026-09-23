<?php

namespace App\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Enums\HelpSection;
use App\Help\HelpArticle;
use App\Help\HelpArticleRenderer;
use App\Help\HelpManifest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The help centre (ADR-0025, PRD #516) — in-app Markdown articles, chrome for every
 * logged-in Member. The index shows a Start here box and one card per section;
 * the article page renders one article's Markdown to a breadcrumb, title, and body.
 * Both are localized (ADR-0008): `/help` ↔ `/fr/aide`, `/help/{article}` ↔
 * `/fr/aide/{article}`, with the article slug the same string in both locales.
 */
class HelpController extends Controller
{
    public function index(HelpManifest $manifest, HelpArticleRenderer $renderer): Response
    {
        $locale = app()->getLocale();

        // Path-only (absolute: false) so Inertia navigates client-side, like chromeNav.
        $link = fn (string $slug) => [
            'title' => $renderer->title($slug, $locale),
            'href' => route('help.show', $slug, false),
        ];

        // One card per section (#623): its overview, the overview's lead line as the
        // summary, up to three task articles, and the task count. Drafts are hidden
        // from the index (ADR-0025 §7), so a draft overview gives no lead; its title
        // still opens the overview by URL, as the section crumb does.
        $cards = collect($manifest->publishedSections())->mapWithKeys(function (HelpSection $section) use ($manifest, $renderer, $locale, $link) {
            $overview = $manifest->overviewSlug($section);
            $published = collect($manifest->publishedIn($section));
            $overviewIsPublished = $published->contains('isOverview', true);
            $tasks = $published->reject(fn (HelpArticle $article) => $article->isOverview)->values();

            return [$section->value => [
                'key' => $section->value,
                'labelKey' => $section->labelKey(),
                'overview' => $overview === null ? null : $link($overview),
                'lead' => $overviewIsPublished ? $renderer->lead($overview, $locale) : null,
                'articles' => $tasks->take(3)->map(fn (HelpArticle $article) => [
                    'slug' => $article->slug,
                    ...$link($article->slug),
                    // The Required-role badge's tokens (ADR-0025 §6); empty means every Member.
                    'requires' => $article->requires,
                ])->all(),
                'count' => $tasks->count(),
            ]];
        });

        // Getting started feeds the "Start here" box, not a card.
        return Inertia::render('help/Index', [
            'startHere' => $cards->get(HelpSection::GettingStarted->value),
            'sections' => $cards->except(HelpSection::GettingStarted->value)->values()->all(),
        ]);
    }

    public function show(string $article, HelpManifest $manifest, HelpArticleRenderer $renderer): Response
    {
        $entry = $manifest->find($article);
        abort_if($entry === null, 404);

        // Drafts render here for any logged-in Member with the URL (ADR-0025 §7).
        $locale = app()->getLocale();
        $rendered = $renderer->render($entry->slug, $locale);
        abort_if($rendered === null, 404);

        // Previous and Next (#620): the published neighbours in display order, or null.
        [$previous, $next] = $manifest->publishedNeighbours($entry->slug);
        $neighbour = fn (?HelpArticle $article) => $article === null ? null : [
            'title' => $renderer->title($article->slug, $locale),
            'href' => route('help.show', $article->slug, false),
            'section' => __($article->section->labelKey()),
        ];

        return Inertia::render('help/Article', [
            'slug' => $entry->slug,
            'title' => $rendered->title,
            'html' => $rendered->html,
            // "On this page" (#621): the level-two headings (text and id), in order.
            'headings' => $rendered->headings,
            // The Required-role badge's tokens (ADR-0025 §6); empty means every Member.
            'requires' => $entry->requires,
            'topics' => $this->topics($manifest, $renderer, $entry, $locale),
            'sectionArticles' => $entry->isOverview ? $this->sectionArticles($manifest, $renderer, $entry, $locale) : null,
            // Breadcrumb resolved server-side at the request locale (Help › Section ›
            // Title): the section crumb points at that section's overview article.
            // Hrefs are path-only (absolute: false) so Inertia navigates client-side.
            'breadcrumb' => [
                ['title' => __('help.title'), 'href' => route('help', absolute: false)],
                ['title' => __($entry->section->labelKey()), 'href' => route('help.show', [$manifest->overviewSlug($entry->section)], false)],
                ['title' => $rendered->title, 'href' => route('help.show', [$entry->slug], false)],
            ],
            'previous' => $neighbour($previous),
            'next' => $neighbour($next),
        ]);
    }

    /**
     * The list at the end of an overview (#622): the section's published task articles,
     * grouped by Required role the same way as the Help topics list.
     *
     * @return array{label: string, groups: list<array<string, mixed>>}
     */
    private function sectionArticles(HelpManifest $manifest, HelpArticleRenderer $renderer, HelpArticle $overview, string $locale): array
    {
        return [
            'label' => __($overview->section->labelKey()),
            'groups' => array_map(fn (array $group) => [
                'requires' => $group['requires'],
                'articles' => array_map(fn (HelpArticle $article) => [
                    'slug' => $article->slug,
                    'title' => $renderer->title($article->slug, $locale),
                    'href' => route('help.show', $article->slug, false),
                ], $group['articles']),
            ], $manifest->publishedTasksByRole($overview->section)),
        ];
    }

    /**
     * The Help topics list (#619): the manifest's tree with titles and hrefs resolved at
     * the request locale. A draft is never in the tree, so opening one by URL marks
     * nothing current.
     *
     * @return list<array<string, mixed>>
     */
    private function topics(HelpManifest $manifest, HelpArticleRenderer $renderer, HelpArticle $current, string $locale): array
    {
        $link = fn (HelpArticle $article) => [
            'title' => $renderer->title($article->slug, $locale),
            'href' => route('help.show', $article->slug, false),
            'current' => $article->slug === $current->slug,
        ];

        return array_map(fn (array $topic) => [
            'key' => $topic['section']->value,
            'label' => __($topic['section']->labelKey()),
            'current' => $current->status === ArticleStatus::Published && $topic['section'] === $current->section,
            'overview' => $topic['overview'] === null ? null : $link($topic['overview']),
            'groups' => array_map(fn (array $group) => [
                'requires' => $group['requires'],
                'articles' => array_map(fn (HelpArticle $article) => ['slug' => $article->slug, ...$link($article)], $group['articles']),
            ], $topic['groups']),
        ], $manifest->topics());
    }
}
