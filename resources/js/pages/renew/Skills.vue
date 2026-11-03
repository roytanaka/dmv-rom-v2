<script setup lang="ts">
// Step 3 of the Renewal (#941, ADR-0038 §9): the Member's skills, in the Settings skills picker,
// saved through the same validation; then on to the payment page. Skipped for a Sustaining choice.
import InputError from '@/components/InputError.vue';
import SkillsSelector from '@/components/SkillsSelector.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface CatalogCategory {
    id: number;
    name: string;
    skills: { id: number; name: string }[];
}

const props = defineProps<{
    year: number;
    catalog: CatalogCategory[];
    selected: number[];
}>();

const title = computed(() => trans('renewal.title'));
const stepTitle = computed(() => trans('renewal.step.skills'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: title.value, href: route('renew') },
    { title: stepTitle.value, href: route('renew.skills') },
]);
const period = computed(() => trans('renewal.period', { year: String(props.year), next: String(props.year + 1) }));

const form = useForm<{ skills: number[] }>({ skills: [...props.selected] });

const submit = () => form.post(route('renew.skills.store'));
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ stepTitle }}</h1>
                <p class="text-muted-foreground text-base">{{ period }}</p>
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <p v-if="catalog.length === 0" class="text-muted-foreground text-sm">{{ trans('settings.skills.empty') }}</p>
                <SkillsSelector v-else v-model="form.skills" :catalog="catalog" />
                <InputError :message="form.errors.skills" />

                <Button type="submit" class="self-end" :loading="form.processing">{{ trans('renewal.next') }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
