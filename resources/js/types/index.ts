// @phosphor-icons/vue ships no shared icon type — every icon is the same Vue
// component shape, so we alias one representative export as the icon type. The
// type-level import query keeps this purely a type (no runtime icon import).
export type PhosphorIcon = (typeof import('@phosphor-icons/vue'))['PhSquaresFour'];

export interface Auth {
    user: User;
    /**
     * Coarse, app-wide capability map for chrome/nav (ADR-0017). UI hints only —
     * the server enforces every action; a `false` here hides a control, it is
     * never the security boundary. Fine-grained per-resource `can` props are
     * passed per page.
     */
    can: {
        administerMembers: boolean;
    };
}

/**
 * A fixed global top-bar destination (#194). Server-shared: a stable `key`, a chrome
 * `labelKey` resolved client-side, and an `href` already localized to the active
 * locale (ADR-0008) — so it is used verbatim, never re-localized on the client.
 */
export interface ChromeDestination {
    key: string;
    labelKey: string;
    href: string;
}

/**
 * One Group row on the server-built grouping rail (PRD #209): an as-authored `name`
 * (content — rendered verbatim in both locales, never translated; ADR-0004), a stable
 * slug `groupId`, an `href` already localized server-side (ADR-0008), and any nested
 * subcommittees at full depth. The wire carries no icon — the client supplies the
 * interim placeholder.
 */
export interface RailGroupNode {
    groupId: string;
    name: string;
    href: string;
    children?: RailGroupNode[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: PhosphorIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    /** Active locale, resolved server-side from the URL (ADR-0008). */
    locale: string;
    /**
     * Per-locale URI-segment translation table (non-default locales only), used by
     * `useLocalizedHref` to keep English-canonical nav hrefs in the active locale
     * (ADR-0008). Keyed locale → { englishSegment: localisedSegment }.
     */
    routeSegments: Record<string, Record<string, string>>;
    /**
     * Top-bar language switcher: the active locale plus one option per supported
     * locale. Each option's `url` is the current page's twin in that locale, or
     * null when there is no twin (the active locale, or a page with no twin) —
     * those render disabled (ADR-0008 / #110).
     */
    localeSwitcher: {
        current: string;
        options: Array<{ code: string; label: string; url: string | null }>;
    };
    /**
     * Fixed global top-bar navigation (#194, ADR-0013 amendment): the cross-domain
     * destinations rendered identically on every page, distinct from a Group's
     * section set and the rail. `href` is localized server-side to the active locale
     * (ADR-0008); `labelKey` is resolved client-side via the i18n bridge. `help` is
     * the right-cluster utility destination.
     */
    chromeNav: {
        destinations: ChromeDestination[];
        help: ChromeDestination;
    };
    /**
     * The grouping rail (PRD #209), built and pruned server-side per signed-in
     * Member. The wire format carries plain Group rows (no icon — the client supplies
     * the interim placeholder); hrefs are localized server-side (ADR-0008), so the
     * client renders them verbatim. Zones absent from a Member's rail are omitted. My
     * Groups is flat; All Groups carries the active org tree reshaped (Option C) with
     * subcommittees nested at full depth. Officer Tools (#212) is the org-wide
     * administration cluster: each item per-item gated by a real authority server-side,
     * the cluster omitted whole when none survive. Its labels are i18n keys resolved
     * client-side; its icons are fixed client config keyed by item `key`.
     */
    rail: {
        myGroups?: {
            labelKey: string;
            items: RailGroupNode[];
        };
        allGroups?: {
            labelKey: string;
            items: RailGroupNode[];
        };
        officer?: {
            labelKey: string;
            items: Array<{ key: string; labelKey: string; href: string }>;
        };
    };
    /**
     * The dev/QA role-switcher (PRD #220, ADR-0009 dev half): the server-computed prop
     * the floating impersonation toolbar renders from — the grouped Persona picker and
     * the active-impersonation state. `null` in production and whenever the visibility
     * rule fails (`non-prod AND (super-tier OR active impersonation session)`), so the
     * toolbar simply does not render then. The client computes no persona list,
     * grouping, or authority — it all comes from here. English-only (a dev tool).
     */
    impersonation: {
        personas: Array<{
            key: string;
            label: string;
            personas: Array<{ email: string; name: string; descriptor: string }>;
        }>;
        active: { as: { name: string; descriptor: string }; operator: string } | null;
    } | null;
    /** Persisted sidebar open state, seeded from the `sidebar:state` cookie. */
    sidebarOpen: boolean;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface RosterMember {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    group_roles: string[];
    group_standing: string;
    email?: string;
    phone?: string;
    // Officer roster CRUD targeting (#192) — the membership id, its leave window, and
    // whether it may be hard-removed (no dependent records). Inert for a non-officer.
    membership_id: number;
    loa_start: string | null;
    loa_end: string | null;
    can_hard_remove: boolean;
}

// The Roster tab's officer-CRUD scaffolding (#192) — withheld (empty) from a
// non-officer. Candidates are the add-member search source (id + name only).
export interface RosterCandidate {
    id: number;
    first_name: string;
    last_name: string;
}

export interface RosterMeta {
    candidates: RosterCandidate[];
    assignableRoles: string[];
    showingPast: boolean;
}

export interface MeetingLink {
    kind: string;
    url: string;
}

export interface Meeting {
    id: number;
    title: string;
    description: string | null;
    held_at: string;
    location: string | null;
    video_url: string | null;
    is_published: boolean;
    links: MeetingLink[];
    // UI hints from the MeetingPolicy — drive the per-meeting officer affordances;
    // the server enforces every mutation regardless (#193).
    can: { update: boolean; delete: boolean };
}
