<script setup lang="ts">
// Standalone minimal layout for the internal /design-system reference page.
// Deliberately does NOT wrap the page in the real app shell (header/sidebar/
// footer): nesting the live nav inside a page *about* the nav is confusing, and
// embedding the chrome would forfeit clean breakpoint/state demos. The shell is
// documented on the page, not rendered around it. Just a max-width container on
// the white canvas, wide enough to host the page's sticky section rail.
// English-only: this is a developer/design reference, not Volunteer-facing
// content (a narrow exception to the bilingual rule, ADR-0008).
</script>

<template>
    <div class="bg-background text-foreground min-h-svh">
        <div class="mx-auto max-w-6xl px-6 py-12 md:px-10">
            <slot />
        </div>
    </div>
</template>
