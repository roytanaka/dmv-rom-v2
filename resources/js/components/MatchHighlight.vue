<script setup lang="ts">
// A name with the parts a search matched marked (Feedback item 85) — the Tours page marks a
// matched Tour or Member name. The cut lives in ./tours/toursSearch.ts.
import { splitMatch } from '@/tours/toursSearch';

defineProps<{ text: string; query: string }>();
</script>

<template>
    <template v-for="(part, index) in splitMatch(text, query)" :key="index">
        <mark v-if="part.match" class="bg-warning-bg text-inherit">{{ part.text }}</mark>
        <template v-else>{{ part.text }}</template>
    </template>
</template>
