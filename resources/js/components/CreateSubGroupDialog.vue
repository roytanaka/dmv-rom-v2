<script setup lang="ts">
// Create a sub-group from DMV Settings (#884, ADR-0036 §11): a parent officer's dialog. Lean
// form: name, parent and an optional Chair (#885) picked from the chosen parent's active roster
// in the roster's own pick list; changing the parent clears the pick. The server makes the slug and sets every other field (a Working group
// with Meetings and Documents on). The parent select lists the officer's Groups and their
// active descendants, indented by depth. The super-tier uses CreateGroupDialog instead. On
// success the server redirects to the new sub-group's structure form.
import InputError from '@/components/InputError.vue';
import MemberPickList from '@/components/MemberPickList.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { treeIndent } from '@/lib/treeIndent';
import { type RosterCandidate } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';

export interface SubGroupParent {
    id: number;
    name: string;
    depth: number;
}

const props = defineProps<{ parents: SubGroupParent[]; chairs: Record<number, RosterCandidate[]> }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm<{ name: string; parent_id: number | null; chair_id: number | null }>({
    name: '',
    parent_id: null,
    chair_id: null,
});

// The pick list holds a list; the Chair is its one entry, if any.
const chairPick = computed({
    get: () => (form.chair_id === null ? [] : [form.chair_id]),
    set: (ids: number[]) => (form.chair_id = ids[0] ?? null),
});

const chairCandidates = computed(() => (form.parent_id === null ? [] : (props.chairs[form.parent_id] ?? [])));

watch(
    () => form.parent_id,
    () => (form.chair_id = null),
);

// One Group to run, one choice: preselect it.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    if (props.parents.length === 1) form.parent_id = props.parents[0].id;
});

function submit(): void {
    form.post(route('dmv-settings.groups.store'));
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('dmv_settings.create.sub_group_title') }}</DialogTitle>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="new-sub-group-name">{{ trans('dmv_settings.field.name') }}</Label>
                    <Input id="new-sub-group-name" v-model="form.name" required maxlength="255" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-sub-group-parent">{{ trans('dmv_settings.field.parent') }}</Label>
                    <NativeSelect id="new-sub-group-parent" v-model="form.parent_id" required>
                        <option :value="null" disabled>{{ trans('dmv_settings.choose_parent') }}</option>
                        <option v-for="option in parents" :key="option.id" :value="option.id">
                            {{ treeIndent(option.depth) + option.name }}
                        </option>
                    </NativeSelect>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div v-if="form.parent_id !== null" class="grid gap-2">
                    <Label for="new-sub-group-chair">{{ trans('dmv_settings.field.chair') }}</Label>
                    <MemberPickList v-model="chairPick" :candidates="chairCandidates" input-id="new-sub-group-chair" :multiple="false" />
                    <InputError :message="form.errors.chair_id" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="open = false">
                        {{ trans('dmv_settings.create.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">{{ trans('dmv_settings.create.submit') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
