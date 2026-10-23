// The Feedback index's remembered filters (#775). The index opens on the type and status
// the viewer chose last, kept in localStorage. With nothing kept, it opens on Open.
// Every storage access is wrapped: a browser that blocks storage gets the default.
//
// The storage is passed in (a function, so reading `window.localStorage` itself can
// throw inside the try), which keeps this testable outside a browser.

export interface FeedbackFilters {
    type: string | null;
    status: string | null;
}

type StorageSource = () => Pick<Storage, 'getItem' | 'setItem'>;

const FILTERS_KEY = 'dmv.feedback.indexFilters';

const DEFAULT_FILTERS: FeedbackFilters = { type: null, status: 'open' };

const browserStorage: StorageSource = () => window.localStorage;

const stringOrNull = (value: unknown): string | null => (typeof value === 'string' ? value : null);

function rememberedFilters(storage: StorageSource): FeedbackFilters {
    try {
        const stored = storage().getItem(FILTERS_KEY);

        if (stored === null) return DEFAULT_FILTERS;

        const parsed: unknown = JSON.parse(stored);

        if (typeof parsed !== 'object' || parsed === null) return DEFAULT_FILTERS;

        const { type, status } = parsed as Record<string, unknown>;

        return { type: stringOrNull(type), status: stringOrNull(status) };
    } catch {
        return DEFAULT_FILTERS;
    }
}

export function rememberFilters(filters: FeedbackFilters, storage: StorageSource = browserStorage): void {
    try {
        storage().setItem(FILTERS_KEY, JSON.stringify({ type: filters.type, status: filters.status }));
    } catch {
        // Storage blocked: the filter still applies, it is just not kept.
    }
}

const carriesFilters = (url: string): boolean => {
    const query = new URL(url, 'http://localhost').searchParams;

    return query.has('type') || query.has('status');
};

/**
 * The filters the index should open on, or null when the page on screen already shows them.
 * `url` is the page's URL and `current` the filters the server applied to it. A URL that
 * carries filters (a shared or bookmarked link) wins, and its filters become the stored
 * setting; otherwise the stored filters (or Open) apply.
 */
export function openingFilters(url: string, current: FeedbackFilters, storage: StorageSource = browserStorage): FeedbackFilters | null {
    if (carriesFilters(url)) {
        rememberFilters(current, storage);

        return null;
    }

    const remembered = rememberedFilters(storage);

    return remembered.type === current.type && remembered.status === current.status ? null : remembered;
}
