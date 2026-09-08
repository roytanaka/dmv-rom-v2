import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Localise an English-canonical nav href to the active locale (ADR-0008).
 *
 * Nav hrefs are authored English-canonical in the chrome fixture (`/groups/docents`,
 * `/calendar`). English is canonical at the root, so on `en` they pass through
 * unchanged. On another locale we translate each known structural segment via the
 * server-supplied `routeSegments` table and prefix the locale, so a Volunteer on a
 * French page navigates to French twins (`/fr/groupes/docents`) instead of reverting
 * to English. Content slugs (group names) and unknown segments pass through verbatim.
 *
 * Limitation: this maps segments positionally, so a content slug that happens to
 * equal a structural keyword (e.g. a group literally named "reports") would be
 * translated. The robust fix is backend route-name generation; this covers every
 * route that exists today. Absolute/external hrefs are returned untouched.
 */
export function useLocalizedHref() {
    const page = usePage<SharedData>();

    return (href: string): string => {
        if (!href.startsWith('/')) {
            return href; // external, absolute, or anchor — leave alone
        }

        const dict = page.props.routeSegments[page.props.locale];
        if (!dict) {
            return href; // default locale (en) — canonical at the root
        }

        const segments = href
            .split('/')
            .filter(Boolean)
            .map((segment) => dict[segment] ?? segment);

        return '/' + [page.props.locale, ...segments].join('/');
    };
}
