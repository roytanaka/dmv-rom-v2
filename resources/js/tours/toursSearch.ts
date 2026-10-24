/**
 * The Tours page search (Feedback items 85, 84) — one box over the Tour list, matched in the
 * browser. A case-insensitive substring of the Tour name or of a Member's name keeps the Tour; a
 * Member match also opens it, so the matched name shows. Framework-free so it unit-tests on Node.
 */

export type SearchableTour = {
    id: number;
    name: string;
    members: { memberId: number; name: string }[];
};

/** A Tour the search kept, and whether a Member name (not the Tour name alone) matched. */
export type TourMatch<T extends SearchableTour> = { tour: T; memberMatch: boolean };

export function searchTours<T extends SearchableTour>(tours: T[], query: string): TourMatch<T>[] {
    const needle = query.trim().toLowerCase();
    if (needle === '') {
        return tours.map((tour) => ({ tour, memberMatch: false }));
    }

    return tours.flatMap((tour) => {
        const memberMatch = tour.members.some((member) => member.name.toLowerCase().includes(needle));

        return memberMatch || tour.name.toLowerCase().includes(needle) ? [{ tour, memberMatch }] : [];
    });
}

/** One piece of a name, and whether the query matched it. */
export type MatchPart = { text: string; match: boolean };

/** A name cut into the parts the query matched and the parts it did not, original case kept. */
export function splitMatch(text: string, query: string): MatchPart[] {
    const needle = query.trim().toLowerCase();
    if (needle === '') {
        return [{ text, match: false }];
    }

    const parts: MatchPart[] = [];
    const haystack = text.toLowerCase();
    let from = 0;
    for (let at = haystack.indexOf(needle); at !== -1; at = haystack.indexOf(needle, from)) {
        if (at > from) {
            parts.push({ text: text.slice(from, at), match: false });
        }
        parts.push({ text: text.slice(at, at + needle.length), match: true });
        from = at + needle.length;
    }
    if (from < text.length) {
        parts.push({ text: text.slice(from), match: false });
    }

    return parts;
}
