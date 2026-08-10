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

/**
 * The Calendar's month grid (#360, ADR-0021 §7) — the second view the reader chooses.
 * It shares the Agenda's day-grouped input: a month is a laid-out arrangement of the same
 * {@link DayGroup} buckets, so a day's Shifts read identically in either view.
 *
 * ── Purity ───────────────────────────────────────────────────────────────────
 *   Layout only, over the delivered day groups — no router, no `fetch`, no network. The
 *   grid never surfaces a Shift the server did not send; it only arranges the ones it did.
 */

/** One cell of a month grid: a real day (with its Shifts), or a padding blank. */
export interface MonthCell<T> {
    /** The org-wall-clock day `YYYY-MM-DD`, or `null` for a leading/trailing blank. */
    date: string | null;
    /** The day's Shifts in delivered order (empty for a Shift-free day and every blank). */
    shifts: T[];
}

/** A month laid out as whole calendar weeks, each exactly seven cells. */
export interface MonthGrid<T> {
    /** The four-digit year the grid covers. */
    year: number;
    /** The month, `1`–`12`. */
    month: number;
    /** Calendar weeks, each seven cells; leading and trailing days padded with blanks. */
    weeks: MonthCell<T>[][];
}

const pad = (n: number) => String(n).padStart(2, '0');

/**
 * Lay a month out as a grid of whole weeks. Each day's cell carries the Shifts grouped
 * onto that org-wall-clock day; days the range does not cover, and the leading/trailing
 * days that pad the first and last weeks, are blank cells (`date: null`). `weekStartsOn`
 * is a weekday index (`0` Sunday … `6` Saturday), Sunday by default.
 *
 * The day math is deliberately timezone-free: the cells are keyed by the same
 * `YYYY-MM-DD` strings {@link groupShiftsByDay} produces on the org wall clock, and
 * `new Date(year, month - 1, day)` reads a local calendar date, never an instant — so the
 * grid places a day under the heading the Agenda already filed it under.
 */
export function buildMonthGrid<T>(groups: DayGroup<T>[], year: number, month: number, weekStartsOn = 0): MonthGrid<T> {
    const byDate = new Map(groups.map((group) => [group.date, group.shifts]));
    const firstWeekday = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const leading = (firstWeekday - weekStartsOn + 7) % 7;

    const cells: MonthCell<T>[] = [];
    for (let i = 0; i < leading; i++) {
        cells.push({ date: null, shifts: [] });
    }
    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${year}-${pad(month)}-${pad(day)}`;
        cells.push({ date, shifts: byDate.get(date) ?? [] });
    }
    while (cells.length % 7 !== 0) {
        cells.push({ date: null, shifts: [] });
    }

    const weeks: MonthCell<T>[][] = [];
    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }

    return { year, month, weeks };
}

/**
 * The months a Schedule's date range spans, inclusive and in order — the set the Calendar
 * pages through. A single month yields one entry; a range crossing a boundary yields each
 * month it touches, so a reader can step from the range's first month to its last and no
 * further. Parses the plain `YYYY-MM-DD` date strings the Schedule carries; no instants.
 */
export function monthsInRange(startsOn: string, endsOn: string): { year: number; month: number }[] {
    const [startYear, startMonth] = startsOn.split('-').map(Number);
    const [endYear, endMonth] = endsOn.split('-').map(Number);

    const months: { year: number; month: number }[] = [];
    let year = startYear;
    let month = startMonth;
    while (year < endYear || (year === endYear && month <= endMonth)) {
        months.push({ year, month });
        month += 1;
        if (month > 12) {
            month = 1;
            year += 1;
        }
    }

    return months;
}
