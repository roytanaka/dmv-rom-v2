<script setup lang="ts">
// The Applicant code dialog (#930, ADR-0038 §8), on the Applicants page for the Applicant Chair: the
// one shared code, whether the sign-in page offers the application, and the last day it is good for.
// Its own trigger button, so the page only places it.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { PhKey } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

export interface ApplicantCodeSettings {
    code: string | null;
    published: boolean;
    expires_on: string | null;
}

const props = defineProps<{ settings: ApplicantCodeSettings }>();

const open = ref(false);
const form = useForm({ code: '', published: false, expires_on: '' });

const show = () => {
    form.defaults({ code: props.settings.code ?? '', published: props.settings.published, expires_on: props.settings.expires_on ?? '' });
    form.reset();
    form.clearErrors();
    open.value = true;
};

const save = () => {
    form.transform((data) => ({ ...data, code: data.code.trim() === '' ? null : data.code.trim(), expires_on: data.expires_on || null })).put(
        route('applicant-code.update'),
        { preserveScroll: true, onSuccess: () => (open.value = false) },
    );
};
</script>

<template>
    <Button variant="outline" @click="show">
        <PhKey aria-hidden="true" />
        {{ trans('apply.code.button') }}
    </Button>

    <Dialog v-model:open="open">
        <DialogContent>
            <form class="flex flex-col gap-4" @submit.prevent="save">
                <DialogHeader>
                    <DialogTitle>{{ trans('apply.code.title') }}</DialogTitle>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="applicant-code">{{ trans('apply.code.code') }}</Label>
                    <Input id="applicant-code" v-model="form.code" maxlength="64" autocomplete="off" />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="applicant-code-expires">{{ trans('apply.code.expires_on') }}</Label>
                    <Input id="applicant-code-expires" v-model="form.expires_on" type="date" />
                    <InputError :message="form.errors.expires_on" />
                </div>
                <Label for="applicant-code-published" class="flex items-center gap-2">
                    <Checkbox
                        id="applicant-code-published"
                        :checked="form.published"
                        @update:checked="(checked: boolean) => (form.published = checked)"
                    />
                    {{ trans('apply.code.published') }}
                </Label>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ trans('apply.code.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">{{ trans('apply.code.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
