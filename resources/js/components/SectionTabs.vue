<script setup lang="ts">
// The contextual section nav in the top bar (the Group Menu layer). Exactly one
// section is active — matched on the current Inertia URL — and highlighted in
// heritage-blue (the slate-300 "active tab on the black bar" token).
//
// Two render modes gated on a single `lg` media query (ADR-0013, reversing M1):
//   • lg+ (≥1024px): the horizontal tab strip, unchanged. The full Group Menu fits;
//     the active tab's heritage-blue bottom border is the selected cue (it sits
//     LOWER in contrast than the inactive tabs, so the underline must always render).
//   • below lg: the whole strip collapses into one full-width trigger naming the
//     current section, opening a vertical list of EVERY section — big rows, icons,
//     a consistent set every time (horizontal scroll is undiscoverable for the DMV's
//     aging volunteers; see ADR-0013).
// External tabs (e.g. Renew Membership) render as visibly outbound links.
import type { NavNode } from '@/chrome/types';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery, useScroll } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { PhCaretDown, PhList } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';

const props = defineProps<{ items: NavNode[] }>();

const page = usePage<SharedData>();
const isActive = (href: string) => href === page.url;

// The single break: at/above lg the full Group Menu fits as tabs; below it can't.
const isWide = useMediaQuery('(min-width: 1024px)');

// The section to name on the collapsed trigger: the active one, else the first.
const activeLabel = computed(() => {
    const active = props.items.find((i) => isActive(i.href));
    return trans((active ?? props.items[0])?.labelKey ?? '');
});

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
    <!-- Wide (lg+): the strip, all tabs visible (they fit). -->
    <nav
        v-if="isWide"
        ref="strip"
        class="flex min-w-0 items-stretch gap-1 overflow-x-auto"
        :style="{ maskImage, WebkitMaskImage: maskImage }"
        aria-label="Section"
    >
        <template v-for="item in items" :key="item.href">
            <!-- Outbound tab — leaves the app; the trailing icon marks it external. -->
            <a
                v-if="item.external"
                :href="item.href"
                target="_blank"
                rel="noopener noreferrer"
                class="flex shrink-0 items-center gap-1.5 border-b-2 border-transparent px-3 text-sm whitespace-nowrap text-white/70 transition-colors hover:text-white"
            >
                <span>{{ trans(item.labelKey) }}</span>
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
                <span>{{ trans(item.labelKey) }}</span>
            </Link>
        </template>
    </nav>

    <!-- Narrow (<lg): one full-width trigger naming the current section. Square
         corners (ROM identity); DropdownMenuContent/Item are already rounded-none. -->
    <DropdownMenu v-else>
        <DropdownMenuTrigger
            class="border-rom-slate-300/40 text-rom-slate-300 flex h-11 w-full items-center justify-between gap-2 self-center rounded-none border bg-white/5 px-3 text-base font-medium outline-none hover:bg-white/10"
        >
            <span class="flex items-center gap-2 truncate"><PhList class="size-5 shrink-0 opacity-80" /> {{ activeLabel }}</span>
            <PhCaretDown class="size-5 shrink-0 opacity-80" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-[calc(100vw-2rem)] max-w-sm">
            <template v-for="item in items" :key="item.href">
                <!-- Outbound section — leaves the app. -->
                <DropdownMenuItem v-if="item.external" :as-child="true" class="gap-3 py-3 text-base">
                    <a :href="item.href" target="_blank" rel="noopener noreferrer" class="flex w-full items-center gap-3">
                        <component :is="item.icon" v-if="item.icon" class="size-5 opacity-70" />
                        <span>{{ trans(item.labelKey) }}</span>
                    </a>
                </DropdownMenuItem>

                <DropdownMenuItem v-else :as-child="true" :class="['gap-3 py-3 text-base', isActive(item.href) ? 'text-rom-ink font-semibold' : '']">
                    <Link :href="item.href" :aria-current="isActive(item.href) ? 'page' : undefined" class="flex w-full items-center gap-3">
                        <component :is="item.icon" v-if="item.icon" class="size-5 opacity-70" />
                        <span>{{ trans(item.labelKey) }}</span>
                    </Link>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
