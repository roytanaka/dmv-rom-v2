<script setup lang="ts">
// The Hosting tab (ADR-0034 §13): a Host's own Invitations, past ones included, or every
// Invitation for the super-tier. Controls come from the server's `can` hints; the server
// enforces every action.
import InvitationTabs from '@/components/InvitationTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface HostedInvitation {
    id: number;
    name: string;
    date: string;
    start_time: string;
    end_time: string;
    state: 'draft' | 'open';
    host: string;
    can: { update: boolean; delete: boolean };
}

defineProps<{ invitations: HostedInvitation[] }>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: trans('invitations.title'), href: '#' }]);

// `date` is a calendar day, so it formats in UTC to stay on the day the Host wrote.
const locale = computed(() => usePage<SharedData>().props.locale);
const formatDate = (date: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(date));

const destroy = (invitation: HostedInvitation) => {
    if (window.confirm(trans('invitations.confirm_delete'))) {
        router.delete(route('invitations.destroy', { invitation: invitation.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="trans('invitations.tabs.hosting')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-rom-ink text-lg font-semibold">{{ trans('invitations.title') }}</h1>
                <Button as-child>
                    <Link :href="route('invitations.create')">{{ trans('invitations.hosting.new') }}</Link>
                </Button>
            </div>
            <InvitationTabs can-host />

            <p v-if="!invitations.length" class="text-muted-foreground text-sm">{{ trans('invitations.hosting.empty') }}</p>

            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('invitations.hosting.name') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{ trans('invitations.hosting.date') }}</TableHead>
                        <TableHead class="hidden lg:table-cell">{{ trans('invitations.hosting.host') }}</TableHead>
                        <TableHead>{{ trans('invitations.hosting.status') }}</TableHead>
                        <TableHead class="text-right">{{ trans('invitations.hosting.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="invitation in invitations" :key="invitation.id">
                        <TableCell class="whitespace-normal">
                            {{ invitation.name }}
                            <p class="text-muted-foreground md:hidden">{{ formatDate(invitation.date) }}</p>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            {{ formatDate(invitation.date) }} · {{ invitation.start_time }}–{{ invitation.end_time }}
                        </TableCell>
                        <TableCell class="hidden lg:table-cell">{{ invitation.host }}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ trans(`invitations.state.${invitation.state}`) }}</Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button v-if="invitation.can.update" as-child variant="ghost" size="sm">
                                    <Link :href="route('invitations.edit', { invitation: invitation.id })">{{ trans('invitations.edit') }}</Link>
                                </Button>
                                <Button v-if="invitation.can.delete" type="button" variant="ghost" size="sm" @click="destroy(invitation)">
                                    {{ trans('invitations.delete') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
