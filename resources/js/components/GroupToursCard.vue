<script setup lang="ts">
// The Settings tab's Tours card (#788, ADR-0033 §1, §4) — a Vetting officer or Chair keeps the
// Group's Tour list: add, rename, retire, restore, reorder, set open to all, map each Tour onto
// shift kinds, and delete one added in error. Each action hits its own endpoint and the server
// re-checks the TourPolicy; the page reloads with the fresh list, so no local list state is kept.
// Tour names are content, shown as authored (ADR-0004).
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
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import { PhArrowDown, PhArrowUp } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

export type SettingsTour = { id: number; name: string; active: boolean; openToAll: boolean; sortOrder: number; shiftKindIds: number[] };

const props = defineProps<{
    tours: SettingsTour[];
    shiftKinds: { id: number; name: string; active: boolean }[];
    groupSlug: string;
}>();

// Add a Tour: name only. It lands at the end of the order, active, not open to all.
const addForm = useForm<{ name: string }>({ name: '' });
const addTour = () =>
    addForm.post(route('groups.tours.store', { group: props.groupSlug }), {
        preserveScroll: true,
        onSuccess: () => addForm.reset('name'),
    });

// The name being edited inline, keyed by Tour id, with one error slot for a rejected rename or delete.
const nameDrafts = ref<Record<number, string>>({});
const rowError = ref<string | undefined>(undefined);

const startRename = (tour: SettingsTour) => {
    rowError.value = undefined;
    nameDrafts.value = { ...nameDrafts.value, [tour.id]: tour.name };
};

const cancelRename = (id: number) => {
    const rest = { ...nameDrafts.value };
    delete rest[id];
    nameDrafts.value = rest;
};

const saveRename = (id: number) =>
    router.patch(
        route('tours.update', { tour: id }),
        { name: nameDrafts.value[id] },
        {
            preserveScroll: true,
            onSuccess: () => cancelRename(id),
            onError: (errors) => (rowError.value = errors.name),
        },
    );

const patchTour = (id: number, data: { active?: boolean; open_to_all?: boolean }) =>
    router.patch(route('tours.update', { tour: id }), data, { preserveScroll: true });

// Reorder by swapping a Tour with its neighbour, then sending the whole id list in the new order.
const moveTour = (index: number, delta: number) => {
    const ids = props.tours.map((tour) => tour.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) {
        return;
    }
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.patch(route('groups.tours.reorder', { group: props.groupSlug }), { ids }, { preserveScroll: true });
};

// The kind-mapping dialog: one checkbox per shift kind, saved as the full set.
const mapping = ref<SettingsTour | null>(null);
const mapForm = useForm<{ shift_kinds: number[] }>({ shift_kinds: [] });

const openMapping = (tour: SettingsTour) => {
    mapForm.clearErrors();
    mapForm.shift_kinds = [...tour.shiftKindIds];
    mapping.value = tour;
};

const toggleKind = (id: number, on: boolean) => {
    mapForm.shift_kinds = on ? [...mapForm.shift_kinds, id] : mapForm.shift_kinds.filter((kindId) => kindId !== id);
};

const saveMapping = () => {
    if (!mapping.value) {
        return;
    }
    mapForm.patch(route('tours.shift-kinds.update', { tour: mapping.value.id }), {
        preserveScroll: true,
        onSuccess: () => (mapping.value = null),
    });
};

// Delete, behind a confirm. The server refuses while a qualification or Sign-up points at the Tour.
const deleting = ref<SettingsTour | null>(null);

const confirmDelete = () => {
    if (!deleting.value) {
        return;
    }
    rowError.value = undefined;
    router.delete(route('tours.destroy', { tour: deleting.value.id }), {
        preserveScroll: true,
        onError: (errors) => (rowError.value = errors.tour),
        onFinish: () => (deleting.value = null),
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ trans('group.tours.heading') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <p class="text-muted-foreground text-sm">{{ trans('group.tours.description') }}</p>

            <p v-if="tours.length === 0" class="text-muted-foreground text-sm">{{ trans('group.tours.empty') }}</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="(tour, index) in tours" :key="tour.id" class="flex flex-wrap items-center gap-2">
                    <div class="flex flex-col">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            :disabled="index === 0"
                            :aria-label="trans('group.tours.move_up')"
                            @click="moveTour(index, -1)"
                        >
                            <PhArrowUp class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            :disabled="index === tours.length - 1"
                            :aria-label="trans('group.tours.move_down')"
                            @click="moveTour(index, 1)"
                        >
                            <PhArrowDown class="size-4" />
                        </Button>
                    </div>

                    <template v-if="nameDrafts[tour.id] !== undefined">
                        <Input v-model="nameDrafts[tour.id]" class="h-8 w-56" @keyup.enter="saveRename(tour.id)" />
                        <Button type="button" size="sm" @click="saveRename(tour.id)">{{ trans('group.scheduling_panel.save') }}</Button>
                        <Button type="button" size="sm" variant="ghost" @click="cancelRename(tour.id)">
                            {{ trans('group.scheduling_panel.cancel') }}
                        </Button>
                    </template>
                    <template v-else>
                        <!-- Each name opens its by-Tour qualification screen (#789). -->
                        <TextLink
                            :href="route('groups.tours.show', { group: groupSlug, tour: tour.id })"
                            class="text-sm"
                            :class="{ 'text-muted-foreground line-through': !tour.active }"
                            >{{ tour.name }}</TextLink
                        >
                        <Badge v-if="!tour.active" variant="secondary">{{ trans('group.tours.retired_badge') }}</Badge>
                        <Button type="button" size="sm" variant="ghost" @click="startRename(tour)">{{ trans('group.tours.rename') }}</Button>
                        <Button type="button" size="sm" variant="ghost" @click="openMapping(tour)">
                            {{ trans('group.tours.shift_kinds') }} ({{ tour.shiftKindIds.length }})
                        </Button>
                        <Button v-if="tour.active" type="button" size="sm" variant="ghost" @click="patchTour(tour.id, { active: false })">
                            {{ trans('group.tours.retire') }}
                        </Button>
                        <Button v-else type="button" size="sm" variant="ghost" @click="patchTour(tour.id, { active: true })">
                            {{ trans('group.tours.restore') }}
                        </Button>
                        <Button type="button" size="sm" variant="ghost" @click="deleting = tour">{{ trans('group.tours.delete') }}</Button>
                        <label class="text-muted-foreground ml-auto flex items-center gap-1.5 text-sm">
                            <Checkbox :checked="tour.openToAll" @update:checked="(on: boolean) => patchTour(tour.id, { open_to_all: on })" />
                            {{ trans('group.tours.open_to_all') }}
                        </label>
                    </template>
                </li>
            </ul>
            <InputError :message="rowError" />

            <div class="flex flex-col gap-1.5">
                <Label for="new-tour">{{ trans('group.tours.add_label') }}</Label>
                <div class="flex flex-wrap items-start gap-2">
                    <Input id="new-tour" v-model="addForm.name" class="h-8 w-56" @keyup.enter="addTour" />
                    <Button type="button" size="sm" :disabled="addForm.processing" @click="addTour">{{ trans('group.tours.add') }}</Button>
                </div>
                <InputError :message="addForm.errors.name" />
            </div>
        </CardContent>
    </Card>

    <Dialog :open="mapping !== null" @update:open="(open: boolean) => !open && (mapping = null)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('group.tours.shift_kinds_title', { tour: mapping?.name ?? '' }) }}</DialogTitle>
            </DialogHeader>
            <p v-if="shiftKinds.length === 0" class="text-muted-foreground text-sm">{{ trans('group.tours.no_kinds') }}</p>
            <div v-else class="flex flex-col gap-2">
                <label v-for="kind in shiftKinds" :key="kind.id" class="flex items-center gap-2 text-sm pointer-coarse:min-h-11">
                    <Checkbox :checked="mapForm.shift_kinds.includes(kind.id)" @update:checked="(on: boolean) => toggleKind(kind.id, on)" />
                    <span :class="{ 'text-muted-foreground line-through': !kind.active }">{{ kind.name }}</span>
                </label>
            </div>
            <InputError :message="mapForm.errors.shift_kinds" />
            <DialogFooter>
                <Button type="button" variant="ghost" @click="mapping = null">{{ trans('group.scheduling_panel.cancel') }}</Button>
                <Button type="button" :disabled="mapForm.processing" @click="saveMapping">{{ trans('group.scheduling_panel.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="deleting !== null" @update:open="(open: boolean) => !open && (deleting = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ trans('group.tours.delete_title', { tour: deleting?.name ?? '' }) }}</AlertDialogTitle>
                <AlertDialogDescription>{{ trans('group.tours.delete_body') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ trans('group.scheduling_panel.cancel') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/80" @click.prevent="confirmDelete">
                    {{ trans('group.tours.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
