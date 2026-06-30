/**
 * Chrome nav — stub fixture (representative, NOT exhaustive).
 *
 * A single typed source for the grouping rail so wiring real data later is a fixture
 * swap, not a rewrite. It models the two-layer / three-zone shape (see types.ts):
 * a few My Groups, the collapsible All Groups browse list (several with nested
 * subcommittees), and the Zone C officer/admin cluster.
 *
 * The Group nodes below are transcribed from `database/seeders/DemoSeeder.php` — the
 * curated DMV org tree (PRD #139) — so the rail reads like the real org. `groupId`s
 * match each node's seeder slug (`Str::slug(name)`, or the explicit slug the seeder
 * assigns to disambiguate recurring names like "Training"). This is still a SUBSET:
 * archived/stale cohorts and the org-level container sections (Governance &
 * Operations, Programs, Special Projects) are omitted — they aren't rail destinations.
 *
 * The exhaustive catalogue (every Program, its capabilities, all officer items) is the
 * forthcoming `docs/nav-spec.md`, the eventual source of truth. Swapping this fixture
 * for a server-shared Inertia prop driven by real Group/role data is a later slice.
 *
 * Structural labels are i18n KEYS (`labelKey`, resolved via the i18n bridge). Group
 * NAMES are CONTENT (`name`): authored strings rendered verbatim in both locales,
 * never resolved through the translator (ADR-0004). French-named Groups (Guides du
 * ROM, Les Amis Francophiles) sit here as literals and read identically under `/fr/`.
 */

import {
    PhAddressBook,
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

// Zone B — My Groups. The Groups this Volunteer belongs to: two org-level standing
// committees (Communications, System Services — both under Governance & Operations
// in the seeder) and the Gallery Interpreters program. Flat, as they are in the
// curated tree — nesting is exercised by the All Groups list below.
const myGroups: GroupNode[] = [
    {
        groupId: 'communications',
        name: 'Communications',
        href: '/groups/communications',
        icon: PhMegaphone,
    },
    {
        groupId: 'system-services',
        name: 'System Services',
        href: '/groups/system-services',
        icon: PhGearSix,
    },
    {
        groupId: 'gallery-interpreters',
        name: 'Gallery Interpreters',
        href: '/groups/gallery-interpreters',
        icon: PhUsersThree,
    },
];

// Zone B — All Groups (browse the rest of the org). A representative subset of the
// curated Programs and Friends-of committees from DemoSeeder: several carry
// subcommittees — a Group whose parent is another Group (ADR-0010 § "'Subcommittee'
// is not a separate noun") — so the collapsed-by-default browse list exercises the
// rail's expand/collapse. Archived/stale cohorts are omitted (dead pools, not nav
// destinations). `groupId`s match seeder slugs; the explicit `*-training` slugs
// mirror the seeder's disambiguation of names that recur across the tree.
const allGroups: GroupNode[] = [
    // The org root: the DMV at large (slug `dmv` in the seeder). Its roster is every
    // volunteer — the one Group everyone belongs to — so it leads the browse list.
    {
        groupId: 'dmv',
        name: 'DMV',
        href: '/groups/dmv',
        icon: PhUsersThree,
    },
    // Docents keeps the one fully-populated Group Menu (see groupMenus below); its
    // cohorts are all archived in the seeder, so it renders flat here.
    {
        groupId: 'docents',
        name: 'Docents',
        href: '/groups/docents',
        icon: PhUsersThree,
    },
    // GDR — a French-named Group. Its `name` is content, so it reads identically
    // ("Guides du ROM") under `/` and `/fr/` with no special-casing (ADR-0004).
    {
        groupId: 'guides-du-rom',
        name: 'Guides du ROM',
        href: '/groups/guides-du-rom',
        icon: PhUsersThree,
    },
    // Another French-named Group — same as-authored rule, no special case.
    {
        groupId: 'les-amis-francophiles',
        name: 'Les Amis Francophiles',
        href: '/groups/les-amis-francophiles',
        icon: PhUsersThree,
    },
    {
        groupId: 'dmv-hands-on-tours',
        name: 'DMV Hands-on Tours',
        href: '/groups/dmv-hands-on-tours',
        icon: PhUsersThree,
        children: [
            { groupId: 'hands-on-tours-social', name: 'Social', href: '/groups/dmv-hands-on-tours/social' },
            { groupId: 'hands-on-tours-training', name: 'Training', href: '/groups/dmv-hands-on-tours/training' },
            { groupId: 'vetting', name: 'Vetting', href: '/groups/dmv-hands-on-tours/vetting' },
        ],
    },
    {
        groupId: 'romforyou',
        name: 'ROMForYou',
        href: '/groups/romforyou',
        icon: PhUsersThree,
        children: [
            { groupId: 'content-development', name: 'Content Development', href: '/groups/romforyou/content-development' },
            { groupId: 'team-leads-adult-presentations', name: 'Team Leads — adult presentations', href: '/groups/romforyou/team-leads' },
            { groupId: 'outreach', name: 'Outreach', href: '/groups/romforyou/outreach' },
            { groupId: 'adapted-presentations', name: 'Adapted Presentations', href: '/groups/romforyou/adapted-presentations' },
        ],
    },
    {
        groupId: 'visitor-wayfinders',
        name: 'Visitor Wayfinders',
        href: '/groups/visitor-wayfinders',
        icon: PhUsersThree,
        children: [
            { groupId: 'documentation', name: 'Documentation', href: '/groups/visitor-wayfinders/documentation' },
            {
                groupId: 'shadow-shift-vetting-volunteers',
                name: 'Shadow Shift & Vetting Volunteers',
                href: '/groups/visitor-wayfinders/shadow-shift-vetting',
            },
            { groupId: 'social-committee', name: 'Social Committee', href: '/groups/visitor-wayfinders/social-committee' },
        ],
    },
    {
        groupId: 'romwalks',
        name: 'ROMWalks',
        href: '/groups/romwalks',
        icon: PhUsersThree,
        children: [
            { groupId: 'brochure-committee', name: 'Brochure Committee', href: '/groups/romwalks/brochure-committee' },
            { groupId: 'education', name: 'Education', href: '/groups/romwalks/education' },
            { groupId: 'pr-committee', name: 'PR Committee', href: '/groups/romwalks/pr-committee' },
            { groupId: 'script-vetting', name: 'Script Vetting', href: '/groups/romwalks/script-vetting' },
            { groupId: 'statistical', name: 'Statistical', href: '/groups/romwalks/statistical' },
            { groupId: 'romwalks-training', name: 'Training', href: '/groups/romwalks/training' },
            { groupId: 'walker-vetting', name: 'Walker Vetting', href: '/groups/romwalks/walker-vetting' },
        ],
    },
    {
        groupId: 'romtravel',
        name: 'ROMTravel',
        href: '/groups/romtravel',
        icon: PhUsersThree,
        children: [
            { groupId: 'admin-committee', name: 'Admin Committee', href: '/groups/romtravel/admin-committee' },
            { groupId: 'feasibility-committee', name: 'Feasibility Committee', href: '/groups/romtravel/feasibility-committee' },
            { groupId: 'support-roles', name: 'Support Roles', href: '/groups/romtravel/support-roles' },
        ],
    },
    // A Friends-of standing committee with its own sub-groups.
    {
        groupId: 'friends-of-textiles-costume',
        name: 'Friends of Textiles & Costume',
        href: '/groups/friends-of-textiles-costume',
        icon: PhUsersThree,
        children: [
            { groupId: 'adopt-a-journal', name: 'Adopt-a-Journal', href: '/groups/friends-of-textiles-costume/adopt-a-journal' },
            { groupId: 'donor-friends', name: 'Donor Friends', href: '/groups/friends-of-textiles-costume/donor-friends' },
            { groupId: 'education-subcommittee', name: 'Education SubCommittee', href: '/groups/friends-of-textiles-costume/education' },
            { groupId: 'newsletter-subcommittee', name: 'Newsletter SubCommittee', href: '/groups/friends-of-textiles-costume/newsletter' },
            { groupId: 'programs-events', name: 'Programs & Events', href: '/groups/friends-of-textiles-costume/programs-events' },
        ],
    },
];

// ── Top-bar section tabs (the Group Menu layer) ──────────────────────────────
// The rail picks the *context*; these drive the top-bar tab strip that reflects it.

// Zone A — personal/global. The Dashboard tab set. Renew is in the avatar menu (#196).
export const zoneA: NavNode[] = [
    { labelKey: 'nav.personal.calendar', href: '/calendar', icon: PhCalendarBlank },
    { labelKey: 'nav.personal.hours', href: '/hours', icon: PhClock },
    { labelKey: 'nav.personal.directory', href: '/directory', icon: PhAddressBook },
    { labelKey: 'nav.personal.documents', href: '/documents', icon: PhFolder },
    { labelKey: 'nav.personal.news', href: '/news', icon: PhNewspaper },
    { labelKey: 'nav.personal.profile', href: '/profile', icon: PhUserCircle },
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
        { labelKey: 'section.about', href: '/groups/docents/about', icon: PhInfo },
        { labelKey: 'section.docents.roster', href: '/groups/docents/roster', icon: PhUsers },
        { labelKey: 'section.docents.schedule', href: '/groups/docents/schedule', icon: PhCalendarBlank, requiresCapability: 'scheduling' },
        { labelKey: 'section.docents.catalog', href: '/groups/docents/data-sheets', icon: PhFileText, requiresCapability: 'content' },
        { labelKey: 'section.docents.publications', href: '/groups/docents/publications', icon: PhBookOpenText, requiresCapability: 'documents' },
        { labelKey: 'section.docents.meetings', href: '/groups/docents/meetings', icon: PhPresentation, requiresCapability: 'meetings' },
        { labelKey: 'section.docents.statistics', href: '/groups/docents/statistics', icon: PhChartBar, requiresCapability: 'stats' },
        // Officer-only slot — role-gated (stubbed show-all for now).
        { labelKey: 'section.docents.schedule_admin', href: '/groups/docents/schedule/admin', icon: PhGearSix, requiresRole: 'chair' },
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
