<script setup lang="ts">
// The selectable locale list shared by both language switchers (ADR-0013): the
// desktop top-bar globe (LanguageSwitcher) and the below-lg avatar menu
// (UserMenuContent). A checkmark marks the active locale; switchable locales are
// plain anchors (a full page load re-boots the i18n bridge, #110); locales with no
// twin on the current page render disabled so we never offer a link that 404s
// (ADR-0008). The globe icon class differs between hosts, so it's a prop.
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { PhCheck, PhGlobe } from '@phosphor-icons/vue';

withDefaults(
    defineProps<{
        switcher: SharedData['localeSwitcher'];
        iconClass?: string;
    }>(),
    { iconClass: 'text-muted-foreground mr-2 h-4 w-4' },
);
</script>

<template>
    <template v-for="opt in switcher.options" :key="opt.code">
        <!-- Active locale — checkmark, not a link. -->
        <DropdownMenuItem v-if="opt.code === switcher.current" class="py-2.5">
            <PhGlobe :class="iconClass" />
            {{ opt.label }}
            <PhCheck class="ml-auto h-4 w-4" />
        </DropdownMenuItem>
        <!-- Switchable locale — full-page anchor so the i18n bridge reboots (#110). -->
        <DropdownMenuItem v-else-if="opt.url" class="py-2.5" :as-child="true">
            <a class="flex w-full items-center" :href="opt.url">
                <PhGlobe :class="iconClass" />
                {{ opt.label }}
            </a>
        </DropdownMenuItem>
        <!-- No twin on this page — disabled, never offer a 404 (ADR-0008). -->
        <DropdownMenuItem v-else class="py-2.5" disabled>
            <PhGlobe :class="iconClass" />
            {{ opt.label }}
        </DropdownMenuItem>
    </template>
</template>
