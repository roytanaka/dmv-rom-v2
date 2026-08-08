// PROTOTYPE — throwaway. See #330. Do not merge; do not import from app code.
//
// The shape the map has already decided, expressed just far enough to draw:
//   Schedule (#324) — group, name, starts_on, ends_on, state (draft|published), description
//   Shift    (#326) — schedule_id, starts_at, ends_at, capacity, nullable shift_kind
//   Sign-up  (#327) — one Member on one Shift, created by the Member or a Scheduler
//
// Times are stored as `YYYY-MM-DDTHH:mm` local strings rather than real datetimes.
// That is a prototype shortcut: it keeps every date calculation trivial and timezone
// questions out of a drawing exercise. The real thing uses datetimes.

/** #327 — two values only. `open` widens the read audience beyond the Group. */
export type Audience = 'group' | 'open';

/** #324 — two states. Sign-ups close when a Shift's date passes, not by state. */
export type ScheduleState = 'draft' | 'published';

export interface Person {
    id: number;
    name: string;
}

export interface Signup {
    person: Person;
    /** #327 — same Sign-up, two actors. True when a Scheduler placed the Member. */
    assigned: boolean;
}

export interface Shift {
    id: number;
    /** `YYYY-MM-DDTHH:mm` */
    startsAt: string;
    endsAt: string;
    capacity: number;
    /** #326 — ShiftKind, the one descriptive axis. Nullable. */
    kind: string | null;
    audience: Audience;
    signups: Signup[];
    /**
     * Set only on a Shift belonging to another Group, surfaced here because its
     * audience is `open`. The map leaves *where these render* unspecified — that
     * is the fog entry this ticket was told it would hit concretely.
     */
    foreignGroup?: string;
}

export interface Schedule {
    id: number;
    name: string;
    /** `YYYY-MM-DD` */
    startsOn: string;
    endsOn: string;
    state: ScheduleState;
    description: string | null;
    shifts: Shift[];
}

/** The three audiences of point 3, as three states of one screen (#328). */
export type ViewerRole = 'member' | 'nonmember' | 'scheduler';

export interface Viewer {
    person: Person;
    role: ViewerRole;
}

export interface Dataset {
    key: string;
    label: string;
    groupName: string;
    groupSlug: string;
    /** The Schedule being viewed. */
    schedule: Schedule;
    /** The section's list state (#328) — every Schedule the Group has. */
    index: Array<Pick<Schedule, 'id' | 'name' | 'startsOn' | 'endsOn' | 'state'> & { shiftCount: number; openSlots: number }>;
    /** Kinds a Scheduler could pick from when adding a Shift (#326). */
    kinds: string[];
}
