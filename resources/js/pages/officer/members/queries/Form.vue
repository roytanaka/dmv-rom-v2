<script setup lang="ts">
// The Saved query form (#923, ADR-0038 §7): create and edit. A name; up to ten conditions, each a
// source, a field, an operator and a value, joined by AND or OR; the output columns in order; one
// sort field. The server sends the allowlist, labelled, so nothing off it can be picked. Saving
// opens the run.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { PhPlus, PhTrash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface Field {
    key: string;
    label: string;
    type: string;
    operators: string[];
    options: { value: string; label: string }[] | null;
}

interface Source {
    key: string;
    label: string;
    fields: Field[];
}

interface Clause {
    source: string;
    field: string;
    operator: string;
    value: string;
}

interface Option {
    key: string;
    label: string;
}

const props = defineProps<{
    sources: Source[];
    operators: Option[];
    columns: Option[];
    sortFields: Option[];
    maxClauses: number;
    query: {
        id: number;
        name: string;
        clauses: Clause[];
        conjunction: string;
        sort: string;
        output_columns: string[];
    } | null;
}>();

const title = computed(() => trans(props.query ? 'saved_queries.edit_title' : 'saved_queries.new'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('records.members'), href: route('officer.members') },
    { title: trans('saved_queries.title'), href: route('officer.members.queries') },
    { title: title.value, href: '' },
]);

const fieldOf = (source: string, field: string): Field | undefined =>
    props.sources.find((s) => s.key === source)?.fields.find((f) => f.key === field);

const blankClause = (): Clause => {
    const field = props.sources[0].fields[0];
    return { source: props.sources[0].key, field: field.key, operator: field.operators[0], value: '' };
};

const form = useForm({
    name: props.query?.name ?? '',
    conjunction: props.query?.conjunction ?? 'and',
    clauses: props.query ? props.query.clauses.map((clause) => ({ ...clause, value: clause.value ?? '' })) : [blankClause()],
    sort: props.query?.sort ?? 'last_name',
    output_columns: props.query?.output_columns ?? ['last_name', 'first_name'],
});

const changeSource = (clause: Clause) => {
    const field = props.sources.find((s) => s.key === clause.source)!.fields[0];
    clause.field = field.key;
    clause.operator = field.operators[0];
    clause.value = '';
};

const changeField = (clause: Clause) => {
    const field = fieldOf(clause.source, clause.field);
    if (field && !field.operators.includes(clause.operator)) {
        clause.operator = field.operators[0];
    }
    clause.value = '';
};

const operatorLabel = (key: string) => props.operators.find((operator) => operator.key === key)?.label ?? key;

const inputType = (field: Field | undefined) => {
    if (field?.type === 'number') {
        return 'number';
    }
    return field?.type === 'date' || field?.type === 'datetime' ? 'date' : 'text';
};

const addClause = () => form.clauses.push(blankClause());
const removeClause = (index: number) => form.clauses.splice(index, 1);

const columnLabel = (key: string) => props.columns.find((column) => column.key === key)?.label ?? key;
const unchosenColumns = computed(() => props.columns.filter((column) => !form.output_columns.includes(column.key)));
const columnToAdd = ref('');
const addColumn = () => {
    if (columnToAdd.value !== '') {
        form.output_columns.push(columnToAdd.value);
        columnToAdd.value = '';
    }
};
const removeColumn = (key: string) => {
    form.output_columns = form.output_columns.filter((column) => column !== key);
};

const clauseError = (index: number): string | undefined => {
    const errors = form.errors as Record<string, string>;
    return errors[`clauses.${index}`] ?? errors[`clauses.${index}.value`] ?? errors[`clauses.${index}.field`];
};

const submit = () => {
    if (props.query) {
        form.put(route('saved-queries.update', { savedQuery: props.query.id }));
    } else {
        form.post(route('saved-queries.store'));
    }
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex h-full max-w-5xl flex-1 flex-col gap-8 p-4 sm:p-6" @submit.prevent="submit">
            <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>

            <div class="grid max-w-md gap-2">
                <Label for="saved-query-name">{{ trans('saved_queries.name') }}</Label>
                <Input id="saved-query-name" v-model="form.name" required maxlength="255" />
                <InputError :message="form.errors.name" />
            </div>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 text-lg font-semibold">{{ trans('saved_queries.clauses') }}</legend>

                <div class="grid max-w-xs gap-2">
                    <Label for="saved-query-conjunction">{{ trans('saved_queries.match') }}</Label>
                    <NativeSelect id="saved-query-conjunction" v-model="form.conjunction">
                        <option value="and">{{ trans('saved_queries.conjunction.and') }}</option>
                        <option value="or">{{ trans('saved_queries.conjunction.or') }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.conjunction" />
                </div>

                <ol class="flex flex-col gap-4">
                    <li v-for="(clause, index) in form.clauses" :key="index" class="flex flex-col gap-2 border-b pb-4">
                        <div class="grid grid-cols-1 items-end gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                            <div class="grid gap-1.5">
                                <Label :for="`clause-${index}-source`">{{ trans('saved_queries.source_label') }}</Label>
                                <NativeSelect :id="`clause-${index}-source`" v-model="clause.source" @change="changeSource(clause)">
                                    <option v-for="source in sources" :key="source.key" :value="source.key">{{ source.label }}</option>
                                </NativeSelect>
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`clause-${index}-field`">{{ trans('saved_queries.field_label') }}</Label>
                                <NativeSelect :id="`clause-${index}-field`" v-model="clause.field" @change="changeField(clause)">
                                    <option
                                        v-for="field in sources.find((s) => s.key === clause.source)?.fields ?? []"
                                        :key="field.key"
                                        :value="field.key"
                                    >
                                        {{ field.label }}
                                    </option>
                                </NativeSelect>
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`clause-${index}-operator`">{{ trans('saved_queries.operator_label') }}</Label>
                                <NativeSelect :id="`clause-${index}-operator`" v-model="clause.operator">
                                    <option
                                        v-for="operator in fieldOf(clause.source, clause.field)?.operators ?? []"
                                        :key="operator"
                                        :value="operator"
                                    >
                                        {{ operatorLabel(operator) }}
                                    </option>
                                </NativeSelect>
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`clause-${index}-value`">{{ trans('saved_queries.value_label') }}</Label>
                                <NativeSelect
                                    v-if="fieldOf(clause.source, clause.field)?.options"
                                    :id="`clause-${index}-value`"
                                    v-model="clause.value"
                                >
                                    <option value="">{{ trans('saved_queries.choose') }}</option>
                                    <option
                                        v-for="option in fieldOf(clause.source, clause.field)?.options ?? []"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </NativeSelect>
                                <Input
                                    v-else
                                    :id="`clause-${index}-value`"
                                    v-model="clause.value"
                                    :type="inputType(fieldOf(clause.source, clause.field))"
                                    maxlength="255"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                :aria-label="trans('saved_queries.remove_clause', { number: String(index + 1) })"
                                @click="removeClause(index)"
                            >
                                <PhTrash aria-hidden="true" />
                            </Button>
                        </div>
                        <InputError :message="clauseError(index)" />
                    </li>
                </ol>
                <InputError :message="form.errors.clauses" />

                <Button v-if="form.clauses.length < maxClauses" type="button" variant="outline" class="w-fit" @click="addClause">
                    <PhPlus aria-hidden="true" />
                    {{ trans('saved_queries.add_clause') }}
                </Button>
            </fieldset>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 text-lg font-semibold">{{ trans('saved_queries.columns') }}</legend>

                <ol class="flex flex-wrap gap-2">
                    <li
                        v-for="column in form.output_columns"
                        :key="column"
                        class="bg-muted flex items-center gap-1 rounded-md py-1 pr-1 pl-3 text-sm"
                    >
                        {{ columnLabel(column) }}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :aria-label="trans('saved_queries.remove_column', { column: columnLabel(column) })"
                            @click="removeColumn(column)"
                        >
                            <PhTrash aria-hidden="true" />
                        </Button>
                    </li>
                </ol>

                <div class="grid max-w-md gap-2">
                    <Label for="saved-query-add-column">{{ trans('saved_queries.add_column') }}</Label>
                    <NativeSelect id="saved-query-add-column" v-model="columnToAdd" @change="addColumn">
                        <option value="">{{ trans('saved_queries.choose') }}</option>
                        <option v-for="column in unchosenColumns" :key="column.key" :value="column.key">{{ column.label }}</option>
                    </NativeSelect>
                    <InputError :message="form.errors.output_columns" />
                </div>
            </fieldset>

            <div class="grid max-w-md gap-2">
                <Label for="saved-query-sort">{{ trans('saved_queries.sort') }}</Label>
                <NativeSelect id="saved-query-sort" v-model="form.sort">
                    <option v-for="field in sortFields" :key="field.key" :value="field.key">{{ field.label }}</option>
                </NativeSelect>
                <InputError :message="form.errors.sort" />
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">{{ trans('saved_queries.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
