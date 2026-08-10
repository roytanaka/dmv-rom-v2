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
 * Cross-Group open Shifts (#361, ADR-0021 §Sign-up) — the foreign Shifts a reader
 * discovers on a Group's Schedule. A foreign Shift is another Group's `open` Shift, and it
 * carries the one thing an own Shift does not need: the name of the Group that owns it, so
 * a reader is never left mistaking whose Shift they are taking.
 */
export interface ForeignAgendaShift extends AgendaShift {
    /** The owning Group's name — a foreign Shift is always attributed to it. */
    group_name: string;
}

/** A day's foreign Shifts for one owning Group — the collapsible "N more open to you" band. */
export interface ForeignBand<T> {
    /** The owning Group's name — the band heading and its attribution. */
    group: string;
    /** That Group's foreign Shifts on the day, in delivered (start) order. */
    shifts: T[];
}

/**
 * One day of the reader's Agenda: this Group's own Shifts, and — kept strictly apart — the
 * foreign open Shifts other Groups advertise, banded by owning Group.
 */
export interface AgendaDay<Own, Foreign> {
    /** The org-wall-clock day `YYYY-MM-DD` — the day heading and its stable key. */
    date: string;
    /** This Group's own Shifts on the day, in delivered order (never mixed with foreign). */
    shifts: Own[];
    /** Foreign open Shifts on the day, one band per owning Group; empty when there are none. */
    bands: ForeignBand<Foreign>[];
}

/**
 * Group a flat list of foreign Shifts into one band per owning Group, each band **attributed**
 * to that Group. A Map preserves first-seen order, and the caller ships start-ordered, so the
 * bands come out ordered by the earliest Shift each holds. Shared by {@link buildAgenda} (per
 * day) and the Calendar's day sheet, so a foreign Shift reads the same wherever it surfaces.
 */
export function bandsByGroup<T extends ForeignAgendaShift>(foreign: T[]): ForeignBand<T>[] {
    const byGroup = new Map<string, T[]>();
    for (const shift of foreign) {
        const bucket = byGroup.get(shift.group_name);
        if (bucket) {
            bucket.push(shift);
        } else {
            byGroup.set(shift.group_name, [shift]);
        }
    }

    return [...byGroup.entries()].map(([group, shifts]) => ({ group, shifts }));
}

/**
 * Partition a Schedule's own Shifts and the foreign open Shifts other Groups advertise into
 * one ascending day list (#361, ADR-0021 §Sign-up). Both are grouped onto the org wall clock
 * exactly as the Agenda groups its own Shifts, so a day reads the same in either. The two
 * are **never interleaved**: a day's own Shifts stay in `shifts`, its foreign Shifts land in
 * `bands` — one band per owning Group, each band **attributed** to that Group and ordered by
 * the earliest foreign Shift it holds. A day carrying only foreign Shifts still surfaces (so
 * an open Shift is discoverable even where this Group runs nothing that day); an own-only day
 * carries no bands.
 *
 * Pure over the delivered Shifts and a timezone — no router, no `fetch`, no ambient clock. It
 * can only rearrange what the server sent; it can never surface a Shift the server withheld.
 */
export function buildAgenda<Own extends AgendaShift, Foreign extends ForeignAgendaShift>(
    own: Own[],
    foreign: Foreign[],
    timeZone: string,
): AgendaDay<Own, Foreign>[] {
    const ownByDate = new Map(groupShiftsByDay(own, timeZone).map((group) => [group.date, group.shifts]));

    const bandsByDate = new Map<string, ForeignBand<Foreign>[]>(
        groupShiftsByDay(foreign, timeZone).map((group) => [group.date, bandsByGroup(group.shifts)]),
    );

    const dates = [...new Set([...ownByDate.keys(), ...bandsByDate.keys()])].sort();

    return dates.map((date) => ({
        date,
        shifts: ownByDate.get(date) ?? [],
        bands: bandsByDate.get(date) ?? [],
    }));
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
