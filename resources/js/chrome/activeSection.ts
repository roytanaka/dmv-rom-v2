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
