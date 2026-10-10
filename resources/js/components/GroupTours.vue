<script setup lang="ts">
// The Tours page (#792, ADR-0033 §5) — the old Who's Who tour lists. Members of the Group see each
// active Tour and the Members who give it, with their Last vet dates. Open-to-all Tours are marked:
// they need no qualification. For a Vetting officer, the Chair or super-tier (`canManage`), each Tour
// name links to its by-Tour screen (#789). Tour and Member names are content, shown as authored
// (ADR-0004). One card per Tour, a stacked list inside, so it reads at phone width.
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { SharedData } from '@/types';
import { formatDateOnly } from '@/lib/dates';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

export type ToursPageTour = {
    id: number;
    name: string;
    openToAll: boolean;
    members: { memberId: number; name: string; lastVetDate: string | null }[];
};

defineProps<{
    tours: ToursPageTour[];
    canManage: boolean;
    groupSlug: string;
}>();

const page = usePage<SharedData>();
const formatDate = (iso: string | null) => (iso === null ? trans('group.tours_page.no_date') : formatDateOnly(iso, page.props.locale));
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="tours.length === 0" class="text-muted-foreground py-12 text-center text-base">{{ trans('group.tours_page.empty') }}</p>

        <Card v-for="tour in tours" :key="tour.id">
            <CardHeader>
                <CardTitle class="flex flex-wrap items-center gap-2 text-base font-semibold">
                    <TextLink v-if="canManage" :href="route('groups.tours.show', { group: groupSlug, tour: tour.id })">{{ tour.name }}</TextLink>
                    <span v-else>{{ tour.name }}</span>
                    <Badge v-if="tour.openToAll" variant="secondary">{{ trans('group.tours_page.open_to_all') }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="tour.members.length === 0" class="text-muted-foreground text-sm">{{ trans('group.tours_page.none') }}</p>
                <ul v-else class="divide-border divide-y text-sm">
                    <li v-for="member in tour.members" :key="member.memberId" class="flex flex-wrap items-baseline justify-between gap-x-4 py-2">
                        <span class="font-medium">{{ member.name }}</span>
                        <span class="text-muted-foreground">
                            <span class="sr-only">{{ trans('group.tours_page.last_vet_date') }}: </span>{{ formatDate(member.lastVetDate) }}
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
