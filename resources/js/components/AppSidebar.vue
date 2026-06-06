<script setup lang="ts">
// The charcoal grouping rail (Chrome Zone B + Zone C). Built on the restyled shadcn
// `sidebar` primitive: offcanvas on desktop (the ☰ trigger toggles it), and its
// built-in mobile sheet is the ☰ drawer on small screens (decision M1) — one IA
// across breakpoints.
//
// The ROM/DMV wordmark lives in the top bar (#67), which persists when this rail is
// collapsed — so the rail carries no second wordmark. The user/avatar menu sits in
// the footer for this slice; it moves to the top bar when that surface lands (#69).
import NavRail from '@/components/NavRail.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter } from '@/components/ui/sidebar';
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarContent class="pt-2">
            <NavRail />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
