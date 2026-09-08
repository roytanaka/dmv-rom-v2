/**
 * Unit tests for the client-side rail filter (ADR-0020 §H).
 *
 * Runs on Node's built-in test runner with native TypeScript support — no test
 * framework dependency (`node --test resources/js/chrome/railFilter.test.ts`, or
 * `pnpm test:unit`). These cover the pure filter contract; the component wiring
 * (AppSidebar input → NavRail overlay) is exercised through the filter it delegates to.
 *
 * The SECURITY-RELEVANT case is structural and asserted here: `filterRail` takes only
 * the already-delivered zones and returns a SUBSET of them — it has no network access
 * (no router/fetch import), so it cannot surface a node that was not in the delivered
 * prop. The "no network request" invariant is guaranteed by construction; these tests
 * pin that the output never contains an href absent from the input.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { filterRail, flattenRail, type FilterableRailNode, type FilterableRailZone } from './railFilter.ts';

// A translator that resolves structural label keys the way `trans()` would, but for a
// fixed table — so the tests read as display strings, not raw keys.
const LABELS: Record<string, string> = {
    'nav.rail.my_groups': 'My Groups',
    'nav.rail.other_groups': 'Browse Groups',
    'nav.rail.peers.programs': 'Programs',
};
const translate = (key: string) => LABELS[key] ?? key;

// A realistic delivered rail: My Groups (a Group with a nested working group), and Other
// Groups (a Programs peer exploding to two programs, one sharing the "Docents" name).
const zones = (): FilterableRailZone[] => [
    {
        labelKey: 'nav.rail.my_groups',
        items: [{ href: '/groups/docents', name: 'Docents', children: [{ href: '/groups/docents/tuesday', name: 'Tuesday Team' }] }],
    },
    {
        labelKey: 'nav.rail.other_groups',
        items: [
            {
                labelKey: 'nav.rail.peers.programs',
                href: '/groups/programs',
                children: [
                    { href: '/groups/junior-docents', name: 'Junior Docents' },
                    { href: '/groups/gallery-guides', name: 'Gallery Guides' },
                ],
            },
        ],
    },
];

const hrefsIn = (zonesArg: FilterableRailZone[]): Set<string> => {
    const acc = new Set<string>();
    const walk = (nodes: FilterableRailNode[]) => {
        for (const node of nodes) {
            if (node.href !== undefined) acc.add(node.href);
            if (node.children) walk(node.children);
        }
    };
    for (const zone of zonesArg) if (zone) walk(zone.items);
    return acc;
};

test('matches surface as a flat list with an ancestor breadcrumb', () => {
    const results = filterRail(zones(), 'docents', translate);
    const byHref = new Map(results.map((r) => [r.href, r]));

    // A buried working group and both differently-parented "Docents" surface as flat rows.
    assert.deepEqual(results.map((r) => r.href).sort(), ['/groups/docents', '/groups/junior-docents']);
    // Breadcrumb distinguishes the two: one under My Groups, one under Browse Groups ▸ Programs.
    assert.deepEqual(byHref.get('/groups/docents')?.breadcrumb, ['My Groups']);
    assert.deepEqual(byHref.get('/groups/junior-docents')?.breadcrumb, ['Browse Groups', 'Programs']);
});

test('a match buried in a collapsed branch surfaces immediately', () => {
    const results = filterRail(zones(), 'tuesday', translate);
    assert.deepEqual(
        results.map((r) => r.href),
        ['/groups/docents/tuesday'],
    );
    assert.deepEqual(results[0].breadcrumb, ['My Groups', 'Docents']);
});

test('Group names match verbatim; structural peer labels match on their translation', () => {
    // "Gallery" (a Group name) matches verbatim.
    assert.deepEqual(
        filterRail(zones(), 'gallery', translate).map((r) => r.href),
        ['/groups/gallery-guides'],
    );
    // "Programs" matches the peer's translated label, not any raw key.
    assert.deepEqual(
        filterRail(zones(), 'program', translate).map((r) => r.href),
        ['/groups/programs'],
    );
});

test('a container node is never a result, but its label trails matched descendants as a breadcrumb', () => {
    // A page-less container peer (PRD #289): no href, so it can never itself surface as a
    // flat result — yet its translated label still leads its children's breadcrumb.
    const withContainer = (): FilterableRailZone[] => [
        {
            labelKey: 'nav.rail.other_groups',
            items: [
                {
                    labelKey: 'nav.rail.peers.programs',
                    children: [{ href: '/groups/junior-docents', name: 'Junior Docents' }],
                },
            ],
        },
    ];

    // Matching the container's own label yields nothing — an href-less node cannot leak in.
    assert.deepEqual(filterRail(withContainer(), 'programs', translate), []);

    // A matched descendant still carries the container's label in its breadcrumb.
    const results = filterRail(withContainer(), 'junior', translate);
    assert.deepEqual(
        results.map((r) => r.href),
        ['/groups/junior-docents'],
    );
    assert.deepEqual(results[0].breadcrumb, ['Browse Groups', 'Programs']);
});

test('an empty or whitespace query yields no results (caller restores the nested rail)', () => {
    assert.deepEqual(filterRail(zones(), '', translate), []);
    assert.deepEqual(filterRail(zones(), '   ', translate), []);
});

test('matching is case-insensitive', () => {
    assert.equal(filterRail(zones(), 'DOCENTS', translate).length, 2);
});

test('delivered-set-only: every result href is one already present in the delivered zones', () => {
    const delivered = hrefsIn(zones());
    // Try many needles — no query can ever produce an href outside the delivered set,
    // because the filter only ever narrows the flattened delivered prop.
    for (const needle of ['d', 'o', 'e', 'guides', 'team', 'z']) {
        for (const result of filterRail(zones(), needle, translate)) {
            assert.ok(delivered.has(result.href), `unexpected href ${result.href} for "${needle}"`);
        }
    }
    // And the flattened universe the filter draws from is exactly the delivered set.
    assert.deepEqual(new Set(flattenRail(zones(), translate).map((r) => r.href)), delivered);
});
