<script setup lang="ts">
// Email results on a Saved query run (#924, ADR-0038 §7): pick Members, add a note, and the server
// writes one Notice each with a link to the run, which opens only behind member administration.
// The recipients are the run page's optional `recipients` prop, loaded when the dialog first opens.
import InputError from '@/components/InputError.vue';
import MemberPickList from '@/components/MemberPickList.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type RosterCandidate } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { watch } from 'vue';

const props = defineProps<{ queryId: number; recipients: RosterCandidate[] | undefined }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ sent: [] }>();

const form = useForm<{ member_ids: number[]; note: string }>({ member_ids: [], note: '' });

watch(open, (isOpen) => {
    if (isOpen && props.recipients === undefined) {
        router.reload({ only: ['recipients'] });
    }
    if (!isOpen) {
        form.reset();
        form.clearErrors();
    }
});

const submit = () =>
    form.post(route('saved-queries.email-results', { savedQuery: props.queryId }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('sent');
        },
    });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('saved_queries.email_dialog.title') }}</DialogTitle>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="email-results-recipients">{{ trans('saved_queries.email_dialog.recipients') }}</Label>
                    <MemberPickList v-model="form.member_ids" :candidates="recipients ?? []" input-id="email-results-recipients" />
                    <InputError :message="form.errors.member_ids" />
                </div>
                <div class="grid gap-2">
                    <Label for="email-results-note">{{ trans('saved_queries.email_dialog.note') }}</Label>
                    <Textarea id="email-results-note" v-model="form.note" rows="3" />
                    <InputError :message="form.errors.note" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="open = false">
                        {{ trans('saved_queries.email_dialog.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">{{ trans('saved_queries.email_dialog.send') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
