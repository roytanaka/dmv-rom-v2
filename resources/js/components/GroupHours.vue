<script setup lang="ts">
// Group Hours tab (#408, PRD #406, ADR-0022 §2) — the extra-hours entry surface and the
// Member's own record list. A Member records the hours they spent helping this Group and
// sees what they have recorded here; they never see anyone else's (§4).
//
// Entry is additive and open to any participating Member (server-gated by `canEnter`): the
// number typed is added to what is on file, a negative number corrects, and the server
// floors the result at zero. Exactly two months are offered — the current one and the
// previous — each showing the hours already on file and when they were last touched, so a
// Member does not double-count. The form states plainly that scheduled shifts and meetings
// are already counted. All strings are translated chrome (ADR-0004).
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type GroupHours, type SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhChartBar, PhClock } from '@phosphor-icons/vue';

const props = defineProps<{ hours: GroupHours; canEnter: boolean; canViewReports: boolean; groupSlug: string }>();

const page = usePage<SharedData>();
const timeZone = page.props.timezone;

// The month name is a calendar month, not an instant: `month` is a first-of-month ISO date,
// so format it in UTC to name the month it is, never sliding a day into the one before.
const formatMonth = (iso: string) =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso));

// `updated_at` is a real instant — format it on the museum's wall clock, like every other
// timestamp in the app, never the reader's browser zone.
const formatDate = (iso: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'medium', timeZone }).format(new Date(iso));

// One additive form per offered month, keyed by its bucket. Each posts its own month so
// logging Saturday now and Sunday tomorrow is two independent entries, never one edit of a
// running total. `hours` is a text field so an untouched one submits a blank — which the
// server treats as a no-op that leaves no trace.
const forms = new Map(
    props.hours.months.map((month) => [
        month.year_month,
        useForm<{ year_month: string; hours: string }>({ year_month: month.year_month, hours: '' }),
    ]),
);

const submit = (yearMonth: string) => {
    const form = forms.get(yearMonth);
    if (!form) return;
    form.post(route('hours.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => form.reset('hours'),
    });
};
</script>

<template>
    <div class="flex max-w-3xl flex-col gap-6">
        <!-- Officer link to the Group's fiscal-year report — shown only to a Chair or
             Statistician (of this Group or an ancestor) the server says may read it. -->
        <Link
            v-if="canViewReports"
            :href="route('groups.hours.report', { group: groupSlug })"
            class="text-rom-ink hover:bg-muted border-border inline-flex w-fit items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium"
        >
            <PhChartBar class="h-4 w-4" />
            {{ trans('hours.report.view') }}
        </Link>

        <!-- Entry form — shown only to a Member the server says may enter (a departed
             Category gets no form at all). -->
        <Card v-if="canEnter">
            <CardHeader>
                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('hours.entry.heading') }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <!-- The one thing a Member must not double-count. -->
                <p class="text-muted-foreground text-sm">{{ trans('hours.entry.counted_note') }}</p>
                <p class="text-muted-foreground text-sm">{{ trans('hours.entry.help') }}</p>

                <form
                    v-for="month in hours.months"
                    :key="month.year_month"
                    class="border-border flex flex-wrap items-end gap-3 border-t pt-4 first:border-t-0 first:pt-0"
                    @submit.prevent="submit(month.year_month)"
                >
                    <div class="flex min-w-40 flex-col gap-0.5">
                        <span class="text-rom-ink font-medium">{{ formatMonth(month.month) }}</span>
                        <span class="text-muted-foreground text-xs">{{ trans('hours.entry.on_file', { hours: String(month.extra_hours) }) }}</span>
                        <span class="text-muted-foreground text-xs">
                            {{
                                month.updated_at
                                    ? trans('hours.entry.last_updated', { date: formatDate(month.updated_at) })
                                    : trans('hours.entry.never_updated')
                            }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label :for="`hours-${month.year_month}`" class="text-xs">{{ trans('hours.entry.hours_label') }}</Label>
                        <Input :id="`hours-${month.year_month}`" v-model="forms.get(month.year_month)!.hours" type="number" step="1" class="w-28" />
                        <InputError :message="forms.get(month.year_month)!.errors.hours" />
                    </div>
                    <Button type="submit" :disabled="forms.get(month.year_month)!.processing">{{ trans('hours.entry.add') }}</Button>
                </form>
            </CardContent>
        </Card>

        <!-- The Member's own records — so the tab is not a write-only box. -->
        <Card>
            <CardHeader>
                <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('hours.records.heading') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <table v-if="hours.records.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-border border-b text-left text-xs tracking-wide uppercase">
                            <th class="py-2 pr-4 font-semibold">{{ trans('hours.records.column.month') }}</th>
                            <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.records.column.scheduled') }}</th>
                            <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.records.column.extra') }}</th>
                            <th class="py-2 pr-4 text-right font-semibold">{{ trans('hours.records.column.total') }}</th>
                            <th class="py-2 font-semibold">{{ trans('hours.records.column.updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="record in hours.records" :key="record.year_month" class="border-border/60 border-b last:border-b-0">
                            <td class="text-rom-ink py-2 pr-4 font-medium">{{ formatMonth(record.month) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ record.scheduled_hours }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ record.extra_hours }}</td>
                            <td class="text-rom-ink py-2 pr-4 text-right font-semibold tabular-nums">{{ record.total_hours }}</td>
                            <td class="text-muted-foreground py-2">{{ record.updated_at ? formatDate(record.updated_at) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                    <PhClock class="h-4 w-4" />
                    {{ trans('hours.records.empty') }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
