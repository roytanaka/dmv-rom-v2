// PROTOTYPE (#405) — throwaway types for the after-shift entry variants.
//
// These mirror what #404 settled, not the eventual schema: a Sign-up carries two
// nullable integers, and whether a Group asks for them is a per-Group setting.

/** What a Group collects at sign-out. Per-Group, not per-ShiftKind (#404 Q6). */
export type Collects = 'none' | 'one' | 'two';

export type ViewerRole = 'volunteer' | 'officer';

export interface SignUp {
    id: number;
    memberId: number;
    memberName: string;
    /** Visitors this Member served on this Shift. Null means not recorded. */
    visitorCount: number | null;
    /** Visitors served outside a tour they led. Tour-leading Groups only. */
    extraInteractionCount: number | null;
}

export interface Shift {
    id: number;
    /** ISO instant. */
    startsAt: string;
    endsAt: string;
    kind: string;
    capacity: number;
    signUps: SignUp[];
    /**
     * Which Schedule this Shift belongs to. A Shift's `schedule_id` is mandatory and its
     * date must fall inside the Schedule's range (ADR-0021 §1, §2), so a shift more than
     * four weeks old is on a *different* Schedule from the current one — which is a real
     * cost of any variant whose entry point is a Schedule.
     */
    scheduleName: string;
}

export interface GroupFixture {
    key: string;
    label: string;
    /** As-authored Group name (ADR-0004) — never translated. */
    groupName: string;
    slug: string;
    scheduleName: string;
    collects: Collects;
    /** Label for the first box, as the volunteer reads it. */
    visitorLabel: string;
    /** Label for the second box. Only read when `collects === 'two'`. */
    extraLabel: string;
    shifts: Shift[];
}

export interface Viewer {
    memberId: number;
    name: string;
    role: ViewerRole;
}

/** A past Sign-up of the viewer's, with the window state worked out. */
export interface OutstandingShift {
    shift: Shift;
    signUp: SignUp;
    /** Still running, or ended within the last few minutes. */
    inProgress: boolean;
    /** Whole days since the shift ended; zero or less means today. */
    daysAgo: number;
    /** Past the 28-day volunteer window. An officer can still write. */
    closed: boolean;
    /** Days left in the volunteer window; zero or less once closed. */
    daysLeft: number;
    recorded: boolean;
}
