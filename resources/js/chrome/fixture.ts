/**
 * Group-Menu fixture — the per-Group section-tab catalogue (STUBBED).
 *
 * The grouping rail (My Groups · All Groups · Officer Tools) is server-driven and
 * server-pruned (PRD #209, ADR-0018): it arrives on the shared `rail` Inertia prop,
 * already localized and carrying only the nodes the Member may see. The rail's old
 * client fixtures (`myGroups`, `allGroups`, `railNav`, `launcherGrids`) and the
 * show-all gating stub are gone.
 *
 * What remains here is the *Group Menu* layer — the top-bar section tabs revealed
 * INSIDE a Group (see types.ts). That surface is legitimately deferred: its
 * per-capability / per-role gating waits on its dependent features, so it ships as a
 * representative stub (one fully-populated sample, `docents`) until the exhaustive
 * catalogue lands in `docs/nav-spec.md`, the eventual source of truth.
 *
 * Structural labels are i18n KEYS (`labelKey`, resolved via the i18n bridge). The
 * capability/role slots carry `requiresCapability` / `requiresRole`; those gating
 * fields live on the section-tab node only (ADR-0018), not on the rail wire.
 */

import { PhBookOpenText, PhCalendarBlank, PhChartBar, PhFileText, PhGearSix, PhInfo, PhPresentation } from '@phosphor-icons/vue';
import type { NavNode } from './types';

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
        { labelKey: 'section.docents.schedule', href: '/groups/docents/schedule', icon: PhCalendarBlank, requiresCapability: 'scheduling' },
        { labelKey: 'section.docents.catalog', href: '/groups/docents/data-sheets', icon: PhFileText, requiresCapability: 'content' },
        { labelKey: 'section.docents.publications', href: '/groups/docents/publications', icon: PhBookOpenText, requiresCapability: 'documents' },
        { labelKey: 'section.docents.meetings', href: '/groups/docents/meetings', icon: PhPresentation, requiresCapability: 'meetings' },
        { labelKey: 'section.docents.statistics', href: '/groups/docents/statistics', icon: PhChartBar, requiresCapability: 'stats' },
        // Officer-only slot — role-gated (stubbed show-all for now).
        { labelKey: 'section.docents.schedule_admin', href: '/groups/docents/schedule/admin', icon: PhGearSix, requiresRole: 'chair' },
    ],
};
