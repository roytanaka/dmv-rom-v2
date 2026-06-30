/**
 * Chrome navigation model — the typed contract behind the app shell's nav.
 *
 * "Chrome" is the persistent frame (rail, top bar, breadcrumb, footer) that wraps
 * every screen (see CONTEXT.md § Chrome). This module types the *grouping rail*
 * (which Group/area you are in). The contextual *top bar* (which section within a
 * Group) lands in a sibling slice (#67) and reuses these same node/gating types.
 *
 * ── Two-layer model ──────────────────────────────────────────────────────────
 *   Group **List** — which Groups a Volunteer can navigate *to* (the rail).
 *   Group **Menu** — what is revealed *inside* a Group (the top-bar section tabs).
 *   Appearing in the List ≠ permission to act. The List lives here; the Menu is a
 *   later slice. A {@link GroupNode} carries a `groupId` so the top bar can resolve
 *   that Group's Menu without the rail and the Menu sharing a node shape.
 *
 * ── Three zones ──────────────────────────────────────────────────────────────
 *   Zone A — personal/global ...... the top-bar tab set on the Dashboard (slice #67).
 *   Zone B — Groups ............... the rail: My Groups lead + collapsible All Groups,
 *                                   subcommittees nested under their parent Group.
 *   Zone C — officer/admin ........ the rail, pinned bottom, officer-only.
 *
 * ── Gating (stubbed) ─────────────────────────────────────────────────────────
 *   Every node may declare `requiresCapability` (the Group runs X) and/or
 *   `requiresRole` (the Volunteer holds role Y). A declared requirement that the
 *   resolved context does not satisfy hides the node; a node with no requirement is
 *   always visible. The resolver is stubbed show-all (see gating.ts); the real one
 *   lands with the authorization model — ADR-0011 (authz), ADR-0010 (Group model).
 *   Roles are resolved on the SERVER and shared via Inertia props; the client never
 *   echoes role flags back into requests.
 *
 * The enumerated catalogue (every Program, its capabilities, all officer items, the
 * dropped programs) is OUT OF BAND in the forthcoming `docs/nav-spec.md`, the
 * eventual source of truth. This module ships a representative stub fixture only.
 */

import type { PhosphorIcon } from '@/types';

/**
 * A capability a Group "switches on". Drives which Group-Menu slots render (#67)
 * and is one of the two gating drivers a node can require. Representative, not the
 * full catalogue — see docs/nav-spec.md.
 */
export type Capability = 'scheduling' | 'content' | 'documents' | 'meetings' | 'stats';

/**
 * A role a Volunteer can hold (in a Group, or org-wide). The other gating driver.
 * Resolved server-side. Representative, not the full catalogue — see docs/nav-spec.md.
 */
export type Role = 'chair' | 'about-contact' | 'officer' | 'super-tier';

/** Fields shared by every chrome nav node, structural or Group. */
interface NavNodeBase {
    /** Inertia path (internal) or absolute URL (when `external`). */
    href: string;
    /** Phosphor icon component. */
    icon?: PhosphorIcon;
    /** Leaves the app (e.g. Renew Membership) — rendered as an outbound link. */
    external?: boolean;
    /** GATING: hidden unless the Group runs this capability. Unset → no constraint. */
    requiresCapability?: Capability;
    /** GATING: hidden unless the Volunteer holds this role. Unset → no constraint. */
    requiresRole?: Role;
    /**
     * A capability slot whose feature has not shipped yet — rendered as a muted,
     * non-navigable "soon" stub rather than a link (the in-body section tabs, #188).
     */
    soon?: boolean;
}

/**
 * A structural chrome node (rail rows, officer items, top-bar sections). Its label
 * is CHROME — a translation key resolved through the i18n bridge, never final copy.
 */
export interface NavNode extends NavNodeBase {
    /** i18n message key resolved to display copy by `trans()`. */
    labelKey: string;
    /** Nested structural children. */
    children?: NavNode[];
}

/**
 * A Group in the rail (Group List). Its name is CONTENT (ADR-0004): rendered exactly
 * as authored in both locales and never resolved through the translator — so a
 * French-named Group (Guides du ROM) reads identically under `/` and `/fr/`. Its
 * `groupId` keys the Group Menu built in #67.
 */
export interface GroupNode extends NavNodeBase {
    /** As-authored Group name (content) — rendered verbatim, never translated. */
    name: string;
    /** Stable Group identifier — the top bar resolves this Group's Menu by it. */
    groupId: string;
    /** Subcommittees — themselves Groups, so their names are content too. */
    children?: GroupNode[];
}

/**
 * Either kind of rail row: a structural {@link NavNode} (officer cluster) or a
 * content {@link GroupNode} (a Group). The rail renders both through one component,
 * so its label resolution branches on which kind a node is.
 */
export type RailNode = NavNode | GroupNode;

/**
 * A labelled cluster of rail nodes (one row group under an optional heading).
 * Generic over its item type: Zone B holds {@link GroupNode}s (content names),
 * Zone C holds structural {@link NavNode}s (translated labels).
 */
export interface NavSection<T extends NavNode | GroupNode = NavNode> {
    /** i18n key for the section heading; omit for an unlabelled cluster. */
    labelKey?: string;
    items: T[];
    /** Initial open state for a collapsible browse section (e.g. "All Groups"). */
    defaultOpen?: boolean;
}

/**
 * One tile grid on the Dashboard launcher — Zone B rendered as solid squares rather
 * than as the rail. The launcher is the legacy home grid: a Group is a black tile
 * with a white program icon and label.
 */
export interface LauncherGrid {
    /** i18n key for the grid heading (e.g. "My Groups" / "All Groups"). */
    labelKey: string;
    /** Groups rendered as tiles — top-level only; subcommittees stay in the rail. */
    items: GroupNode[];
    /**
     * GATING: the whole grid is hidden unless the Volunteer holds this role — a
     * super-tier officer (President / VP1 / VP2) additionally gets the "All Groups"
     * grid. Unset → always shown. Honoured by the same show-all stub as every node
     * (see gating.ts); the real resolver lands with the authorization model.
     */
    requiresRole?: Role;
}

/** The grouping rail — Zone B (Groups) + Zone C (officer/admin). */
export interface RailNav {
    /** Zone B lead — the Groups this Volunteer belongs to (content names). */
    myGroups: NavSection<GroupNode>;
    /** Zone B browse — the rest of the org, collapsed by default (content names). */
    allGroups: NavSection<GroupNode>;
    /** Zone C — officer/admin, pinned to the rail bottom, officer-only (chrome labels). */
    officer: NavSection<NavNode>;
}
