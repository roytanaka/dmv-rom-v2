<?php

namespace App\Help;

use Illuminate\Support\Str;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Renders a Help article's Markdown to a title and sanitized HTML (ADR-0025).
 *
 * The title is the file's first level-one heading; everything else is the body.
 * The body is GitHub-flavoured Markdown as Laravel's own `Str::markdown` renders
 * it — HTML in the source is stripped and unsafe links are disallowed, so no raw
 * markup from a source file reaches the page. No new package.
 *
 * An image written `![Caption](01.png)` is a screenshot: the bare filename resolves
 * against the article's public folder (`/help-images/<slug>/01.png`) and the image renders
 * as a `<figure>` with the caption as its `<figcaption>` — captions are the only
 * annotation. The folder is `help-images`, not `help`: a `public/help/` directory
 * shadows the `/help` route on Apache (403 before Laravel runs). A blockquote that
 * opens with a bold `Tip:`/`Astuce :` or `Note:` label renders as a callout
 * `<div class="callout" data-callout="tip|note">`. A link written `[Title](slug)`
 * with a bare slug is an article link: it resolves to that article's help URL in
 * the requested locale (`/help/<slug>` or `/fr/aide/<slug>`); anchors, absolute
 * paths and full URLs stay as they are. If a locale's file
 * is missing, the English file renders (the manifest test keeps that a dev-only
 * fallback).
 */
final class HelpArticleRenderer
{
    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? resource_path('help');
    }

    /** Render an article for a locale, or null when no source file exists. */
    public function render(string $slug, string $locale): ?RenderedArticle
    {
        $source = $this->read($slug, $locale);

        if ($source === null) {
            return null;
        }

        [$title, $body] = $source;

        $html = Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $this->renderScreenshots($html, $slug);
        $html = $this->renderArticleLinks($html, $locale);

        return new RenderedArticle($title, $this->renderCallouts($html));
    }

    /** An article's title (its first level-one heading), or null when it is missing. */
    public function title(string $slug, string $locale): ?string
    {
        return $this->read($slug, $locale)[0] ?? null;
    }

    /**
     * The image filenames an article's source references, for the manifest-integrity
     * test that every referenced screenshot exists on disk.
     *
     * @return list<string>
     */
    public function referencedImages(string $slug, string $locale): array
    {
        $source = $this->read($slug, $locale);

        if ($source === null) {
            return [];
        }

        preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $source[1], $matches);

        return collect($matches[1])
            ->filter(fn (string $src) => $this->isScreenshot($src))
            ->values()
            ->all();
    }

    /**
     * The article slugs an article's source links to, for the manifest-integrity test
     * that every article link names an article in the manifest.
     *
     * @return list<string>
     */
    public function referencedArticles(string $slug, string $locale): array
    {
        $source = $this->read($slug, $locale);

        if ($source === null) {
            return [];
        }

        preg_match_all('/(?<!!)\[[^\]]*\]\(([^)]+)\)/', $source[1], $matches);

        return collect($matches[1])
            ->filter(fn (string $href) => $this->isArticleSlug($href))
            ->values()
            ->all();
    }

    /**
     * Split a source file into [title, body]: the first `# ` heading is the title,
     * every other line is the body. Returns null when the file does not exist.
     *
     * @return array{0: string, 1: string}|null
     */
    private function read(string $slug, string $locale): ?array
    {
        $path = $this->pathFor($slug, $locale);

        if ($path === null) {
            return null;
        }

        $title = '';
        $body = [];

        foreach (preg_split('/\r\n|\r|\n/', file_get_contents($path)) as $line) {
            if ($title === '' && preg_match('/^#\s+(.+?)\s*$/', $line, $match)) {
                $title = $match[1];

                continue;
            }

            $body[] = $line;
        }

        return [$title, trim(implode("\n", $body))];
    }

    /** Resolve a slug/locale to a file, falling back to the English file. */
    private function pathFor(string $slug, string $locale): ?string
    {
        foreach ([$locale, 'en'] as $candidate) {
            $path = "{$this->root}/{$candidate}/{$slug}.md";

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Rewrite each screenshot image into a captioned figure. */
    private function renderScreenshots(string $html, string $slug): string
    {
        $html = preg_replace_callback(
            '/<img\s+src="([^"]*)"\s+alt="([^"]*)"\s*\/?>/',
            function (array $match) use ($slug) {
                [, $src, $alt] = $match;
                $resolved = $this->isScreenshot($src) ? "/help-images/{$slug}/{$src}" : $src;

                return sprintf(
                    '<figure><img src="%s" alt="%s"><figcaption>%s</figcaption></figure>',
                    $resolved,
                    $alt,
                    $alt,
                );
            },
            $html,
        );

        // Unwrap the paragraph CommonMark put a lone image in, so the figure is a block.
        return preg_replace('#<p>(<figure>.*?</figure>)</p>#s', '$1', $html);
    }

    /** Point each bare-slug link at that article's help URL in the locale. */
    private function renderArticleLinks(string $html, string $locale): string
    {
        return preg_replace_callback(
            '/<a href="([^"]*)"/',
            function (array $match) use ($locale) {
                if (! $this->isArticleSlug($match[1])) {
                    return $match[0];
                }

                $url = LaravelLocalization::getURLFromRouteNameTranslated($locale, 'routes.help.show', ['article' => $match[1]]);

                // Path-only, like the breadcrumb, so the link stays on this host.
                return sprintf('<a href="%s"', parse_url($url, PHP_URL_PATH));
            },
            $html,
        );
    }

    /**
     * Turn each blockquote that opens with a bold Tip or Note label (either locale)
     * into a callout that carries its kind. Any other blockquote stays as it is.
     */
    private function renderCallouts(string $html): string
    {
        return preg_replace_callback(
            '#<blockquote>\n(?<body><p><strong>(?<label>Tip|Astuce|Note)\s*:</strong>.*?)</blockquote>#s',
            function (array $match) {
                $kind = $match['label'] === 'Note' ? 'note' : 'tip';

                return "<div class=\"callout\" data-callout=\"{$kind}\">\n{$match['body']}</div>";
            },
            $html,
        );
    }

    /** An article link's target is a bare slug: lowercase words joined by hyphens. */
    private function isArticleSlug(string $href): bool
    {
        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $href) === 1;
    }

    /** A screenshot is a bare filename, not an absolute path or external URL. */
    private function isScreenshot(string $src): bool
    {
        return ! Str::startsWith($src, ['/', 'http://', 'https://', '#']);
    }
}
