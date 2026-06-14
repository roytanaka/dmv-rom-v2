// @phosphor-icons/vue ships no shared icon type — every icon is the same Vue
// component shape, so we alias one representative export as the icon type. The
// type-level import query keeps this purely a type (no runtime icon import).
export type PhosphorIcon = (typeof import('@phosphor-icons/vue'))['PhSquaresFour'];

export interface Auth {
    user: User;
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
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
