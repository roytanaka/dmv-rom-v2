<script setup lang="ts">
// My invitations (ADR-0034 §13), the tab the Invitations page opens on: the Invitations
// this Member can reply to, each with their own Reply.
import InvitationTabs from '@/components/InvitationTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface GuestInvitation {
    id: number;
    name: string;
    date: string;
    start_time: string;
    end_time: string;
    host: string;
    reply: 'no_reply' | 'accepted' | 'declined';
}

defineProps<{ can: { host: boolean }; invitations: GuestInvitation[] }>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: trans('invitations.title'), href: '#' }]);

// `date` is a calendar day, so it formats in UTC to stay on the day the Host wrote.
const locale = computed(() => usePage<SharedData>().props.locale);
const formatDate = (date: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(date));
</script>

<template>
    <Head :title="trans('invitations.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ trans('invitations.title') }}</h1>
            <InvitationTabs :can-host="can.host" />

            <p v-if="!invitations.length" class="text-muted-foreground text-sm">{{ trans('invitations.mine.empty') }}</p>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('invitations.hosting.name') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('invitations.hosting.date') }}</TableHead>
                        <TableHead class="hidden lg:table-cell">{{ trans('invitations.hosting.host') }}</TableHead>
                        <TableHead>{{ trans('invitations.show.your_reply') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="invitation in invitations" :key="invitation.id">
                        <TableCell class="whitespace-normal">
                            <Link :href="route('invitations.show', { invitation: invitation.id })" class="underline">{{ invitation.name }}</Link>
                            <p class="text-muted-foreground md:hidden">{{ formatDate(invitation.date) }}</p>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            {{ formatDate(invitation.date) }} · {{ invitation.start_time }}–{{ invitation.end_time }}
                        </TableCell>
                        <TableCell class="hidden lg:table-cell">{{ invitation.host }}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ trans(`invitations.reply.${invitation.reply}`) }}</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
