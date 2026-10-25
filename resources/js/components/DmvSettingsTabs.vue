<script setup lang="ts">
// The DMV Settings section switch (#841, ADR-0036 §3): Groups · Stewardships, one route each,
// rendered through SectionTabs in its body placement so the strip collapses to a single trigger
// below lg. A thin wrapper so the DMV Settings pages share one item list. Hrefs are
// English-canonical; SectionTabs localizes them to the active locale (ADR-0008). A Group's
// structure page sits under /officer/settings, so Groups stays active there (#648).
import SectionTabs from '@/components/SectionTabs.vue';
import type { NavNode } from '@/chrome/types';

const items: NavNode[] = [
    { href: '/officer/settings', labelKey: 'dmv_settings.section.groups' },
    { href: '/officer/settings/stewardships', labelKey: 'dmv_settings.section.stewardships' },
];
</script>

<template>
    <SectionTabs :items="items" variant="body" />
</template>
