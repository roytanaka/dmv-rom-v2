<script setup lang="ts">
// Saved queries (#923, ADR-0038 §7): Officer Tools → Members → Queries. The office's queries by
// name, each with Run, Edit, Rename and Delete; New query opens the form.
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PhPlus } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface Query {
    id: number;
    name: string;
}

defineProps<{ queries: Query[] }>();

const title = computed(() => trans('saved_queries.title'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('records.members'), href: route('officer.members') },
    { title: title.value, href: route('officer.members.queries') },
]);

// Rename: one query at a time, in a dialog.
const renaming = ref<Query | null>(null);
const renameOpen = ref(false);
const renameForm = useForm({ name: '' });

const openRename = (query: Query) => {
    renaming.value = query;
    renameForm.clearErrors();
    renameForm.name = query.name;
    renameOpen.value = true;
};

const saveRename = () => {
    if (!renaming.value) {
        return;
    }
    renameForm.patch(route('saved-queries.rename', { savedQuery: renaming.value.id }), {
        preserveScroll: true,
        onSuccess: () => (renameOpen.value = false),
    });
};

// Delete: confirmed in an alert dialog.
const deleting = ref<Query | null>(null);
const deleteOpen = ref(false);

const openDelete = (query: Query) => {
    deleting.value = query;
    deleteOpen.value = true;
};

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }
    router.delete(route('saved-queries.destroy', { savedQuery: deleting.value.id }), {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-rom-ink text-2xl font-semibold">{{ title }}</h1>
                <Button as-child>
                    <Link :href="route('officer.members.queries.create')">
                        <PhPlus aria-hidden="true" />
                        {{ trans('saved_queries.new') }}
                    </Link>
                </Button>
            </header>

            <Table v-if="queries.length > 0">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ trans('saved_queries.name') }}</TableHead>
                        <TableHead class="text-right">{{ trans('saved_queries.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="query in queries" :key="query.id">
                        <TableCell class="whitespace-normal">
                            <Link
                                :href="route('officer.members.queries.run', { savedQuery: query.id })"
                                class="font-medium underline-offset-4 hover:underline"
                            >
                                {{ query.name }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" as-child>
                                    <Link :href="route('officer.members.queries.run', { savedQuery: query.id })">{{
                                        trans('saved_queries.run')
                                    }}</Link>
                                </Button>
                                <Button variant="outline" as-child>
                                    <Link :href="route('officer.members.queries.edit', { savedQuery: query.id })">{{
                                        trans('saved_queries.edit')
                                    }}</Link>
                                </Button>
                                <Button variant="outline" @click="openRename(query)">{{ trans('saved_queries.rename') }}</Button>
                                <Button variant="outline" @click="openDelete(query)">{{ trans('saved_queries.delete') }}</Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <p v-else class="text-muted-foreground py-12 text-center text-base">{{ trans('saved_queries.empty') }}</p>

            <Dialog v-model:open="renameOpen">
                <DialogContent v-if="renaming">
                    <form class="flex flex-col gap-4" @submit.prevent="saveRename">
                        <DialogHeader>
                            <DialogTitle>{{ trans('saved_queries.rename_title') }}</DialogTitle>
                        </DialogHeader>
                        <div class="grid gap-2">
                            <Label for="saved-query-rename">{{ trans('saved_queries.name') }}</Label>
                            <Input id="saved-query-rename" v-model="renameForm.name" required maxlength="255" />
                            <InputError :message="renameForm.errors.name" />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="renameOpen = false">{{ trans('saved_queries.cancel') }}</Button>
                            <Button type="submit" :disabled="renameForm.processing">{{ trans('saved_queries.save') }}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog v-model:open="deleteOpen">
                <AlertDialogContent v-if="deleting">
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ trans('saved_queries.delete_title', { name: deleting.name }) }}</AlertDialogTitle>
                        <AlertDialogDescription>{{ trans('saved_queries.delete_body') }}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ trans('saved_queries.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction @click="confirmDelete">{{ trans('saved_queries.delete') }}</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
