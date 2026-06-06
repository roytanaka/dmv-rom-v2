<script setup lang="ts">
// The charcoal grouping rail (Chrome Zone B + Zone C). Built on the restyled shadcn
// `sidebar` primitive: offcanvas on desktop (the ☰ trigger toggles it), and its
// built-in mobile sheet is the ☰ drawer on small screens (decision M1) — one IA
// across breakpoints.
//
// The ROM/DMV wordmark and the avatar menu both live in the top bar (#67/#69),
// which persists when this rail is collapsed — so the rail is nav only, no second
// wordmark and no footer user menu.
import NavRail from '@/components/NavRail.vue';
import { Sidebar, SidebarContent } from '@/components/ui/sidebar';
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarContent class="pt-2">
            <NavRail />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
