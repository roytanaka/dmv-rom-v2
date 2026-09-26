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

/**
 * The part of a seat these rules read: the Member id, the recorded count (null = none), the
 * server's verdict that the viewer may change this entry (#653; absent = no), and who last saved
 * the numbers and when (#654; null or absent = nobody yet).
 */
export type ReportSeat = {
    id: number;
    visitor_count?: number | null;
    can_record?: boolean;
    last_edited?: { name: string; at: string } | null;
};

export type EntryState = 'form' | 'summary' | 'no-count';

/** Whether a seat has a count on file. A recorded zero counts; null or absent does not. */
const hasCount = (seat: ReportSeat): boolean => (seat.visitor_count ?? null) !== null;

/**
 * The one seat whose entry shows the form, or null for none. A seat opened by Change (the
 * viewer's own, or any seat for an Officer, #653) wins. Otherwise the viewer's own seat opens
 * by itself when its window is open and it has no count yet. One seat at most, so a card never
 * shows two forms.
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

/**
 * Whether an entry shows Change (#653): the server says the viewer may change this seat, and the
 * entry is not already the form. A schedule admin gets it on every seat, "No count yet" included;
 * a volunteer on their own seat only.
 */
export function showsChange(seat: ReportSeat, state: EntryState): boolean {
    return state !== 'form' && seat.can_record === true;
}

/** The header's "N of M recorded": seats with a count (a zero counts) out of all seats. */
export function recordedTally(seats: ReportSeat[]): { recorded: number; total: number } {
    return {
        recorded: seats.filter(hasCount).length,
        total: seats.length,
    };
}

/**
 * The parts of an entry's "Last edited by [name] · [time]" line (#654): the editor's name, and the
 * save time on the org wall clock in the viewer's locale. Null for an entry nobody has saved, which
 * shows no line.
 */
export function lastEditedLine(seat: ReportSeat, locale: string, timeZone: string): { name: string; time: string } | null {
    if (!seat.last_edited) return null;

    const time = new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', timeZone }).format(new Date(seat.last_edited.at));

    return { name: seat.last_edited.name, time };
}
