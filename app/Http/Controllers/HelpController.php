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
 * logged-in Member. The index lists the manifest's sections and published articles;
 * the article page renders one article's Markdown to a breadcrumb, title, and body.
 * Both are localized (ADR-0008): `/help` ↔ `/fr/aide`, `/help/{article}` ↔
 * `/fr/aide/{article}`, with the article slug the same string in both locales.
 */
class HelpController extends Controller
{
    public function index(HelpManifest $manifest, HelpArticleRenderer $renderer): Response
    {
        $locale = app()->getLocale();

        // Drafts are hidden from the index (ADR-0025 §7); they open only by URL.
        $sections = collect($manifest->publishedSections())->map(fn (HelpSection $section) => [
            'key' => $section->value,
            'labelKey' => $section->labelKey(),
            'articles' => collect($manifest->publishedIn($section))->map(fn (HelpArticle $article) => [
                'slug' => $article->slug,
                'title' => $renderer->title($article->slug, $locale),
                // The Required-role badge's tokens (ADR-0025 §6); empty means every Member.
                'requires' => $article->requires,
                // Path-only (absolute: false) so Inertia navigates client-side, like chromeNav.
                'href' => route('help.show', $article->slug, false),
            ])->all(),
        ])->all();

        return Inertia::render('help/Index', ['sections' => $sections]);
    }

    public function show(string $article, HelpManifest $manifest, HelpArticleRenderer $renderer): Response
    {
        $entry = $manifest->find($article);
        abort_if($entry === null, 404);

        // Drafts render here for any logged-in Member with the URL (ADR-0025 §7).
        $locale = app()->getLocale();
        $rendered = $renderer->render($entry->slug, $locale);
        abort_if($rendered === null, 404);

        return Inertia::render('help/Article', [
            'slug' => $entry->slug,
            'title' => $rendered->title,
            'html' => $rendered->html,
            // The Required-role badge's tokens (ADR-0025 §6); empty means every Member.
            'requires' => $entry->requires,
            'topics' => $this->topics($manifest, $renderer, $entry, $locale),
            // Breadcrumb resolved server-side at the request locale (Help › Section ›
            // Title): the section crumb points at that section's overview article.
            // Hrefs are path-only (absolute: false) so Inertia navigates client-side.
            'breadcrumb' => [
                ['title' => __('help.title'), 'href' => route('help', absolute: false)],
                ['title' => __($entry->section->labelKey()), 'href' => route('help.show', [$manifest->overviewSlug($entry->section)], false)],
                ['title' => $rendered->title, 'href' => route('help.show', [$entry->slug], false)],
            ],
        ]);
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
