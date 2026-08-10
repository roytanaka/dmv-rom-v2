/**
 * The Agenda's day-grouping (#355, ADR-0021 §5) — the pure date logic the Agenda and
 * the Calendar (a later slice) will share.
 *
 * A Schedule's Shifts arrive as a flat, start-ordered list of UTC instants. The reader
 * groups them by *day*, and the day that matters is the day on the organization's wall
 * clock — the museum's local time — not the viewer's zone and not UTC. A Shift that
 * starts at 10pm in Toronto is stored as the next day in UTC; grouping on UTC would
 * file it under tomorrow and split one evening across two headings. So the org timezone
 * (`page.props.timezone`, an IANA name) is threaded in and every day key is resolved
 * against it.
 *
 * ── Purity ───────────────────────────────────────────────────────────────────
 *   This module is a pure function over the already-delivered Shifts and a timezone
 *   string. It imports no router, no `fetch`, no Inertia — it issues no network
 *   request and reaches no ambient clock. Grouping the delivered prop can never surface
 *   a Shift the server did not send. Keep it that way: no data-fetching import here.
 */

/** The minimal shape day-grouping needs: a Shift carries a UTC start instant. */
export interface AgendaShift {
    /** ISO-8601 UTC instant (`2026-08-05T14:00:00Z`) — the Shift's start. */
    starts_at: string;
}

/** One day's Shifts, headed by its org-wall-clock calendar date. */
export interface DayGroup<T> {
    /** The org-wall-clock day, `YYYY-MM-DD` — the group heading and its stable key. */
    date: string;
    /** The day's Shifts, in the order delivered (the server ships them start-ordered). */
    shifts: T[];
}

/**
 * The calendar day a UTC instant falls on, read on the org's wall clock, as `YYYY-MM-DD`.
 * `en-CA` formats a date as `YYYY-MM-DD`, and `formatToParts` under a fixed `timeZone`
 * gives the org-local components regardless of where the viewer's device sits.
 */
export function orgDayKey(iso: string, timeZone: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date(iso));

    const part = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}`;
}

/**
 * Group a flat list of Shifts into per-day buckets on the org wall clock, the days
 * ordered ascending. Shifts keep their delivered order within a day (the server ships
 * them start-ordered), so a 3-day occasion and a 30-day month read the same way.
 */
export function groupShiftsByDay<T extends AgendaShift>(shifts: T[], timeZone: string): DayGroup<T>[] {
    const byDay = new Map<string, T[]>();

    for (const shift of shifts) {
        const key = orgDayKey(shift.starts_at, timeZone);
        const bucket = byDay.get(key);
        if (bucket) {
            bucket.push(shift);
        } else {
            byDay.set(key, [shift]);
        }
    }

    return [...byDay.keys()].sort().map((date) => ({ date, shifts: byDay.get(date)! }));
}
