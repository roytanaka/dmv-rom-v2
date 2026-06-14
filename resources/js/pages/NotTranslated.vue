<script setup lang="ts">
// Locale-aware language-boundary page (#109, ADR-0008). Served (with a 404) when a
// /fr/ URL has no registered French route, instead of silently rendering the
// English page. Standalone — no app shell — because it can be reached before a
// real page exists. The polished 404 + request-translation CTA is a later slice.
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
</script>

<template>
    <Head :title="trans('placeholder.not_translated.title')" />

    <div class="bg-background text-foreground flex min-h-screen flex-col items-center justify-center gap-2 p-6 text-center">
        <h1 class="text-lg font-semibold">{{ trans('placeholder.not_translated.title') }}</h1>
        <p class="text-muted-foreground max-w-md text-sm">{{ trans('placeholder.not_translated.body') }}</p>
    </div>
</template>
