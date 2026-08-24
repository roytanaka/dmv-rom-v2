<script setup lang="ts">
// The three zero-hours reports (#413, PRD #406, ADR-0022 §8) — Members with Zero Hours, Zero
// Shift Hours, and Zero Extra Hours. One shared page; the `variant` names which list it is,
// because the renewal conversation needs a different list depending on the question being
// asked. Each lists the active and provisional members whose relevant fiscal-year sum is zero.
//
// Reports are officer-only (§4). All chrome is translated (ADR-0004); member names render
// as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import OrgHoursReportNav from '@/components/OrgHoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type DmvZeroHours } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<DmvZeroHours>();

// Each variant maps to its own route (for the fiscal-year picker) and its own nav highlight.
const routeName = computed(() => ({ hours: 'hours.zero-hours', shift: 'hours.zero-shift-hours', extra: 'hours.zero-extra-hours' })[props.variant]);
const active = computed(
    () => ({ hours: 'zero_hours', shift: 'zero_shift', extra: 'zero_extra' })[props.variant] as 'zero_hours' | 'zero_shift' | 'zero_extra',
);

const title = computed(() => trans(`hours.dmv.zero.${props.variant}.title`));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route(routeName.value) }]);

const csvHref = computed(() => route(`${routeName.value}.csv`, { fy: props.fiscalYear }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.dmv.zero.lead') }}</p>
            </header>

            <OrgHoursReportNav :active="active" />

            <HoursReportActions :csv-href="csvHref" />

            <!-- Fiscal-year picker — one link per pickable year, the year in view marked. -->
            <nav class="flex flex-wrap items-center gap-2 print:hidden" :aria-label="trans('hours.dmv.pick_year')">
                <span class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{{ trans('hours.dmv.pick_year') }}</span>
                <Link
                    v-for="year in fiscalYears"
                    :key="year"
                    :href="route(routeName, { fy: year })"
                    :aria-current="year === fiscalYear ? 'page' : undefined"
                    class="rounded-md px-3 py-1 text-sm font-medium"
                    :class="year === fiscalYear ? 'bg-rom-ink text-white' : 'text-rom-ink hover:bg-muted border-border border'"
                >
                    {{ trans('hours.dmv.fiscal_year', { year: String(year) }) }}
                </Link>
            </nav>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ trans('hours.dmv.fiscal_year', { year: String(fiscalYear) }) }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="members.length" class="flex flex-col gap-1">
                        <li v-for="member in members" :key="member.id" class="text-rom-ink border-border/60 border-b py-1.5 font-medium">
                            {{ member.name }}
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground py-6 text-center">{{ trans('hours.dmv.zero.empty') }}</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
