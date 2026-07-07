/**
 * Chrome navigation model — the typed contract behind the app shell's nav.
 *
 * "Chrome" is the persistent frame (rail, top bar, breadcrumb, footer) that wraps
 * every screen (see CONTEXT.md § Chrome). This module types the nodes the *grouping
 * rail* and the contextual *top bar* render.
 *
 * ── Two-layer model ──────────────────────────────────────────────────────────
 *   Group **List** — which Groups a Volunteer can navigate *to* (the rail).
 *   Group **Menu** — what is revealed *inside* a Group (the top-bar section tabs).
 *   Appearing in the List ≠ permission to act. A {@link GroupNode} carries a
 *   `groupId` so the top bar can resolve that Group's Menu without the rail and the
 *   Menu sharing a node shape.
 *
 * ── Where the data comes from ────────────────────────────────────────────────
 *   The rail (My Groups · Other Groups · Officer Tools) is server-driven and
 *   server-pruned (PRD #209, ADR-0018): it arrives on the shared `rail` Inertia prop
 *   ({@link SharedData}), already localized and carrying only the nodes the Member may
 *   see. These rail rows map onto {@link GroupNode} / {@link NavNode} for rendering.
 *   The Group **Menu** (top-bar section tabs) is still a client stub (fixture.ts),
 *   gated per capability/role — those gating fields live on {@link NavNode} only.
 *
 * The enumerated catalogue (every Program, its capabilities, all officer items, the
 * dropped programs) is OUT OF BAND in the forthcoming `docs/nav-spec.md`, the
 * eventual source of truth.
 */

import type { PhosphorIcon } from '@/types';

/**
 * A capability a Group "switches on". Drives which Group-Menu slots render (#67)
 * and is one of the two gating drivers a section-tab node can require. Representative,
 * not the full catalogue — see docs/nav-spec.md.
 */
export type Capability = 'scheduling' | 'content' | 'documents' | 'meetings' | 'stats';

/**
 * A role a Volunteer can hold (in a Group, or org-wide). The other gating driver.
 * Resolved server-side. Representative, not the full catalogue — see docs/nav-spec.md.
 */
export type Role = 'chair' | 'about-contact' | 'super-tier';

/** Fields shared by every chrome nav node, structural or Group. */
interface NavNodeBase {
    /** Inertia path (internal) or absolute URL (when `external`). */
    href: string;
    /** Phosphor icon component. */
    icon?: PhosphorIcon;
    /** Leaves the app (e.g. Renew Membership) — rendered as an outbound link. */
    external?: boolean;
    /**
     * A capability slot whose feature has not shipped yet — rendered as a muted,
     * non-navigable "soon" stub rather than a link (the in-body section tabs, #188).
     */
    soon?: boolean;
}

/**
 * A structural chrome node (officer rail items, top-bar section tabs). Its label is
 * CHROME — a translation key resolved through the i18n bridge, never final copy.
 *
 * GATING is a section-tab concern only: a Group Menu tab declares `requiresCapability`
 * (the Group runs X) and/or `requiresRole` (the Volunteer holds role Y). The rail is
 * pruned server-side (ADR-0018), so its rows never carry these fields.
 */
export interface NavNode extends NavNodeBase {
    /** i18n message key resolved to display copy by `trans()`. */
    labelKey: string;
    /** GATING: section tab hidden unless the Group runs this capability. Unset → none. */
    requiresCapability?: Capability;
    /** GATING: section tab hidden unless the Volunteer holds this role. Unset → none. */
    requiresRole?: Role;
    /** Nested structural children. */
    children?: NavNode[];
}

/**
 * A Group in the rail (Group List). Its name is CONTENT (ADR-0004): rendered exactly
 * as authored in both locales and never resolved through the translator — so a
 * French-named Group (Guides du ROM) reads identically under `/` and `/fr/`. Its
 * `groupId` keys the Group Menu built in #67. The rail is server-pruned (ADR-0018), so
 * a Group row carries no gating fields.
 */
export interface GroupNode extends NavNodeBase {
    /** As-authored Group name (content) — rendered verbatim, never translated. */
    name: string;
    /** Stable Group identifier — the top bar resolves this Group's Menu by it. */
    groupId: string;
    /**
     * The Group's logo key (its stored `logo_key`), or null when it has none.
     * IDENTITY, not chrome: the launcher tile resolves it to an asset via
     * `@/groups/logos`, falling back to the generic mark when null. Carried on
     * every rail row but read only by the launcher grid (the rail ignores it).
     */
    logo?: string | null;
    /** Subcommittees — themselves Groups, so their names are content too. */
    children?: GroupNode[];
}

/**
 * A container-peer row heading the Other Groups zone (ADR-0020 §C): one of the four
 * organization-scope containers. Its label is CHROME — a `labelKey` resolved through the
 * i18n bridge — because a peer is structural scaffolding, not a member content Group; the
 * Groups nested beneath it are {@link GroupNode}s and render their names verbatim. Carries
 * an optional `logo` for the launcher tile.
 */
export interface PeerNode extends NavNodeBase {
    /** i18n message key resolved to display copy by `trans()`. */
    labelKey: string;
    /** The container's logo key, or null — resolved to a launcher-tile mark, else the fallback. */
    logo?: string | null;
    /** The peer's visible child Groups (content). */
    children?: GroupNode[];
}

/**
 * Any rail row: a structural {@link NavNode} (officer cluster), a content {@link GroupNode}
 * (a Group), or a chrome {@link PeerNode} (an Other-Groups container peer). The rail renders
 * all three through one component, so its label resolution branches on which kind a node is.
 */
export type RailNode = NavNode | GroupNode | PeerNode;
