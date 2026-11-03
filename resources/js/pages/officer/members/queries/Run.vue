<script setup lang="ts">
// A Saved query's run (#923, ADR-0038 §7): the count, the clause text, and a table of the chosen
// columns with a checkbox per row. The server sends every cell as text. Send email (#925) opens the
// composer on the run with the unchecked rows removed. CSV, Labels and Email results stay disabled
// until their ticket (#924) wires them to the checked rows.
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import RecordsSendEmail from '@/emailing/RecordsSendEmail.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { PhPencilSimple } from '@phosphor-icons/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

interface Row {
    id: number;
    cells: Record<string, string | null>;
}

const props = defineProps<{
    query: { id: number; name: string };
    clauseText: string;
    count: number;
    columns: { key: string; label: string }[];
    rows: Row[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('records.members'), href: route('officer.members') },
    { title: trans('saved_queries.title'), href: route('officer.members.queries') },
    { title: props.query.name, href: route('officer.members.queries.run', { savedQuery: props.query.id }) },
]);

// Every row starts checked, as the Legacy app's result did.
const checked = ref<Set<number>>(new Set(props.rows.map((row) => row.id)));
watch(
    () => props.rows,
    (rows) => (checked.value = new Set(rows.map((row) => row.id))),
);

const allChecked = computed<boolean | 'indeterminate'>(() => {
    if (checked.value.size === 0) {
        return false;
    }
    return checked.value.size === props.rows.length ? true : 'indeterminate';
});

// The unchecked rows, posted as removals when Send email opens the composer (#925).
const unchecked = computed(() => props.rows.filter((row) => !checked.value.has(row.id)).map((row) => row.id));

const toggleAll = (value: boolean | 'indeterminate') => {
    checked.value = new Set(value === true ? props.rows.map((row) => row.id) : []);
};

const toggle = (id: number, value: boolean | 'indeterminate') => {
    const next = new Set(checked.value);
    if (value === true) {
        next.add(id);
    } else {
        next.delete(id);
    }
    checked.value = next;
};

// The row's accessible name: its name cells when shown, else its position.
const rowName = (row: Row, index: number) => [row.cells.first_name, row.cells.last_name].filter(Boolean).join(' ') || String(index + 1);
</script>

<template>
    <Head :title="query.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h1 class="text-rom-ink text-2xl font-semibold">{{ query.name }}</h1>
                    <p class="text-muted-foreground text-base">{{ transChoice('saved_queries.count', count, { count: String(count) }) }}</p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="route('officer.members.queries.edit', { savedQuery: query.id })">
                        <PhPencilSimple aria-hidden="true" />
                        {{ trans('saved_queries.edit') }}
                    </Link>
                </Button>
            </header>

            <p class="bg-muted rounded-md px-3 py-2 font-mono text-sm break-words">{{ clauseText }}</p>

            <div v-if="rows.length > 0" class="flex flex-wrap gap-2">
                <RecordsSendEmail
                    context="saved_query"
                    :context-subject="String(query.id)"
                    :label="trans('saved_queries.send_email')"
                    :exclude="unchecked"
                    :disabled="checked.size === 0"
                />
                <Button variant="outline" disabled>{{ trans('saved_queries.csv') }}</Button>
                <Button variant="outline" disabled>{{ trans('saved_queries.labels') }}</Button>
                <Button variant="outline" disabled>{{ trans('saved_queries.email_results') }}</Button>
            </div>

            <div v-if="rows.length > 0" class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10">
                                <Checkbox :checked="allChecked" :aria-label="trans('saved_queries.select_all')" @update:checked="toggleAll" />
                            </TableHead>
                            <TableHead v-for="column in columns" :key="column.key">{{ column.label }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(row, index) in rows" :key="row.id">
                            <TableCell>
                                <Checkbox
                                    :checked="checked.has(row.id)"
                                    :aria-label="trans('saved_queries.select_row', { name: rowName(row, index) })"
                                    @update:checked="(value: boolean | 'indeterminate') => toggle(row.id, value)"
                                />
                            </TableCell>
                            <TableCell v-for="column in columns" :key="column.key" class="whitespace-pre-line">{{
                                row.cells[column.key] ?? ''
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('saved_queries.no_results') }}</p>
        </div>
    </AppLayout>
</template>
