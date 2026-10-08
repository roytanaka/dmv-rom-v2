// Hide-on-scroll for the phone chrome (#740). The top bar (and a page's sticky bar
// under it) slides out on scroll down and back in after a deliberate scroll up.
//   • At or above `hideAfter` (the bar's own height) the chrome always shows.
//   • A pinned bar (a menu open in it, or keyboard focus inside it) always shows.
//   • Hidden, it comes back once the page scrolls up REVEAL_DISTANCE from its lowest
//     point, so a small upward drift does not bring it back.
// Pure state so it tests without a DOM; useChromeReveal feeds it the window scroll.

export const REVEAL_DISTANCE = 40;

export interface RevealState {
    hidden: boolean;
    lastY: number;
    // The lowest point reached while hidden; the reveal distance counts from here.
    peakY: number;
}

export const INITIAL_REVEAL: RevealState = { hidden: false, lastY: 0, peakY: 0 };

export const nextReveal = (state: RevealState, y: number, { hideAfter, pinned }: { hideAfter: number; pinned: boolean }): RevealState => {
    if (y <= hideAfter || pinned) {
        return { hidden: false, lastY: y, peakY: y };
    }

    if (!state.hidden) {
        return { hidden: y > state.lastY, lastY: y, peakY: y };
    }

    const peakY = Math.max(state.peakY, y);

    return peakY - y >= REVEAL_DISTANCE ? { hidden: false, lastY: y, peakY: y } : { hidden: true, lastY: y, peakY };
};
