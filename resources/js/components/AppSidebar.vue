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
import { Sidebar, SidebarContent, SidebarHeader, SidebarInput } from '@/components/ui/sidebar';
import { PhMagnifyingGlass } from '@phosphor-icons/vue';
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <!-- Inert search — relocated here from the top bar (#73). No backend yet; styled
             for the charcoal rail. Lives only in the rail, so it's hidden when the rail
             is collapsed (reopen with ☰). -->
        <SidebarHeader class="px-2 pt-3 pb-1">
            <div class="relative">
                <PhMagnifyingGlass class="text-sidebar-foreground/70 pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                <SidebarInput
                    type="search"
                    disabled
                    placeholder="Search"
                    aria-label="Search (not yet available)"
                    class="bg-rom-ink border-sidebar-border text-sidebar-foreground placeholder:text-sidebar-foreground/70 h-9 cursor-not-allowed rounded-md pl-8 opacity-100 disabled:opacity-100"
                />
            </div>
        </SidebarHeader>
        <SidebarContent class="pt-2">
            <NavRail />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
