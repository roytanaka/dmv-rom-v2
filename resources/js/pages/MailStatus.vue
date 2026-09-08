<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PhWarning } from '@phosphor-icons/vue';
import { computed } from 'vue';

// The Mail status page (#492, ADR-0024 §10) — super-tier only. The controller judges the two
// warnings and hands the four values as plain ISO strings; this screen only formats and shows
// them. "Mail last sent" is shown, never judged.
interface ConnectionError {
    message: string;
    at: string | null;
}

interface Props {
    schedulerLastRan: string | null;
    mailLastSent: string | null;
    lastConnectionError: ConnectionError | null;
    pendingCount: number;
    warnings: {
        dead: boolean;
        cannotSend: boolean;
    };
}

const props = defineProps<Props>();

const page = usePage<SharedData>();

const breadcrumbs: BreadcrumbItem[] = [{ title: trans('mail_status.title'), href: '/mail-status' }];

// Pin the wall clock to the org timezone the server shares, as the meetings screen does: a
// heartbeat at 11:00 is 11:00 at the museum for whoever reads it, not the reader's local zone.
const formatInstant = (iso: string | null) =>
    iso === null
        ? trans('mail_status.never')
        : new Intl.DateTimeFormat(page.props.locale, {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: page.props.timezone,
          }).format(new Date(iso));

const errorAt = computed(() => (props.lastConnectionError?.at ? formatInstant(props.lastConnectionError.at) : null));
</script>

<template>
    <Head :title="trans('mail_status.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-2xl font-semibold">{{ trans('mail_status.title') }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">{{ trans('mail_status.subtitle') }}</p>
            </div>

            <!-- Warnings first, loudest. Dead: the cron heartbeat is stale or missing. Cannot
                 send: the last connection error stands newer than the last good send. -->
            <div v-if="warnings.dead || warnings.cannotSend" class="flex flex-col gap-3">
                <div
                    v-if="warnings.dead"
                    class="border-destructive/50 bg-destructive/10 text-destructive flex items-start gap-3 rounded-md border p-4"
                >
                    <PhWarning class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p class="font-medium">{{ trans('mail_status.dead.title') }}</p>
                        <p class="text-sm">{{ trans('mail_status.dead.body') }}</p>
                    </div>
                </div>
                <div
                    v-if="warnings.cannotSend"
                    class="border-destructive/50 bg-destructive/10 text-destructive flex items-start gap-3 rounded-md border p-4"
                >
                    <PhWarning class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p class="font-medium">{{ trans('mail_status.cannot_send.title') }}</p>
                        <p class="text-sm">{{ trans('mail_status.cannot_send.body') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ trans('mail_status.scheduler_last_ran') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-lg">{{ formatInstant(schedulerLastRan) }}</CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ trans('mail_status.mail_last_sent') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-lg">{{ formatInstant(mailLastSent) }}</CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ trans('mail_status.pending') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-lg">{{ pendingCount }}</CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{ trans('mail_status.last_error') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-lg">
                        <template v-if="lastConnectionError">
                            <p class="break-words">{{ lastConnectionError.message }}</p>
                            <p v-if="errorAt" class="text-muted-foreground mt-1 text-sm">{{ errorAt }}</p>
                        </template>
                        <template v-else>{{ trans('mail_status.no_error') }}</template>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
