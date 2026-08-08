<script setup lang="ts">
// PROTOTYPE — the variant switcher. See #330.
//
// Deliberately ugly and high-contrast so it never reads as part of the design being
// judged. Three axes, because this question has three: which layout, which shape of
// Schedule, and which of the three audiences is looking at it.
import { DATASETS } from './fixtures';
import type { ViewerRole } from './types';

const props = defineProps<{ variants: Array<{ key: string; name: string }>; hasForeign: boolean }>();

const variant = defineModel<string>('variant', { required: true });
const dataset = defineModel<string>('dataset', { required: true });
const viewerRole = defineModel<ViewerRole>('viewerRole', { required: true });
const showForeign = defineModel<boolean>('showForeign', { required: true });
const showIndex = defineModel<boolean>('showIndex', { required: true });

const cycle = (step: number) => {
    const keys = props.variants.map((v) => v.key);
    const i = keys.indexOf(variant.value);
    variant.value = keys[(i + step + keys.length) % keys.length];
};

const currentName = () => props.variants.find((v) => v.key === variant.value)?.name ?? '';

const ROLES: Array<{ key: ViewerRole; label: string }> = [
    { key: 'member', label: 'Member' },
    { key: 'nonmember', label: 'Non-member' },
    { key: 'scheduler', label: 'Scheduler' },
];

defineExpose({ cycle });
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-center p-3">
        <div class="pointer-events-auto flex max-w-[calc(100vw-1.5rem)] flex-col gap-2 bg-black/90 px-3 py-2 text-white shadow-2xl backdrop-blur">
            <!-- Variant cycler -->
            <div class="flex items-center justify-center gap-2">
                <button type="button" class="px-2 py-1 text-lg leading-none hover:bg-white/20" title="Previous variant (←)" @click="cycle(-1)">
                    ‹
                </button>
                <span class="min-w-52 text-center text-sm font-semibold">{{ variant }} — {{ currentName() }}</span>
                <button type="button" class="px-2 py-1 text-lg leading-none hover:bg-white/20" title="Next variant (→)" @click="cycle(1)">›</button>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[0.7rem]">
                <!-- Fixture -->
                <span class="flex items-center gap-1">
                    <span class="text-white/50">data</span>
                    <button
                        v-for="d in DATASETS"
                        :key="d.key"
                        type="button"
                        class="px-1.5 py-0.5"
                        :class="dataset === d.key ? 'bg-white text-black' : 'hover:bg-white/20'"
                        @click="dataset = d.key"
                    >
                        {{ d.label }}
                    </button>
                </span>

                <!-- Audience -->
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

                <!-- The two extra states worth flipping -->
                <label class="flex items-center gap-1" :class="hasForeign ? '' : 'opacity-40'">
                    <input v-model="showForeign" type="checkbox" class="size-3 accent-white" :disabled="!hasForeign" />
                    other Groups' open shifts
                </label>
                <label class="flex items-center gap-1">
                    <input v-model="showIndex" type="checkbox" class="size-3 accent-white" />
                    schedule list
                </label>
            </div>
        </div>
    </div>
</template>
