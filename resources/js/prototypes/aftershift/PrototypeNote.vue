<script setup lang="ts">
// PROTOTYPE (#405) — the annotation block above each variant. Deliberately plain and
// dashed so it never reads as part of the design being judged. It states the three
// things the ticket asks each variant to answer, so they can be compared without
// scrolling back to the issue.
defineProps<{
    surface: string;
    url: string;
    /** What tells someone there is something waiting to be recorded? */
    prompt: string;
    /** What this variant costs. */
    cost: string;
}>();
</script>

<template>
    <div class="border-input text-muted-foreground grid gap-1 border border-dashed p-3 text-xs">
        <p>
            <span class="text-rom-ink font-semibold">Surface</span> — {{ surface }} <code class="ml-1">{{ url }}</code>
        </p>
        <p><span class="text-rom-ink font-semibold">What prompts you</span> — {{ prompt }}</p>
        <p><span class="text-rom-ink font-semibold">Cost</span> — {{ cost }}</p>
    </div>
</template>
