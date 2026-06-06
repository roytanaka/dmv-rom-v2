<script setup lang="ts">
// The black top bar (Chrome) — the contextual *section* nav surface, sitting above
// the breadcrumb strip and the white content canvas. The rail picks the context;
// this bar reflects it.
//   Left   — the ☰ sidebar trigger + the ROM/DMV wordmark (SVG, never live text),
//            linking Home.
//   Centre — the section-tab strip, resolved from the active context: Zone A on the
//            Dashboard (no Group), else the active Group's Menu.
//   Right  — the avatar menu plus two inert placeholders (search, EN/FR language).
//            Both are present so the layout is final but visibly non-functional:
//            search has no backend yet, and bilingual routing (ADR-0008) lands later.
//            No notification bell (the prototype's bell was dropped). #69
//
// `activeGroupId` is threaded from the page (mirroring `breadcrumbs`); URL-derived
// context waits on bilingual routing (ADR-0008). Undefined → Zone A.
import BrandLogo from '@/components/BrandLogo.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import TopBarUser from '@/components/TopBarUser.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { resolveSections } from '@/chrome/sections';
import { Link } from '@inertiajs/vue3';
import { PhMagnifyingGlass, PhTranslate } from '@phosphor-icons/vue';
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

        <!-- Right slot — inert search + inert EN/FR toggle + avatar menu (#69). -->
        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            <!-- Inert search — full field on desktop, icon affordance on mobile. No backend yet. -->
            <div class="relative hidden md:block">
                <PhMagnifyingGlass class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-white/50" />
                <input
                    type="search"
                    disabled
                    placeholder="Search"
                    aria-label="Search (not yet available)"
                    class="h-9 w-44 cursor-not-allowed rounded-md border border-white/15 bg-white/10 pr-3 pl-8 text-sm text-white placeholder:text-white/50"
                />
            </div>
            <button
                type="button"
                disabled
                aria-label="Search (not yet available)"
                class="flex size-9 cursor-not-allowed items-center justify-center rounded-md text-white/60 md:hidden"
            >
                <PhMagnifyingGlass class="size-5" />
            </button>

            <!-- Inert language toggle — bilingual routing (ADR-0008) lands later. -->
            <button
                type="button"
                disabled
                aria-label="Switch language (not yet available)"
                class="flex h-9 cursor-not-allowed items-center gap-1 rounded-md px-2 text-sm text-white/60"
            >
                <PhTranslate class="size-4" />
                <span>EN<span class="text-white/40">/FR</span></span>
            </button>

            <TopBarUser />
        </div>
    </header>
</template>
