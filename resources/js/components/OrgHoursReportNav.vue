<script setup lang="ts">
// The nav that links the six DMV-wide reports to one another (#413, PRD #406, ADR-0022 §8):
// Summary and Detailed Committee Statistics, Active Members Ranked Hours, and the three
// zero-hours lists. Each is its own addressable route, so this bar is just links; the server
// gates every destination identically (viewOrgReports, §4).
//
// `print:hidden` because the nav is chrome, not report content. All labels are translated
// (ADR-0004).
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    active: 'summary' | 'detailed' | 'ranked' | 'zero_hours' | 'zero_shift' | 'zero_extra';
}>();

const links = [
    { key: 'summary', route: 'hours.committee-summary' },
    { key: 'detailed', route: 'hours.committee-detailed' },
    { key: 'ranked', route: 'hours.ranked' },
    { key: 'zero_hours', route: 'hours.zero-hours' },
    { key: 'zero_shift', route: 'hours.zero-shift-hours' },
    { key: 'zero_extra', route: 'hours.zero-extra-hours' },
] as const;
</script>

<template>
    <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.dmv.nav_label')">
        <Link
            v-for="link in links"
            :key="link.key"
            :href="route(link.route)"
            :aria-current="link.key === active ? 'page' : undefined"
            class="rounded-md px-3 py-1 text-sm font-medium"
            :class="link.key === active ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
        >
            {{ trans(`hours.dmv.nav.${link.key}`) }}
        </Link>
    </nav>
</template>
