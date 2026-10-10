<script setup lang="ts">
// The qualification screens (#789, ADR-0033 §3, §4) — a Vetting officer, the Chair or super-tier
// records who may give each Tour. One component, two views: by Tour (the Members who give one
// Tour) and by Member (the Tours one Member gives). Each can add (today prefilled; adding an
// inactive row reactivates it), change the Last vet date, and remove. Inactive qualifications
// show apart. The Last vet date is shown only; nothing expires. Tour names are content, shown
// as authored (ADR-0004).
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { SharedData } from '@/types';
import { formatDateOnly } from '@/lib/dates';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

export type QualificationRow = {
    id: number;
    name: string;
    lastVetDate: string | null;
    // By Tour: the holder's Membership. By Member: the Tour held.
    membershipId?: number;
    tourId?: number;
    tourActive?: boolean;
};

export type QualificationScreen =
    | {
          view: 'tour';
          today: string;
          tour: { id: number; name: string; active: boolean };
          active: QualificationRow[];
          inactive: QualificationRow[];
          candidates: { membershipId: number; name: string }[];
      }
    | {
          view: 'member';
          today: string;
          member: { membershipId: number; memberId: number; name: string; standing: string; current: boolean };
          active: QualificationRow[];
          inactive: QualificationRow[];
          candidates: { tourId: number; name: string }[];
      };

const props = defineProps<{
    screen: QualificationScreen;
    groupSlug: string;
}>();

const page = usePage<SharedData>();
const formatDate = (iso: string | null) => (iso === null ? trans('group.qualifications.no_date') : formatDateOnly(iso, page.props.locale));

const heading = computed(() =>
    props.screen.view === 'tour'
        ? trans('group.qualifications.tour_heading', { tour: props.screen.tour.name })
        : trans('group.qualifications.member_heading', { name: props.screen.member.name }),
);

// Each row's name links across to the other screen: a Member to their Tours, a Tour to its Members.
const rowHref = (row: QualificationRow) =>
    props.screen.view === 'tour'
        ? route('groups.tours.member', { group: props.groupSlug, membership: row.membershipId })
        : route('groups.tours.show', { group: props.groupSlug, tour: row.tourId });

const canAdd = computed(() => props.screen.view === 'tour' || props.screen.member.current);

// Add: pick a Member (by Tour) or a Tour (by Member), with today's date filled in.
const adding = ref(false);
const addForm = useForm<{ pick: number | null; last_vet_date: string }>({ pick: null, last_vet_date: '' });

const openAdd = () => {
    addForm.clearErrors();
    addForm.pick = null;
    addForm.last_vet_date = props.screen.today;
    adding.value = true;
};

const saveAdd = () => {
    const screen = props.screen;
    addForm
        .transform((data) =>
            screen.view === 'tour'
                ? { group_member_id: data.pick, tour_id: screen.tour.id, last_vet_date: data.last_vet_date }
                : { group_member_id: screen.member.membershipId, tour_id: data.pick, last_vet_date: data.last_vet_date },
        )
        .post(route('groups.qualifications.store', { group: props.groupSlug }), {
            preserveScroll: true,
            onSuccess: () => (adding.value = false),
        });
};

// The server names the picked field, which the transform renamed from `pick`.
const pickError = computed(() => {
    const errors = addForm.errors as Record<string, string | undefined>;
    return props.screen.view === 'tour' ? errors.group_member_id : errors.tour_id;
});

// Change the Last vet date.
const editing = ref<QualificationRow | null>(null);
const dateForm = useForm<{ last_vet_date: string }>({ last_vet_date: '' });

const openEdit = (row: QualificationRow) => {
    dateForm.clearErrors();
    dateForm.last_vet_date = row.lastVetDate ?? props.screen.today;
    editing.value = row;
};

const saveEdit = () => {
    if (!editing.value) {
        return;
    }
    dateForm.patch(route('qualifications.update', { qualification: editing.value.id }), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
};

// Remove, behind a confirm.
const removing = ref<QualificationRow | null>(null);

const confirmRemove = () => {
    if (!removing.value) {
        return;
    }
    router.delete(route('qualifications.destroy', { qualification: removing.value.id }), {
        preserveScroll: true,
        onFinish: () => (removing.value = null),
    });
};

const sections = computed(() => [
    { key: 'active', title: trans('group.qualifications.active_heading'), rows: props.screen.active, muted: false },
    { key: 'inactive', title: trans('group.qualifications.inactive_heading'), rows: props.screen.inactive, muted: true },
]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <TextLink :href="route('groups.show', { group: groupSlug, section: 'tours' })" class="text-sm">
                {{ trans('group.qualifications.back') }}
            </TextLink>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-rom-ink text-xl font-semibold">{{ heading }}</h2>
                <Button v-if="canAdd" type="button" size="sm" @click="openAdd">{{ trans('group.qualifications.add') }}</Button>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <Badge v-if="screen.view === 'tour' && !screen.tour.active" variant="secondary">{{
                    trans('group.qualifications.retired_tour')
                }}</Badge>
                <Badge v-if="screen.view === 'member' && !screen.member.current" variant="secondary">{{
                    trans('group.qualifications.not_current')
                }}</Badge>
            </div>
        </div>

        <template v-for="block in sections" :key="block.key">
            <Card v-if="block.key === 'active' || block.rows.length">
                <CardHeader>
                    <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ block.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="block.rows.length === 0" class="text-muted-foreground text-sm">
                        {{ screen.view === 'tour' ? trans('group.qualifications.none_active') : trans('group.qualifications.none_active_member') }}
                    </p>
                    <Table v-else>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{
                                    screen.view === 'tour' ? trans('group.qualifications.column_member') : trans('group.qualifications.column_tour')
                                }}</TableHead>
                                <TableHead>{{ trans('group.qualifications.column_last_vet_date') }}</TableHead>
                                <TableHead class="text-right"
                                    ><span class="sr-only">{{ trans('group.qualifications.change_date') }}</span></TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in block.rows" :key="row.id" :class="{ 'text-muted-foreground': block.muted }">
                                <TableCell class="whitespace-normal">
                                    <TextLink :href="rowHref(row)" class="font-medium">{{ row.name }}</TextLink>
                                    <Badge v-if="row.tourActive === false" variant="secondary" class="ml-2">{{
                                        trans('group.qualifications.retired_tour')
                                    }}</Badge>
                                </TableCell>
                                <TableCell>{{ formatDate(row.lastVetDate) }}</TableCell>
                                <TableCell class="text-right whitespace-nowrap">
                                    <Button type="button" size="sm" variant="ghost" @click="openEdit(row)">{{
                                        trans('group.qualifications.change_date')
                                    }}</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="removing = row">{{
                                        trans('group.qualifications.remove')
                                    }}</Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </template>
    </div>

    <Dialog v-model:open="adding">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    screen.view === 'tour'
                        ? trans('group.qualifications.add_member_title', { tour: screen.tour.name })
                        : trans('group.qualifications.add_tour_title', { name: screen.member.name })
                }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="saveAdd">
                <div class="grid gap-2">
                    <Label for="qualification-pick">{{
                        screen.view === 'tour' ? trans('group.qualifications.member_label') : trans('group.qualifications.tour_label')
                    }}</Label>
                    <NativeSelect id="qualification-pick" v-model="addForm.pick" required>
                        <option :value="null" disabled></option>
                        <template v-if="screen.view === 'tour'">
                            <option v-for="candidate in screen.candidates" :key="candidate.membershipId" :value="candidate.membershipId">
                                {{ candidate.name }}
                            </option>
                        </template>
                        <template v-else>
                            <option v-for="candidate in screen.candidates" :key="candidate.tourId" :value="candidate.tourId">
                                {{ candidate.name }}
                            </option>
                        </template>
                    </NativeSelect>
                    <InputError :message="pickError" />
                </div>
                <div class="grid gap-2">
                    <Label for="qualification-date">{{ trans('group.qualifications.last_vet_date_label') }}</Label>
                    <Input id="qualification-date" v-model="addForm.last_vet_date" type="date" required />
                    <InputError :message="addForm.errors.last_vet_date" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="adding = false">{{ trans('group.qualifications.cancel') }}</Button>
                    <Button type="submit" :disabled="addForm.processing">{{ trans('group.qualifications.add') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="editing !== null" @update:open="(open: boolean) => !open && (editing = null)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('group.qualifications.change_date_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="saveEdit">
                <div class="grid gap-2">
                    <Label for="qualification-edit-date">{{ trans('group.qualifications.last_vet_date_label') }}</Label>
                    <Input id="qualification-edit-date" v-model="dateForm.last_vet_date" type="date" required />
                    <InputError :message="dateForm.errors.last_vet_date" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="editing = null">{{ trans('group.qualifications.cancel') }}</Button>
                    <Button type="submit" :disabled="dateForm.processing">{{ trans('group.qualifications.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="removing !== null" @update:open="(open: boolean) => !open && (removing = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('group.qualifications.remove_title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('group.qualifications.remove_body') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ trans('group.qualifications.cancel') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/80" @click.prevent="confirmRemove">
                    {{ trans('group.qualifications.remove') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
