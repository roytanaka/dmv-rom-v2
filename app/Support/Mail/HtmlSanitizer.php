<?php

namespace App\Support\Mail;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The allowlist sanitizer for a composer body (spec #479, ADR-0024 §6, §12.6). A
 * Broadcast or Direct message is rich text an officer types, so its HTML is stored
 * sanitized: legacy shipped unsanitized bodies and headers, and this port does not
 * inherit that. Everything outside the allowlist is dropped — scripts and styles with
 * their contents, every event handler and inline style, and any `href` whose scheme is
 * not http, https, or mailto. Disallowed *formatting* tags are unwrapped so their text
 * survives; disallowed *active* tags are removed whole.
 *
 * Built on PHP's own DOM extension — no package (a hard rule). It parses a fragment,
 * not a document: no doctype, `<html>`, or `<body>` is added or kept.
 */
class HtmlSanitizer
{
    /**
     * The tags kept as-is — plain prose formatting an email body needs and nothing that
     * can run code or load a remote resource.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'a', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'span',
    ];

    /**
     * The attributes kept, per tag. Everything else — every `on*` handler, `style`,
     * `class`, `id`, `src` — is stripped.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
    ];

    /**
     * The schemes an `href` may carry. A `javascript:` or `data:` URL is dropped with
     * the attribute.
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Tags removed whole — content and all — because unwrapping them would keep script
     * text or a form the body should never carry.
     *
     * @var list<string>
     */
    private const DROP_WHOLE = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'head'];

    /**
     * Return the body reduced to the allowlist. Empty in, empty out.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        // Parse a fragment: no implied <html>/<body> wrapper, no doctype, and UTF-8 kept
        // by pinning the encoding rather than relying on a meta tag.
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__sanitizer_root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__sanitizer_root__');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        return trim($this->innerHtml($document, $root));
    }

    /**
     * Walk a node's children, dropping and unwrapping in place. Iterates over a static
     * copy of the child list because the walk mutates it.
     */
    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    /**
     * Decide one node's fate: text stays, an allowed element keeps its allowed
     * attributes and is recursed into, a drop-whole element is removed with its subtree,
     * and any other element is unwrapped so its text survives.
     */
    private function cleanNode(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            // Text stays; comments and processing instructions are removed.
            if ($node->nodeType !== XML_TEXT_NODE) {
                $node->parentNode?->removeChild($node);
            }

            return;
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROP_WHOLE, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $this->unwrap($node);

            return;
        }

        $this->stripAttributes($node, $tag);
        $this->cleanChildren($node);
    }

    /**
     * Replace an element with its children in the parent, then clean those children in
     * their new home — so `<div>a <b>b</b></div>` becomes `a <b>b</b>` with the `<div>`
     * gone but the text and any allowed inner tag kept.
     */
    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $parent->insertBefore($child, $node);
            $this->cleanNode($child);
        }

        $parent->removeChild($node);
    }

    /**
     * Strip every attribute not on the tag's allowlist, and drop an allowed `href` whose
     * scheme is not permitted.
     */
    private function stripAttributes(DOMElement $node, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' && ! $this->hrefIsSafe($attribute->nodeValue)) {
                $node->removeAttribute($attribute->nodeName);
            }
        }
    }

    /**
     * Whether an `href` is safe to keep: a relative link, an anchor, or one of the
     * allowed schemes. A `javascript:`/`data:`/`vbscript:` URL is not.
     */
    private function hrefIsSafe(?string $href): bool
    {
        $href = trim((string) $href);

        if ($href === '') {
            return false;
        }

        // A scheme-relative or relative URL (no colon before the first slash/question
        // mark) carries no dangerous scheme.
        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $href, $matches)) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Serialize a node's children back to an HTML string — the sanitized fragment,
     * without the wrapper the parse added.
     */
    private function innerHtml(DOMDocument $document, DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}
