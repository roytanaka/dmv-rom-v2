/**
 * Client-side rail type-ahead filter (ADR-0020 §H).
 *
 * As the Member types in the sidebar search box, the grouping rail's visible node
 * set is flattened and substring-matched; matches render as a FLAT list, each carrying
 * a path breadcrumb (its ancestor trail) so similarly-named Groups stay distinguishable.
 * Clearing the box restores the full nested rail — the filter is a temporary overlay,
 * not a permanent reshape.
 *
 * ── Hard security invariant ──────────────────────────────────────────────────
 *   This module is a PURE function over the already-delivered, server-pruned rail
 *   zones. It imports no router, no `fetch`, no Inertia — it CANNOT issue a network
 *   request. Because the server ships only visible nodes (ADR-0018), filtering the
 *   delivered prop can never surface a `Private` / `Group` node the Member was not
 *   already sent. Typing filters what is in the prop; nothing more. Keep it that way:
 *   never add a data-fetching import here.
 *
 * Label resolution mirrors {@link NavRailItem}: a content Group renders its `name`
 * verbatim (ADR-0004); a structural node (a container peer) translates its `labelKey`.
 * The `translate` callback is injected so this module stays decoupled from the i18n
 * bridge (the component passes `trans`).
 */

/** The minimal shape this filter needs — satisfied by both a Group row and a container peer. */
export interface FilterableRailNode {
    href: string;
    /** A content Group's as-authored name (rendered verbatim). */
    name?: string;
    /** A structural node's i18n key (translated). */
    labelKey?: string;
    children?: FilterableRailNode[];
}

/** One rail zone as delivered on the shared `rail` prop, or absent for this Member. */
export type FilterableRailZone = { labelKey: string; items: FilterableRailNode[] } | undefined;

/** A flattened, matched rail node ready to render as a flat result row. */
export interface RailFilterResult {
    /** The node's localized path (also the stable v-for key). */
    href: string;
    /** Display label — Group name verbatim, or translated structural label. */
    label: string;
    /** Ancestor trail (zone heading, then parent Groups), resolved to display strings. */
    breadcrumb: string[];
}

/** A content Group renders `name` verbatim; a structural node translates `labelKey`. */
function nodeLabel(node: FilterableRailNode, translate: (key: string) => string): string {
    return node.name !== undefined ? node.name : translate(node.labelKey ?? '');
}

function walk(nodes: FilterableRailNode[], trail: string[], translate: (key: string) => string, out: RailFilterResult[]): void {
    for (const node of nodes) {
        const label = nodeLabel(node, translate);
        out.push({ href: node.href, label, breadcrumb: trail });
        const children = node.children ?? [];
        if (children.length) {
            walk(children, [...trail, label], translate, out);
        }
    }
}

/**
 * Flatten the delivered rail zones into a single depth-first list, each entry carrying
 * its ancestor breadcrumb (the zone heading, then each parent Group's label).
 */
export function flattenRail(zones: FilterableRailZone[], translate: (key: string) => string): RailFilterResult[] {
    const out: RailFilterResult[] = [];
    for (const zone of zones) {
        if (!zone) continue;
        walk(zone.items, [translate(zone.labelKey)], translate, out);
    }
    return out;
}

/**
 * Substring-match the flattened rail against the query (case-insensitive, on the node's
 * own label). An empty/whitespace query returns no results — the caller then restores
 * the nested rail rather than showing an empty flat list.
 */
export function filterRail(zones: FilterableRailZone[], query: string, translate: (key: string) => string): RailFilterResult[] {
    const needle = query.trim().toLowerCase();
    if (!needle) return [];
    return flattenRail(zones, translate).filter((result) => result.label.toLowerCase().includes(needle));
}
