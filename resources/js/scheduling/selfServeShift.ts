/**
 * The self-serve shift end (#585, PRD #576, ADR-0026 §2) — the client's copy of the rule that
 * turns a start and a count of units into an end time.
 *
 * A Gallery Interpreter picks units, not an end time: the dialog shows the derived end so they
 * see how long the shift runs before they save. The server's {@see Shift::deriveEndsAt} is the
 * enforcing rule and stores the same value; this is the client's matching predicate, so the
 * preview and the stored end always agree.
 *
 * ── Purity ───────────────────────────────────────────────────────────────────
 *   A pure function of the start instant, the unit count, and the Group's per-unit length. It
 *   reaches no ambient clock and no unit setting of its own, so a test pins every input and the
 *   dialog can re-derive on each keystroke without this module hiding state.
 */

/** The number of milliseconds in one minute — the unit-minutes are given in minutes. */
const MS_PER_MINUTE = 60 * 1000;

/**
 * The end instant a shift starting at `startsAt` runs to, given `units` units of `unitMinutes`
 * each. Always `startsAt` plus `units × unitMinutes`; the unit count is never stored, so this
 * is the only place the end exists on the client.
 */
export function deriveEndsAt(startsAt: Date, units: number, unitMinutes: number): Date {
    return new Date(startsAt.getTime() + units * unitMinutes * MS_PER_MINUTE);
}
