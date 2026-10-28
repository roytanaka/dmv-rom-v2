<script setup lang="ts">
// The "Email me updates" fields (#857, #860, ADR-0029 §15), shared by the Send feedback dialog
// and the comment form: a checkbox, and when ticked, the required Follower email. The forms
// fill the email from the browser's remembered one (`@/feedback/rememberedTester`) and remember it
// after a send with the box ticked. On Beta (`feedbackUsesAccountIdentity`, #861) the server
// follows with the account's email, so the form shows the checkbox alone.
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    // Prefixes the element ids, so two forms on one page do not clash.
    idPrefix: string;
    error?: string;
}>();

const follow = defineModel<boolean>('follow', { required: true });
const email = defineModel<string>('email', { required: true });

const page = usePage<SharedData>();
</script>

<template>
    <Label class="flex items-center gap-2 font-normal">
        <Checkbox :id="`${idPrefix}-follow`" v-model:checked="follow" />
        {{ trans('feedback.follow.checkbox') }}
    </Label>
    <div v-if="follow && !page.props.feedbackUsesAccountIdentity" class="grid gap-2">
        <Label :for="`${idPrefix}-follower-email`">{{ trans('feedback.follow.email') }}</Label>
        <Input
            :id="`${idPrefix}-follower-email`"
            v-model="email"
            type="email"
            required
            maxlength="254"
            autocomplete="email"
            :aria-invalid="error ? true : undefined"
        />
        <InputError :message="error" />
    </div>
</template>
