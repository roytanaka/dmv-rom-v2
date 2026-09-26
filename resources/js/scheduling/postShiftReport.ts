/**
 * The Post-shift report section's entry states (#652, PRD #651, ADR-0023 §5) — the pure rules
 * the shift card reads to lay out one entry per seat.
 *
 * The server decides what the viewer may do: it sends each seat's numbers only to a reader of
 * the report, and `canRecordOwn` (the Shift's `can.record`) says the viewer's own seat is
 * inside its sign-out window. These functions only arrange what arrived: which one seat shows
 * the form, and whether every other seat reads as a summary or "No count yet".
 *
 * Pure functions of their arguments, so they are tested without a component.
 */

/** The part of a seat these rules read: the Member id and the recorded count (null = none). */
export type ReportSeat = { id: number; visitor_count?: number | null };

export type EntryState = 'form' | 'summary' | 'no-count';

/** Whether a seat has a count on file. A recorded zero counts; null or absent does not. */
const hasCount = (seat: ReportSeat): boolean => (seat.visitor_count ?? null) !== null;

/**
 * The one seat whose entry shows the form, or null for none. A seat opened by Change (or an
 * Officer's pencil) wins. Otherwise the viewer's own seat opens by itself when its window is
 * open and it has no count yet. One seat at most, so a card never shows two forms.
 */
export function openFormSeat(options: {
    seats: ReportSeat[];
    ownSeatId: number | null;
    canRecordOwn: boolean;
    editingSeatId: number | null;
}): number | null {
    const { seats, ownSeatId, canRecordOwn, editingSeatId } = options;

    if (editingSeatId !== null) return editingSeatId;
    if (ownSeatId === null || !canRecordOwn) return null;

    const own = seats.find((seat) => seat.id === ownSeatId);

    return own && !hasCount(own) ? ownSeatId : null;
}

/** How one seat's entry reads: the open form, a summary of its numbers, or "No count yet". */
export function entryState(seat: ReportSeat, formSeatId: number | null): EntryState {
    if (seat.id === formSeatId) return 'form';

    return hasCount(seat) ? 'summary' : 'no-count';
}

/** The header's "N of M recorded": seats with a count (a zero counts) out of all seats. */
export function recordedTally(seats: ReportSeat[]): { recorded: number; total: number } {
    return {
        recorded: seats.filter(hasCount).length,
        total: seats.length,
    };
}
