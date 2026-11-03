<script setup lang="ts">
// The confirmation a visitor sees once the application is stored (#930, ADR-0038 §8). The app sends
// the applicant nothing; the Membership Committee calls.
import TextLink from '@/components/TextLink.vue';
import AuthHeroLayout from '@/layouts/auth/AuthHeroLayout.vue';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
</script>

<template>
    <AuthHeroLayout :title="trans('apply.received_title')">
        <Head :title="trans('apply.received_title')" />

        <p class="text-sm">{{ trans('apply.received') }}</p>
        <p class="mt-6 text-sm">
            <TextLink :href="route('login')">{{ trans('apply.back_to_sign_in') }}</TextLink>
        </p>
    </AuthHeroLayout>
</template>
