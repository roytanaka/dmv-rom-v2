<script setup lang="ts">
// Step 4 of the Renewal (#941, ADR-0038 §9): the fee, the ROM's Akaraisin page with the amount,
// and the cheque instructions. Nothing comes back from the ROM's page; Records types the
// paid-through year when the money arrives.
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    year: number;
    amount: string | null;
    link: string | null;
}>();

const page = usePage<SharedData>();

const title = computed(() => trans('renewal.title'));
const stepTitle = computed(() => trans('renewal.step.payment'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: route('renew') },
    { title: stepTitle.value, href: route('renew.payment') },
]);
const period = computed(() => trans('renewal.period', { year: String(props.year), next: String(props.year + 1) }));
const money = computed(() =>
    props.amount === null ? '' : new Intl.NumberFormat(page.props.locale, { style: 'currency', currency: 'CAD' }).format(Number(props.amount)),
);
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-6 p-4 text-base sm:p-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ stepTitle }}</h1>
                <p class="text-muted-foreground">{{ period }}</p>
            </div>

            <p>{{ trans('renewal.payment.thanks') }}</p>

            <template v-if="amount !== null && link !== null">
                <dl class="grid grid-cols-[max-content_1fr] gap-x-4">
                    <dt class="text-muted-foreground">{{ trans('renewal.payment.fee') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ money }}</dd>
                </dl>

                <section class="flex flex-col gap-2">
                    <h2 class="text-rom-ink text-xl font-semibold">{{ trans('renewal.payment.card_heading') }}</h2>
                    <p>{{ trans('renewal.payment.card', { amount: money }) }}</p>
                    <p>
                        <a :href="link" target="_blank" rel="noopener nofollow" class="text-primary font-semibold underline underline-offset-4">
                            {{ trans('renewal.payment.link') }}
                        </a>
                    </p>
                </section>
            </template>
            <p v-else>{{ trans('renewal.payment.no_fee') }}</p>

            <section class="flex flex-col gap-2">
                <h2 class="text-rom-ink text-xl font-semibold">{{ trans('renewal.payment.cheque_heading') }}</h2>
                <p>{{ trans('renewal.payment.cheque') }}</p>
                <p class="font-semibold">{{ trans('renewal.payment.cash') }}</p>
            </section>
        </div>
    </AppLayout>
</template>
