<script setup lang="ts">
// The black top bar (Chrome) — the contextual *section* nav surface, sitting above
// the breadcrumb strip and the white content canvas. The rail picks the context;
// this bar reflects it.
//   Left   — the ☰ sidebar trigger + the ROM/DMV wordmark (SVG, never live text),
//            linking Home.
//   Centre — the section-tab strip, resolved from the active context: Zone A on the
//            Dashboard (no Group), else the active Group's Menu.
//   Right  — a slot reserved for the avatar / search / language affordances (#69),
//            kept structurally present so the layout is final.
//
// `activeGroupId` is threaded from the page (mirroring `breadcrumbs`); URL-derived
// context waits on bilingual routing (ADR-0008). Undefined → Zone A.
import BrandLogo from '@/components/BrandLogo.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { resolveSections } from '@/chrome/sections';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ activeGroupId?: string }>();

const sections = computed(() => resolveSections(props.activeGroupId));
</script>

<template>
    <header class="bg-rom-ink flex h-16 shrink-0 items-stretch gap-3 px-4 text-white">
        <div class="flex shrink-0 items-center gap-2">
            <SidebarTrigger class="text-white hover:bg-white/10 hover:text-white" />
            <Link :href="route('dashboard')" class="flex items-center" aria-label="Home">
                <BrandLogo variant="white" class="h-8 w-auto" />
            </Link>
        </div>

        <SectionTabs :items="sections" class="flex-1" />

        <!-- Right slot — avatar / search / language land in #69; structurally reserved. -->
        <div class="flex shrink-0 items-center gap-2"></div>
    </header>
</template>
