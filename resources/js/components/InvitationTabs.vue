<script setup lang="ts">
// The Invitations tabs (ADR-0034 §13): My invitations for everyone, Hosting only when the
// server says this Member may host. Rendered through SectionTabs in its body placement.
import type { NavNode } from '@/chrome/types';
import SectionTabs from '@/components/SectionTabs.vue';
import { computed } from 'vue';

const props = defineProps<{ canHost: boolean }>();

const items = computed<NavNode[]>(() => [
    { href: route('invitations', {}, false), labelKey: 'invitations.tabs.mine' },
    ...(props.canHost ? [{ href: route('invitations.hosting', {}, false), labelKey: 'invitations.tabs.hosting' }] : []),
]);
</script>

<template>
    <SectionTabs :items="items" variant="body" />
</template>
