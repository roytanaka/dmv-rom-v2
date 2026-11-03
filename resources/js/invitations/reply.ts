// What a Guest's row can hold (ADR-0034 §10), and the two answers anyone can give.
export type Reply = 'no_reply' | 'accepted' | 'declined';
export type Answer = Exclude<Reply, 'no_reply'>;

// The chosen Option ids with one Option ticked or unticked.
export function toggledOption(chosen: number[], id: number, on: boolean): number[] {
    return on ? [...chosen, id] : chosen.filter((option) => option !== id);
}
