<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { useChromeRevealDriver } from '@/composables/useChromeReveal';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

// The sidebar open state is persisted in the `sidebar:state` cookie by
// SidebarProvider's setOpen, and read back server-side and shared as
// `sidebarOpen` (see HandleInertiaRequests). Seeding :default-open from that
// shared prop lets the rail render in its remembered state on first paint —
// no client-side flash, single source of truth.
const page = usePage<SharedData>();

// Below lg the top bar hides on scroll down (#740). --header-offset is the part of the
// bar still on screen, so the md–lg fixed rail moves up with it and leaves no gap.
const { hidden } = useChromeRevealDriver();
</script>

<template>
    <!-- flex-col so the full-width top bar stacks above the [rail][content] row;
         --header-height (= TopBar h-16 / 4rem) offsets the rail's fixed positioning
         so it starts below the bar (consumed in Sidebar.vue). -->
    <SidebarProvider
        :default-open="page.props.sidebarOpen"
        :class="['flex-col [--header-height:4rem]', hidden ? '[--header-offset:0px]' : '[--header-offset:var(--header-height)]']"
    >
        <slot />
    </SidebarProvider>
</template>
