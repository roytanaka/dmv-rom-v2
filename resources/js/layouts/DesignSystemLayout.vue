<script setup lang="ts">
// Standalone minimal layout for the internal /design-system reference page.
// Deliberately does NOT use the app shell (header/sidebar/footer) — that does
// not yet exist in ROM form (PR #3). Just a max-width container on the white
// canvas. English-only: this is a developer/design reference, not Volunteer-
// facing content (a narrow exception to the bilingual rule, ADR-0008).
</script>

<template>
    <div class="bg-background text-foreground min-h-svh">
        <div class="mx-auto max-w-4xl px-6 py-12 md:px-10">
            <slot />
        </div>
    </div>
</template>
