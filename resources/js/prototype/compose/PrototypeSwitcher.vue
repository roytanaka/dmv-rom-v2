<script setup lang="ts">
// PROTOTYPE (#467) — the floating variant bar. High-contrast so it reads as not part
// of the design. Hidden in a production build. Arrow keys cycle unless a field has focus.
import { PhCaretLeft, PhCaretRight } from '@phosphor-icons/vue';
import { onBeforeUnmount, onMounted } from 'vue';
import { useVariant } from './variant';

const { label, cycle, enabled } = useVariant();

const onKey = (e: KeyboardEvent) => {
    const t = e.target as HTMLElement | null;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
    if (e.key === 'ArrowLeft') cycle(-1);
    if (e.key === 'ArrowRight') cycle(1);
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div
        v-if="enabled"
        class="fixed bottom-4 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-2 rounded-full bg-amber-400 px-2 py-1 text-sm font-medium text-black shadow-lg ring-2 ring-black/70"
    >
        <button type="button" class="rounded-full p-1 hover:bg-black/10" aria-label="Previous variant" @click="cycle(-1)">
            <PhCaretLeft class="size-4" />
        </button>
        <span class="px-1 whitespace-nowrap">PROTOTYPE · {{ label.key }} — {{ label.name }}</span>
        <button type="button" class="rounded-full p-1 hover:bg-black/10" aria-label="Next variant" @click="cycle(1)">
            <PhCaretRight class="size-4" />
        </button>
    </div>
</template>
