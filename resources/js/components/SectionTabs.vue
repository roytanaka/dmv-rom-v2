<script setup lang="ts">
// The contextual section-tab strip in the top bar (the Group Menu layer). Exactly
// one tab is active — matched on the current Inertia URL — and highlighted in
// heritage-blue (the slate-300 "active tab on the black bar" token). Across every
// breakpoint it is a horizontal scroll-strip (decision M1): each section stays
// reachable by scrolling sideways, none hidden behind a "More" menu. External tabs
// (e.g. Renew Membership) render as visibly outbound links with a trailing icon.
import { t } from '@/chrome/messages';
import type { NavNode } from '@/chrome/types';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useScroll } from '@vueuse/core';
import { computed, ref } from 'vue';

defineProps<{ items: NavNode[] }>();

const page = usePage<SharedData>();
const isActive = (href: string) => href === page.url;

// Edge fade — the strip is horizontally scrollable, so we fade the side that has
// content scrolled out of view (and only that side: no fade at the very start/end).
// `arrivedState` flips as the user reaches each edge; the mask collapses that edge's
// fade to 0 when there's nothing more to reveal.
const strip = ref<HTMLElement | null>(null);
const { arrivedState } = useScroll(strip);
const maskImage = computed(() => {
    const left = arrivedState.left ? '0px' : '2rem';
    const right = arrivedState.right ? '0px' : '2rem';
    return `linear-gradient(to right, transparent, black ${left}, black calc(100% - ${right}), transparent)`;
});
</script>

<template>
    <nav ref="strip" class="flex min-w-0 items-stretch gap-1 overflow-x-auto" :style="{ maskImage, WebkitMaskImage: maskImage }" aria-label="Section">
        <template v-for="item in items" :key="item.href">
            <!-- Outbound tab — leaves the app; the trailing icon marks it external. -->
            <a
                v-if="item.external"
                :href="item.href"
                target="_blank"
                rel="noopener noreferrer"
                class="flex shrink-0 items-center gap-1.5 border-b-2 border-transparent px-3 text-sm whitespace-nowrap text-white/70 transition-colors hover:text-white"
            >
                <span>{{ t(item.labelKey) }}</span>
                <component :is="item.icon" v-if="item.icon" class="size-4 opacity-80" />
            </a>

            <Link
                v-else
                :href="item.href"
                :aria-current="isActive(item.href) ? 'page' : undefined"
                :class="[
                    'flex shrink-0 items-center border-b-2 px-3 text-sm whitespace-nowrap transition-colors',
                    isActive(item.href) ? 'border-rom-slate-300 text-rom-slate-300' : 'border-transparent text-white/70 hover:text-white',
                ]"
            >
                <span>{{ t(item.labelKey) }}</span>
            </Link>
        </template>
    </nav>
</template>
