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
        ->toContain('<img src="/help/figure-fixture/01.png"')
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
