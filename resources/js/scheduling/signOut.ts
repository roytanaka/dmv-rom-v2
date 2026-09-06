/**
 * The sign-out window (#445, PRD #443, ADR-0023 §5) — the client's copy of the rule that
 * decides whether the sign-out panel shows on a Shift the viewer holds a seat on.
 *
 * A seat-holder may record their visitor count **from five minutes before the Shift ends,
 * with no upper bound** — they file the number as they pack up, or a month later. The server's
 * SignUpPolicy is the enforcing rule; this is the client's matching predicate, so the panel
 * appears exactly when a write would be accepted and the reader is never shown a box the server
 * would refuse.
 *
 * ── Purity ───────────────────────────────────────────────────────────────────
 *   A pure function of the Shift's end instant and a `now` the caller passes in. It reaches no
 *   ambient clock of its own, so a test pins the clock and a component can re-evaluate it
 *   against a ticking `now` without this module ever issuing a request or hiding state.
 */

/** Five minutes, in milliseconds — how far before a Shift ends the panel opens. */
const WINDOW_OPENS_BEFORE_END_MS = 5 * 60 * 1000;

/**
 * Whether the sign-out panel is open for a Shift ending at `endsAt` (an ISO-8601 UTC instant),
 * evaluated against `now`. True from `endsAt` minus five minutes onward, forever after — there
 * is no deadline. Comparing instants, so the org wall clock never enters into it.
 */
export function withinSignOutWindow(endsAt: string, now: Date): boolean {
    const opensAt = new Date(endsAt).getTime() - WINDOW_OPENS_BEFORE_END_MS;

    return now.getTime() >= opensAt;
}
