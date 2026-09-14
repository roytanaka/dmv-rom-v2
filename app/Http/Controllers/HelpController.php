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
 * logged-in Member. The index lists the manifest's sections and articles; the
 * article page renders one article's Markdown to a breadcrumb, title, and body. Both
 * are localized (ADR-0008): `/help` ↔ `/fr/aide`, `/help/{article}` ↔
 * `/fr/aide/{article}`, with the article slug the same string in both locales.
 */
class HelpController extends Controller
{
    public function index(HelpArticleRenderer $renderer): Response
    {
        $locale = app()->getLocale();

        $sections = collect(HelpManifest::sections())->map(fn (HelpSection $section) => [
            'key' => $section->value,
            'labelKey' => $section->labelKey(),
            'articles' => collect(HelpManifest::articlesIn($section))->map(fn (HelpArticle $article) => [
                'slug' => $article->slug,
                'title' => $renderer->title($article->slug, $locale),
                // Path-only (absolute: false) so Inertia navigates client-side, like chromeNav.
                'href' => route('help.show', $article->slug, false),
            ])->all(),
        ])->all();

        return Inertia::render('help/Index', ['sections' => $sections]);
    }

    public function show(string $article, HelpArticleRenderer $renderer): Response
    {
        $entry = HelpManifest::find($article);
        abort_if($entry === null, 404);

        $rendered = $renderer->render($entry->slug, app()->getLocale());
        abort_if($rendered === null, 404);

        return Inertia::render('help/Article', [
            'slug' => $entry->slug,
            'title' => $rendered->title,
            'html' => $rendered->html,
            // Breadcrumb resolved server-side at the request locale (Help › Section ›
            // Title): the section crumb points at that section's overview article.
            // Hrefs are path-only (absolute: false) so Inertia navigates client-side.
            'breadcrumb' => [
                ['title' => __('help.title'), 'href' => route('help', absolute: false)],
                ['title' => __($entry->section->labelKey()), 'href' => route('help.show', [HelpManifest::overviewSlug($entry->section)], false)],
                ['title' => $rendered->title, 'href' => route('help.show', [$entry->slug], false)],
            ],
        ]);
    }
}
