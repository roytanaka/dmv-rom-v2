<script setup lang="ts">
// Member History (#412, PRD #406, ADR-0022 §8) — one Member's hours in this group over time.
// This is what a Chair opens before a standing conversation, and the only place one Member's
// record is legible to someone else.
//
// Reports are not open reading (§4): the server gates this page to a Chair or Statistician of
// the group or any ancestor, or the super-tier, and only a Member with hours here can be
// picked. The Member in view is the `?member=` query param. All chrome is translated
// (ADR-0004); the group and member names render as-authored.
import HoursReportActions from '@/components/HoursReportActions.vue';
import HoursReportNav from '@/components/HoursReportNav.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type GroupHoursMemberHistory, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<GroupHoursMemberHistory>();

const page = usePage<SharedData>();

const title = computed(() => trans('hours.detail.member.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: props.group.name, href: route('groups.show', { group: props.group.slug }) },
    { title: title.value, href: route('groups.hours.member', { group: props.group.slug }) },
]);

// The month name is a calendar month, not an instant: `month` is a first-of-month ISO date,
// so format it in UTC to name the month it is, never sliding a day into the one before.
const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso));

// Picking a Member navigates by the `?member=` param, so the view is addressable and the
// back button walks the history a Chair reviewed.
const pick = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    router.get(route('groups.hours.member', { group: props.group.slug, ...(value ? { member: value } : {}) }));
};

// The export follows the picked Member, so the file is the history the Chair is looking at.
const csvHref = computed(() => route('groups.hours.member.csv', { group: props.group.slug, ...(props.member ? { member: props.member.id } : {}) }));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-lg font-semibold">{{ group.name }} — {{ title }}</h1>
                <p class="text-muted-foreground text-sm">{{ trans('hours.detail.member.lead') }}</p>
            </header>

            <HoursReportNav :group-slug="group.slug" active="member" />

            <HoursReportActions :csv-href="csvHref" />

            <!-- Member picker — a plain select over the Members with hours in this group. -->
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <label for="member-pick" class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                    {{ trans('hours.detail.member.pick') }}
                </label>
                <select
                    id="member-pick"
                    class="border-border text-rom-ink rounded-md border px-3 py-1 text-sm"
                    :value="member?.id ?? ''"
                    @change="pick"
                >
                    <option value="">—</option>
                    <option v-for="option in members" :key="option.id" :value="option.id">{{ option.name }}</option>
                </select>
            </div>

            <Card>
                <!-- Names the Member in view. The picker above does not print, so without
                     this a printed history says whose hours it is nowhere on the page. The
                     name renders as-authored (ADR-0004: chrome is translated, content is
                     not). Absent until a Member is picked, when there is nothing to name. -->
                <CardHeader v-if="member">
                    <CardTitle class="text-base">{{ member.name }}</CardTitle>
                </CardHeader>
                <CardContent :class="member ? undefined : 'pt-6'">
                    <!-- No Member picked yet — a prompt, not a broken page. -->
                    <p v-if="!member" class="text-muted-foreground py-8 text-center">{{ trans('hours.detail.member.none') }}</p>

                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                                <th class="py-2 pr-4 font-semibold">{{ trans('hours.detail.member.column.month') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.detail.member.column.scheduled') }}</th>
                                <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.detail.member.column.extra') }}</th>
                                <th class="py-2 text-right font-semibold">{{ trans('hours.detail.member.column.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.year_month" class="border-border/60 border-b">
                                <td class="text-rom-ink py-1.5 pr-4 font-medium">{{ formatMonth(row.month) }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ row.scheduled_hours }}</td>
                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ row.extra_hours }}</td>
                                <td class="text-rom-ink py-1.5 text-right font-semibold tabular-nums">{{ row.total_hours }}</td>
                            </tr>
                            <!-- A picked Member with nothing on file — plain, never a broken page. -->
                            <tr v-if="!rows.length">
                                <td colspan="4" class="text-muted-foreground py-8 text-center">{{ trans('hours.detail.member.empty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
