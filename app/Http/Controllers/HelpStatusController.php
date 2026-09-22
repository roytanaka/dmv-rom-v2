<?php

namespace App\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Enums\FrenchState;
use App\Help\HelpArticle;
use App\Help\HelpArticleRenderer;
use App\Help\HelpManifest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Help ledger at /help-status (ADR-0025 §9) — the super-tier "what have I lost
 * track of" view. It reads the same manifest the reader's index reads, but shows the
 * ledger state readers never see: draft status, French state, file and screenshot
 * presence, and every localized page route no article yet maps.
 *
 * Super-tier only, via the `view-help-ledger` gate, which denies everyone but the
 * Gate::before short-circuit. English-only and non-localized, like the Mail status
 * and Design System pages — an operations screen, not member-facing chrome.
 */
class HelpStatusController extends Controller
{
    public function __invoke(HelpManifest $manifest, HelpArticleRenderer $renderer): Response
    {
        Gate::authorize('view-help-ledger');

        $articles = $manifest->all();

        return Inertia::render('HelpStatus', [
            'counts' => [
                'articles' => count($articles),
                'published' => $this->countBy($articles, fn (HelpArticle $a) => $a->status === ArticleStatus::Published),
                'drafts' => $this->countBy($articles, fn (HelpArticle $a) => $a->status === ArticleStatus::Draft),
                'frenchReviewed' => $this->countBy($articles, fn (HelpArticle $a) => $a->fr === FrenchState::Reviewed),
            ],
            'rows' => array_map(fn (HelpArticle $a) => $this->row($a, $renderer), $articles),
            'gaps' => $this->gaps($manifest),
        ]);
    }

    /**
     * One ledger row per manifest entry: its state, its files, and the screenshots it
     * references measured against the ones on disk.
     *
     * @return array<string, mixed>
     */
    private function row(HelpArticle $article, HelpArticleRenderer $renderer): array
    {
        // Screenshots are English-only (ADR-0025 §8), so the English source is the one
        // that references them; count how many of those references have a file on disk.
        $referenced = collect($renderer->referencedImages($article->slug, 'en'))->unique();

        return [
            'slug' => $article->slug,
            'section' => $article->section->value,
            'status' => $article->status->value,
            'fr' => $article->fr->value,
            'requires' => $article->requires,
            'route' => $article->route,
            'enFile' => is_file(resource_path("help/en/{$article->slug}.md")),
            'frFile' => is_file(resource_path("help/fr/{$article->slug}.md")),
            'screenshotsReferenced' => $referenced->count(),
            'screenshotsPresent' => $referenced
                ->filter(fn (string $image) => is_file(public_path("help-images/{$article->slug}/{$image}")))
                ->count(),
        ];
    }

    /**
     * Every named GET route inside a localized group that no manifest entry maps and the
     * exclusion list beside the manifest does not cover (ADR-0025 §9). The `localize`
     * middleware marks the localized group, so a page moving between files still counts;
     * POST-only write seams never appear, the filter keeps GET routes only.
     *
     * @return list<string>
     */
    private function gaps(HelpManifest $manifest): array
    {
        $mapped = collect($manifest->all())->flatMap->mappedRoutes()->unique();
        $excluded = collect(HelpManifest::ledgerRouteExclusions());

        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (RoutingRoute $route) => $route->getName() !== null)
            ->filter(fn (RoutingRoute $route) => in_array('localize', $route->gatherMiddleware(), true))
            ->map->getName()
            ->unique()
            ->reject(fn (string $name) => $mapped->contains($name) || $excluded->contains($name))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<HelpArticle>  $articles
     * @param  callable(HelpArticle): bool  $predicate
     */
    private function countBy(array $articles, callable $predicate): int
    {
        return count(array_filter($articles, $predicate));
    }
}
