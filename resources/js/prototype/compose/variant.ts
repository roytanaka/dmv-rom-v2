// PROTOTYPE (#467) — the `?variant=` switch. Reads the query string once, writes it
// back with history.replaceState so a reload or a shared link lands on the same
// variant. Inertia ignores query-only changes, so nothing re-renders but us.
import { computed, ref } from 'vue';

export const VARIANTS = [
    { key: 'A', name: 'Header button, two-pane dialog' },
    { key: 'B', name: 'Audience menu, stepped sheet' },
    { key: 'C', name: 'Select first, full-page compose' },
] as const;

export type VariantKey = (typeof VARIANTS)[number]['key'];

const read = (): VariantKey => {
    if (typeof window === 'undefined') return 'A';
    const v = new URLSearchParams(window.location.search).get('variant')?.toUpperCase();
    return VARIANTS.some((x) => x.key === v) ? (v as VariantKey) : 'A';
};

const current = ref<VariantKey>(read());

export const useVariant = () => {
    const set = (key: VariantKey) => {
        current.value = key;
        const url = new URL(window.location.href);
        url.searchParams.set('variant', key);
        window.history.replaceState(window.history.state, '', url);
    };
    const cycle = (by: 1 | -1) => {
        const i = VARIANTS.findIndex((x) => x.key === current.value);
        set(VARIANTS[(i + by + VARIANTS.length) % VARIANTS.length].key);
    };
    return {
        variant: computed(() => current.value),
        label: computed(() => VARIANTS.find((x) => x.key === current.value)!),
        set,
        cycle,
        enabled: !import.meta.env.PROD,
    };
};
