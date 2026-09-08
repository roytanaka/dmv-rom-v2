<script setup lang="ts">
// The nav linking the DMV-wide reports (#413, PRD #406, ADR-0022 §8). Each is its own
// addressable route. Most destinations require viewOrgReports (§4); the visitors report is
// the named exception — open to any signed-in Member — but this nav is only rendered for
// officers (canViewOrgReports in each page), so every link here resolves for its viewer.
//
// `print:hidden` because the nav is chrome, not report content. All labels are translated
// (ADR-0004).
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    active: 'summary' | 'detailed' | 'visitors' | 'ranked' | 'zero_hours' | 'zero_shift' | 'zero_extra';
}>();

const links = [
    { key: 'summary', route: 'hours.committee-summary' },
    { key: 'detailed', route: 'hours.committee-detailed' },
    { key: 'visitors', route: 'hours.visitor-summary' },
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
