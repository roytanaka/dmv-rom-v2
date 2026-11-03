<script setup lang="ts">
// Step 1 of the Renewal (#939, ADR-0038 §9): the code of conduct and one checkbox. The text comes
// from the server's translation file in the page's locale; the save records the year and moves on.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    year: number;
    text: {
        title: string;
        preamble_heading: string;
        preamble: string;
        policy_heading: string;
        policy: string;
        duties_intro: string;
        duties: string[];
        closing: string;
        accept: string;
    };
}>();

const title = computed(() => trans('renewal.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: title.value, href: route('renew') }]);
const period = computed(() => trans('renewal.period', { year: String(props.year), next: String(props.year + 1) }));

const form = useForm({ accept: false });

const submit = () => form.post(route('renew.code-of-conduct.store'));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>
                <p class="text-muted-foreground text-base">{{ period }}</p>
            </div>

            <article class="flex flex-col gap-4 text-base">
                <h2 class="text-rom-ink text-xl font-semibold">{{ text.title }}</h2>
                <h3 class="font-semibold">{{ text.preamble_heading }}</h3>
                <p>{{ text.preamble }}</p>
                <h3 class="font-semibold">{{ text.policy_heading }}</h3>
                <p>{{ text.policy }}</p>
                <p>{{ text.duties_intro }}</p>
                <ul class="flex list-disc flex-col gap-2 pl-6">
                    <li v-for="(duty, index) in text.duties" :key="index">{{ duty }}</li>
                </ul>
                <p>{{ text.closing }}</p>
            </article>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2">
                        <Checkbox id="accept" v-model:checked="form.accept" />
                        <Label for="accept">{{ text.accept }}</Label>
                    </div>
                    <InputError :message="form.errors.accept" />
                </div>
                <div>
                    <Button type="submit" :disabled="form.processing">{{ trans('renewal.next') }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
