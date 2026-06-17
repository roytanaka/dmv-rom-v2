<script setup lang="ts">
// Alpha-jump rail (#171, PRD #167). A vertical A–Z rail that leaps the directory to
// the first row under a letter instead of scrolling hundreds of rows. This is the
// PRD's flagged bespoke-UI element: no installed shadcn-vue primitive composes into
// an index rail (it's neither a menu, a tab strip, nor a toggle group), so it lives
// as a small reusable component of its own — flagged for review rather than silently
// hand-rolled.
//
// The component is sort-agnostic: the parent owns the active sort and the letter
// buckets, so "follow the active sort" (surname vs given name) is the parent's job.
// The rail only renders the fixed A–Z, dims letters the parent reports as empty, and
// emits the chosen letter back for the parent to scroll to.
import { trans } from 'laravel-vue-i18n';

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

const props = defineProps<{ available: string[] }>();
const emit = defineEmits<{ jump: [letter: string] }>();

const isAvailable = (letter: string) => props.available.includes(letter);
</script>

<template>
    <nav :aria-label="trans('directory.jump.label')" class="flex flex-col items-center gap-px">
        <button
            v-for="letter in ALPHABET"
            :key="letter"
            type="button"
            :disabled="!isAvailable(letter)"
            :aria-label="trans('directory.jump.letter', { letter })"
            class="text-muted-foreground hover:text-rom-slate hover:bg-rom-slate-50 focus-visible:ring-rom-slate flex size-5 items-center justify-center text-xs font-medium tabular-nums outline-hidden transition-colors focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-30"
            @click="emit('jump', letter)"
        >
            {{ letter }}
        </button>
    </nav>
</template>
