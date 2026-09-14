<?php

use App\Help\HelpArticleRenderer;
use App\Help\HelpManifest;

// Seam B (#517, ADR-0025) — catalogue integrity, in the shape of ChromeCatalogueTest.
// The manifest and the files on disk must agree: a renamed slug, a missing locale
// file, a duplicate slug, or a missing screenshot breaks the build, not the page.

it('has an English and a French Markdown file for every article', function () {
    foreach (HelpManifest::all() as $article) {
        expect(is_file(resource_path("help/en/{$article->slug}.md")))
            ->toBeTrue("Missing English file for '{$article->slug}'");
        expect(is_file(resource_path("help/fr/{$article->slug}.md")))
            ->toBeTrue("Missing French file for '{$article->slug}'");
    }
});

it('has unique article slugs', function () {
    $slugs = collect(HelpManifest::all())->map->slug;

    expect($slugs->duplicates()->all())->toBe([]);
});

it('has an English and a French label for every section', function () {
    foreach (HelpManifest::sections() as $section) {
        expect(__($section->labelKey(), [], 'en'))
            ->not->toBe($section->labelKey(), "Missing English label for section '{$section->value}'");
        expect(__($section->labelKey(), [], 'fr'))
            ->not->toBe($section->labelKey(), "Missing French label for section '{$section->value}'");
    }
});

it('has every referenced screenshot on disk', function () {
    $renderer = app(HelpArticleRenderer::class);
    $missing = [];

    foreach (HelpManifest::all() as $article) {
        foreach (['en', 'fr'] as $locale) {
            foreach ($renderer->referencedImages($article->slug, $locale) as $image) {
                $path = "help/{$article->slug}/{$image}";

                if (! is_file(public_path($path))) {
                    $missing[] = $path;
                }
            }
        }
    }

    // Empty in this slice — no article references an image yet — but the check runs,
    // so a later article's missing screenshot breaks this test, not the page.
    expect($missing)->toBe([]);
});
