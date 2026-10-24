<script setup lang="ts">
// The Tours page (#792, ADR-0033 §5) — the old Who's Who tour lists. Members of the Group see each
// active Tour and the Members who give it, with their Last vet dates. Open-to-all Tours are marked:
// they need no qualification, so an empty one says any Member may give it (#802). Tour and Member
// names are content, shown as authored (ADR-0004).
//
// About 90 Tours in Docents, so each row starts closed and opens to its Members (Feedback item 85),
// and one search box filters on Tour or Member name; a Member match opens its Tour and marks the
// name (./tours/toursSearch.ts). For a Vetting officer, the Chair or super-tier (`canManage`) an
// open row links to its by-Tour screen (#789), and a row with Members carries an Email button
// (Feedback item 84) that opens the composer as a hand-pick seeded with them (ADR-0024 §6). Both
// deviate from the legacy Who's Who, which listed everything open and emailed from a menu.
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import ComposerSheet from '@/emailing/ComposerSheet.vue';
import type { Recipient } from '@/emailing/composer';
import { formatDateOnly } from '@/lib/dates';
import { searchTours, splitMatch } from '@/tours/toursSearch';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhCaretDown, PhEnvelopeSimple, PhMagnifyingGlass } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

export type ToursPageTour = {
    id: number;
    name: string;
    openToAll: boolean;
    members: { memberId: number; name: string; lastVetDate: string | null }[];
};

const props = defineProps<{
    tours: ToursPageTour[];
    canManage: boolean;
    groupSlug: string;
    groupName: string;
    // The Group's hand-pick pool (#551), the composer's roster for a row's Email button.
    emailRoster: Recipient[];
}>();

const page = usePage<SharedData>();
const formatDate = (iso: string | null) => (iso === null ? trans('group.tours_page.no_date') : formatDateOnly(iso, page.props.locale));

const search = ref('');
const shown = computed(() => searchTours(props.tours, search.value));

// A row is open when the viewer toggled it, or when a Member name matched — then a toggle closes it.
// A new search starts from that default again.
const toggled = ref<Set<number>>(new Set());
watch(search, () => (toggled.value = new Set()));

function setOpen(id: number, memberMatch: boolean, open: boolean): void {
    const next = new Set(toggled.value);
    if (open !== memberMatch) {
        next.add(id);
    } else {
        next.delete(id);
    }
    toggled.value = next;
}

function countLabel(tour: ToursPageTour): string {
    if (tour.members.length > 0) {
        return transChoice('group.tours_page.member_count', tour.members.length, { count: String(tour.members.length) });
    }
    return trans(tour.openToAll ? 'group.tours_page.any_member' : 'group.tours_page.no_one');
}

const sheetOpen = ref(false);
const seed = ref<number[]>([]);

function email(tour: ToursPageTour): void {
    seed.value = tour.members.map((member) => member.memberId);
    sheetOpen.value = true;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="tours.length === 0" class="text-muted-foreground py-12 text-center text-base">{{ trans('group.tours_page.empty') }}</p>

        <template v-else>
            <div class="flex flex-col gap-2">
                <div class="relative sm:max-w-sm">
                    <PhMagnifyingGlass class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                    <Input
                        v-model="search"
                        type="search"
                        class="pl-9"
                        :placeholder="trans('group.tours_page.search_label')"
                        :aria-label="trans('group.tours_page.search_label')"
                    />
                </div>
                <p class="text-muted-foreground text-sm" aria-live="polite">
                    {{
                        shown.length === 0
                            ? trans('group.tours_page.no_match')
                            : transChoice('group.tours_page.shown', tours.length, { shown: String(shown.length), total: String(tours.length) })
                    }}
                </p>
            </div>

            <ul class="divide-border border-border divide-y rounded-lg border">
                <li v-for="{ tour, memberMatch } in shown" :key="tour.id">
                    <Collapsible
                        :open="memberMatch !== toggled.has(tour.id)"
                        class="group/tour"
                        @update:open="(open: boolean) => setOpen(tour.id, memberMatch, open)"
                    >
                        <div class="flex items-center gap-2 pr-2">
                            <CollapsibleTrigger
                                class="hover:bg-muted/50 focus-visible:ring-ring flex min-h-11 flex-1 items-start gap-2 px-3 py-2 text-left focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <PhCaretDown
                                    class="mt-1 size-4 shrink-0 -rotate-90 transition-transform group-data-[state=open]/tour:rotate-0"
                                    aria-hidden="true"
                                />
                                <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="text-base font-semibold">
                                        <template v-for="(part, index) in splitMatch(tour.name, search)" :key="index">
                                            <mark v-if="part.match" class="bg-warning-bg text-inherit">{{ part.text }}</mark>
                                            <template v-else>{{ part.text }}</template>
                                        </template>
                                    </span>
                                    <Badge v-if="tour.openToAll" variant="secondary">{{ trans('group.tours_page.open_to_all') }}</Badge>
                                    <span class="text-muted-foreground text-sm">{{ countLabel(tour) }}</span>
                                </span>
                            </CollapsibleTrigger>
                            <Button
                                v-if="canManage && tour.members.length > 0"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                :aria-label="trans('group.tours_page.email_label', { tour: tour.name })"
                                @click="email(tour)"
                            >
                                <PhEnvelopeSimple class="size-4" aria-hidden="true" />
                                <span class="max-sm:sr-only">{{ trans('group.tours_page.email') }}</span>
                            </Button>
                        </div>
                        <CollapsibleContent>
                            <div class="flex flex-col gap-2 px-3 pb-3 pl-9">
                                <p v-if="tour.members.length === 0" class="text-muted-foreground text-sm">
                                    {{ trans(tour.openToAll ? 'group.tours_page.open_to_all_none' : 'group.tours_page.none') }}
                                </p>
                                <ul v-else class="divide-border divide-y text-sm">
                                    <li
                                        v-for="member in tour.members"
                                        :key="member.memberId"
                                        class="flex flex-wrap items-baseline justify-between gap-x-4 py-2"
                                    >
                                        <span class="font-medium">
                                            <template v-for="(part, index) in splitMatch(member.name, search)" :key="index">
                                                <mark v-if="part.match" class="bg-warning-bg text-inherit">{{ part.text }}</mark>
                                                <template v-else>{{ part.text }}</template>
                                            </template>
                                        </span>
                                        <span class="text-muted-foreground">
                                            <span class="sr-only">{{ trans('group.tours_page.last_vet_date') }}: </span
                                            >{{ formatDate(member.lastVetDate) }}
                                        </span>
                                    </li>
                                </ul>
                                <TextLink v-if="canManage" class="text-sm" :href="route('groups.tours.show', { group: groupSlug, tour: tour.id })">
                                    {{ trans('group.tours_page.edit') }}
                                </TextLink>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </li>
            </ul>
        </template>

        <ComposerSheet
            v-if="canManage"
            v-model:open="sheetOpen"
            context="group"
            :context-subject="groupSlug"
            :group-name="groupName"
            :roster="emailRoster"
            :audience="null"
            :seed="seed"
        />
    </div>
</template>
