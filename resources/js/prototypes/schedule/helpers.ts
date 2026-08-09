// PROTOTYPE — throwaway. See #330.
//
// The little that is genuinely shared between the three variants: date formatting,
// the two authorization floors as the viewer experiences them, and the in-memory
// sign-up / drop. Layout is deliberately NOT shared — each variant is free to throw
// the whole structure away, which is the point of the exercise.
//
// English-only. Every string here would be a translation key in the real thing
// (ADR-0004); a throwaway prototype is not worth 60 lang entries.
import { VIEWER_PERSON } from './fixtures';
import type { Schedule, Shift, Viewer } from './types';

export const dayOf = (at: string) => at.slice(0, 10);
export const timeOf = (at: string) => at.slice(11, 16);

export const timeRange = (shift: Shift) => `${timeOf(shift.startsAt)}–${timeOf(shift.endsAt)}`;

const fmt = (date: string, opts: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('en-CA', opts).format(new Date(`${date}T00:00`));

export const dayLong = (date: string) => fmt(date, { weekday: 'long', day: 'numeric', month: 'long' });
export const dayShort = (date: string) => fmt(date, { weekday: 'short', day: 'numeric', month: 'short' });
export const weekdayNarrow = (date: string) => fmt(date, { weekday: 'short' });
export const dayNumber = (date: string) => new Date(`${date}T00:00`).getDate();

export const rangeLabel = (schedule: Schedule) =>
    `${fmt(schedule.startsOn, { day: 'numeric', month: 'long' })} – ${fmt(schedule.endsOn, { day: 'numeric', month: 'long', year: 'numeric' })}`;

// ---------------------------------------------------------------- viewer state

export const isMine = (shift: Shift) => shift.signups.some((s) => s.person.id === VIEWER_PERSON.id);
export const remaining = (shift: Shift) => Math.max(0, shift.capacity - shift.signups.length);
export const isFull = (shift: Shift) => remaining(shift) === 0;

/**
 * Whether the viewer may take this Shift.
 *
 * #327 settled the rule as a read-time filter on the Shift's `audience`, so a
 * non-member of the Group can still take an `open` Shift — which is why the
 * read-only audience is not uniformly read-only. Both `canSignUp()` floors are
 * assumed cleared here; they are not a layout question.
 */
export function canTake(shift: Shift, viewer: Viewer): boolean {
    if (isMine(shift) || isFull(shift)) return false;
    if (viewer.role === 'nonmember') return shift.audience === 'open';
    return true;
}

/**
 * Whether the viewer may give this Shift back. Mirrors `canTake`'s audience rule —
 * you can only drop a Sign-up you could have made — which also keeps the fixture
 * honest when the audience toggle flips underneath seeded sign-ups.
 */
export const canDrop = (shift: Shift, viewer: Viewer) => isMine(shift) && (viewer.role !== 'nonmember' || shift.audience === 'open');

/** A Scheduler authors their own Group's Shifts only — never a foreign one. */
export const canAuthor = (shift: Shift, viewer: Viewer) => viewer.role === 'scheduler' && shift.foreignGroup === undefined;

/**
 * Whether the viewer sees WHO is signed up, or only how many.
 *
 * Decided 2026-08-08 (Roy): **names, for every viewer who can read the Schedule**.
 * Drawing it surfaced the question — legacy shows names to everyone who can see the
 * schedule, but #328 made the read audience org-wide, which is wider than legacy's,
 * so the inherited behaviour was not automatically safe. It stands: a Schedule is a
 * roster of who is on the floor, and a name on it is no more exposing than the
 * Directory, which is already org-open. Consequence for #331: this is a field the
 * ADR-0017 §6 allowlist has to name explicitly rather than leave to inheritance.
 */
export const canSeeNames = () => true;

// ---------------------------------------------------------------- mutations (in-memory)

export function take(shift: Shift) {
    if (isFull(shift) || isMine(shift)) return;
    shift.signups.push({ person: VIEWER_PERSON, assigned: false });
}

export function drop(shift: Shift) {
    const i = shift.signups.findIndex((s) => s.person.id === VIEWER_PERSON.id);
    if (i >= 0) shift.signups.splice(i, 1);
}

export function removeSignup(shift: Shift, personId: number) {
    const i = shift.signups.findIndex((s) => s.person.id === personId);
    if (i >= 0) shift.signups.splice(i, 1);
}

// ---------------------------------------------------------------- grouping

export interface DayBucket {
    date: string;
    shifts: Shift[];
}

/** The Schedule's Shifts bucketed by day, in date order, days with none omitted. */
export function byDay(shifts: Shift[]): DayBucket[] {
    const map = new Map<string, Shift[]>();
    for (const shift of shifts) {
        const key = dayOf(shift.startsAt);
        (map.get(key) ?? map.set(key, []).get(key)!).push(shift);
    }
    return [...map.entries()]
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([date, list]) => ({
            date,
            shifts: list.sort((a, b) => a.startsAt.localeCompare(b.startsAt) || (a.kind ?? '').localeCompare(b.kind ?? '')),
        }));
}

/** Distinct `HH:mm–HH:mm` bands across the Schedule, in start order. */
export function timeBands(shifts: Shift[]): string[] {
    return [...new Set(shifts.map(timeRange))].sort();
}

/** Distinct kinds across the Schedule, in first-seen order, nulls last. */
export function kindsIn(shifts: Shift[]): string[] {
    return [...new Set(shifts.map((s) => s.kind ?? '—'))];
}

export interface Totals {
    shifts: number;
    slots: number;
    open: number;
    mine: number;
}

export function totals(shifts: Shift[]): Totals {
    return {
        shifts: shifts.length,
        slots: shifts.reduce((n, s) => n + s.capacity, 0),
        open: shifts.reduce((n, s) => n + remaining(s), 0),
        mine: shifts.filter(isMine).length,
    };
}

/** The calendar weeks (Sun-first) covering a date range, as `YYYY-MM-DD` grids. */
export function calendarWeeks(startsOn: string, endsOn: string): string[][] {
    const first = new Date(`${startsOn}T00:00`);
    const last = new Date(`${endsOn}T00:00`);
    const cursor = new Date(first);
    cursor.setDate(cursor.getDate() - cursor.getDay());
    const weeks: string[][] = [];
    while (cursor <= last) {
        const week: string[] = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(cursor);
            d.setDate(d.getDate() + i);
            week.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`);
        }
        weeks.push(week);
        cursor.setDate(cursor.getDate() + 7);
    }
    return weeks;
}

export const inRange = (date: string, schedule: Schedule) => date >= schedule.startsOn && date <= schedule.endsOn;
