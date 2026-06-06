<script setup lang="ts">
// The charcoal grouping rail (Chrome Zone B + Zone C). Built on the restyled shadcn
// `sidebar` primitive: offcanvas on desktop (the ☰ trigger toggles it), and its
// built-in mobile sheet is the ☰ drawer on small screens (decision M1) — one IA
// across breakpoints. The ROM/DMV wordmark at the head links Home.
//
// The user/avatar menu lives in the footer for this slice; it moves to the top bar
// when that surface lands (#69).
import BrandLogo from '@/components/BrandLogo.vue';
import NavRail from '@/components/NavRail.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarHeader>
            <Link :href="route('dashboard')" class="flex items-center px-2 py-1.5">
                <BrandLogo variant="white" class="h-9 w-auto" />
            </Link>
        </SidebarHeader>

        <SidebarContent>
            <NavRail />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
