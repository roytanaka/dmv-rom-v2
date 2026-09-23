<?php

namespace App\Http\Controllers;

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
            $tasks = $published->reject(fn (HelpArticle $article) => $article->isOverview)->values();

            return [$section->value => [
                'key' => $section->value,
                'labelKey' => $section->labelKey(),
                'overview' => $overview === null ? null : $link($overview),
                'lead' => $published->contains('isOverview', true) ? $renderer->lead($overview, $locale) : null,
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
}
