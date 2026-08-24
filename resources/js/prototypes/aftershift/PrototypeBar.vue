<script setup lang="ts">
// PROTOTYPE (#405) — the variant switcher.
//
// Deliberately ugly and high-contrast so it never reads as part of the design being
// judged. Three axes, because this question has three: which surface, which Group shape
// (one number, two numbers, none at all), and which of the two actors is looking.
import { GROUPS } from './fixtures';
import type { ViewerRole } from './types';

const props = defineProps<{ variants: Array<{ key: string; name: string }> }>();

const variant = defineModel<string>('variant', { required: true });
const groupKey = defineModel<string>('groupKey', { required: true });
const viewerRole = defineModel<ViewerRole>('viewerRole', { required: true });

const cycle = (step: number) => {
    const keys = props.variants.map((v) => v.key);
    const i = keys.indexOf(variant.value);
    variant.value = keys[(i + step + keys.length) % keys.length];
};

const currentName = () => props.variants.find((v) => v.key === variant.value)?.name ?? '';

const ROLES: Array<{ key: ViewerRole; label: string }> = [
    { key: 'volunteer', label: 'Volunteer' },
    { key: 'officer', label: 'Officer' },
];

defineExpose({ cycle });
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center p-3">
        <div class="pointer-events-auto flex max-w-[calc(100vw-1.5rem)] flex-col gap-2 bg-black/90 px-3 py-2 text-white shadow-2xl backdrop-blur">
            <div class="flex items-center justify-center gap-2">
                <button type="button" class="px-2 py-1 text-lg leading-none hover:bg-white/20" title="Previous variant (←)" @click="cycle(-1)">
                    ‹
                </button>
                <span class="min-w-64 text-center text-sm font-semibold">{{ variant }} — {{ currentName() }}</span>
                <button type="button" class="px-2 py-1 text-lg leading-none hover:bg-white/20" title="Next variant (→)" @click="cycle(1)">›</button>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[0.7rem]">
                <span class="flex items-center gap-1">
                    <span class="text-white/50">group</span>
                    <button
                        v-for="g in GROUPS"
                        :key="g.key"
                        type="button"
                        class="px-1.5 py-0.5"
                        :class="groupKey === g.key ? 'bg-white text-black' : 'hover:bg-white/20'"
                        @click="groupKey = g.key"
                    >
                        {{ g.label }}
                    </button>
                </span>

                <span class="flex items-center gap-1">
                    <span class="text-white/50">as</span>
                    <button
                        v-for="r in ROLES"
                        :key="r.key"
                        type="button"
                        class="px-1.5 py-0.5"
                        :class="viewerRole === r.key ? 'bg-white text-black' : 'hover:bg-white/20'"
                        @click="viewerRole = r.key"
                    >
                        {{ r.label }}
                    </button>
                </span>

                <span class="text-white/50">clock fixed at Mon 24 Aug 2026, 16:58 — two minutes from the end of a shift</span>
            </div>
        </div>
    </div>
</template>
