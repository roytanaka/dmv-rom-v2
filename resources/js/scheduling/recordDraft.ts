/**
 * The sign-out form's boxes (#646, ADR-0023) — the client's copy of the rules that decide when
 * **Sign out** / **Save** is enabled and what the write sends.
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
};

/** What the Group collects on sign-out, beyond the count. */
export type RecordFlags = {
    collectsExtraInteractions: boolean;
    collectsVisitorProvenance: boolean;
};

export type RecordPayload = {
    count: number;
    extra: number | null;
    provenance: VisitorProvenance | null;
};

/** An untouched or cleared box. A typed `0` is not blank. */
const isBlank = (value: BoxValue): boolean => String(value).trim() === '';

/**
 * All five origins filled and summing to the count — the client's copy of the server rule, so
 * the button never offers a write the server would refuse.
 */
const provenanceComplete = (draft: RecordDraft): boolean => {
    const values = Object.values(draft.provenance);

    if (values.some(isBlank)) return false;

    return values.reduce<number>((sum, value) => sum + Number(value), 0) === Number(draft.count);
};

/** The count is required; on a Group that collects origins, all five must add up to it. */
export function canSubmitRecord(draft: RecordDraft, flags: RecordFlags): boolean {
    return !isBlank(draft.count) && (!flags.collectsVisitorProvenance || provenanceComplete(draft));
}

/**
 * The write, from boxes `canSubmitRecord` has passed. The count is always sent; the extra rides
 * only where the Group collects it, and a blank extra files null; the origins ride only on GDR.
 */
export function buildRecordPayload(draft: RecordDraft, flags: RecordFlags): RecordPayload {
    const extra = flags.collectsExtraInteractions && !isBlank(draft.extra) ? Number(draft.extra) : null;

    const provenance = flags.collectsVisitorProvenance
        ? (Object.fromEntries(Object.entries(draft.provenance).map(([key, value]) => [key, Number(value)])) as unknown as VisitorProvenance)
        : null;

    return { count: Number(draft.count), extra, provenance };
}
