<script setup lang="ts">
// ROM split auth layout (#157): form left, photo right (hidden below lg).
import BrandLogo from '@/components/BrandLogo.vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
// AVIF-first with JPG fallback — every user hits this screen, so it cannot be AVIF-only.
import heroAvif from '../../../images/auth/login-hero.avif';
import heroJpg from '../../../images/auth/login-hero.jpg';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-2">
        <!-- Form column -->
        <div class="flex min-h-dvh flex-col">
            <header class="bg-rom-ink px-6 py-4 sm:px-10">
                <Link :href="route('home')" class="inline-flex items-center" aria-label="ROM DMV">
                    <BrandLogo variant="white" class="h-7 w-auto" />
                </Link>
            </header>

            <div class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-10">
                <div class="mx-auto w-full max-w-sm">
                    <div v-if="title || description" class="mb-8 space-y-2">
                        <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </div>

        <!-- Photo column — decorative; hidden below lg. Image is absolutely
             positioned so it fills the stretched grid row without shifting layout. -->
        <div class="relative hidden lg:block">
            <picture>
                <source :srcset="heroAvif" type="image/avif" />
                <img :src="heroJpg" alt="" class="absolute inset-0 h-full w-full object-cover" />
            </picture>
            <!-- Bottom scrim keeps the corner captions legible over any photo. -->
            <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/70 to-transparent" />
            <p class="absolute bottom-4 left-6 text-xs text-white/90">{{ trans('auth.login.photo_location') }}</p>
            <p class="absolute right-6 bottom-4 text-xs text-white/70">{{ trans('auth.login.photo_credit') }}</p>
        </div>
    </div>
</template>
