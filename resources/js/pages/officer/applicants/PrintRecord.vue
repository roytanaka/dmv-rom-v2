<script setup lang="ts">
// Print record (#929, ADR-0038 §8, §14): the whole Applicant record as a print-styled page, which
// the browser prints with no PDF package. The page runs without the app chrome; its toolbar carries
// `print:hidden`. The server sends each section's rows already labelled in the reader's language.
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { PhArrowLeft, PhPrinter } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    id: number;
    name: string;
    sections: { title: string; rows: { label: string; value: string }[] }[];
    notes: string | null;
}>();

const print = () => window.print();
</script>

<template>
    <Head :title="name" />

    <div class="bg-background min-h-screen">
        <div class="flex flex-wrap gap-2 border-b p-4 print:hidden">
            <Button variant="outline" as-child>
                <Link :href="route('officer.members.applicants.show', { applicant: id })">
                    <PhArrowLeft aria-hidden="true" />
                    {{ trans('applicants.outputs.back') }}
                </Link>
            </Button>
            <Button type="button" @click="print">
                <PhPrinter aria-hidden="true" />
                {{ trans('applicants.outputs.print') }}
            </Button>
        </div>

        <article class="text-rom-ink mx-auto flex max-w-3xl flex-col gap-5 p-6 text-sm print:max-w-none print:p-0">
            <header>
                <p class="text-muted-foreground">{{ trans('applicants.title') }}</p>
                <h1 class="text-2xl font-semibold">{{ name }}</h1>
            </header>

            <section v-for="section in sections" :key="section.title" class="break-inside-avoid">
                <h2 class="mb-1 border-b pb-1 text-base font-semibold">{{ section.title }}</h2>
                <dl class="grid grid-cols-[minmax(10rem,auto)_1fr] gap-x-4 gap-y-0.5">
                    <template v-for="row in section.rows" :key="row.label">
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="whitespace-pre-wrap">{{ row.value }}</dd>
                    </template>
                </dl>
            </section>

            <section>
                <h2 class="mb-1 border-b pb-1 text-base font-semibold">{{ trans('records.section.notes') }}</h2>
                <p v-if="notes" class="whitespace-pre-wrap">{{ notes }}</p>
                <p v-else class="text-muted-foreground">{{ trans('records.no_notes') }}</p>
            </section>
        </article>
    </div>
</template>
