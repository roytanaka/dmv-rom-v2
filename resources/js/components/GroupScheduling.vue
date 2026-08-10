<script setup lang="ts">
// Group Scheduling tab (#353, PRD #352, ADR-0021 §1) — the Schedule read surface.
// The section is org-open (deliberately not Meetings' members-only gate) and renders
// whenever the Group runs scheduling.
//
// Two states, resolved server-side: `open` is a single Schedule shown directly (the
// one current published Schedule, or a Schedule reached by permalink), and `schedules`
// is the list shown otherwise — current & upcoming first, then past — with an honest
// empty state when there are none. Drafts appear only for the Group's schedule admins;
// the server has already filtered the list to the viewer's audience.
//
// A Schedule holds nothing yet (Shifts land in a later slice), so its detail is its
// name, date range, state, and as-authored description. Names and descriptions are
// as-authored content (ADR-0004); everything else is translated chrome.
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type Scheduling, type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhArrowLeft } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{ scheduling: Scheduling; groupSlug: string }>();

const page = usePage<SharedData>();

// Schedule ranges are dates, not instants — a month, not an o'clock — so they format
// on the plain calendar date the server sends (`YYYY-MM-DD`), read in the active
// locale. Parsed as local midnight to avoid a UTC-shift landing on the day before.
const formatDate = (date: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'long' }).format(new Date(`${date}T00:00:00`));

const dateRange = (starts: string, ends: string) => trans('group.scheduling_panel.date_range', { start: formatDate(starts), end: formatDate(ends) });

// The list arrives already ordered (current & upcoming first, then past). Splitting
// here only heads the two blocks; an empty block is dropped rather than left bare.
const sections = computed(() =>
    (['current', 'past'] as const)
        .map((key) => ({ key, schedules: props.scheduling.schedules.filter((s) => s.is_past === (key === 'past')) }))
        .filter((section) => section.schedules.length > 0),
);

// The link back to the list from an opened Schedule — the bare section URL, which
// re-runs the navigation branch. Localised by the section-tab machinery on the page.
const listHref = computed(() => `/groups/${props.groupSlug}/scheduling`);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- One Schedule, opened directly (the single current published one, or a
             permalink). It holds nothing yet, so this is its name, range, and text. -->
        <template v-if="scheduling.open">
            <TextLink :href="listHref" class="inline-flex items-center gap-1.5 text-sm">
                <PhArrowLeft class="size-4 shrink-0" />
                {{ trans('group.scheduling_panel.back_to_list') }}
            </TextLink>

            <Card>
                <CardHeader>
                    <CardTitle class="text-rom-ink flex flex-wrap items-center gap-2 text-lg">
                        {{ scheduling.open.name }}
                        <Badge v-if="scheduling.open.state === 'draft'" variant="secondary">
                            {{ trans('group.scheduling_panel.draft_badge') }}
                        </Badge>
                    </CardTitle>
                    <p class="text-muted-foreground text-sm">{{ dateRange(scheduling.open.starts_on, scheduling.open.ends_on) }}</p>
                </CardHeader>
                <CardContent v-if="scheduling.open.description">
                    <p class="text-rom-ink text-base whitespace-pre-line">{{ scheduling.open.description }}</p>
                </CardContent>
            </Card>
        </template>

        <!-- The list — current & upcoming first, then past, each block headed. -->
        <template v-else-if="scheduling.schedules.length">
            <section v-for="section in sections" :key="section.key" class="flex flex-col gap-3">
                <h3 class="text-muted-foreground text-sm font-medium tracking-wide uppercase">
                    {{ trans(`group.scheduling_panel.${section.key}_heading`) }}
                </h3>

                <Card v-for="schedule in section.schedules" :key="schedule.id">
                    <CardHeader>
                        <CardTitle class="flex flex-wrap items-center gap-2 text-lg">
                            <TextLink :href="schedule.url" class="text-rom-ink font-semibold">{{ schedule.name }}</TextLink>
                            <Badge v-if="schedule.state === 'draft'" variant="secondary">
                                {{ trans('group.scheduling_panel.draft_badge') }}
                            </Badge>
                        </CardTitle>
                        <p class="text-muted-foreground text-sm">{{ dateRange(schedule.starts_on, schedule.ends_on) }}</p>
                    </CardHeader>
                </Card>
            </section>
        </template>

        <!-- Honest empty state — the Group runs scheduling but has no Schedules yet. -->
        <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('group.scheduling_panel.empty') }}</p>
    </div>
</template>
