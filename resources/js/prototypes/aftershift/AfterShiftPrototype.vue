<script setup lang="ts">
// PROTOTYPE HOST — four surfaces for entering the per-shift visitor count. See #405.
//
//   "Four variants of where after-shift data is entered, switchable via ?variant=,
//    running against in-memory fixtures with `pnpm prototype`."
//
// Everything under resources/js/prototypes/aftershift/ is throwaway. It renders from
// fixtures, not the database; English only, no translation keys, no tests, no server.
// Saving a number mutates the fixture in memory and resets on reload or on switching
// Group.
//
// The question the variants disagree about is *where*, not *what*: #404 already fixed
// the fields, the entry moment, the 28-day volunteer window, the officer's unlimited
// correction, and that entry is required at the surface.
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PrototypeBar from './PrototypeBar.vue';
import VariantAgendaInline from './VariantAgendaInline.vue';
import VariantCloseOut from './VariantCloseOut.vue';
import VariantHoursTab from './VariantHoursTab.vue';
import VariantMyShifts from './VariantMyShifts.vue';
import { buildGroup, viewerOf } from './fixtures';
import type { ViewerRole } from './types';

const VARIANTS = [
    { key: 'A', name: 'Inline on the Agenda' },
    { key: 'B', name: 'Close-out view for a Schedule' },
    { key: 'C', name: 'My Calendar — the volunteer’s own list' },
    { key: 'D', name: 'On the Hours tab' },
];

const COMPONENTS: Record<string, unknown> = {
    A: VariantAgendaInline,
    B: VariantCloseOut,
    C: VariantMyShifts,
    D: VariantHoursTab,
};

// URL is the source of truth on load, so a variant is shareable and reload-stable.
const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);

const variant = ref(VARIANTS.some((v) => v.key === params.get('variant')) ? params.get('variant')! : 'A');
const groupKey = ref(params.get('group') ?? 'guides');
const viewerRole = ref<ViewerRole>((params.get('as') as ViewerRole) ?? 'volunteer');

// Rebuilt on every Group change — counts are mutated in place, so switching away and
// back is the reset.
const group = ref(buildGroup(groupKey.value));
watch(groupKey, (key) => (group.value = buildGroup(key)));

const viewer = computed(() => viewerOf(viewerRole.value));

// Keep the URL in step without reloading — a reload would rebuild the fixture and throw
// away whatever was just typed.
watch([variant, groupKey, viewerRole], () => {
    const next = new URLSearchParams(window.location.search);
    next.set('variant', variant.value);
    next.set('group', groupKey.value);
    next.set('as', viewerRole.value);
    window.history.replaceState({}, '', `${window.location.pathname}?${next}`);
});

const bar = ref<InstanceType<typeof PrototypeBar> | null>(null);

const onKey = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null;
    if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) return;
    if (event.key === 'ArrowLeft') bar.value?.cycle(-1);
    if (event.key === 'ArrowRight') bar.value?.cycle(1);
};

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="pb-28">
        <component :is="COMPONENTS[variant]" :key="`${variant}-${group.key}`" :group="group" :viewer="viewer" />

        <PrototypeBar ref="bar" v-model:variant="variant" v-model:group-key="groupKey" v-model:viewer-role="viewerRole" :variants="VARIANTS" />
    </div>
</template>
