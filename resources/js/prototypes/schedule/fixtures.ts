// PROTOTYPE — throwaway. See #330.
//
// Four fixtures, chosen so the layouts are judged against the real density Adrian
// described on the 2026-08-07 walkthrough rather than an imagined one:
//
//   reception  — the simplest shape in the system, and his pick for "most basic".
//   guides     — the dense month: most days, three bands, two kinds. ~80 Shifts.
//                Carries the `open` Shifts from another Group's event (#327).
//   wayfinders — the date-range event: 3 days × 4 activities × 3 bands. The
//                "three-dimensional intersect" a flat table is supposed to defeat.
//   empty      — a draft with no Shifts. The Scheduler's actual first screen, and
//                the one state with no legacy prior art (legacy renders nothing
//                until a Schedule is generated).
//
// Everything is generated from a fixed seed, so a reload shows the same schedule
// and two people looking at the same URL see the same thing.
import type { Dataset, Person, Schedule, Shift, Signup } from './types';

// ---------------------------------------------------------------- deterministic rng

function mulberry32(seed: number): () => number {
    let a = seed;
    return () => {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

// Invented volunteers. The repo is public; nobody real is named here.
const NAMES = [
    'Margaret Chen',
    'David Osei',
    'Priya Raman',
    'Helen Kowalczyk',
    'Robert Lindqvist',
    'Anne-Marie Tremblay',
    'Sunil Bhatt',
    'Joyce Whitfield',
    'Tomás Aguilar',
    'Barbara Nkemelu',
    'Frank Delaney',
    'Yuki Mori',
    'Elaine Fortescue',
    'Michael Adeyemi',
    'Rosa Villanueva',
    'Peter Vandenberg',
    'Nadia Haddad',
    'Colin Brightwater',
    'Shirley Tam',
    'George Papadakis',
    'Ingrid Solheim',
    'Amara Diallo',
];

const PEOPLE: Person[] = NAMES.map((name, i) => ({ id: i + 2, name }));

/** The signed-in volunteer, on every fixture. Id 1 so `isViewer` is a cheap check. */
export const VIEWER_PERSON: Person = { id: 1, name: 'Frances Bellwood' };

// ---------------------------------------------------------------- date helpers

const pad = (n: number) => String(n).padStart(2, '0');

const dateStr = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/** Every date in `[from, to]`, inclusive. */
function eachDay(from: string, to: string): string[] {
    const out: string[] = [];
    const cursor = new Date(`${from}T00:00`);
    const end = new Date(`${to}T00:00`);
    while (cursor <= end) {
        out.push(dateStr(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }
    return out;
}

const weekdayOf = (date: string) => new Date(`${date}T00:00`).getDay();

// ---------------------------------------------------------------- shift building

interface Band {
    from: string;
    to: string;
    kind: string;
    capacity: number;
    /** 0–1: how much of this Shift tends to be taken. */
    fill: number;
}

let nextShiftId = 1;

function buildShift(date: string, band: Band, rng: () => number, viewerChance: number): Shift {
    const taken = Math.min(band.capacity, Math.round(band.capacity * band.fill * (0.55 + rng() * 0.75)));
    const signups: Signup[] = [];
    const used = new Set<number>();

    for (let i = 0; i < taken; i++) {
        // A Scheduler pre-books roughly a third of the seats — Adrian's "officer
        // assignment", which #327 confirmed is the same Sign-up with a second actor.
        const assigned = rng() < 0.35;
        let person: Person;
        if (signups.length === 0 && rng() < viewerChance) {
            person = VIEWER_PERSON;
        } else {
            let candidate = PEOPLE[Math.floor(rng() * PEOPLE.length)];
            let guard = 0;
            while (used.has(candidate.id) && guard++ < 12) {
                candidate = PEOPLE[Math.floor(rng() * PEOPLE.length)];
            }
            person = candidate;
        }
        if (used.has(person.id)) continue;
        used.add(person.id);
        signups.push({ person, assigned: person.id === VIEWER_PERSON.id ? false : assigned });
    }

    return {
        id: nextShiftId++,
        startsAt: `${date}T${band.from}`,
        endsAt: `${date}T${band.to}`,
        capacity: band.capacity,
        kind: band.kind,
        audience: 'group',
        signups,
    };
}

// ---------------------------------------------------------------- fixtures

function receptionSchedule(): Schedule {
    const rng = mulberry32(20261101);
    const bands: Band[] = [
        { from: '10:00', to: '13:00', kind: 'Front desk', capacity: 2, fill: 0.8 },
        { from: '13:00', to: '16:00', kind: 'Front desk', capacity: 2, fill: 0.6 },
    ];
    const shifts: Shift[] = [];
    for (const date of eachDay('2026-11-01', '2026-11-30')) {
        // Tuesday, Thursday, Saturday.
        if (![2, 4, 6].includes(weekdayOf(date))) continue;
        for (const band of bands) shifts.push(buildShift(date, band, rng, 0.18));
    }
    return {
        id: 101,
        name: 'November 2026',
        startsOn: '2026-11-01',
        endsOn: '2026-11-30',
        state: 'published',
        description: 'Front desk coverage, Tuesdays / Thursdays / Saturdays. Two volunteers per shift.',
        shifts,
    };
}

function guidesSchedule(): Schedule {
    const rng = mulberry32(778899);
    const bands: Band[] = [
        { from: '10:00', to: '13:00', kind: 'Desk', capacity: 2, fill: 0.85 },
        { from: '13:00', to: '16:00', kind: 'Desk', capacity: 2, fill: 0.7 },
        { from: '10:30', to: '13:30', kind: 'Roving', capacity: 3, fill: 0.5 },
    ];
    const shifts: Shift[] = [];
    for (const date of eachDay('2026-11-01', '2026-11-30')) {
        // Closed Mondays.
        if (weekdayOf(date) === 1) continue;
        for (const band of bands) {
            // The late Roving band only runs Friday–Sunday.
            if (band.kind === 'Roving' && ![5, 6, 0].includes(weekdayOf(date))) continue;
            shifts.push(buildShift(date, band, rng, 0.1));
        }
    }

    // #327's `open` audience, drawn concretely: a Visitor Wayfinders event pads the
    // building on three days, and those Shifts are open to anyone who can see them.
    // Legacy solved this by writing duplicate rows into the Visitor Guides schedule.
    for (const date of ['2026-11-14', '2026-11-15', '2026-11-21']) {
        for (const band of [
            { from: '10:00', to: '13:00', kind: 'Rotunda greeter', capacity: 5, fill: 0.4 },
            { from: '13:00', to: '16:00', kind: 'Rotunda greeter', capacity: 5, fill: 0.3 },
        ] as Band[]) {
            const shift = buildShift(date, band, rng, 0.05);
            shift.audience = 'open';
            shift.foreignGroup = 'Visitor Wayfinders';
            shifts.push(shift);
        }
    }

    return {
        id: 201,
        name: 'November 2026',
        startsOn: '2026-11-01',
        endsOn: '2026-11-30',
        state: 'published',
        description: null,
        shifts,
    };
}

function wayfindersSchedule(): Schedule {
    const rng = mulberry32(424242);
    const kinds: Array<[string, number, number]> = [
        // kind, capacity, fill
        ['Rotunda greeter', 5, 0.6],
        ['Level 2 landing', 3, 0.5],
        ['Coat check', 2, 0.75],
        ['Bloor entrance', 3, 0.35],
    ];
    const times: Array<[string, string]> = [
        ['09:30', '12:30'],
        ['12:30', '15:30'],
        ['15:30', '18:00'],
    ];
    const shifts: Shift[] = [];
    for (const date of eachDay('2026-12-27', '2026-12-29')) {
        for (const [kind, capacity, fill] of kinds) {
            for (const [from, to] of times) {
                // Coat check does not run the late band.
                if (kind === 'Coat check' && from === '15:30') continue;
                shifts.push(buildShift(date, { from, to, kind, capacity, fill }, rng, 0.12));
            }
        }
    }
    return {
        id: 301,
        name: 'Winter Family Weekend',
        startsOn: '2026-12-27',
        endsOn: '2026-12-29',
        state: 'published',
        description: 'Three days, four positions, three shifts a day. Extra hands welcome on the Monday.',
        shifts,
    };
}

function emptySchedule(): Schedule {
    return {
        id: 102,
        name: 'December 2026',
        startsOn: '2026-12-01',
        endsOn: '2026-12-31',
        state: 'draft',
        description: null,
        shifts: [],
    };
}

const openSlots = (s: Schedule) => s.shifts.reduce((n, shift) => n + Math.max(0, shift.capacity - shift.signups.length), 0);

const summarise = (s: Schedule) => ({
    id: s.id,
    name: s.name,
    startsOn: s.startsOn,
    endsOn: s.endsOn,
    state: s.state,
    shiftCount: s.shifts.length,
    openSlots: openSlots(s),
});

/** Rebuild a dataset from scratch — sign-ups in the prototype are mutated in place. */
export function buildDataset(key: string): Dataset {
    nextShiftId = 1;

    if (key === 'guides') {
        const schedule = guidesSchedule();
        return {
            key,
            label: 'Visitor Guides — a dense month',
            groupName: 'Visitor Guides',
            groupSlug: 'visitor-guides',
            schedule,
            index: [
                summarise(schedule),
                { id: 202, name: 'October 2026', startsOn: '2026-10-01', endsOn: '2026-10-31', state: 'published', shiftCount: 74, openSlots: 0 },
            ],
            kinds: ['Desk', 'Roving'],
        };
    }

    if (key === 'wayfinders') {
        const schedule = wayfindersSchedule();
        return {
            key,
            label: 'Visitor Wayfinders — a three-day event',
            groupName: 'Visitor Wayfinders',
            groupSlug: 'visitor-wayfinders',
            schedule,
            index: [
                summarise(schedule),
                {
                    id: 302,
                    name: 'Doors Open Toronto',
                    startsOn: '2026-05-23',
                    endsOn: '2026-05-24',
                    state: 'published',
                    shiftCount: 22,
                    openSlots: 0,
                },
                { id: 303, name: 'March Break 2027', startsOn: '2027-03-13', endsOn: '2027-03-21', state: 'draft', shiftCount: 0, openSlots: 0 },
            ],
            kinds: ['Rotunda greeter', 'Level 2 landing', 'Coat check', 'Bloor entrance'],
        };
    }

    if (key === 'empty') {
        const schedule = emptySchedule();
        const november = receptionSchedule();
        return {
            key,
            label: 'A draft with no Shifts',
            groupName: 'Reception',
            groupSlug: 'reception',
            schedule,
            index: [summarise(november), summarise(schedule)],
            kinds: ['Front desk'],
        };
    }

    const schedule = receptionSchedule();
    return {
        key: 'reception',
        label: 'Reception — the simplest month',
        groupName: 'Reception',
        groupSlug: 'reception',
        schedule,
        index: [
            summarise(schedule),
            summarise(emptySchedule()),
            { id: 103, name: 'October 2026', startsOn: '2026-10-01', endsOn: '2026-10-31', state: 'published', shiftCount: 26, openSlots: 0 },
        ],
        kinds: ['Front desk'],
    };
}

export const DATASETS = [
    { key: 'reception', label: 'Reception' },
    { key: 'guides', label: 'Visitor Guides' },
    { key: 'wayfinders', label: 'Wayfinders event' },
    { key: 'empty', label: 'Empty draft' },
] as const;
