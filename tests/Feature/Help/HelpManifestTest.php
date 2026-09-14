<?php

use App\Enums\ArticleStatus;
use App\Enums\FrenchState;
use App\Help\HelpArticleRenderer;
use App\Help\HelpManifest;
use Illuminate\Support\Facades\Route;

// Seam B (#517, #518, ADR-0025) — catalogue integrity, in the shape of ChromeCatalogueTest.
// The manifest and the files on disk must agree: a renamed slug, a missing locale
// file, a duplicate slug, or a missing screenshot breaks the build, not the page.
// #518 adds the badge, status, and French-state fields to the same wall.

it('has an English and a French Markdown file for every article', function () {
    foreach ((new HelpManifest)->all() as $article) {
        expect(is_file(resource_path("help/en/{$article->slug}.md")))
            ->toBeTrue("Missing English file for '{$article->slug}'");
        expect(is_file(resource_path("help/fr/{$article->slug}.md")))
            ->toBeTrue("Missing French file for '{$article->slug}'");
    }
});

it('has unique article slugs', function () {
    $slugs = collect((new HelpManifest)->all())->map->slug;

    expect($slugs->duplicates()->all())->toBe([]);
});

it('has an English and a French label for every section', function () {
    foreach ((new HelpManifest)->sections() as $section) {
        expect(__($section->labelKey(), [], 'en'))
            ->not->toBe($section->labelKey(), "Missing English label for section '{$section->value}'");
        expect(__($section->labelKey(), [], 'fr'))
            ->not->toBe($section->labelKey(), "Missing French label for section '{$section->value}'");
    }
});

it('has every referenced screenshot on disk', function () {
    $renderer = app(HelpArticleRenderer::class);
    $missing = [];

    foreach ((new HelpManifest)->all() as $article) {
        foreach (['en', 'fr'] as $locale) {
            foreach ($renderer->referencedImages($article->slug, $locale) as $image) {
                $path = "help-images/{$article->slug}/{$image}";

                if (! is_file(public_path($path))) {
                    $missing[] = $path;
                }
            }
        }
    }

    // A missing screenshot breaks this test, not the page.
    expect($missing)->toBe([]);
});

it('carries a valid status and French state on every entry', function () {
    foreach ((new HelpManifest)->all() as $article) {
        expect($article->status)->toBeInstanceOf(ArticleStatus::class);
        expect($article->fr)->toBeInstanceOf(FrenchState::class);
    }
});

it('ships the two Getting started articles published', function () {
    $published = collect((new HelpManifest)->all())
        ->filter(fn ($article) => $article->status === ArticleStatus::Published)
        ->map->slug
        ->all();

    expect($published)->toContain('getting-started', 'change-your-language');
});

it('requires only known role tokens', function () {
    $used = collect((new HelpManifest)->all())
        ->flatMap(fn ($article) => $article->requires)
        ->unique();

    $unknown = $used->diff(HelpManifest::requirableRoles())->values()->all();

    expect($unknown)->toBe([]);
});

it('maps only route names that exist in the router', function () {
    $unknown = collect((new HelpManifest)->all())
        ->map->route
        ->filter()
        ->reject(fn (string $name) => Route::has($name))
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

it('has an English and a French label for every requirable role, plus the prefix and joiner', function () {
    foreach (HelpManifest::requirableRoles() as $token) {
        $key = "help.required_role.role.{$token}";
        expect(__($key, [], 'en'))->not->toBe($key, "Missing English label for role '{$token}'");
        expect(__($key, [], 'fr'))->not->toBe($key, "Missing French label for role '{$token}'");
    }

    foreach (['help.required_role.prefix', 'help.required_role.or'] as $key) {
        expect(__($key, [], 'en'))->not->toBe($key, "Missing English string for '{$key}'");
        expect(__($key, [], 'fr'))->not->toBe($key, "Missing French string for '{$key}'");
    }
});
