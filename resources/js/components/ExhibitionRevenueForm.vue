<script setup lang="ts">
// The month's exhibition revenue (#800, ADR-0032 §12), entered by the Statistician (or Chair, or
// super-tier) from the Tour Summary and added to its grand total. An empty field clears the month.
// The server re-checks every write (UpdateExhibitionRevenueRequest). Chrome, so `print:hidden`:
// the figure prints in the table.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, useId } from 'vue';

const props = defineProps<{
    groupSlug: string;
    yearMonth: string;
    month: string;
    amount: string | null;
}>();

const page = usePage<SharedData>();
const fieldId = useId();

const monthName = computed(() =>
    new Intl.DateTimeFormat(page.props.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(props.month)),
);

const form = useForm<{ year_month: string; amount: string }>({ year_month: props.yearMonth, amount: props.amount ?? '' });
form.transform((data) => ({ ...data, amount: data.amount === '' ? null : data.amount }));

const save = () => {
    form.put(route('groups.exhibition-revenue.update', { group: props.groupSlug }), { preserveScroll: true });
};
</script>

<template>
    <form class="flex flex-wrap items-end gap-2 print:hidden" @submit.prevent="save">
        <div class="grid gap-2">
            <Label :for="fieldId">{{ trans('hours.tours.exhibition.field', { month: monthName }) }}</Label>
            <Input :id="fieldId" v-model="form.amount" class="w-40" type="number" inputmode="decimal" min="0" step="0.01" />
            <InputError :message="form.errors.amount" />
        </div>
        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('hours.tours.exhibition.save') }}</Button>
    </form>
</template>
