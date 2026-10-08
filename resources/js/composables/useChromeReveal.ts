// Hide-on-scroll for the phone chrome (#740). Below lg the top bar slides out on scroll
// down and back in on scroll up (rules in chrome/reveal.ts). A page's own sticky bar
// under it (the Group section bar) registers as a chrome bar too, so the two move as
// one unit. At lg and up nothing hides.
//
// One shared state: AppShell runs the scroll driver once; each bar registers its element
// so an open menu or keyboard focus inside it keeps the chrome shown.
import { INITIAL_REVEAL, nextReveal } from '@/chrome/reveal';
import { useActiveElement, useEventListener, useMediaQuery, useWindowScroll } from '@vueuse/core';
import { computed, onBeforeUnmount, shallowReactive, shallowRef, watch, watchEffect, type Ref } from 'vue';

// The top bar's height (TopBar h-16). Below this scroll position the chrome always shows.
const HIDE_AFTER = 64;

const bars = shallowReactive(new Set<HTMLElement>());
const state = shallowRef(INITIAL_REVEAL);
const activeElement = useActiveElement();
const isDesktop = useMediaQuery('(min-width: 64rem)');

const focusInBar = computed(() => {
    const active = activeElement.value;

    return active !== null && active !== undefined && [...bars].some((bar) => bar.contains(active));
});

// Radix menu triggers carry aria-expanded while their menu is open.
const menuOpenInBar = () => [...bars].some((bar) => bar.querySelector('[aria-expanded="true"]') !== null);

const hidden = computed(() => !isDesktop.value && state.value.hidden);

// Focus moving into a hidden bar (Shift+Tab from the page) makes the browser scroll the
// page to reach it: Chrome scrolls before focusin on Tab, and after it on focus(). So the
// handler puts the page back where it was (the scroll event that updates lastY has not
// fired yet) and shows every bar at once, without the slide, so it is already on screen
// for a later scroll check. Vue's own render comes a frame later.
const revealForFocus = () => {
    if (!hidden.value) return;

    if (window.scrollY !== state.value.lastY) {
        window.scrollTo({ top: state.value.lastY, behavior: 'instant' });
    }
    for (const bar of bars) {
        bar.style.transition = 'none';
        bar.style.translate = 'none';
    }
    state.value = { ...state.value, hidden: false };
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            for (const bar of bars) {
                bar.style.transition = '';
                bar.style.translate = '';
            }
        }),
    );
};

// Called once, by AppShell.
export function useChromeRevealDriver() {
    const { y } = useWindowScroll();

    watch(y, (scrollY) => {
        state.value = nextReveal(state.value, scrollY, { hideAfter: HIDE_AFTER, pinned: focusInBar.value || menuOpenInBar() });
    });

    return { hidden };
}

// Called by each sticky chrome bar with its element.
export function useChromeBar(el: Ref<HTMLElement | null>) {
    let registered: HTMLElement | null = null;

    watchEffect(() => {
        if (registered) bars.delete(registered);
        registered = el.value;
        if (registered) bars.add(registered);
    });

    onBeforeUnmount(() => {
        if (registered) bars.delete(registered);
    });

    useEventListener(el, 'focusin', revealForFocus);

    return { hidden };
}
