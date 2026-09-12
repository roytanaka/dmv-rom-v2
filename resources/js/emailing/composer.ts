/**
 * The composer's selection core (#489, ADR-0024 §6) — the pure derivations behind the
 * stepped Who → Message → Sent sheet. The sheet picks an Audience by key and lets the
 * sender un-tick or add rows drawn from the page's roster; this module turns the picked
 * Audience and the ticked set into everything the sheet renders and the little it posts.
 *
 * It never holds a recipient list to post: the browser posts the Audience key and the
 * per-Member edits ({@see audienceEdits}), and the server re-resolves the recipients (§5).
 * Kept framework-free and i18n-free so it unit-tests on Node and the Vue layer owns the
 * reactivity and the translated strings.
 */

/** An Audience the menu offers: its key, optional parameter, translated label, and live count. */
export interface AudienceOption {
    key: string;
    parameter: string | null;
    label: string;
    count: number;
}

/**
 * The Email menu's shape, derived from the server's Audience index (ADR-0024 §5–6): the
 * named Audiences the menu lists, whether the actor may hand-pick, and whether the index
 * came back empty. "Pick people…" is offered only when the server returned a hand-pick
 * Audience — the picker rule decides it (any member of a Group; Records on the Directory),
 * so a plain Member on the Directory sees the three leadership Audiences and no hand-pick.
 * The `hand_picked` key itself is dropped from the list: the menu renders its own
 * always-last "Pick people…" item when it may. `isEmpty` is true when there is nothing to
 * offer at all — the menu shows one disabled "nothing to email" line instead of a bare
 * dropdown (a plain Member on the root roster, #506).
 */
export function menuFromIndex(audiences: AudienceOption[]): { audiences: AudienceOption[]; canHandPick: boolean; isEmpty: boolean } {
    const named = audiences.filter((audience) => audience.key !== 'hand_picked');
    const canHandPick = audiences.some((audience) => audience.key === 'hand_picked');

    return {
        audiences: named,
        canHandPick,
        isEmpty: named.length === 0 && !canHandPick,
    };
}

/** A resolved recipient row (ADR-0024 §5) — identity and a standing hint, never an address. */
export interface Recipient {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    standing: string;
}

/** The stored-label shape, formatted by the Vue layer so this module stays i18n-free. */
export type LabelDescriptor =
    { kind: 'hand_picked'; count: number } | { kind: 'named'; label: string } | { kind: 'edited'; label: string; removed: number };

/** A recipient's display name, for a To chip or a summary line. */
export function recipientName(recipient: Recipient): string {
    return `${recipient.first_name} ${recipient.last_name}`.trim();
}

/**
 * The picker's edits relative to the Audience's resolved membership: the base ids the
 * sender un-ticked (`removed`) and the ids they ticked from outside it (`added`). These
 * are the only recipient data the composer posts (§5).
 */
export function audienceEdits(baseIds: Iterable<number>, ticked: Set<number>): { removed: number[]; added: number[] } {
    const base = new Set(baseIds);

    return {
        removed: [...base].filter((id) => !ticked.has(id)),
        added: [...ticked].filter((id) => !base.has(id)),
    };
}

/**
 * Whether the selection diverges from the Audience it started as. A hand-picked selection
 * has no base Audience and is never "edited" (ADR-0024 §6); a named one is edited the
 * moment the ticked set stops matching its resolved membership.
 */
export function isEdited(audience: AudienceOption | null, baseIds: Iterable<number>, ticked: Set<number>): boolean {
    if (audience === null) {
        return false;
    }

    const { removed, added } = audienceEdits(baseIds, ticked);

    return removed.length > 0 || added.length > 0;
}

/**
 * The Audience label as the record will store it (ADR-0024 §6), as a descriptor the Vue
 * layer formats: a hand-picked count, the bare Audience name, or the name with the number
 * of rows removed. Only removals are named — the Audience is the select-all, so an addition
 * still reads under its name.
 */
export function audienceLabelDescriptor(audience: AudienceOption | null, baseIds: Iterable<number>, ticked: Set<number>): LabelDescriptor {
    if (audience === null) {
        return { kind: 'hand_picked', count: ticked.size };
    }

    const { removed } = audienceEdits(baseIds, ticked);

    if (removed.length === 0) {
        return { kind: 'named', label: audience.label };
    }

    return { kind: 'edited', label: audience.label, removed: removed.length };
}

/**
 * Whether a rich-text body carries real content. The editor leaves empty scaffolding
 * (`<p></p>`, a lone `<br>`) behind when the sender clears it, which must not satisfy Send.
 */
export function bodyHasContent(body: string): boolean {
    return (
        body
            .replace(/<[^>]*>/g, '')
            .replace(/&nbsp;/g, ' ')
            .trim().length > 0
    );
}

/**
 * Whether Send may fire (ADR-0024 §6): a subject, a non-empty body, and at least one
 * recipient must all be present.
 */
export function canSend(input: { subject: string; body: string; recipientCount: number }): boolean {
    return input.subject.trim().length > 0 && bodyHasContent(input.body) && input.recipientCount > 0;
}

/** The Add-people panel's name search: a case-insensitive match on the full name. */
export function filterRoster(roster: Recipient[], query: string): Recipient[] {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
        return roster;
    }

    return roster.filter((recipient) => recipientName(recipient).toLowerCase().includes(needle));
}

/** The tick-all target: every row on the page's roster. */
export function rosterIds(roster: Recipient[]): Set<number> {
    return new Set(roster.map((recipient) => recipient.id));
}

/**
 * How many recipient chips the read-only Message-step summary shows before it collapses
 * the rest behind a "+N more" control. "All Members" is ~500 people, so the summary caps
 * the visible chips and offers to expand in place (ADR-0024 §6).
 */
export const CHIP_CAP = 12;

/**
 * The Message step's read-only chip view: the chips to render and how many are still hidden.
 * Collapsed, it shows the first {@link CHIP_CAP} and reports the overflow so the sheet can
 * offer a "+N more" control; expanded, it shows them all and hides none.
 */
export function cappedChips(chips: Recipient[], expanded: boolean): { shown: Recipient[]; hidden: number } {
    if (expanded || chips.length <= CHIP_CAP) {
        return { shown: chips, hidden: 0 };
    }

    return { shown: chips.slice(0, CHIP_CAP), hidden: chips.length - CHIP_CAP };
}
