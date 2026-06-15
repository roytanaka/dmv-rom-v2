<script setup lang="ts">
// The desktop language switcher (ADR-0013) — a globe button in the top bar showing
// the active locale, opening a list of every supported locale with a checkmark on
// the active one. A selectable list (not a toggle) so the current selection is
// always visible and the choice is explicit — the clarity problem the old "Language
// … FR" badge had. Shown at lg+ only; below lg it would crowd the full-width section
// dropdown, so the same list moves into the avatar menu (UserMenuContent).
//
// Switching uses a plain anchor (not an Inertia <Link>) so a full page load re-boots
// the laravel-vue-i18n bridge in the target locale (#110). Locales with no twin on
// the current page arrive with url: null and render disabled — we never offer a link
// that 404s (ADR-0008).
import LocaleOptionList from '@/components/LocaleOptionList.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhCaretDown, PhGlobe } from '@phosphor-icons/vue';
import { computed } from 'vue';

const page = usePage<SharedData>();
const switcher = computed(() => page.props.localeSwitcher);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            :aria-label="trans('user.language')"
            class="ring-offset-rom-ink flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-sm font-medium text-white hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            <PhGlobe class="h-4 w-4" />
            {{ switcher.current.toUpperCase() }}
            <PhCaretDown class="h-3 w-3 opacity-70" />
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-44" align="end" :side-offset="8">
            <LocaleOptionList :switcher="switcher" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
