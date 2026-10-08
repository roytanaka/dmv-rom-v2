// Which section a page belongs to (#648). A section stays active on the pages beneath it — a
// Schedule permalink (`/groups/docents/scheduling/2`) is still the Scheduling section — so the
// match is the longest href the path equals or sits under, segment by segment. The query string
// and fragment never count. Returns null when no href matches.
export const activeSectionHref = (hrefs: string[], url: string): string | null => {
    const path = url.split(/[?#]/)[0];

    return hrefs
        .filter((href) => path === href || path.startsWith(`${href}/`))
        .reduce<string | null>((best, href) => (best === null || href.length > best.length ? href : best), null);
};

// The item whose href the page sits under, by the same rule (#741: the top bar's four links).
export const activeDestination = <T extends { href: string }>(items: T[], url: string): T | null => {
    const href = activeSectionHref(
        items.map((item) => item.href),
        url,
    );

    return items.find((item) => item.href === href) ?? null;
};
