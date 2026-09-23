<?php

use App\Help\HelpArticleRenderer;

// The Markdown renderer (#517, ADR-0025) exercised against committed fixtures, so the
// figure and sanitization behaviour is proved without a real article referencing an
// image yet. Fixtures live under tests/Fixtures/help/<locale>/<slug>.md.
function fixtureRenderer(): HelpArticleRenderer
{
    return new HelpArticleRenderer(base_path('tests/Fixtures/help'));
}

it('reads the title from the first level-one heading and keeps it out of the body', function () {
    $rendered = fixtureRenderer()->render('raw-html-fixture', 'en');

    expect($rendered->title)->toBe('Raw HTML fixture')
        ->and($rendered->html)->not->toContain('Raw HTML fixture');
});

it('renders a screenshot as a captioned figure pointing at the article public folder', function () {
    $rendered = fixtureRenderer()->render('figure-fixture', 'en');

    expect($rendered->html)
        ->toContain('<figure>')
        ->toContain('<img src="/help-images/figure-fixture/01.png"')
        ->toContain('<figcaption>The language menu open in the top bar</figcaption>')
        // The lone image is unwrapped from its paragraph so the figure is a block.
        ->not->toContain('<p><figure>');
});

it('strips raw HTML from the source so it never reaches the page', function () {
    $rendered = fixtureRenderer()->render('raw-html-fixture', 'en');

    expect($rendered->html)
        ->not->toContain('<script>')
        ->not->toContain('class="danger"')
        ->toContain('This paragraph is safe body text.');
});

it('lists the screenshots an article references', function () {
    expect(fixtureRenderer()->referencedImages('figure-fixture', 'en'))->toBe(['01.png']);
});

it('falls back to the English file when the locale file is missing', function () {
    expect(fixtureRenderer()->render('figure-fixture', 'fr')?->title)->toBe('Figure fixture');
});

it('returns null for a slug with no source file', function () {
    expect(fixtureRenderer()->render('does-not-exist', 'en'))->toBeNull();
});

it('renders a Tip blockquote as a Tip callout in both locales', function (string $locale, string $label) {
    $html = fixtureRenderer()->render('callout-fixture', $locale)->html;

    expect($html)->toContain("<div class=\"callout\" data-callout=\"tip\">\n<p><strong>{$label}</strong>");
})->with([
    'English' => ['en', 'Tip:'],
    'French' => ['fr', 'Astuce :'],
]);

it('renders a Note blockquote as a Note callout in both locales', function (string $locale, string $label) {
    $html = fixtureRenderer()->render('callout-fixture', $locale)->html;

    expect($html)->toContain("<div class=\"callout\" data-callout=\"note\">\n<p><strong>{$label}</strong>");
})->with([
    'English' => ['en', 'Note:'],
    'French' => ['fr', 'Note :'],
]);

it('renders a blockquote without a Tip or Note label as a plain blockquote', function () {
    $html = fixtureRenderer()->render('callout-fixture', 'en')->html;

    expect($html)
        ->toContain("<blockquote>\n<p>A plain quote with no label.</p>\n</blockquote>")
        ->and(substr_count($html, 'class="callout"'))->toBe(2);
});

it('renders a bare-slug link as the article help URL in the request language', function (string $locale, string $href) {
    $html = fixtureRenderer()->render('link-fixture', $locale)->html;

    expect($html)->toContain("<a href=\"{$href}\">");
})->with([
    'English' => ['en', '/help/sign-up-for-a-shift'],
    'French' => ['fr', '/fr/aide/sign-up-for-a-shift'],
]);

it('leaves anchor, absolute-path and full-URL links as they are', function () {
    $html = fixtureRenderer()->render('link-fixture', 'en')->html;

    expect($html)
        ->toContain('<a href="#steps">')
        ->toContain('<a href="/directory">')
        ->toContain('<a href="https://www.rom.on.ca">');
});

it('lists the article slugs an article links to', function () {
    expect(fixtureRenderer()->referencedArticles('link-fixture', 'en'))->toBe(['sign-up-for-a-shift']);
});

it('gives each level-two heading a stable id in both locales', function (string $locale, array $ids) {
    $html = fixtureRenderer()->render('headings-fixture', $locale)->html;

    foreach ($ids as $id) {
        expect($html)->toContain("<h2 id=\"{$id}\">");
    }

    // Only level-two headings get an id.
    expect($html)->toContain('<h3>');
})->with([
    'English' => ['en', ['before-you-start', 'open-the-shifts-tab', 'fix-a-mistake', 'fix-a-mistake-2']],
    'French' => ['fr', ['avant-de-commencer', 'ouvrir-longlet-quarts', 'corriger-une-erreur', 'corriger-une-erreur-2']],
]);

it('lists the level-two headings in order with their text and id', function () {
    expect(fixtureRenderer()->render('headings-fixture', 'en')->headings)->toBe([
        ['text' => 'Before you start', 'id' => 'before-you-start'],
        ['text' => 'Open the Shifts tab', 'id' => 'open-the-shifts-tab'],
        ['text' => 'Fix a mistake', 'id' => 'fix-a-mistake'],
        ['text' => 'Fix a mistake', 'id' => 'fix-a-mistake-2'],
    ]);
});

it('lists no headings for an article without level-two headings', function () {
    expect(fixtureRenderer()->render('open-task', 'en')->headings)->toBe([]);
});

// #623: the lead line — the first body paragraph as plain text, the index card's summary.
it('reads the lead line as the first body paragraph in plain text', function (string $locale, string $lead) {
    expect(fixtureRenderer()->lead('link-fixture', $locale))->toBe($lead);
})->with([
    'English' => ['en', 'Read Sign up for a shift next.'],
    'French' => ['fr', "Lisez ensuite S'inscrire à un quart."],
]);

it('skips headings and screenshots to reach the lead line', function () {
    expect(fixtureRenderer()->lead('headings-fixture', 'en'))->toBe('A short lead line.')
        ->and(fixtureRenderer()->lead('figure-fixture', 'en'))->toBe('This article references a screenshot.');
});

it('reads no lead line from an article with no body paragraph outside a blockquote', function () {
    expect(fixtureRenderer()->lead('callout-fixture', 'en'))->toBeNull()
        ->and(fixtureRenderer()->lead('does-not-exist', 'en'))->toBeNull();
});
