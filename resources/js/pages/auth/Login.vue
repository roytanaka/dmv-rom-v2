<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthHeroLayout from '@/layouts/auth/AuthHeroLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { PhArrowRight, PhEye, PhEyeSlash } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// "Remember me" is intentionally dropped — it is not in the ROM design (#157).
const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);

const receptionPhone = '(416) 586-8097';
const officeEmail = 'volunteers@rom.on.ca';

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthHeroLayout :title="trans('auth.login.heading')" :description="trans('auth.login.welcome')">
        <Head :title="trans('auth.login.heading')" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ trans('auth.login.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="email"
                    v-model="form.email"
                    placeholder="email@example.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ trans('auth.login.password') }}</Label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                        {{ trans('auth.login.forgot') }}
                    </TextLink>
                </div>
                <div class="relative">
                    <Input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        class="pr-10"
                        :placeholder="trans('auth.login.password')"
                    />
                    <button
                        type="button"
                        tabindex="3"
                        class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex items-center px-3"
                        :aria-label="showPassword ? trans('auth.login.hide_password') : trans('auth.login.show_password')"
                        :aria-pressed="showPassword"
                        @click="showPassword = !showPassword"
                    >
                        <PhEyeSlash v-if="showPassword" class="h-5 w-5" />
                        <PhEye v-else class="h-5 w-5" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" class="w-full" tabindex="4" :loading="form.processing">
                <PhArrowRight class="h-4 w-4" />
                {{ trans('auth.login.submit') }}
            </Button>
        </form>

        <p class="text-muted-foreground mt-8 text-sm">
            {{ trans('auth.login.help_before', { phone: receptionPhone }) }}
            <!-- mailto is an external scheme, so a plain anchor — not the Inertia <Link> TextLink wraps. -->
            <a
                :href="`mailto:${officeEmail}`"
                class="text-rom-slate decoration-rom-slate/40 hover:text-rom-slate-700 underline underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                >{{ trans('auth.login.help_email') }}</a
            >.
        </p>
    </AuthHeroLayout>
</template>
