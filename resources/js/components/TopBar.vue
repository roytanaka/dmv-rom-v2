<script setup lang="ts">
// The black top bar (Chrome) — ONE fixed global navigation strip, identical on every
// page (#194, ADR-0013 amendment). It carries cross-domain navigation only; a page's
// within-domain section tabs now live in the page body (the Group page's sticky strip),
// not here. The bar no longer reflects the active context.
//   Left   — the ☰ sidebar trigger + the ROM/DMV wordmark (SVG, never live text),
//            linking Home. The wordmark drops below sm (no square mark exists yet).
//   Centre — the primary destinations (My Hours · My Calendar · News · Directory),
//            persistent locale-aware links shared from the server (`chromeNav`); the
//            active one is highlighted in heritage-blue. Below lg they don't fit, so
//            they collapse into one menu named for the current page (#741, ADR-0013
//            option C): a horizontal scroll strip hides links from this audience.
//   Right  — the Help menu (ADR-0025 amendment), the language switcher (globe, lg+
//            only), and the avatar menu. Search lives in the rail. Below lg the globe is
//            hidden and the locale list moves into the avatar menu (UserMenuContent).
//
// Destinations are resolved and gated SERVER-side and shared via `chromeNav` — their
// hrefs are already localized to the active locale (ADR-0008), so they are used
// verbatim and matched against the current URL for the active state.
import { activeDestination } from '@/chrome/activeSection';
import BrandLogo from '@/components/BrandLogo.vue';
import FeedbackDialog from '@/components/FeedbackDialog.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import TopBarUser from '@/components/TopBarUser.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useChromeBar } from '@/composables/useChromeReveal';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import type { ChromeDestination, PhosphorIcon, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhBookOpen, PhCaretDown, PhChatCenteredText, PhCheck, PhLifebuoy, PhListBullets, PhQuestion } from '@phosphor-icons/vue';
import { computed, ref, useTemplateRef } from 'vue';

const page = usePage<SharedData>();
const nav = computed(() => page.props.chromeNav);

// Below lg the bar slides out on scroll down and back in on scroll up (#740).
const { hidden } = useChromeBar(useTemplateRef<HTMLElement>('bar'));

// Hrefs arrive already localized (server-side); match the active one against the
// current URL. The Home wordmark is authored English-canonical, so it still localizes
// client-side to stay in-locale on /fr/ (ADR-0008).
const localizeHref = useLocalizedHref();
const isCurrentPage = (dest: ChromeDestination) => dest.href === page.url;

// A primary destination stays active on the pages beneath it (`/directory?page=2`), so the
// collapsed menu keeps the page's name. With none active the trigger reads "Menu".
const activeDest = computed(() => activeDestination(nav.value.destinations, page.url));
const isDestActive = (dest: ChromeDestination) => activeDest.value?.key === dest.key;
const menuLabel = computed(() => trans(activeDest.value?.labelKey ?? 'nav.menu'));

// Help menu item icons are fixed client config keyed by item `key`, like the rail's
// officer items; the server ships only key, label, and href.
const HELP_ICONS: Record<string, PhosphorIcon> = {
    page: PhBookOpen,
    centre: PhLifebuoy,
    'feedback-send': PhChatCenteredText,
    'feedback-list': PhListBullets,
};

// Tester feedback (#676, ADR-0029 §12). The server adds these items outside production
// only. Send feedback opens the dialog instead of navigating; its href is where the
// dialog posts. A separator sets the feedback items apart from help.
const FEEDBACK_SEND = 'feedback-send';
const feedbackSend = computed(() => nav.value.help.items.find((item) => item.key === FEEDBACK_SEND) ?? null);
const feedbackOpen = ref(false);
const startsFeedback = (item: ChromeDestination) => item.key.startsWith('feedback-');

// Shared destination styling — white-on-ink. The active tab is the BRIGHTEST in the
// strip (full-white text, semibold) with a heritage-slate underline carrying the hue,
// so it reads as active rather than dimmer than its neighbours; inactive tabs sit back
// at white/70 and brighten on hover. The bottom border always renders (no layout shift).
const destClass = (dest: ChromeDestination): string => {
    const base = 'flex shrink-0 items-center gap-1.5 border-b-[3px] px-3 whitespace-nowrap transition-colors text-sm font-medium';
    return isDestActive(dest) ? `${base} border-rom-slate-300 font-semibold text-white` : `${base} border-transparent text-white/70 hover:text-white`;
};
</script>

<template>
    <header
        ref="bar"
        class="bg-rom-ink flex h-16 shrink-0 items-stretch text-white transition-transform duration-200 ease-out motion-reduce:transition-none print:hidden"
        :class="{ '-translate-y-full': hidden }"
    >
        <!-- Left cluster spans exactly the sidebar's width at md+ (where the fixed rail
             is visible), so the primary nav starts at the sidebar's right edge. Below md
             the rail is an off-canvas sheet, so this stays auto-width. -->
        <div class="flex shrink-0 items-center gap-2 pl-4 md:w-(--sidebar-width) md:px-4">
            <SidebarTrigger class="text-white hover:bg-white/10 hover:text-white" />
            <!-- Wordmark drops below sm — no square mark exists yet (ADR-0013). -->
            <Link :href="localizeHref('/dashboard')" class="hidden items-center sm:flex" aria-label="Home">
                <BrandLogo variant="white" class="h-8 w-auto" />
            </Link>
        </div>

        <!-- Primary destinations — the fixed global set, identical on every page. -->
        <nav class="flex min-w-0 flex-1 items-stretch" aria-label="Primary">
            <!-- Below lg: one trigger naming the current page, opening every destination. -->
            <DropdownMenu>
                <DropdownMenuTrigger
                    class="border-rom-slate-300/40 text-rom-slate-300 ring-offset-rom-ink flex h-11 max-w-56 min-w-0 items-center justify-between gap-2 self-center border bg-white/5 px-3 text-base font-medium transition-colors hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:outline-none lg:hidden"
                >
                    <span class="truncate">{{ menuLabel }}</span>
                    <PhCaretDown class="size-4 shrink-0 opacity-80" />
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-56" align="start" :side-offset="8">
                    <DropdownMenuItem v-for="dest in nav.destinations" :key="dest.key" :as-child="true" class="py-3 text-base">
                        <Link
                            :href="dest.href"
                            :aria-current="isDestActive(dest) ? 'page' : undefined"
                            :class="['flex w-full items-center gap-3', { 'text-rom-ink font-semibold': isDestActive(dest) }]"
                        >
                            {{ trans(dest.labelKey) }}
                            <PhCheck v-if="isDestActive(dest)" class="ml-auto size-4" />
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- lg+: the destinations inline. -->
            <div class="hidden items-stretch gap-1 lg:flex">
                <Link
                    v-for="dest in nav.destinations"
                    :key="dest.key"
                    :href="dest.href"
                    :aria-current="isDestActive(dest) ? 'page' : undefined"
                    :class="destClass(dest)"
                >
                    {{ trans(dest.labelKey) }}
                </Link>
            </div>
        </nav>

        <!-- Right utilities — Help, the language switcher (globe, lg+), the avatar
             menu. Below lg the globe is hidden and the locale list lives in the
             avatar menu (UserMenuContent), so the bar isn't crowded (#69). -->
        <div class="flex shrink-0 items-center gap-2 pr-4 pl-2">
            <!-- Help — always a menu, even with one item (ADR-0025 amendment). The label
                 hides below sm, so the trigger carries its name in aria-label. -->
            <DropdownMenu>
                <DropdownMenuTrigger
                    :aria-label="trans(nav.help.labelKey)"
                    class="ring-offset-rom-ink flex h-10 items-center gap-1.5 px-2 text-sm font-medium text-white/70 transition-colors hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:outline-none data-[state=open]:text-white pointer-coarse:h-11"
                >
                    <PhQuestion class="size-4 opacity-80" />
                    <span class="hidden sm:inline">{{ trans(nav.help.labelKey) }}</span>
                    <PhCaretDown class="size-3 opacity-70" />
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-56" align="end" :side-offset="8">
                    <template v-for="(item, index) in nav.help.items" :key="item.key">
                        <DropdownMenuSeparator v-if="index > 0 && startsFeedback(item) && !startsFeedback(nav.help.items[index - 1])" />
                        <DropdownMenuItem v-if="item.key === FEEDBACK_SEND" class="py-2.5" @select="feedbackOpen = true">
                            <component :is="HELP_ICONS[item.key]" class="text-muted-foreground size-4" />
                            {{ trans(item.labelKey) }}
                        </DropdownMenuItem>
                        <DropdownMenuItem v-else class="py-2.5" :as-child="true">
                            <Link class="w-full" :href="item.href" :aria-current="isCurrentPage(item) ? 'page' : undefined">
                                <component :is="HELP_ICONS[item.key]" class="text-muted-foreground size-4" />
                                {{ trans(item.labelKey) }}
                            </Link>
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenu>
            <FeedbackDialog v-if="feedbackSend" v-model:open="feedbackOpen" :href="feedbackSend.href" />
            <div class="hidden items-center lg:flex">
                <LanguageSwitcher />
            </div>
            <TopBarUser />
        </div>
    </header>
</template>
