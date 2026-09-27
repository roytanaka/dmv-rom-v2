// The client half of a Feedback item's context (#676, ADR-0029 §10): what only the browser knows.
import assert from 'node:assert/strict';
import test from 'node:test';
import { buildClientContext, type ClientContextSource } from './clientContext.ts';

const source = (overrides: Partial<ClientContextSource> = {}): ClientContextSource => ({
    location: { pathname: '/groups/docents', search: '?tab=roster' },
    navigator: { userAgent: 'Mozilla/5.0 (Macintosh)' },
    innerWidth: 1280,
    innerHeight: 800,
    ...overrides,
});

test('builds the page URL, user agent, and viewport', () => {
    assert.deepEqual(buildClientContext(source()), {
        page_url: '/groups/docents?tab=roster',
        user_agent: 'Mozilla/5.0 (Macintosh)',
        viewport_width: 1280,
        viewport_height: 800,
    });
});

test('the page URL is the path and query only, with no host', () => {
    const context = buildClientContext(source({ location: { pathname: '/fr/tableau-de-bord', search: '' } }));

    assert.equal(context.page_url, '/fr/tableau-de-bord');
});

test('rounds a fractional viewport to whole pixels', () => {
    const context = buildClientContext(source({ innerWidth: 390.4, innerHeight: 843.6 }));

    assert.equal(context.viewport_width, 390);
    assert.equal(context.viewport_height, 844);
});

test('caps the page URL and user agent at the lengths the server accepts', () => {
    const context = buildClientContext(
        source({
            location: { pathname: `/${'a'.repeat(3000)}`, search: '' },
            navigator: { userAgent: 'b'.repeat(600) },
        }),
    );

    assert.equal(context.page_url.length, 2048);
    assert.equal(context.user_agent.length, 512);
});
