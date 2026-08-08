<script setup lang="ts">
// PROTOTYPE HOST — three variants of a Group's Scheduling section. See #330.
//
//   "Three variants of the opened Schedule, switchable via ?variant=, mounted on the
//    existing /groups/{slug}/scheduling section."
//
// Everything below is throwaway. It renders from in-memory fixtures, not the database
// — the group named in the page banner is whichever Group you navigated to, while the
// Schedule below is whichever fixture the bar has selected. Ignore the mismatch.
//
// English-only, no translation keys, no tests, no server. Sign up / drop mutate the
// fixture in memory and reset on reload or on switching fixture.
//
// Dev-only: the whole thing is gated by the caller on import.meta.env.DEV.
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { VIEWER_PERSON, buildDataset } from './fixtures';
import PrototypeBar from './PrototypeBar.vue';
import ScheduleIndex from './ScheduleIndex.vue';
import VariantAgenda from './VariantAgenda.vue';
import VariantCalendar from './VariantCalendar.vue';
import VariantMatrix from './VariantMatrix.vue';
import type { Viewer, ViewerRole } from './types';

const VARIANTS = [
    { key: 'A', name: 'Agenda — a day-grouped list' },
    { key: 'B', name: 'Calendar — a month grid + day sheet' },
    { key: 'C', name: 'Matrix — days across, time or activity down' },
];

// URL is the source of truth on load, so a variant is shareable and reload-stable.
const params = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);

const variant = ref(VARIANTS.some((v) => v.key === params.get('variant')) ? params.get('variant')! : 'A');
const datasetKey = ref(params.get('data') ?? 'reception');
const viewerRole = ref<ViewerRole>((params.get('as') as ViewerRole) ?? 'member');
const showForeign = ref(params.get('foreign') === '1');
const showIndex = ref(params.get('list') === '1');

// Rebuilt on every fixture change — sign-ups are mutated in place, so switching away
// and back is the reset.
const dataset = ref(buildDataset(datasetKey.value));
watch(datasetKey, (key) => (dataset.value = buildDataset(key)));

const viewer = computed<Viewer>(() => ({ person: VIEWER_PERSON, role: viewerRole.value }));
const hasForeign = computed(() => dataset.value.schedule.shifts.some((s) => s.foreignGroup !== undefined));

// Keep the URL in step without an Inertia visit — a full round-trip would rebuild the
// fixture and throw away whatever you just signed up for.
watch([variant, datasetKey, viewerRole, showForeign, showIndex], () => {
    const next = new URLSearchParams(window.location.search);
    next.set('variant', variant.value);
    next.set('data', datasetKey.value);
    next.set('as', viewerRole.value);
    if (showForeign.value) next.set('foreign', '1');
    else next.delete('foreign');
    if (showIndex.value) next.set('list', '1');
    else next.delete('list');
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
        <ScheduleIndex v-if="showIndex" :dataset="dataset" :viewer="viewer" @open="showIndex = false" />

        <component
            :is="variant === 'B' ? VariantCalendar : variant === 'C' ? VariantMatrix : VariantAgenda"
            v-else
            :key="`${variant}-${dataset.key}`"
            :schedule="dataset.schedule"
            :viewer="viewer"
            :group-name="dataset.groupName"
            :show-foreign="showForeign"
        />

        <PrototypeBar
            ref="bar"
            v-model:variant="variant"
            v-model:dataset="datasetKey"
            v-model:viewer-role="viewerRole"
            v-model:show-foreign="showForeign"
            v-model:show-index="showIndex"
            :variants="VARIANTS"
            :has-foreign="hasForeign"
        />
    </div>
</template>
