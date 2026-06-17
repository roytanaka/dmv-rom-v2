<script setup lang="ts">
// Org-wide news feed (#155, ADR-0017 §5). One feed, read by every logged-in member
// regardless of their Groups; writing is gated by a capability-scoped news-editor
// role on the posting Group. All authority decisions are made server-side and
// arrive as `can` hints — this page renders controls from those hints and never
// computes authority itself. The richer feed UI is a later slice; this is the
// functional seam over the CRUD + NewsPolicy.
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

interface NewsItem {
    id: number;
    title: string;
    body: string;
    group: string;
    posted_at: string;
    // Per-item UI hints from the NewsPolicy. Drive whether the edit/delete controls
    // render; the server enforces every mutation regardless.
    can: { update: boolean; delete: boolean };
}

interface PostableGroup {
    id: number;
    name: string;
}

const props = defineProps<{
    news: NewsItem[];
    postableGroups: PostableGroup[];
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: trans('news.title'), href: '#' }];

const locale = computed(() => usePage<SharedData>().props.locale);
const formatDate = (iso: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'long' }).format(new Date(iso));

// New post — visible only when the server says this member may post somewhere.
const showCreate = ref(false);
const createForm = useForm({
    posting_group_id: props.postableGroups[0]?.id ?? null,
    title: '',
    body: '',
});
const submitCreate = () =>
    createForm.post(route('news.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('title', 'body');
            showCreate.value = false;
        },
    });

// Inline edit — one item at a time, revealed by the per-item edit control.
const editingId = ref<number | null>(null);
const editForm = useForm({ title: '', body: '' });
const startEdit = (item: NewsItem) => {
    editingId.value = item.id;
    editForm.title = item.title;
    editForm.body = item.body;
    editForm.clearErrors();
};
const submitEdit = () =>
    editForm.patch(route('news.update', { news: editingId.value as number }), {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });

const destroy = (item: NewsItem) => {
    if (window.confirm(trans('news.confirm_delete'))) {
        router.delete(route('news.destroy', { news: item.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="trans('news.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-rom-ink text-lg font-semibold">{{ trans('news.title') }}</h1>
                <Button v-if="can.create" type="button" @click="showCreate = !showCreate">{{ trans('news.new_post') }}</Button>
            </div>

            <!-- New post form -->
            <form v-if="can.create && showCreate" class="border-border flex flex-col gap-4 rounded-lg border p-4" @submit.prevent="submitCreate">
                <div class="grid gap-2">
                    <Label for="create-group">{{ trans('news.form.group') }}</Label>
                    <select
                        id="create-group"
                        v-model="createForm.posting_group_id"
                        class="border-input bg-background h-9 rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option v-for="group in postableGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="create-title">{{ trans('news.form.title') }}</Label>
                    <Input id="create-title" v-model="createForm.title" required />
                </div>
                <div class="grid gap-2">
                    <Label for="create-body">{{ trans('news.form.body') }}</Label>
                    <textarea
                        id="create-body"
                        v-model="createForm.body"
                        required
                        rows="4"
                        class="border-input bg-background rounded-md border px-3 py-2 text-sm shadow-xs"
                    ></textarea>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="createForm.processing">{{ trans('news.publish') }}</Button>
                    <Button type="button" variant="ghost" @click="showCreate = false">{{ trans('news.cancel') }}</Button>
                </div>
            </form>

            <!-- Feed -->
            <p v-if="!news.length" class="text-muted-foreground text-sm">{{ trans('news.empty') }}</p>

            <ul v-else class="flex flex-col gap-4">
                <li v-for="item in news" :key="item.id" class="border-border rounded-lg border p-4">
                    <template v-if="editingId === item.id">
                        <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
                            <div class="grid gap-2">
                                <Label :for="`edit-title-${item.id}`">{{ trans('news.form.title') }}</Label>
                                <Input :id="`edit-title-${item.id}`" v-model="editForm.title" required />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`edit-body-${item.id}`">{{ trans('news.form.body') }}</Label>
                                <textarea
                                    :id="`edit-body-${item.id}`"
                                    v-model="editForm.body"
                                    required
                                    rows="4"
                                    class="border-input bg-background rounded-md border px-3 py-2 text-sm shadow-xs"
                                ></textarea>
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="editForm.processing">{{ trans('news.save') }}</Button>
                                <Button type="button" variant="ghost" @click="editingId = null">{{ trans('news.cancel') }}</Button>
                            </div>
                        </form>
                    </template>
                    <template v-else>
                        <div class="flex items-start justify-between gap-4">
                            <h2 class="text-rom-ink font-semibold">{{ item.title }}</h2>
                            <div v-if="item.can.update || item.can.delete" class="flex shrink-0 gap-2">
                                <Button v-if="item.can.update" type="button" variant="ghost" size="sm" @click="startEdit(item)">
                                    {{ trans('news.edit') }}
                                </Button>
                                <Button v-if="item.can.delete" type="button" variant="ghost" size="sm" @click="destroy(item)">
                                    {{ trans('news.delete') }}
                                </Button>
                            </div>
                        </div>
                        <p class="text-rom-ink mt-2 text-sm whitespace-pre-line">{{ item.body }}</p>
                        <p class="text-muted-foreground mt-3 text-xs">
                            {{ trans('news.posted_by') }} {{ item.group }} · {{ formatDate(item.posted_at) }}
                        </p>
                    </template>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
