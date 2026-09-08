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
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

// The rail type-ahead query (ADR-0020 §H). Lives here beside the input and threads down
// to NavRail, which filters the already-delivered rail prop entirely client-side — no
// server query, so it can never surface a node the Member was not already sent.
const query = ref('');
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <!-- Rail type-ahead filter (#73, ADR-0020 §H). Filters the already-delivered rail
             CLIENT-SIDE — no server query — so it can only ever surface nodes the Member
             was already sent. Lives only in the rail, so it's hidden when the rail is
             collapsed (reopen with ☰). Content/document search is a separate future PRD. -->
        <SidebarHeader class="px-2 pt-3 pb-1">
            <div class="relative">
                <PhMagnifyingGlass class="text-sidebar-foreground/70 pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                <SidebarInput
                    v-model="query"
                    type="search"
                    :placeholder="trans('nav.filter.placeholder')"
                    :aria-label="trans('nav.filter.aria')"
                    class="search-clear-white bg-rom-ink border-sidebar-border text-sidebar-foreground placeholder:text-sidebar-foreground/70 h-9 rounded-md pl-8"
                />
            </div>
        </SidebarHeader>
        <SidebarContent class="pt-2">
            <NavRail :query="query" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>

<style scoped>
/* WebKit's native search clear button renders in the OS accent colour (blue on macOS),
   which clashes with the dark rail. We can't recolour the native glyph, so drop it
   (appearance:none) and paint our own white X via a mask on the same pseudo-element.
   The class rides down onto the <input> two components deep, so :deep() is required. */
:deep(.search-clear-white)::-webkit-search-cancel-button {
    -webkit-appearance: none;
    appearance: none;
    width: 0.8rem;
    height: 0.8rem;
    cursor: pointer;
    background-color: #fff;
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='3' stroke-linecap='round'%3E%3Cpath d='M6 6l12 12M18 6L6 18'/%3E%3C/svg%3E")
        center / contain no-repeat;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='3' stroke-linecap='round'%3E%3Cpath d='M6 6l12 12M18 6L6 18'/%3E%3C/svg%3E")
        center / contain no-repeat;
}
</style>
