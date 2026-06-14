<script setup lang="ts">
// The black top bar (Chrome) — the contextual *section* nav surface, sitting above
// the breadcrumb strip and the white content canvas. The rail picks the context;
// this bar reflects it.
//   Left   — the ☰ sidebar trigger + the ROM/DMV wordmark (SVG, never live text),
//            linking Home.
//   Centre — the section-tab strip, resolved from the active context: Zone A on the
//            Dashboard (no Group), else the active Group's Menu.
//   Right  — the language switcher (globe, lg+ only) + the avatar menu. Search lives
//            in the rail. The EN/FR control is a globe dropdown here on desktop
//            (a selectable locale list with a checkmark); below lg it would crowd the
//            full-width section dropdown, so the same list moves into the avatar menu
//            (UserMenuContent). No notification bell (ADR-0013). #69
//
// On small screens (<sm) the wordmark drops — no square brand mark exists yet, and
// the ☰ + black bar anchor home (ADR-0013); a compact mark is a flagged follow-up.
//
// `activeGroupId` is threaded from the page (mirroring `breadcrumbs`); URL-derived
// context waits on bilingual routing (ADR-0008). Undefined → Zone A.
import BrandLogo from '@/components/BrandLogo.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SectionTabs from '@/components/SectionTabs.vue';
import TopBarUser from '@/components/TopBarUser.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { resolveSections } from '@/chrome/sections';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ activeGroupId?: string }>();

const sections = computed(() => resolveSections(props.activeGroupId));

// Keep the Home wordmark in the active locale (ADR-0008) — on /fr/ it must point at
// the French dashboard twin, not revert to the English root.
const localizeHref = useLocalizedHref();
</script>

<template>
    <header class="bg-rom-ink flex h-16 shrink-0 items-stretch gap-3 px-4 text-white">
        <div class="flex shrink-0 items-center gap-2">
            <SidebarTrigger class="text-white hover:bg-white/10 hover:text-white" />
            <!-- Wordmark drops below sm — no square mark exists yet (ADR-0013). -->
            <Link :href="localizeHref('/dashboard')" class="hidden items-center sm:flex" aria-label="Home">
                <BrandLogo variant="white" class="h-8 w-auto" />
            </Link>
        </div>

        <SectionTabs :items="sections" class="flex-1" />

        <!-- Right slot — language switcher (globe, lg+) + avatar menu (#69). Below lg
             the globe is hidden and the locale list lives in the avatar menu
             (UserMenuContent), so the bar isn't crowded next to the section dropdown. -->
        <div class="flex shrink-0 items-center gap-2">
            <div class="hidden items-center lg:flex">
                <LanguageSwitcher />
            </div>
            <TopBarUser />
        </div>
    </header>
</template>
