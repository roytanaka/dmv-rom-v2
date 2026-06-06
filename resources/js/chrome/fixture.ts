/**
 * Chrome nav — stub fixture (representative, NOT exhaustive).
 *
 * A single typed source for the grouping rail so wiring real data later is a fixture
 * swap, not a rewrite. It models the two-layer / three-zone shape (see types.ts):
 * a couple of sample My Groups (one with nested subcommittees), a sample All Groups
 * browse entry, and the Zone C officer/admin cluster.
 *
 * The exhaustive catalogue (every Program, its capabilities, all officer items, the
 * dropped programs) is OUT OF SCOPE here — it lives in the forthcoming
 * `docs/nav-spec.md`, the eventual source of truth. Swapping this fixture for a
 * server-shared Inertia prop driven by real Group/role data is a later slice.
 *
 * Labels are i18n KEYS (resolved via messages.ts), never final copy.
 */

import {
    PhAddressBook,
    PhArrowSquareOut,
    PhBookOpenText,
    PhBuildings,
    PhCalendarBlank,
    PhChartBar,
    PhChatCircleDots,
    PhClock,
    PhFileText,
    PhFolder,
    PhGear,
    PhGearSix,
    PhInfo,
    PhMegaphone,
    PhNewspaper,
    PhPresentation,
    PhUserCircle,
    PhUsers,
    PhUsersThree,
} from '@phosphor-icons/vue';
import type { GroupNode, LauncherGrid, NavNode, RailNav } from './types';

// Zone B — My Groups. Docents carries subcommittees that nest under it; the
// `requiresRole: 'chair'` child shows the gating contract living in the data
// (stubbed show-all → visible for now).
const myGroups: GroupNode[] = [
    {
        groupId: 'docents',
        labelKey: 'nav.group.docents',
        href: '/groups/docents',
        icon: PhUsersThree,
        children: [
            {
                groupId: 'docents-school-visits',
                labelKey: 'nav.group.docents.school_visits',
                href: '/groups/docents/school-visits',
            },
            {
                groupId: 'docents-public-tours',
                labelKey: 'nav.group.docents.public_tours',
                href: '/groups/docents/public-tours',
            },
        ],
    },
    {
        groupId: 'gallery-interpreters',
        labelKey: 'nav.group.gallery_interpreters',
        href: '/groups/gallery-interpreters',
        icon: PhUsersThree,
    },
];

// Zone B — All Groups (browse the rest of the org). Representative stub entries:
// some carry subcommittees — a Group whose parent is another Group (ADR-0010 §
// "'Subcommittee' is not a separate noun") — so the collapsed-by-default browse
// list exercises the rail's expand/collapse. Names are PLACEHOLDERS; the real
// catalogue is out of band in docs/nav-spec.md.
const allGroups: GroupNode[] = [
    {
        groupId: 'gallery-guides',
        labelKey: 'nav.group.gallery_guides',
        href: '/groups/gallery-guides',
        icon: PhUsersThree,
        children: [
            { groupId: 'gallery-guides-highlights', labelKey: 'nav.group.gallery_guides.highlights', href: '/groups/gallery-guides/highlights' },
            { groupId: 'gallery-guides-family', labelKey: 'nav.group.gallery_guides.family', href: '/groups/gallery-guides/family' },
            { groupId: 'gallery-guides-access', labelKey: 'nav.group.gallery_guides.access', href: '/groups/gallery-guides/access' },
        ],
    },
    {
        groupId: 'special-events',
        labelKey: 'nav.group.special_events',
        href: '/groups/special-events',
        icon: PhUsersThree,
        children: [
            { groupId: 'special-events-openings', labelKey: 'nav.group.special_events.openings', href: '/groups/special-events/openings' },
            { groupId: 'special-events-previews', labelKey: 'nav.group.special_events.previews', href: '/groups/special-events/previews' },
        ],
    },
    {
        groupId: 'reception',
        labelKey: 'nav.group.reception',
        href: '/groups/reception',
        icon: PhUsersThree,
    },
    {
        groupId: 'romwalks',
        labelKey: 'nav.group.romwalks',
        href: '/groups/romwalks',
        icon: PhUsersThree,
    },
];

// ── Top-bar section tabs (the Group Menu layer) ──────────────────────────────
// The rail picks the *context*; these drive the top-bar tab strip that reflects it.

// Zone A — personal/global. The tab set on the Dashboard (no Group selected).
// `Renew Membership` leaves the app for the ROM renewal site (external link).
export const zoneA: NavNode[] = [
    { labelKey: 'nav.personal.calendar', href: '/calendar', icon: PhCalendarBlank },
    { labelKey: 'nav.personal.hours', href: '/hours', icon: PhClock },
    { labelKey: 'nav.personal.directory', href: '/directory', icon: PhAddressBook },
    { labelKey: 'nav.personal.documents', href: '/documents', icon: PhFolder },
    { labelKey: 'nav.personal.news', href: '/news', icon: PhNewspaper },
    { labelKey: 'nav.personal.profile', href: '/profile', icon: PhUserCircle },
    // STUB renewal URL — the real ROM membership-renewal destination drops in later.
    { labelKey: 'nav.personal.renew', href: 'https://www.rom.on.ca/en/join-give/membership', icon: PhArrowSquareOut, external: true },
];

// Group Menus, keyed by `GroupNode.groupId`. A Group's tab strip is its capability
// slots × per-program labels: the SAME slot reads differently per Group (Catalog →
// "Data Sheets" for Docents), so labels are i18n keys, never hard-coded. `About` is
// always present; the capability/role slots are gated (stubbed show-all for now).
//
// ONE fully-populated sample (`docents`) — the exhaustive per-Group catalogue lives
// in docs/nav-spec.md. Other Groups resolve to an empty menu until they're modelled.
export const groupMenus: Record<string, NavNode[]> = {
    docents: [
        { labelKey: 'nav.section.about', href: '/groups/docents/about', icon: PhInfo },
        { labelKey: 'nav.section.docents.roster', href: '/groups/docents/roster', icon: PhUsers },
        { labelKey: 'nav.section.docents.schedule', href: '/groups/docents/schedule', icon: PhCalendarBlank, requiresCapability: 'scheduling' },
        { labelKey: 'nav.section.docents.catalog', href: '/groups/docents/data-sheets', icon: PhFileText, requiresCapability: 'content' },
        { labelKey: 'nav.section.docents.publications', href: '/groups/docents/publications', icon: PhBookOpenText, requiresCapability: 'documents' },
        { labelKey: 'nav.section.docents.meetings', href: '/groups/docents/meetings', icon: PhPresentation, requiresCapability: 'meetings' },
        { labelKey: 'nav.section.docents.statistics', href: '/groups/docents/statistics', icon: PhChartBar, requiresCapability: 'stats' },
        // Officer-only slot — role-gated (stubbed show-all for now).
        { labelKey: 'nav.section.docents.schedule_admin', href: '/groups/docents/schedule/admin', icon: PhGearSix, requiresRole: 'chair' },
    ],
};

// ── Dashboard launcher (the legacy home grid) ────────────────────────────────
// The same Zone B Groups the rail lists, rendered as the landing-page tile grid.
// My Groups is always shown; the All Groups grid is gated to super-tier officers
// (President / VP1 / VP2) so org-wide navigation is reachable from landing —
// fixture-modelled now, gated for real later (stubbed show-all → visible for now).
export const launcherGrids: LauncherGrid[] = [
    { labelKey: 'nav.rail.my_groups', items: myGroups },
    { labelKey: 'nav.rail.all_groups', items: allGroups, requiresRole: 'super-tier' },
];

export const railNav: RailNav = {
    myGroups: {
        labelKey: 'nav.rail.my_groups',
        items: myGroups,
    },
    allGroups: {
        labelKey: 'nav.rail.all_groups',
        items: allGroups,
        collapsible: true,
        defaultOpen: false,
    },
    // Zone C — officer/admin. Each item requires the `officer` role; the section as a
    // whole renders only when at least one item survives gating (stubbed → all do).
    officer: {
        labelKey: 'nav.rail.officer',
        items: [
            { labelKey: 'nav.officer.members', href: '/officer/members', icon: PhBuildings, requiresRole: 'officer' },
            { labelKey: 'nav.officer.communications', href: '/officer/communications', icon: PhMegaphone, requiresRole: 'officer' },
            { labelKey: 'nav.officer.reports', href: '/officer/reports', icon: PhChartBar, requiresRole: 'officer' },
            { labelKey: 'nav.officer.flash_messages', href: '/officer/flash-messages', icon: PhChatCircleDots, requiresRole: 'officer' },
            { labelKey: 'nav.officer.dmv_settings', href: '/officer/settings', icon: PhGear, requiresRole: 'officer' },
        ],
    },
};
