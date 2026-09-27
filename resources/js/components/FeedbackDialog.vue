<script setup lang="ts">
// The Send feedback dialog (#676, ADR-0029), opened from the top-bar Help menu outside
// production. The Tester types a name, picks a type, and writes a message. The client
// context (page URL, user agent, viewport) rides along on submit; the server fills the
// rest. Label, control, and error only — no helper text.
//
// The name is remembered in the browser (§5, `@/feedback/testerName`).
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { buildClientContext } from '@/feedback/clientContext';
import { rememberedName, rememberName } from '@/feedback/testerName';
import { useForm } from '@inertiajs/vue3';
import { PhCheckCircle } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

const props = defineProps<{
    // The Feedback page: the dialog posts here and links here.
    href: string;
}>();

const open = defineModel<boolean>('open', { required: true });

// FeedbackType values (ADR-0029 §6), in picker order.
const TYPES = ['bug', 'feature-request', 'translation', 'missing-from-new-site', 'confusing', 'other'] as const;

const form = useForm({
    tester_name: '',
    type: '',
    message: '',
});

form.transform((data) => ({ ...data, ...buildClientContext(window) }));

const sent = ref(false);

// Each opening starts a fresh form with the remembered name. The fields are cleared by
// hand: after a successful send, useForm's defaults are the sent values, so reset()
// would bring the last message back.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    sent.value = false;
    form.clearErrors();
    form.tester_name = rememberedName();
    form.type = '';
    form.message = '';
});

function submit(): void {
    form.post(props.href, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            rememberName(form.tester_name);
            sent.value = true;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('feedback.dialog.title') }}</DialogTitle>
            </DialogHeader>

            <div v-if="sent" class="flex flex-col gap-4" role="status">
                <p class="flex items-center gap-2 text-base">
                    <PhCheckCircle class="text-success size-5 shrink-0" />
                    {{ trans('feedback.dialog.sent') }}
                </p>
                <DialogFooter class="items-center gap-4 sm:justify-between">
                    <TextLink :href="href" @click="open = false">{{ trans('feedback.dialog.see_all') }}</TextLink>
                    <Button type="button" size="sm" @click="open = false">{{ trans('feedback.dialog.close') }}</Button>
                </DialogFooter>
            </div>

            <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="feedback-name">{{ trans('feedback.dialog.name') }}</Label>
                    <Input
                        id="feedback-name"
                        v-model="form.tester_name"
                        required
                        maxlength="100"
                        autocomplete="name"
                        :aria-invalid="form.errors.tester_name ? true : undefined"
                    />
                    <InputError :message="form.errors.tester_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="feedback-type">{{ trans('feedback.dialog.type') }}</Label>
                    <Select v-model="form.type" required>
                        <SelectTrigger id="feedback-type" :aria-invalid="form.errors.type ? true : undefined">
                            <SelectValue :placeholder="trans('feedback.dialog.type_placeholder')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in TYPES" :key="type" :value="type">{{ trans(`feedback.type.${type}`) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="grid gap-2">
                    <Label for="feedback-message">{{ trans('feedback.dialog.message') }}</Label>
                    <Textarea
                        id="feedback-message"
                        v-model="form.message"
                        required
                        maxlength="5000"
                        :rows="5"
                        :aria-invalid="form.errors.message ? true : undefined"
                    />
                    <InputError :message="form.errors.message" />
                </div>

                <DialogFooter class="items-center gap-4 sm:justify-between">
                    <TextLink :href="href" @click="open = false">{{ trans('feedback.dialog.see_all') }}</TextLink>
                    <div class="flex gap-2">
                        <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                            {{ trans('feedback.dialog.cancel') }}
                        </Button>
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('feedback.dialog.send') }}</Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
