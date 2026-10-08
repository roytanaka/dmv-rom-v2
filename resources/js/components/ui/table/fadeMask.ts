// The CSS mask that fades the edges of a pinned data grid's scroll box (#749). An edge fades
// only while there are more columns to scroll to on that side (`start` / `end`), so a table
// that fits gets no mask at all. The start fade begins at `inset` (the pinned name column's
// width, in px): the name stays solid and the columns fade in as they leave it.
const WIDTH = '2rem';

export const fadeMask = ({ start, end, inset }: { start: boolean; end: boolean; inset: number }): string | undefined => {
    if (!start && !end) {
        return undefined;
    }

    const head = start ? `black ${inset}px, transparent ${inset}px, black calc(${inset}px + ${WIDTH})` : 'black';
    const tail = end ? `black calc(100% - ${WIDTH}), transparent` : 'black';

    return `linear-gradient(to right, ${head}, ${tail})`;
};
