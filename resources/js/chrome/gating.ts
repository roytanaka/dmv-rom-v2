/**
 * Chrome nav gating — STUBBED show-all.
 *
 * The *mechanism* below is real — {@link isNodeVisible} hides a node whose declared
 * requirement is unmet — but the two "granted" inputs it consults are stubbed to
 * always-true, so nothing is hidden yet. Flipping a stub to the real resolver lights
 * up gating without restructuring callers.
 *
 * TODO(ADR-0011): replace `CAPABILITY_GRANTED` / `ROLE_GRANTED` with the real
 * authorization resolver (access tier · membership · role flags · super-tier
 * bypass), fed by Group capabilities (ADR-0010) and the Volunteer's roles.
 *
 * SECURITY CONTRACT: roles are resolved on the SERVER and shared via Inertia props.
 * The client never echoes role flags back into requests. This stub honours that by
 * deciding nothing from client state.
 */

import type { NavNode } from './types';

// STUB — show-all. Typed `boolean` (not literal `true`) so the real per-node lookups
// can drop in here; see the TODO above.
const CAPABILITY_GRANTED: boolean = true;
const ROLE_GRANTED: boolean = true;

/** True unless the node declares a requirement the resolved context does not satisfy. */
export function isNodeVisible(node: NavNode): boolean {
    if (node.requiresCapability && !CAPABILITY_GRANTED) return false;
    if (node.requiresRole && !ROLE_GRANTED) return false;
    return true;
}

/** Filter a node list (recursively, including children) down to the visible nodes. */
export function visibleNodes<T extends NavNode>(nodes: T[]): T[] {
    return nodes.filter(isNodeVisible).map((node) => (node.children ? { ...node, children: visibleNodes(node.children) } : node));
}
