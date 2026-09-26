/**
 * The record form's boxes (#646, #652, ADR-0023) — the client's copy of the rules that decide when
 * **Record shift** / **Save changes** is enabled and what the write sends.
 *
 * A `type="number"` box holds a string until the user types, then Vue's v-model hands back a
 * number. A pre-filled box holds a string; an empty one holds `''`. Every box here is therefore
 * `string | number`, and nothing assumes it is a string.
 *
 * ADR-0023: an empty box is "not recorded" (null), a typed `0` is a real zero.
 *
 * ── Purity ───────────────────────────────────────────────────────────────────
 *   Pure functions of the box values and the Group's collection flags, so a test types into
 *   every box without mounting the shift card.
 */
import type { VisitorProvenance } from '@/types';

/** One box's v-model: a string when pre-filled or empty, a number once the user types. */
export type BoxValue = string | number;

export type RecordDraft = {
    count: BoxValue;
    extra: BoxValue;
    provenance: Record<keyof VisitorProvenance, BoxValue>;
    // The viewer's own comment (#655): free text, so a plain string.
    comment: string;
};

/**
 * What the Group collects after a shift, beyond the count, and whether this form writes a comment:
 * true on the viewer's own seat, false on an officer's correction, which the server refuses a
 * comment on (#655).
 */
export type RecordFlags = {
    collectsExtraInteractions: boolean;
    collectsVisitorProvenance: boolean;
    writesComment: boolean;
};

export type RecordPayload = {
    count: number;
    extra: number | null;
    provenance: VisitorProvenance | null;
    // Absent on an officer's correction; null clears the viewer's own comment.
    comment?: string | null;
};

/** An untouched or cleared box. A typed `0` is not blank. */
const isBlank = (value: BoxValue): boolean => String(value).trim() === '';

/** GDR's five origin fields, in the order the record form lists them. */
export const PROVENANCE_KEYS = [
    'visitors_france_europe',
    'visitors_quebec',
    'visitors_toronto',
    'visitors_rest_of_canada',
    'visitors_other_countries',
] as const satisfies readonly (keyof VisitorProvenance)[];

/**
 * The count is required; on a Group that collects origins, all five must be filled. Whether they
 * add up to the count is the server's rule (#649): a set that does not goes out, and the server's
 * message names both totals under the origin boxes. A disabled button would give no reason.
 */
export function canSubmitRecord(draft: RecordDraft, flags: RecordFlags): boolean {
    return !isBlank(draft.count) && (!flags.collectsVisitorProvenance || !Object.values(draft.provenance).some(isBlank));
}

/** The server's refusal of a save, one message per place the form shows one (#649). */
export type RecordErrors = {
    count: string | undefined;
    extra: string | undefined;
    provenance: string | undefined;
    comment: string | undefined;
};

/** No refusal: the form before a save, or after one the server accepted. */
export const NO_RECORD_ERRORS: RecordErrors = { count: undefined, extra: undefined, provenance: undefined, comment: undefined };

/** How the page hands a save's outcome back to the card that submitted it (#649). */
export type RecordCallbacks = {
    onSuccess: () => void;
    onError: (errors: Record<string, string>) => void;
};

/**
 * Map the server's validation errors onto the form's boxes. The five origins share one message
 * under the origin boxes: the server hangs the sum rule on the first origin, and the per-origin
 * messages already read the same for every box.
 */
export function recordErrorsFrom(errors: Partial<Record<string, string>>): RecordErrors {
    return {
        count: errors.visitor_count,
        extra: errors.extra_interaction_count,
        provenance: PROVENANCE_KEYS.map((key) => errors[key]).find((message) => message !== undefined),
        comment: errors.comment,
    };
}

/**
 * The write, from boxes `canSubmitRecord` has passed. The count is always sent; the extra rides
 * only where the Group collects it, and a blank extra files null; the origins ride only on GDR.
 * The comment rides only from the viewer's own seat (#655), and a blank one files null so the
 * volunteer can clear it; an officer's correction leaves it out, so the saved comment stays.
 */
export function buildRecordPayload(draft: RecordDraft, flags: RecordFlags): RecordPayload {
    const extra = flags.collectsExtraInteractions && !isBlank(draft.extra) ? Number(draft.extra) : null;

    const provenance = flags.collectsVisitorProvenance
        ? (Object.fromEntries(Object.entries(draft.provenance).map(([key, value]) => [key, Number(value)])) as unknown as VisitorProvenance)
        : null;

    const payload: RecordPayload = { count: Number(draft.count), extra, provenance };

    if (flags.writesComment) {
        payload.comment = draft.comment.trim() === '' ? null : draft.comment;
    }

    return payload;
}
