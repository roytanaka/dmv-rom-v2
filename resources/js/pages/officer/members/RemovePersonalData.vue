<script setup lang="ts">
// Remove personal data (#921, ADR-0038 §6): the yearly privacy clean-up. The year is a query
// parameter; changing it reloads the preview counts from the server. The run needs the Member
// count typed back; the server counts again and opens the Past list when done.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    year: number;
    memberCount: number;
    applicantCount: number;
}>();

const title = computed(() => trans('records.remove_personal_data.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('records.members'), href: route('officer.members') },
    { title: title.value, href: route('officer.members.remove-personal-data') },
]);

const year = ref(String(props.year));
watch(
    () => props.year,
    (value) => (year.value = String(value)),
);

const preview = () => {
    router.get(route('officer.members.remove-personal-data'), { year: year.value }, { preserveScroll: true, replace: true });
};

const form = useForm({ year: props.year, confirm_count: '' });

const submit = () => {
    form.year = props.year;
    form.post(route('members.remove-personal-data'), { preserveScroll: true });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full max-w-xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>

            <form class="flex items-end gap-2" @submit.prevent="preview">
                <div class="flex flex-1 flex-col gap-1.5">
                    <Label for="removal-year">{{ trans('records.remove_personal_data.year') }}</Label>
                    <Input id="removal-year" v-model="year" type="number" min="1900" max="2100" />
                </div>
                <Button type="submit" variant="outline">{{ trans('records.remove_personal_data.preview') }}</Button>
            </form>

            <ul class="flex flex-col gap-1 text-base">
                <li>{{ trans('records.remove_personal_data.members', { count: String(memberCount) }) }}</li>
                <li>{{ trans('records.remove_personal_data.applicants', { count: String(applicantCount) }) }}</li>
            </ul>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <Label for="removal-confirm">{{ trans('records.remove_personal_data.confirm_count') }}</Label>
                    <Input id="removal-confirm" v-model="form.confirm_count" inputmode="numeric" autocomplete="off" />
                    <InputError :message="form.errors.confirm_count ?? form.errors.year" />
                </div>
                <Button type="submit" variant="destructive" class="w-fit" :disabled="form.processing || form.confirm_count === ''">
                    {{ trans('records.remove_personal_data.submit') }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
