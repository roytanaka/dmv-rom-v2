<script setup lang="ts">
// The Member Extra Hours summary (#412, PRD #406, ADR-0022 §8) — a Member × twelve-month
// matrix of the extra hours each Member self-entered this fiscal year, read from the group's
// records carrying no meeting. This is "what people told us they did", kept apart from
// meeting attendance the way legacy prints them as two reports.
//
// Reports are not open reading (§4): the server gates this page to a Chair or Statistician of
// the group or any ancestor, or the super-tier. The `title`/`lead` distinguish this from its
// meeting-hours twin; everything else is the shared summary layout. All chrome is translated
// (ADR-0004); the group and member names render as-authored.
import HoursMemberSummary from '@/components/HoursMemberSummary.vue';
import { type GroupHoursSummary } from '@/types';

const props = defineProps<GroupHoursSummary>();
</script>

<template>
    <HoursMemberSummary v-bind="props" active="extra" title-key="hours.detail.extra.title" lead-key="hours.detail.extra.lead" />
</template>
