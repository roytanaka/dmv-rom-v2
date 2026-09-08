<script setup lang="ts">
// Member directory (#169, #170, PRD #167). The living roster as a dense table —
// avatar, name (surname order), Groups, and a standing badge per row, each row
// linking to the Member's profile. A thin view over
// MemberResource::directoryCollection: the payload carries no contact PII, so this
// page never reasons about contact at all.
//
// #170 adds client-side findability over the already-loaded set (no server round
// trip): a live name search, a single-select Group filter (composing as an
// intersection), and an explicit no-matches row so an empty result reads as a
// filter state, not a broken page.
//
// #171 adds two scanning aids over the same loaded set: a surname/given-name sort
// toggle (display order follows the active sort) and an A–Z jump rail that leaps to
// the first row under a letter. Both follow the active sort — the rail keys on the
// surname when sorting by last name, the given name when sorting by first name.
import AlphaJumpRail from '@/components/AlphaJumpRail.vue';
import StandingBadge from '@/components/StandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuRadioGroup, DropdownMenuRadioItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { PhCaretDown, PhMagnifyingGlass } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface DirectoryGroup {
    name: string;
    slug: string;
    roles: string[];
}

interface DirectoryMember {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    standing: string;
    groups: DirectoryGroup[];
}

const props = defineProps<{ members: DirectoryMember[] }>();

// `computed` so the label survives a full-page locale switch — the messages load
// async, so a `trans()` snapshot taken at setup would capture the raw key.
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: trans('directory.title'), href: route('directory') }]);

// Live name search and the active Group filter ('' = all Groups). Both narrow the
// loaded set; nothing here touches the server.
const search = ref('');
const groupFilter = ref('');

// Active sort: by surname ('last') or given name ('first'). Display order, the row
// label, and the jump rail all key off this.
const sortBy = ref<'last' | 'first'>('last');

// The Group filter's options: the distinct Groups present across the loaded roster,
// keyed by slug and labelled with the DB-authored name (rendered as-authored —
// Group names are content, not chrome; ADR-0004). Sorted by name for a scannable list.
const groupOptions = computed(() => {
    const bySlug = new Map<string, string>();
    for (const member of props.members) {
        for (const group of member.groups) {
            if (!bySlug.has(group.slug)) {
                bySlug.set(group.slug, group.name);
            }
        }
    }
    return [...bySlug.entries()].map(([slug, name]) => ({ slug, name })).sort((a, b) => a.name.localeCompare(b.name));
});

const activeGroupLabel = computed(
    () => groupOptions.value.find((group) => group.slug === groupFilter.value)?.name ?? trans('directory.filter.group.all'),
);

// Search and filter compose: a member shows only if it matches both. Name search is
// a case-insensitive substring of the full name (covering first or last name).
const filteredMembers = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return props.members.filter((member) => {
        const matchesName = needle === '' || `${member.first_name} ${member.last_name}`.toLowerCase().includes(needle);
        const matchesGroup = groupFilter.value === '' || member.groups.some((group) => group.slug === groupFilter.value);
        return matchesName && matchesGroup;
    });
});

const hasActiveFilters = computed(() => search.value.trim() !== '' || groupFilter.value !== '');

const clearFilters = () => {
    search.value = '';
    groupFilter.value = '';
};

// The name the active sort keys on — surname or given name. The jump rail and the
// sort comparator both read it so they can never disagree.
const sortName = (member: DirectoryMember) => (sortBy.value === 'last' ? member.last_name : member.first_name);

// The A–Z bucket for a name: its first letter, accent-folded (so "Étienne" jumps
// under E) and uppercased. Non-letter or empty names fall outside A–Z and simply
// get no rail entry.
const jumpLetter = (member: DirectoryMember) =>
    sortName(member)
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .charAt(0)
        .toUpperCase();

// Display order follows the active sort: surnames break ties on given name and vice
// versa, both folded so accents sort with their base letter (fr-CA-friendly).
const sortedMembers = computed(() => {
    const secondary = (m: DirectoryMember) => (sortBy.value === 'last' ? m.first_name : m.last_name);
    return [...filteredMembers.value].sort((a, b) => sortName(a).localeCompare(sortName(b)) || secondary(a).localeCompare(secondary(b)));
});

// Letters with at least one row, for the rail to light up.
const availableLetters = computed(() => [...new Set(sortedMembers.value.map(jumpLetter))]);

// The first row id under each letter, so a rail tap scrolls straight to the bucket.
const firstIdByLetter = computed(() => {
    const map = new Map<string, number>();
    for (const member of sortedMembers.value) {
        const letter = jumpLetter(member);
        if (!map.has(letter)) {
            map.set(letter, member.id);
        }
    }
    return map;
});

// Anchor id for a row, present only on the first row of each letter (the jump target).
const anchorId = (member: DirectoryMember) => {
    const letter = jumpLetter(member);
    return firstIdByLetter.value.get(letter) === member.id ? `directory-letter-${letter}` : undefined;
};

const jumpTo = (letter: string) => {
    document.getElementById(`directory-letter-${letter}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const initials = (member: DirectoryMember) => `${member.first_name.charAt(0)}${member.last_name.charAt(0)}`.toUpperCase();

// Display order follows the sort: "Last, First" under surname sort, "First Last"
// under given-name sort, so the leading token is always what the list is ordered by.
const displayName = (member: DirectoryMember) =>
    sortBy.value === 'last' ? `${member.last_name}, ${member.first_name}` : `${member.first_name} ${member.last_name}`;

const groupNames = (member: DirectoryMember) =>
    member.groups.length ? member.groups.map((group) => group.name).join(', ') : trans('directory.no_groups');
</script>

<template>
    <Head :title="trans('directory.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold">{{ trans('directory.title') }}</h1>
                <p class="text-muted-foreground text-sm">{{ transChoice('directory.count', filteredMembers.length) }}</p>
            </header>

            <!-- Above-table toolbar: name search + single-select Group filter (a
                 per-screen composition, not part of the Table primitive). -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative sm:max-w-xs sm:flex-1">
                    <PhMagnifyingGlass class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="trans('directory.search.placeholder')"
                        :aria-label="trans('directory.search.label')"
                        class="pl-9"
                    />
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger
                        :aria-label="trans('directory.filter.group.label')"
                        class="border-input bg-background focus-visible:border-rom-slate focus-visible:ring-rom-slate-50 flex h-11 items-center justify-between gap-2 rounded-none border px-3 text-base outline-hidden focus-visible:ring-2 sm:w-56"
                    >
                        <span class="truncate">{{ activeGroupLabel }}</span>
                        <PhCaretDown class="size-4 shrink-0 opacity-60" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="max-h-80 w-56 overflow-y-auto">
                        <DropdownMenuRadioGroup v-model="groupFilter">
                            <DropdownMenuRadioItem value="">{{ trans('directory.filter.group.all') }}</DropdownMenuRadioItem>
                            <DropdownMenuRadioItem v-for="group in groupOptions" :key="group.slug" :value="group.slug">
                                {{ group.name }}
                            </DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Surname / given-name sort toggle: a two-segment control over the
                     installed Button primitive (no toggle-group primitive is installed).
                     Display order and the jump rail both follow the active sort. -->
                <div role="group" :aria-label="trans('directory.sort.label')" class="flex items-center gap-2 sm:ml-auto">
                    <span class="text-muted-foreground text-sm">{{ trans('directory.sort.label') }}</span>
                    <div class="flex">
                        <Button
                            :variant="sortBy === 'last' ? 'default' : 'outline'"
                            size="sm"
                            :aria-pressed="sortBy === 'last'"
                            @click="sortBy = 'last'"
                        >
                            {{ trans('directory.sort.last_name') }}
                        </Button>
                        <Button
                            :variant="sortBy === 'first' ? 'default' : 'outline'"
                            size="sm"
                            class="-ml-px"
                            :aria-pressed="sortBy === 'first'"
                            @click="sortBy = 'first'"
                        >
                            {{ trans('directory.sort.first_name') }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12"
                                    ><span class="sr-only">{{ trans('directory.column.name') }}</span></TableHead
                                >
                                <TableHead>{{ trans('directory.column.name') }}</TableHead>
                                <TableHead>{{ trans('directory.column.groups') }}</TableHead>
                                <TableHead>{{ trans('directory.column.standing') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="member in sortedMembers" :id="anchorId(member)" :key="member.id" class="scroll-mt-24">
                                <TableCell>
                                    <Avatar size="sm">
                                        <AvatarImage v-if="member.photo" :src="member.photo" :alt="`${member.first_name} ${member.last_name}`" />
                                        <AvatarFallback>{{ initials(member) }}</AvatarFallback>
                                    </Avatar>
                                </TableCell>
                                <TableCell class="font-medium">
                                    <TextLink :href="route('members.show', { member: member.id })">{{ displayName(member) }}</TextLink>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ groupNames(member) }}</TableCell>
                                <TableCell><StandingBadge :standing="member.standing" /></TableCell>
                            </TableRow>

                            <!-- No-matches row: an empty result reads as a filter state, with a
                                 one-click way back to the full roster. -->
                            <TableRow v-if="!sortedMembers.length">
                                <TableCell colspan="4" class="py-10 text-center">
                                    <div class="text-muted-foreground flex flex-col items-center gap-1">
                                        <span>{{ trans('directory.no_matches.message') }}</span>
                                        <Button v-if="hasActiveFilters" variant="link" class="h-auto p-0" @click="clearFilters">
                                            {{ trans('directory.no_matches.clear') }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- A–Z jump rail: a large-screen scanning aid that sticks beside the
                     table. It keys on whichever name the active sort orders by. -->
                <AlphaJumpRail :available="availableLetters" class="sticky top-24 hidden self-start sm:flex" @jump="jumpTo" />
            </div>
        </div>
    </AppLayout>
</template>
