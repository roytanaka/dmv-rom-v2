<?php

use App\Enums\ArticleStatus;
use App\Enums\FrenchState;
use App\Enums\HelpSection;
use App\Help\HelpArticle;
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

it('gives every overview a title that differs from its section label, so no breadcrumb repeats', function () {
    // #565: a breadcrumb reads "Help › Getting started › <title>". An overview whose
    // title equals its section label shows the same crumb twice. Guard both locales.
    $renderer = app(HelpArticleRenderer::class);

    foreach ((new HelpManifest)->all() as $article) {
        if (! $article->isOverview) {
            continue;
        }

        foreach (['en', 'fr'] as $locale) {
            $title = $renderer->title($article->slug, $locale);
            $label = __($article->section->labelKey(), [], $locale);

            expect($title)->not->toBe(
                $label,
                "Overview '{$article->slug}' ({$locale}) repeats the section label '{$label}'"
            );
        }
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

it('links only to articles in the manifest', function () {
    // #618: a "What next" link is `[Title](slug)`. A renamed or removed article
    // breaks this test, not the link.
    $renderer = app(HelpArticleRenderer::class);
    $manifest = new HelpManifest;
    $unknown = [];

    foreach ($manifest->all() as $article) {
        foreach (['en', 'fr'] as $locale) {
            foreach ($renderer->referencedArticles($article->slug, $locale) as $target) {
                if ($manifest->find($target) === null) {
                    $unknown[] = "{$locale}/{$article->slug}.md → {$target}";
                }
            }
        }
    }

    expect($unknown)->toBe([]);
});

it('links the articles it names instead of naming them in italics or quotes', function () {
    // #618: an article name in an article is a link, so the Member opens it in one step.
    // Italics are left for nothing else, so any italic span is an unlinked name.
    $renderer = app(HelpArticleRenderer::class);
    $articles = (new HelpManifest)->all();
    $unlinked = [];

    foreach ($articles as $article) {
        foreach (['en', 'fr'] as $locale) {
            $source = file_get_contents(resource_path("help/{$locale}/{$article->slug}.md"));

            preg_match_all('/(?<![\w\\\\])_(?=\S)[^_\n]+(?<=\S)_(?!\w)/u', $source, $italics);
            foreach ($italics[0] as $italic) {
                $unlinked[] = "{$locale}/{$article->slug}.md: {$italic}";
            }

            foreach ($articles as $named) {
                $title = $renderer->title($named->slug, $locale);

                foreach (["\"{$title}\"", "« {$title} »"] as $quoted) {
                    if (str_contains($source, $quoted)) {
                        $unlinked[] = "{$locale}/{$article->slug}.md: {$quoted}";
                    }
                }
            }
        }
    }

    expect($unlinked)->toBe([]);
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

it('offers records as a requirable role, since member administration is not a Group Role', function () {
    // The no-email flag is a Records stewardship decision (#564, #483, ADR-0024 §9), not
    // a Group Role. It rides the badge as its own tier, beside super_tier / support_operator.
    expect(HelpManifest::requirableRoles())->toContain('records');
});

it('lists the no-email-flag article as a Records draft in Emailing, mapped to the member page', function () {
    // #564: a Records-only task article for the no-email switch on a Member's profile.
    $article = (new HelpManifest)->find('set-the-no-email-flag');

    expect($article)->not->toBeNull();
    expect($article->section)->toBe(HelpSection::Emailing);
    expect($article->requires)->toBe(['records']);
    expect($article->route)->toBe('members.show');
    expect($article->status)->toBe(ArticleStatus::Draft);
    expect($article->fr)->toBe(FrenchState::MachineTranslated);
});

it('lists the write-your-own-shift article as a no-role Scheduling draft, mapped to the schedule page', function () {
    // #590, ADR-0026 §1: a self-serve Group's Member writes their own Shift on the
    // Group Scheduling page. No required role — any Member of a self-serve Group.
    $article = (new HelpManifest)->find('write-your-own-shift');

    expect($article)->not->toBeNull();
    expect($article->section)->toBe(HelpSection::Scheduling);
    expect($article->requires)->toBe([]);
    expect($article->route)->toBe('groups.scheduling.show');
    expect($article->status)->toBe(ArticleStatus::Draft);
    expect($article->fr)->toBe(FrenchState::MachineTranslated);
});

it('lists the objects-and-off-site-stations article as a Scheduler/Chair Scheduling draft, mapped to the Group page', function () {
    // #590, ADR-0026 §3 and §4: a Scheduler maintains Objects and marks a kind off-site.
    // #608, ADR-0027 §2: the cards live on the Group Settings tab, a section of the Group page.
    $article = (new HelpManifest)->find('objects-and-off-site-stations');

    expect($article)->not->toBeNull();
    expect($article->section)->toBe(HelpSection::Scheduling);
    expect($article->requires)->toBe(['scheduler', 'chair']);
    expect($article->route)->toBe('groups.show');
    expect($article->status)->toBe(ArticleStatus::Draft);
    expect($article->fr)->toBe(FrenchState::MachineTranslated);
});

it('lists the group-settings article as a Scheduler/Chair Groups draft, mapped to the Group page', function () {
    // #608, ADR-0027 §1: the Settings tab is a Group tab, shown to officers with a configuration
    // right. Today every such right is a schedule admin's, so the badge reads Scheduler or Chair.
    $article = (new HelpManifest)->find('group-settings');

    expect($article)->not->toBeNull();
    expect($article->section)->toBe(HelpSection::Groups);
    expect($article->requires)->toBe(['scheduler', 'chair']);
    expect($article->route)->toBe('groups.show');
    expect($article->status)->toBe(ArticleStatus::Draft);
    expect($article->fr)->toBe(FrenchState::MachineTranslated);
});

it('maps the articles for cards on the Group Settings tab to the Group page, not the Scheduling tab', function () {
    // #608, ADR-0027 §2: the Reminders, Empty-desk, Shift kinds and Objects cards left the
    // Scheduling tab for the Settings tab, which renders on the Group page's own route.
    $manifest = new HelpManifest;

    foreach (['set-reminders-and-the-empty-desk-alert', 'manage-your-groups-shift-kinds', 'objects-and-off-site-stations'] as $slug) {
        expect($manifest->find($slug)->route)->toBe('groups.show', $slug);
    }
});

it('maps a secondary route to the article that lists it', function () {
    // One article documents a page and its sibling views (#562): the primary route and
    // every name in `routes` resolve the "?" to that article.
    $manifest = new HelpManifest([
        new HelpArticle('report', HelpSection::HoursAndReports, status: ArticleStatus::Published, route: 'groups.hours.report', routes: ['groups.hours.month']),
    ]);

    expect($manifest->publishedForRoute('groups.hours.report')?->slug)->toBe('report');
    expect($manifest->publishedForRoute('groups.hours.month')?->slug)->toBe('report');
});

it('maps the ten report views onto their two articles', function () {
    $manifest = new HelpManifest;

    // The Group hours report and its four tab views (#562).
    foreach (['groups.hours.month', 'groups.hours.member', 'groups.hours.extra', 'groups.hours.meetings'] as $route) {
        expect($manifest->publishedForRoute($route)?->slug)->toBe('run-your-groups-hours-report');
    }

    // The org-wide committee summary and its six siblings (#562).
    foreach (['hours.committee-detailed', 'hours.visitor-summary', 'hours.ranked', 'hours.zero-hours', 'hours.zero-shift-hours', 'hours.zero-extra-hours'] as $route) {
        expect($manifest->publishedForRoute($route)?->slug)->toBe('the-org-wide-reports');
    }
});

it('maps only route names that exist in the router', function () {
    // Every mapped route counts — a primary `route` and any sibling in `routes` (#562).
    $unknown = collect((new HelpManifest)->all())
        ->flatMap->mappedRoutes()
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
