<script setup lang="ts">
// Member directory (#169, PRD #167). The living roster as a dense table — avatar,
// name (surname order), Groups, and a standing badge per row, each row linking to
// the Member's profile. A thin view over MemberResource::directoryCollection: the
// payload carries no contact PII, so this page never reasons about contact at all.
// Search / filter / sort are follow-up slices; this is the read-only base table.
import StandingBadge from '@/components/StandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';

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
                <p class="text-muted-foreground text-sm">{{ transChoice('directory.count', props.members.length) }}</p>
            </header>

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
                    <TableRow v-for="member in members" :key="member.id">
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
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
