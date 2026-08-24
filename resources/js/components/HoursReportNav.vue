<script setup lang="ts">
// The nav that links the officer hours surfaces to one another (#412, PRD #406, ADR-0022 §8):
// the fiscal-year matrix (#411) and the four surfaces after it — the month picker, Member
// History, and the two Member × twelve-month summaries. Each is its own addressable route, so
// this bar is just links; the server gates every destination identically (viewReports, §4).
//
// `print:hidden` because the nav is chrome, not report content — a printed report carries the
// matrix, not the way one navigated to it. All labels are translated (ADR-0004).
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    groupSlug: string;
    active: 'report' | 'month' | 'member' | 'extra' | 'meetings';
}>();

const links = [
    { key: 'report', route: 'groups.hours.report' },
    { key: 'month', route: 'groups.hours.month' },
    { key: 'member', route: 'groups.hours.member' },
    { key: 'extra', route: 'groups.hours.extra' },
    { key: 'meetings', route: 'groups.hours.meetings' },
] as const;
</script>

<template>
    <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.report.title')">
        <Link
            v-for="link in links"
            :key="link.key"
            :href="route(link.route, { group: groupSlug })"
            :aria-current="link.key === active ? 'page' : undefined"
            class="rounded-md px-3 py-1 text-sm font-medium"
            :class="link.key === active ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
        >
            {{ trans(`hours.detail.nav.${link.key}`) }}
        </Link>
    </nav>
</template>
