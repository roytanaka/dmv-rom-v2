<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

// The sidebar open state is persisted in the `sidebar:state` cookie by
// SidebarProvider's setOpen, and read back server-side and shared as
// `sidebarOpen` (see HandleInertiaRequests). Seeding :default-open from that
// shared prop lets the rail render in its remembered state on first paint —
// no client-side flash, single source of truth.
const page = usePage<SharedData>();
</script>

<template>
    <!-- flex-col so the full-width top bar stacks above the [rail][content] row;
         --header-height (= TopBar h-16 / 4rem) offsets the rail's fixed positioning
         so it starts below the bar (consumed in Sidebar.vue). -->
    <SidebarProvider :default-open="page.props.sidebarOpen" class="flex-col [--header-height:4rem]">
        <slot />
    </SidebarProvider>
</template>
