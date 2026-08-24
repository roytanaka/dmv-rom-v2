<script setup lang="ts">
// The Member Meeting Hours summary (#412, PRD #406, ADR-0022 §8) — the twin of the Extra Hours
// summary, read instead from the group's records carrying a meeting. This is "what people
// showed up to". Meeting-hours entry is out of this pass (§2): legacy rows import and this
// reads them, but nothing records new ones, so a recent fiscal year renders empty — the honest
// answer, not a bug.
//
// Reports are not open reading (§4): the server gates this page to a Chair or Statistician of
// the group or any ancestor, or the super-tier. All chrome is translated (ADR-0004); the group
// and member names render as-authored.
import HoursMemberSummary from '@/components/HoursMemberSummary.vue';
import { type GroupHoursSummary } from '@/types';

const props = defineProps<GroupHoursSummary>();
</script>

<template>
    <HoursMemberSummary v-bind="props" active="meetings" title-key="hours.detail.meetings.title" lead-key="hours.detail.meetings.lead" />
</template>
