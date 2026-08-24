// PROTOTYPE (#405) — throwaway in-memory fixtures.
//
// The clock is fixed so every variant tells the same story: it is Monday 24 August
// 2026, 16:58 at the museum, and the viewer is on the last few minutes of a shift that
// ends at 17:00 — the moment legacy opens the sign-out box. Three Groups, chosen because
// they are the three shapes #404 found: one number, two numbers, and a Group that
// collects nothing at all.

import type { GroupFixture, OutstandingShift, Shift, SignUp, Viewer } from './types';

/** Fixed "now" — a real Date so the variants can do ordinary date arithmetic. */
export const NOW = new Date('2026-08-24T16:58:00-04:00');

/** Legacy keeps a shift fillable by its volunteer for 28 days (#404 Q4). */
export const WINDOW_DAYS = 28;

export const VIEWER_NAME = 'Marguerite Okonjo';
export const VIEWER_ID = 1;

const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/** A shift start, given as days before/after NOW and a wall-clock hour. */
const at = (dayOffset: number, hour: number, minute = 0) => {
    const base = new Date(NOW.getTime() + dayOffset * DAY);
    base.setHours(hour, minute, 0, 0);
    return base.toISOString();
};

let nextSignUpId = 100;

const seat = (memberId: number, memberName: string, visitorCount: number | null = null, extraInteractionCount: number | null = null): SignUp => ({
    id: nextSignUpId++,
    memberId,
    memberName,
    visitorCount,
    extraInteractionCount,
});

const me = (visitorCount: number | null = null, extra: number | null = null) => seat(VIEWER_ID, VIEWER_NAME, visitorCount, extra);

let nextShiftId = 1;

const shift = (startsAt: string, hours: number, kind: string, capacity: number, signUps: SignUp[], scheduleName = 'August 2026'): Shift => ({
    id: nextShiftId++,
    startsAt,
    endsAt: new Date(new Date(startsAt).getTime() + hours * HOUR).toISOString(),
    kind,
    capacity,
    signUps,
    scheduleName,
});

// ---------------------------------------------------------------------------
// Visitor Guides — one number, the shape 6 of 9 collecting Groups have.
// ---------------------------------------------------------------------------
const visitorGuides = (): GroupFixture => ({
    key: 'guides',
    label: 'Visitor Guides',
    groupName: 'Visitor Guides',
    slug: 'visitor-guides',
    scheduleName: 'August 2026',
    collects: 'one',
    visitorLabel: 'Visitors you spoke with',
    extraLabel: '',
    shifts: [
        // Yesterday, everyone signed out but one.
        shift(at(-2, 10), 3, 'Level 1 Welcome Desk', 3, [me(47), seat(2, 'Hélène Barbeau', 51), seat(3, 'Desmond Wray')]),
        // Two days ago — the viewer's own blank one, well inside the window.
        shift(at(-2, 13), 4, 'Bloor Street Entrance', 2, [me(), seat(4, 'Nadia Petrov', 88)]),
        // Nine days ago, done.
        shift(at(-9, 10), 3, 'Level 1 Welcome Desk', 2, [me(39), seat(2, 'Hélène Barbeau', 44)]),
        // Thirty-five days ago — past the volunteer window, still blank, and on last
        // month's Schedule rather than this one.
        shift(at(-35, 13), 4, 'Level 1 Welcome Desk', 2, [me(), seat(3, 'Desmond Wray', 61)], 'July 2026'),
        // Today, in progress, ends at 17:00.
        shift(at(0, 13), 4, 'Level 1 Welcome Desk', 3, [me(), seat(2, 'Hélène Barbeau'), seat(5, 'Ivor Chan')]),
        // Today, earlier — the viewer was not on it.
        shift(at(0, 9), 4, 'Bloor Street Entrance', 2, [seat(4, 'Nadia Petrov', 26), seat(6, 'Priya Raman')]),
        // Later this week — nothing to record yet.
        shift(at(2, 13), 4, 'Level 1 Welcome Desk', 3, [me(), seat(5, 'Ivor Chan')]),
        shift(at(6, 10), 3, 'Bloor Street Entrance', 2, [seat(6, 'Priya Raman')]),
    ],
});

// ---------------------------------------------------------------------------
// Docents — two numbers. `Interactions` is legacy's second box, labelled
// "Visitor interactions excluding tour" (docent.php:518).
// ---------------------------------------------------------------------------
const docents = (): GroupFixture => ({
    key: 'docents',
    label: 'Docents',
    groupName: 'Docents',
    slug: 'docents',
    scheduleName: 'August 2026',
    collects: 'two',
    visitorLabel: 'Visitors on your tour',
    extraLabel: 'Other visitors you spoke with',
    shifts: [
        shift(at(-2, 11), 2, 'Highlights Tour', 2, [me(18, 6), seat(7, 'Colm Ferreira', 22, 0)]),
        shift(at(-4, 14), 2, 'Egypt Gallery Tour', 1, [me()]),
        shift(at(-11, 11), 2, 'Highlights Tour', 2, [me(24, 9), seat(8, 'Ruth Adeyemi')]),
        shift(at(-31, 14), 2, 'Dinosaur Tour', 1, [me()], 'July 2026'),
        shift(at(0, 15), 2, 'Highlights Tour', 2, [me(), seat(7, 'Colm Ferreira')]),
        shift(at(3, 11), 2, 'Egypt Gallery Tour', 2, [me(), seat(8, 'Ruth Adeyemi')]),
    ],
});

// ---------------------------------------------------------------------------
// Reception — collects nothing. Every variant has to disappear cleanly here.
// ---------------------------------------------------------------------------
const reception = (): GroupFixture => ({
    key: 'reception',
    label: 'Reception',
    groupName: 'Reception',
    slug: 'reception',
    scheduleName: 'August 2026',
    collects: 'none',
    visitorLabel: '',
    extraLabel: '',
    shifts: [
        shift(at(-2, 9), 4, 'Desk', 1, [me()]),
        shift(at(-9, 9), 4, 'Desk', 1, [me()]),
        shift(at(0, 13), 4, 'Desk', 1, [me()]),
        shift(at(2, 9), 4, 'Desk', 1, [seat(9, 'Jean-Paul Rousseau')]),
    ],
});

/**
 * One more outstanding shift, in a Group the viewer does not belong to — they took an
 * `open` Shift (ADR-0021 §4). Hardcoded rather than generated, because it exists to make
 * one point: only a personal, cross-Group surface can show this row at all.
 */
export const FOREIGN_OUTSTANDING = {
    groupName: 'Visitor Wayfinders',
    kind: 'Level 1 Oslo gate',
    when: 'Sat 15 Aug',
    daysLeft: 19,
};

export const GROUPS = [
    { key: 'guides', label: 'Visitor Guides' },
    { key: 'docents', label: 'Docents' },
    { key: 'reception', label: 'Reception' },
];

export const buildGroup = (key: string): GroupFixture => {
    nextShiftId = 1;
    nextSignUpId = 100;
    if (key === 'docents') return docents();
    if (key === 'reception') return reception();
    return visitorGuides();
};

export const viewerOf = (role: Viewer['role']): Viewer => ({ memberId: VIEWER_ID, name: VIEWER_NAME, role });

// --- Derivations every variant needs -----------------------------------------

export const hasEnded = (shift: Shift) => new Date(shift.endsAt).getTime() <= NOW.getTime();

/**
 * Legacy offers the box shortly before the nominal end so a volunteer can sign out on
 * their way out of the building — five minutes, per #404 Q4. Legacy computes the moment
 * as `55 * Count` minutes after the start, which was a workaround for not storing an end
 * time; ADR-0021 requires `ends_at`, so it is plain subtraction here.
 */
export const signOutOpen = (shift: Shift) => new Date(shift.endsAt).getTime() - 5 * MINUTE <= NOW.getTime();

export const daysSinceEnd = (shift: Shift) => Math.floor((NOW.getTime() - new Date(shift.endsAt).getTime()) / DAY);

export const isRecorded = (group: GroupFixture, signUp: SignUp) =>
    group.collects === 'none' || (signUp.visitorCount !== null && (group.collects !== 'two' || signUp.extraInteractionCount !== null));

/** The viewer's own past Sign-ups, with window state. Newest first. */
export const outstandingFor = (group: GroupFixture, viewer: Viewer): OutstandingShift[] =>
    group.shifts
        .flatMap((shift) => {
            const signUp = shift.signUps.find((s) => s.memberId === viewer.memberId);
            if (!signUp || !signOutOpen(shift)) return [];
            const days = Math.max(0, daysSinceEnd(shift));
            return [
                {
                    shift,
                    signUp,
                    inProgress: !hasEnded(shift),
                    daysAgo: days,
                    closed: days >= WINDOW_DAYS,
                    daysLeft: WINDOW_DAYS - days,
                    recorded: isRecorded(group, signUp),
                },
            ];
        })
        .sort((a, b) => new Date(b.shift.startsAt).getTime() - new Date(a.shift.startsAt).getTime());

/** Every past seat on *this* Schedule — the officer's close-out list. Newest first. */
export const allPastSeats = (group: GroupFixture) =>
    group.shifts
        .filter((shift) => shift.scheduleName === group.scheduleName)
        .filter(hasEnded)
        .flatMap((shift) => shift.signUps.map((signUp) => ({ shift, signUp, recorded: isRecorded(group, signUp) })))
        .sort((a, b) => new Date(b.shift.startsAt).getTime() - new Date(a.shift.startsAt).getTime());

// --- Formatting ---------------------------------------------------------------

const LOCALE = 'en-CA';
const TZ = 'America/Toronto';

export const formatTime = (iso: string) => new Intl.DateTimeFormat(LOCALE, { timeStyle: 'short', timeZone: TZ }).format(new Date(iso));

export const formatDay = (iso: string) =>
    new Intl.DateTimeFormat(LOCALE, { weekday: 'long', day: 'numeric', month: 'long', timeZone: TZ }).format(new Date(iso));

export const formatShortDay = (iso: string) =>
    new Intl.DateTimeFormat(LOCALE, { weekday: 'short', day: 'numeric', month: 'short', timeZone: TZ }).format(new Date(iso));

export const timeRange = (shift: Shift) => `${formatTime(shift.startsAt)} – ${formatTime(shift.endsAt)}`;
