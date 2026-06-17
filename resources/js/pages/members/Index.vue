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
// filter state, not a broken page. Sort lands in a follow-up slice.
import StandingBadge from '@/components/StandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: trans('directory.title'), href: route('directory') }];

// Live name search and the active Group filter ('' = all Groups). Both narrow the
// loaded set; nothing here touches the server.
const search = ref('');
const groupFilter = ref('');

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

const initials = (member: DirectoryMember) => `${member.first_name.charAt(0)}${member.last_name.charAt(0)}`.toUpperCase();

const surnameOrder = (member: DirectoryMember) => `${member.last_name}, ${member.first_name}`;

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
            </div>

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
                    <TableRow v-for="member in filteredMembers" :key="member.id">
                        <TableCell>
                            <Avatar size="sm">
                                <AvatarFallback>{{ initials(member) }}</AvatarFallback>
                            </Avatar>
                        </TableCell>
                        <TableCell class="font-medium">
                            <TextLink :href="route('members.show', { member: member.id })">{{ surnameOrder(member) }}</TextLink>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ groupNames(member) }}</TableCell>
                        <TableCell><StandingBadge :standing="member.standing" /></TableCell>
                    </TableRow>

                    <!-- No-matches row: an empty result reads as a filter state, with a
                         one-click way back to the full roster. -->
                    <TableRow v-if="!filteredMembers.length">
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
    </AppLayout>
</template>
