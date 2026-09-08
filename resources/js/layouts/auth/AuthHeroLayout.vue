<script setup lang="ts">
// ROM auth layout (#157): full-bleed ceiling photo with a floating login Card
// shifted left, so the architecture reads on the open right side. Photo captions
// sit in the viewport's bottom corners.
import BrandLogo from '@/components/BrandLogo.vue';
import { Card, CardContent } from '@/components/ui/card';
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
    <div class="relative flex min-h-dvh flex-col items-center overflow-hidden">
        <!-- Full-bleed background photo. Decorative; absolutely positioned so it
             fills the viewport behind the floating card and captions. -->
        <picture>
            <source :srcset="heroAvif" type="image/avif" />
            <img :src="heroJpg" alt="" class="absolute inset-0 h-full w-full object-cover" />
        </picture>
        <!-- Bottom scrim keeps the corner captions legible over any photo. -->
        <div class="absolute inset-0 bg-linear-to-t from-black/50 to-black/30" />

        <!-- Floating card — vertically centered, shifted left. shadow-2xl lifts it
             off the photo (the Card default shadow-xs is too quiet over imagery);
             square corners are the ROM default, kept. -->
        <div class="relative flex max-w-7xl flex-1 items-center px-4 py-12 sm:px-8 md:w-full">
            <Card class="w-full max-w-sm overflow-hidden shadow-2xl">
                <header class="bg-rom-ink px-8 py-5">
                    <Link :href="route('home')" class="inline-flex items-center" aria-label="ROM DMV">
                        <BrandLogo variant="white" class="h-7 w-auto" />
                    </Link>
                </header>

                <CardContent class="px-8 py-8">
                    <div v-if="title || description" class="mb-8 space-y-2">
                        <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
                    </div>
                    <slot />
                </CardContent>
            </Card>
        </div>

        <!-- Photo captions in the viewport's bottom corners. -->
        <div class="pointer-events-none relative flex w-full max-w-7xl items-end justify-between gap-4 px-6 pb-4 sm:px-8">
            <p class="text-xs text-white/90">{{ trans('auth.login.photo_location') }}</p>
            <p class="text-right text-xs text-white/70">{{ trans('auth.login.photo_credit') }}</p>
        </div>
    </div>
</template>
