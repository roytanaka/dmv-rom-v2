// @phosphor-icons/vue ships no shared icon type — every icon is the same Vue
// component shape, so we alias one representative export as the icon type. The
// type-level import query keeps this purely a type (no runtime icon import).
export type PhosphorIcon = (typeof import('@phosphor-icons/vue'))['PhSquaresFour'];

export interface Auth {
    user: User;
    /**
     * Coarse, app-wide capability map for chrome/nav (ADR-0017). UI hints only —
     * the server enforces every action; a `false` here hides a control, it is
     * never the security boundary. Fine-grained per-resource `can` props are
     * passed per page.
     */
    can: {
        administerMembers: boolean;
    };
}

/**
 * A fixed global top-bar destination (#194). Server-shared: a stable `key`, a chrome
 * `labelKey` resolved client-side, and an `href` already localized to the active
 * locale (ADR-0008) — so it is used verbatim, never re-localized on the client.
 */
export interface ChromeDestination {
    key: string;
    labelKey: string;
    href: string;
}

/**
 * One Group row on the server-built grouping rail (PRD #209): an as-authored `name`
 * (content — rendered verbatim in both locales, never translated; ADR-0004), a stable
 * slug `groupId`, an `href` already localized server-side (ADR-0008), and any nested
 * subcommittees at full depth. The wire carries no icon — the client supplies the
 * interim placeholder.
 */
export interface RailGroupNode {
    groupId: string;
    name: string;
    href: string;
    children?: RailGroupNode[];
}

/**
 * A container-peer row heading the Other Groups zone (ADR-0020 §C): one of the four
 * organization-scope containers. Unlike a {@link RailGroupNode}, its label is CHROME —
 * a `labelKey` resolved client-side, not a verbatim Group name — because a peer is
 * structural scaffolding, not a member content Group. It keeps a server-localized
 * `href`, an optional `logo` (for the launcher), and its visible child Groups.
 */
export interface RailPeerNode {
    labelKey: string;
    href: string;
    logo?: string | null;
    children?: RailGroupNode[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: PhosphorIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    /** Active locale, resolved server-side from the URL (ADR-0008). */
    locale: string;
    /**
     * The organization's wall-clock timezone (an IANA name, e.g. `America/Toronto`).
     * Datetimes arrive as UTC instants and are formatted in *this* zone, never the
     * browser's, so a meeting reads at the same o'clock for every viewer.
     */
    timezone: string;
    /**
     * Per-locale URI-segment translation table (non-default locales only), used by
     * `useLocalizedHref` to keep English-canonical nav hrefs in the active locale
     * (ADR-0008). Keyed locale → { englishSegment: localisedSegment }.
     */
    routeSegments: Record<string, Record<string, string>>;
    /**
     * Top-bar language switcher: the active locale plus one option per supported
     * locale. Each option's `url` is the current page's twin in that locale, or
     * null when there is no twin (the active locale, or a page with no twin) —
     * those render disabled (ADR-0008 / #110).
     */
    localeSwitcher: {
        current: string;
        options: Array<{ code: string; label: string; url: string | null }>;
    };
    /**
     * Fixed global top-bar navigation (#194, ADR-0013 amendment): the cross-domain
     * destinations rendered identically on every page, distinct from a Group's
     * section set and the rail. `href` is localized server-side to the active locale
     * (ADR-0008); `labelKey` is resolved client-side via the i18n bridge. `help` is
     * the right-cluster utility destination.
     */
    chromeNav: {
        destinations: ChromeDestination[];
        help: ChromeDestination;
    };
    /**
     * The grouping rail (PRD #209), built and pruned server-side per signed-in
     * Member. The wire format carries plain Group rows (no icon — the client supplies
     * the interim placeholder); hrefs are localized server-side (ADR-0008), so the
     * client renders them verbatim. Zones absent from a Member's rail are omitted. My
     * Groups is flat; Other Groups carries the four organization-scope container peers
     * (ADR-0020 §C), each exploding one level to its visible Groups. Officer Tools (#212)
     * is the org-wide administration cluster: each item per-item gated by a real authority
     * server-side, the cluster omitted whole when none survive. Its labels are i18n keys
     * resolved client-side; its icons are fixed client config keyed by item `key`.
     */
    rail: {
        myGroups?: {
            labelKey: string;
            items: RailGroupNode[];
        };
        otherGroups?: {
            labelKey: string;
            items: RailPeerNode[];
        };
        officer?: {
            labelKey: string;
            items: Array<{ key: string; labelKey: string; href: string }>;
        };
    };
    /** Dev/QA role-switcher prop (ADR-0009). Null in production and for ordinary Members.
     *  Persona catalogue, grouping, and authority checks are entirely server-computed. */
    impersonation: {
        personas: Array<{
            key: string;
            label: string;
            personas: Array<{ email: string; name: string; descriptor: string }>;
        }>;
        active: { as: { name: string; descriptor: string }; operator: string } | null;
    } | null;
    /** Persisted sidebar open state, seeded from the `sidebar:state` cookie. */
    sidebarOpen: boolean;
    /**
     * Flashed one-shot output that survives a redirect (#362 / #363, PRD #352). A bulk
     * Shift or Sign-up run is N single writes plus a report; the controller flashes it and
     * this prop carries it into the next page's props so the Scheduler reads it as output,
     * not an error. Each report is null when no run flashed it (the common case).
     */
    flash: {
        shiftsBulk: BulkReport | null;
        assignmentsBulk: BulkReport | null;
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    // Self-service contact record (#232) — all optional. Shared on the signed-in
    // member (auth.user), so present for self-editing on Settings → Profile.
    phone: string | null;
    alternate_phone: string | null;
    business_phone: string | null;
    address_street: string | null;
    address_city: string | null;
    address_province: string | null;
    address_postal_code: string | null;
    address_country: string | null;
    // Public URL of the uploaded profile photo (#233), or null when none is set.
    // Appended server-side (Member::photoUrl) so the avatar renders from a ready URL.
    photo_url: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface RosterMember {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    group_roles: string[];
    group_standing: string;
    email?: string;
    phone?: string;
    // Officer roster CRUD targeting (#192) — the membership id, its leave window, and
    // whether it may be hard-removed (no dependent records). Inert for a non-officer.
    membership_id: number;
    loa_start: string | null;
    loa_end: string | null;
    can_hard_remove: boolean;
}

// The Roster tab's officer-CRUD scaffolding (#192) — withheld (empty) from a
// non-officer. Candidates are the add-member search source (id + name only).
export interface RosterCandidate {
    id: number;
    first_name: string;
    last_name: string;
}

export interface RosterMeta {
    candidates: RosterCandidate[];
    assignableRoles: string[];
    showingPast: boolean;
}

export interface MeetingLink {
    kind: string;
    url: string;
}

export interface Meeting {
    id: number;
    title: string;
    description: string | null;
    /** A UTC instant; format it in `SharedData['timezone']`, never the browser's. */
    held_at: string;
    /** Which block this meeting heads under — resolved server-side against one clock. */
    is_upcoming: boolean;
    location: string | null;
    video_url: string | null;
    is_published: boolean;
    links: MeetingLink[];
    // UI hints from the MeetingPolicy — drive the per-meeting officer affordances;
    // the server enforces every mutation regardless (#193).
    can: { update: boolean; delete: boolean };
}

// The Group Hours tab payload (#408, ADR-0022 §2). The viewer's own records for this
// Group and the two-month entry state — never another Member's hours (§4).
export interface HoursRecordRow {
    /** The YYYYMM bucket — the form value and the row key. */
    year_month: string;
    /** The first of the month as an ISO date, for formatting a localized month name. */
    month: string;
    scheduled_hours: number;
    extra_hours: number;
    total_hours: number;
    /** A UTC instant; format it in `SharedData['timezone']`. Null before first write. */
    updated_at: string | null;
}

export interface HoursMonth {
    /** The YYYYMM bucket entry writes against. */
    year_month: string;
    /** The first of the month as an ISO date, for the localized month label. */
    month: string;
    /** The extra hours already on file for this month — the additive base. */
    extra_hours: number;
    /** When the month's row was last touched; null when nothing is recorded yet. */
    updated_at: string | null;
}

export interface GroupHours {
    records: HoursRecordRow[];
    months: HoursMonth[];
}

// The My Hours destination payload (#409, ADR-0022 §8). The authenticated Member's own
// hours across every Group they have hours in, broken out by month across a fiscal year.
// The `scheduled`/`extra`/`total` split holds on every cell and on the year-to-date total.
export interface MyHoursTotals {
    scheduled_hours: number;
    extra_hours: number;
    total_hours: number;
}

// One month cell in a Group's twelve-month row. `scheduled_hours` reads zero until
// recalculation ships — honest, not broken (ADR-0022 §8).
export interface MyHoursCell extends MyHoursTotals {
    /** The YYYYMM bucket — the row key. */
    year_month: string;
}

export interface MyHoursGroupRow {
    id: number;
    /** The Group name, as-authored content — never a translation key. */
    name: string;
    /** Twelve cells in April-to-March order, aligned to the page's `months`. */
    months: MyHoursCell[];
    /** The sum of the twelve months — the renewal-question figure. */
    ytd: MyHoursTotals;
}

// A fiscal-year month column header. `month` is a first-of-month ISO date, formatted in
// UTC into a localized month name so it never slides a day into the month before.
export interface MyHoursMonthColumn {
    year_month: string;
    month: string;
}

export interface MyHours {
    /** The fiscal year in view, named for the year it ends in (ADR-0022 §8). */
    fiscalYear: number;
    /** The fiscal years the Member may pick, newest first. */
    fiscalYears: number[];
    months: MyHoursMonthColumn[];
    groups: MyHoursGroupRow[];
}

// The Group fiscal-year report payload (#411, ADR-0022 §5). A Member × twelve-month matrix
// with a year-to-date column, plus the Group's own hours next to its hours including every
// descendant. Reports are officer-only (§4), so this payload never reaches an ordinary peer.
export interface GroupHoursReportMember {
    id: number;
    /** The Member's name, as-authored — never a translation key. */
    name: string;
    /** Twelve cells in April-to-March order, aligned to the page's `months`. */
    months: MyHoursCell[];
    ytd: MyHoursTotals;
}

// One of the two side-by-side rollups: the Group's own hours, or its hours including every
// descendant. Twelve total-hour buckets aligned to `months`, and their sum.
export interface GroupHoursReportRollup {
    months: number[];
    ytd: number;
}

export interface GroupHoursReport {
    group: { id: number; name: string; slug: string };
    /** The fiscal year in view, named for the year it ends in (ADR-0022 §8). */
    fiscalYear: number;
    /** The fiscal years the viewer may pick, newest first. */
    fiscalYears: number[];
    months: MyHoursMonthColumn[];
    members: GroupHoursReportMember[];
    totals: { own: GroupHoursReportRollup; subtree: GroupHoursReportRollup };
}

// A Schedule row in the Scheduling tab's list (#353, ADR-0021 §1). A date-only range;
// `is_past` heads the two blocks (current & upcoming vs past), resolved server-side.
export interface ScheduleListItem {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    state: string;
    is_past: boolean;
    url: string;
    can: ScheduleAbilities;
}

// One seated Member on a Shift (#357, ADR-0017 §6) — name only, routed through
// MemberResource so contact PII stays gated. Visible to every reader who can read the
// Schedule, non-members included: a Schedule is a roster of who is on the floor.
export interface ShiftSignUp {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    // Officer removal (#359) — the seat's own Sign-up id, the remove target. Present only
    // for a schedule admin (a plain reader never learns another seat's id).
    signup_id?: number;
}

// A placeable Member in the officer-assignment picker (#359, ADR-0017 §6) — the Group's
// roster narrowed to Members who clear both sign-up floors, routed through MemberResource
// (name tier, contact suppressed). Withheld (empty) from a non-admin.
export interface PlacementCandidate {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    standing: string;
}

// A Shift on an opened Schedule — one slot the Agenda reads (#355, #357, ADR-0021 §2).
// `starts_at` / `ends_at` cross the wire as UTC instants and are read on the org wall
// clock (grouped by day client-side). `kind` is the ShiftKind name where the Group uses
// kinds, null for Reception's shape. `taken` is the filled-seat count and `capacity` the
// slot's integer size; `signups` are the seated Members. `signup_id` is the viewer's own
// seat (null if none) for a one-click drop, and `can.signUp` is the SignUpPolicy verdict
// folded with a free seat — the button shows only when a Sign-up would take.
export interface ShiftAgendaItem {
    id: number;
    starts_at: string;
    ends_at: string;
    capacity: number;
    taken: number;
    kind: string | null;
    // The Shift's own authored fields the edit form round-trips (#356 front end): its
    // `audience` (the discovery filter) and the id of its chosen kind (null for a kind-less
    // Shift), so the form pre-selects both rather than guessing from the display name.
    audience: string;
    shift_kind_id: number | null;
    signups: ShiftSignUp[];
    signup_id: number | null;
    // `signUp` is the self-service verdict; `assign` is the officer verdict — the
    // schedule-admin gate plus a free seat (capacity binds the Scheduler too, #359).
    // `update` / `delete` are the Shift authoring hints (#356 front end): `update` is the
    // schedule-admin gate, `delete` folds in the zero-Sign-ups rule. All false on a foreign
    // Shift, which carries no authoring affordances.
    can: { signUp: boolean; assign: boolean; update: boolean; delete: boolean };
}

// A foreign open Shift (#361, ADR-0021 §Sign-up) — another Group's `open` Shift a reader
// discovers on this Schedule. It is an ordinary Shift plus the one thing attribution needs:
// the owning Group's name. It never carries authoring affordances (`can.assign` is always
// false and its seats never carry a `signup_id`), and it is kept out of the own `shifts`
// list — never interleaved, always banded by owning Group.
export interface ForeignShiftItem extends ShiftAgendaItem {
    group_name: string;
}

// A Schedule opened directly — its read detail: name, range, state, as-authored
// description, its own Shifts as a flat, start-ordered list (the Agenda groups them by day
// on the org wall clock), and the foreign open Shifts other Groups advertise in its range.
export interface ScheduleDetail {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    state: string;
    description: string | null;
    can: ScheduleAbilities;
    shifts: ShiftAgendaItem[];
    foreign: ForeignShiftItem[];
}

// UI hints from the SchedulePolicy — drive the per-Schedule authoring affordances; the
// server enforces every mutation regardless (#354). `publish` / `unpublish` are the two
// `state` transitions, only one applicable at a time by the Schedule's current state.
export interface ScheduleAbilities {
    update: boolean;
    publish: boolean;
    unpublish: boolean;
    delete: boolean;
}

// The Scheduling tab's payload: the viewer's visible Schedules, and the one (if any)
// that opens directly. Exactly one of `schedules` / `open` is populated at a time.
export interface Scheduling {
    schedules: ScheduleListItem[];
    open: ScheduleDetail | null;
    // The officer-assignment picker's roster (#359) — placeable Members for the opened
    // Schedule, present only for a schedule admin. Empty for a plain reader and on the list.
    roster: PlacementCandidate[];
    // The Group's kind vocabulary for the Shift authoring form's kind picker (#356 front
    // end) — id and name of each active ShiftKind. Present only for a schedule admin on an
    // opened Schedule; empty for a plain reader and on the list.
    shift_kinds: ShiftKind[];
}

// One option in the Shift form's kind picker (#356 front end, ADR-0021 §3) — a Group's
// ShiftKind by id and as-authored name. Names are officer-authored content, never
// translated (ADR-0004).
export interface ShiftKind {
    id: number;
    name: string;
}

// A bulk run's report (#362 / #363 front end, PRD #352) — flashed by the controller and
// carried to the page in `SharedData['flash']`. A run is N single writes plus this report:
// the count written or removed (exactly one of `created` / `deleted` / `removed`, by
// action) and every row it skipped. Each skip carries its own translated `reason` lang key
// and the identifier the row was skipped on — a `date` for a bulk-create day outside the
// range, a `shift_id` for a bulk-delete match that could not be removed.
export interface BulkReport {
    created?: number;
    deleted?: number;
    removed?: number;
    skipped: BulkSkip[];
}

export interface BulkSkip {
    date?: string;
    shift_id?: number;
    reason: string;
}
