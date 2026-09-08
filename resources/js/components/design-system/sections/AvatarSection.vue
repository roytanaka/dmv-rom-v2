<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { snippets } from '../snippets';

// Avatar image specimen. A self-contained SVG data URI (no network) so the
// image branch always renders in the gallery, visibly distinct from the
// initials fallback shown alongside it.
const avatarImage = `data:image/svg+xml,${encodeURIComponent(
    "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' fill='#516d80'/><circle cx='32' cy='24' r='12' fill='#ffffff'/><path d='M12 58a20 20 0 0 1 40 0z' fill='#ffffff'/></svg>",
)}`;
</script>

<template>
    <section id="avatar" aria-labelledby="avatar-heading" class="mb-12 scroll-mt-24">
        <h2 id="avatar-heading" class="text-xl font-semibold tracking-tight">Avatar</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The one deliberately-round element on the page — a true circle (<code>rounded-full</code>) against the square component identity. It shows
            an image when one loads and falls back to initials when it doesn’t, across the
            <code>sm</code> / <code>base</code> / <code>lg</code> sizes.
        </p>

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Sizes (image)</h3>
        <div class="mt-4 flex flex-wrap items-end gap-6">
            <div v-for="size in ['sm', 'base', 'lg'] as const" :key="size" class="flex flex-col items-center gap-2">
                <Avatar :size="size">
                    <AvatarImage :src="avatarImage" alt="Volunteer portrait" />
                    <AvatarFallback>AR</AvatarFallback>
                </Avatar>
                <code class="text-muted-foreground font-mono text-xs">{{ size }}</code>
            </div>
        </div>

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Initials fallback</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            With no image source the fallback renders the volunteer’s initials on the secondary surface.
        </p>
        <div class="mt-4 flex flex-wrap items-end gap-6">
            <div v-for="size in ['sm', 'base', 'lg'] as const" :key="size" class="flex flex-col items-center gap-2">
                <Avatar :size="size">
                    <AvatarFallback>AR</AvatarFallback>
                </Avatar>
                <code class="text-muted-foreground font-mono text-xs">{{ size }}</code>
            </div>
        </div>
        <CodeSnippet :value="snippets.avatar" />
    </section>
</template>
