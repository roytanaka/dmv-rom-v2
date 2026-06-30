<script setup lang="ts">
// The contextual section nav. Exactly one section is active — matched on the current
// Inertia URL — and highlighted in heritage-blue.
//
// Two render modes gated on a single `lg` media query (ADR-0013, reversing M1):
//   • lg+ (≥1024px): the horizontal tab strip. The full menu fits; the active tab's
//     heritage-blue bottom border is the selected cue (it sits LOWER in contrast than
//     the inactive tabs, so the underline must always render).
//   • below lg: the whole strip collapses into one full-width trigger naming the
//     current section, opening a vertical list of EVERY section — big rows, icons,
//     a consistent set every time (horizontal scroll is undiscoverable for the DMV's
//     aging volunteers; see ADR-0013).
//
// Two placements via `variant` (ADR-0013 amendment, #188):
//   • 'bar'  — the black chrome top bar (white-on-ink). The original placement.
//   • 'body' — sticky under a page's own header (ink-on-white), where a page owns its
//     tabs (a Group's Overview · Roster · Meetings). Same two-mode behaviour, retoned.
//
// A `soon` item is a capability slot whose feature hasn't shipped — rendered muted and
// NON-navigable (no link) in both modes, with the optional `soonLabel` marker. External
// tabs (e.g. Renew Membership) render as visibly outbound links.
import type { NavNode } from '@/chrome/types';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery, useScroll } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { PhCaretDown, PhList } from '@phosphor-icons/vue';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{ items: NavNode[]; variant?: 'bar' | 'body'; soonLabel?: string }>(), {
    variant: 'bar',
});

const page = usePage<SharedData>();
// Hrefs are English-canonical; localise them to the active locale so navigation stays
// in-locale (ADR-0008), and match the active tab against the localised href. A `soon`
// stub is never active.
const localizeHref = useLocalizedHref();
const isActive = (item: NavNode) => !item.soon && localizeHref(item.href) === page.url;

// The single break: at/above lg the full menu fits as tabs; below it can't.
const isWide = useMediaQuery('(min-width: 1024px)');

// The section to name on the collapsed trigger: the active one, else the first.
const activeLabel = computed(() => {
    const active = props.items.find((i) => isActive(i));
    return trans((active ?? props.items[0])?.labelKey ?? '');
});

// Per-variant tab styling. The two placements share structure (a bordered, padded,
// inline row) and diverge only in tone — white-on-ink for the bar, ink-on-white for
// the body — and the active/soon states within each.
const tabClass = (item: NavNode): string => {
    const base = 'flex shrink-0 items-center gap-1.5 border-b-2 px-3 whitespace-nowrap transition-colors text-sm';
    if (props.variant === 'body') {
        if (item.soon) return `${base} font-medium border-transparent text-muted-foreground/60 cursor-default`;
        return isActive(item)
            ? `${base} font-medium border-rom-slate text-rom-slate`
            : `${base} font-medium border-transparent text-muted-foreground hover:text-rom-ink`;
    }
    if (item.soon) return `${base} border-transparent text-white/40 cursor-default`;
    return isActive(item) ? `${base} border-rom-slate-300 text-rom-slate-300` : `${base} border-transparent text-white/70 hover:text-white`;
};

const triggerClass = computed(() =>
    props.variant === 'body'
        ? 'border-input text-rom-ink bg-background hover:bg-muted'
        : 'border-rom-slate-300/40 text-rom-slate-300 bg-white/5 hover:bg-white/10',
);

const dropdownActiveClass = (item: NavNode) =>
    isActive(item) ? (props.variant === 'body' ? 'text-rom-slate font-semibold' : 'text-rom-ink font-semibold') : '';

// Edge fade — the strip is horizontally scrollable, so we fade the side that has
// content scrolled out of view (and only that side: no fade at the very start/end).
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
            <!-- Soon stub — a capability whose feature hasn't shipped; muted, not a link. -->
            <span v-if="item.soon" :class="tabClass(item)" aria-disabled="true">
                <component :is="item.icon" v-if="item.icon" class="size-4 opacity-70" />
                <span>{{ trans(item.labelKey) }}</span>
                <span v-if="soonLabel" class="text-[0.625rem] font-semibold tracking-wide uppercase opacity-70">{{ soonLabel }}</span>
            </span>

            <!-- Outbound tab — leaves the app; the trailing icon marks it external. -->
            <a v-else-if="item.external" :href="item.href" target="_blank" rel="noopener noreferrer" :class="tabClass(item)">
                <span>{{ trans(item.labelKey) }}</span>
                <component :is="item.icon" v-if="item.icon" class="size-4 opacity-80" />
            </a>

            <Link v-else :href="localizeHref(item.href)" :aria-current="isActive(item) ? 'page' : undefined" :class="tabClass(item)">
                <component :is="item.icon" v-if="item.icon" class="size-4 opacity-80" />
                <span>{{ trans(item.labelKey) }}</span>
            </Link>
        </template>
    </nav>

    <!-- Narrow (<lg): one full-width trigger naming the current section. Square
         corners (ROM identity); DropdownMenuContent/Item are already rounded-none. -->
    <DropdownMenu v-else>
        <DropdownMenuTrigger
            :class="[
                'flex h-11 w-full items-center justify-between gap-2 self-center rounded-none border px-3 text-base font-medium outline-none',
                triggerClass,
            ]"
        >
            <span class="flex items-center gap-2 truncate"><PhList class="size-5 shrink-0 opacity-80" /> {{ activeLabel }}</span>
            <PhCaretDown class="size-5 shrink-0 opacity-80" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-[calc(100vw-2rem)] max-w-sm">
            <template v-for="item in items" :key="item.href">
                <!-- Soon stub — disabled, muted, with the marker; never a link. -->
                <DropdownMenuItem v-if="item.soon" :disabled="true" class="text-muted-foreground gap-3 py-3 text-base">
                    <component :is="item.icon" v-if="item.icon" class="size-5 opacity-70" />
                    <span>{{ trans(item.labelKey) }}</span>
                    <span v-if="soonLabel" class="ml-auto text-[0.625rem] font-semibold tracking-wide uppercase opacity-70">{{ soonLabel }}</span>
                </DropdownMenuItem>

                <!-- Outbound section — leaves the app. -->
                <DropdownMenuItem v-else-if="item.external" :as-child="true" class="gap-3 py-3 text-base">
                    <a :href="item.href" target="_blank" rel="noopener noreferrer" class="flex w-full items-center gap-3">
                        <component :is="item.icon" v-if="item.icon" class="size-5 opacity-70" />
                        <span>{{ trans(item.labelKey) }}</span>
                    </a>
                </DropdownMenuItem>

                <DropdownMenuItem v-else :as-child="true" :class="['gap-3 py-3 text-base', dropdownActiveClass(item)]">
                    <Link :href="localizeHref(item.href)" :aria-current="isActive(item) ? 'page' : undefined" class="flex w-full items-center gap-3">
                        <component :is="item.icon" v-if="item.icon" class="size-5 opacity-70" />
                        <span>{{ trans(item.labelKey) }}</span>
                    </Link>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
