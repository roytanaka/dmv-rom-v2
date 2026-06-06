/**
 * Chrome top-bar sections — resolve the contextual tab set for the active context.
 *
 * The rail (#66) selects *context*; the top bar reflects it. With no Group selected
 * (the Dashboard) the strip is Zone A (personal/global). Inside a Group it is that
 * Group's Group Menu — its capability slots × per-program labels (see fixture.ts).
 *
 * Resolution runs the chosen node list through the (stubbed show-all) gate, so
 * `requiresCapability` / `requiresRole` are honoured here exactly as in the rail —
 * flipping the real resolver in gating.ts lights both surfaces up at once.
 */

import { groupMenus, zoneA } from './fixture';
import { visibleNodes } from './gating';
import type { NavNode } from './types';

/**
 * The gated tab set for the active context: Zone A when no Group is selected, else
 * that Group's Menu (empty for a Group not yet modelled in the fixture).
 */
export function resolveSections(activeGroupId?: string): NavNode[] {
    const nodes = activeGroupId ? (groupMenus[activeGroupId] ?? []) : zoneA;
    return visibleNodes(nodes);
}
