<script setup lang="ts">
// The black top bar (Chrome) — ONE fixed global navigation strip, identical on every
// page (#194, ADR-0013 amendment). It carries cross-domain navigation only; a page's
// within-domain section tabs now live in the page body (the Group page's sticky strip),
// not here. The bar no longer reflects the active context.
//   Left   — the ☰ sidebar trigger + the ROM/DMV wordmark (SVG, never live text),
//            linking Home. The wordmark drops below sm (no square mark exists yet).
//   Centre — the primary destinations (My Hours · My Calendar · News · Directory),
//            persistent locale-aware links shared from the server (`chromeNav`); the
//            active one is highlighted in heritage-blue.
//   Right  — Help (a utility destination), the language switcher (globe, lg+ only),
//            and the avatar menu. Search lives in the rail. Below lg the globe is
//            hidden and the locale list moves into the avatar menu (UserMenuContent).
//
// Destinations are resolved and gated SERVER-side and shared via `chromeNav` — their
// hrefs are already localized to the active locale (ADR-0008), so they are used
// verbatim and matched against the current URL for the active state.
import BrandLogo from '@/components/BrandLogo.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import TopBarUser from '@/components/TopBarUser.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import type { ChromeDestination, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhQuestion } from '@phosphor-icons/vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const nav = computed(() => page.props.chromeNav);

// Hrefs arrive already localized (server-side); match the active one against the
// current URL. The Home wordmark is authored English-canonical, so it still localizes
// client-side to stay in-locale on /fr/ (ADR-0008).
const localizeHref = useLocalizedHref();
const isActive = (dest: ChromeDestination) => dest.href === page.url;

// Shared destination styling — white-on-ink, heritage-blue active underline. The
// active tab sits LOWER in contrast than the inactive ones, so the cue rides on the
// hue and the bottom border, which must always render (ADR-0013 contrast findings).
const destClass = (dest: ChromeDestination): string => {
    const base = 'flex shrink-0 items-center gap-1.5 border-b-2 px-3 whitespace-nowrap transition-colors text-sm font-medium';
    return isActive(dest) ? `${base} border-rom-slate-300 text-rom-slate-300` : `${base} border-transparent text-white/70 hover:text-white`;
};
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

        <!-- Primary destinations — the fixed global strip, identical on every page. -->
        <nav class="flex min-w-0 flex-1 items-stretch gap-1 overflow-x-auto" aria-label="Primary">
            <Link
                v-for="dest in nav.destinations"
                :key="dest.key"
                :href="dest.href"
                :aria-current="isActive(dest) ? 'page' : undefined"
                :class="destClass(dest)"
            >
                {{ trans(dest.labelKey) }}
            </Link>
        </nav>

        <!-- Right utilities — Help, the language switcher (globe, lg+), the avatar
             menu. Below lg the globe is hidden and the locale list lives in the
             avatar menu (UserMenuContent), so the bar isn't crowded (#69). -->
        <div class="flex shrink-0 items-center gap-2">
            <Link
                :href="nav.help.href"
                :aria-current="isActive(nav.help) ? 'page' : undefined"
                class="flex items-center gap-1.5 px-2 text-sm font-medium text-white/70 transition-colors hover:text-white"
            >
                <PhQuestion class="size-4 opacity-80" />
                <span class="hidden sm:inline">{{ trans(nav.help.labelKey) }}</span>
            </Link>
            <div class="hidden items-center lg:flex">
                <LanguageSwitcher />
            </div>
            <TopBarUser />
        </div>
    </header>
</template>
