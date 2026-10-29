<script setup lang="ts">
// The DMV Settings section switch (#841, ADR-0036 §3): Groups · Stewardships, one route each,
// rendered through SectionTabs in its body placement so the strip collapses to a single trigger
// below lg. A thin wrapper so the DMV Settings pages share one item list. The hrefs are the
// server's, already localized (ADR-0008). A Group's structure page sits under the Groups href,
// so Groups stays active there (#648). A parent officer's page (`scoped`, #883) has no
// Stewardships section, so the strip is not shown at all.
import SectionTabs from '@/components/SectionTabs.vue';
import type { NavNode } from '@/chrome/types';
import { computed } from 'vue';

const props = defineProps<{ groupsHref: string; stewardshipsHref: string; scoped?: boolean }>();

const items = computed<NavNode[]>(() => [
    { href: props.groupsHref, labelKey: 'dmv_settings.section.groups' },
    { href: props.stewardshipsHref, labelKey: 'dmv_settings.section.stewardships' },
]);
</script>

<template>
    <SectionTabs v-if="!scoped" :items="items" variant="body" />
</template>
