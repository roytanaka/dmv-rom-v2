<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
    // Per-resource UI hint from MemberPolicy (ADR-0017). Drives whether the save
    // control renders enabled; the server enforces the action regardless.
    can: { update: boolean };
}

defineProps<Props>();

const localizeHref = useLocalizedHref();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: trans('settings.profile.title'),
        href: localizeHref('/settings/profile'),
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
    // Contact record (#232) — all optional. Province defaults to Ontario and country
    // to Canada (the ROM's home province) so the common case needs no input.
    phone: user.phone ?? '',
    alternate_phone: user.alternate_phone ?? '',
    business_phone: user.business_phone ?? '',
    address_street: user.address_street ?? '',
    address_city: user.address_city ?? '',
    address_province: user.address_province ?? 'ON',
    address_postal_code: user.address_postal_code ?? '',
    address_country: user.address_country ?? 'Canada',
    current_password: '',
});

// PRD #228: mirror the server's email-change gate so the field only appears when needed.
const emailIsChanging = computed(() => form.email !== user.email);

const submit = () => {
    form.patch(route('settings.profile.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('current_password'),
        onError: () => form.reset('current_password'),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="trans('settings.profile.title')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :title="trans('settings.profile.heading')" :description="trans('settings.profile.description')" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="first_name">{{ trans('settings.profile.first_name') }}</Label>
                        <Input
                            id="first_name"
                            class="mt-1 block w-full"
                            v-model="form.first_name"
                            required
                            autocomplete="given-name"
                            :placeholder="trans('settings.profile.first_name')"
                        />
                        <InputError class="mt-2" :message="form.errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">{{ trans('settings.profile.last_name') }}</Label>
                        <Input
                            id="last_name"
                            class="mt-1 block w-full"
                            v-model="form.last_name"
                            required
                            autocomplete="family-name"
                            :placeholder="trans('settings.profile.last_name')"
                        />
                        <InputError class="mt-2" :message="form.errors.last_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ trans('settings.profile.email') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            :placeholder="trans('settings.profile.email')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2 border-t pt-6">
                        <HeadingSmall
                            :title="trans('settings.profile.contact_heading')"
                            :description="trans('settings.profile.contact_description')"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">{{ trans('settings.profile.phone') }}</Label>
                        <Input id="phone" class="mt-1 block w-full" v-model="form.phone" autocomplete="tel" />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="alternate_phone">{{ trans('settings.profile.alternate_phone') }}</Label>
                        <Input id="alternate_phone" class="mt-1 block w-full" v-model="form.alternate_phone" autocomplete="tel" />
                        <InputError class="mt-2" :message="form.errors.alternate_phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="business_phone">{{ trans('settings.profile.business_phone') }}</Label>
                        <Input id="business_phone" class="mt-1 block w-full" v-model="form.business_phone" autocomplete="tel" />
                        <InputError class="mt-2" :message="form.errors.business_phone" />
                    </div>

                    <div class="grid gap-2 border-t pt-6">
                        <HeadingSmall :title="trans('settings.profile.address_heading')" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address_street">{{ trans('settings.profile.address_street') }}</Label>
                        <Input id="address_street" class="mt-1 block w-full" v-model="form.address_street" autocomplete="street-address" />
                        <InputError class="mt-2" :message="form.errors.address_street" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address_city">{{ trans('settings.profile.address_city') }}</Label>
                        <Input id="address_city" class="mt-1 block w-full" v-model="form.address_city" autocomplete="address-level2" />
                        <InputError class="mt-2" :message="form.errors.address_city" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address_province">{{ trans('settings.profile.address_province') }}</Label>
                        <Input id="address_province" class="mt-1 block w-full" v-model="form.address_province" autocomplete="address-level1" />
                        <InputError class="mt-2" :message="form.errors.address_province" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address_postal_code">{{ trans('settings.profile.address_postal_code') }}</Label>
                        <Input id="address_postal_code" class="mt-1 block w-full" v-model="form.address_postal_code" autocomplete="postal-code" />
                        <InputError class="mt-2" :message="form.errors.address_postal_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address_country">{{ trans('settings.profile.address_country') }}</Label>
                        <Input id="address_country" class="mt-1 block w-full" v-model="form.address_country" autocomplete="country-name" />
                        <InputError class="mt-2" :message="form.errors.address_country" />
                    </div>

                    <div v-if="emailIsChanging" class="grid gap-2">
                        <Label for="current_password">{{ trans('settings.profile.current_password') }}</Label>
                        <Input
                            id="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.current_password"
                            autocomplete="current-password"
                            :placeholder="trans('settings.profile.current_password')"
                        />
                        <p class="text-sm text-neutral-600">{{ trans('settings.profile.current_password_hint') }}</p>
                        <InputError class="mt-2" :message="form.errors.current_password" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-neutral-800">
                            {{ trans('settings.profile.unverified') }}
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                            >
                                {{ trans('settings.profile.resend') }}
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            {{ trans('settings.profile.verification_sent') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :loading="form.processing" :disabled="!can.update">{{ trans('settings.profile.save') }}</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">{{ trans('settings.profile.saved') }}</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
