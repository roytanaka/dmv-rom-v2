<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import { Separator } from '@/components/ui/separator';
import { snippets } from '../snippets';
</script>

<template>
    <section id="separator" aria-labelledby="separator-heading" class="mb-12 scroll-mt-24">
        <h2 id="separator-heading" class="text-xl font-semibold tracking-tight">Separator</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            A hairline rule on the <code>border</code> token, in both orientations. An optional <code>label</code> centres text on the rule for
            “or”-style dividers.
        </p>

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Horizontal</h3>
        <div class="mt-4 max-w-md">
            <p class="text-sm">Museum Highlights</p>
            <Separator class="my-4" />
            <p class="text-sm">Egyptian Galleries</p>
            <Separator class="my-4" label="or" />
            <p class="text-sm">Dinosaurs &amp; Fossils</p>
        </div>

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Vertical</h3>
        <div class="text-muted-foreground mt-4 flex h-6 items-center gap-3 text-sm">
            <span>Tours</span>
            <Separator orientation="vertical" />
            <span>Volunteers</span>
            <Separator orientation="vertical" />
            <span>Reports</span>
        </div>
        <CodeSnippet :value="snippets.separator" />
    </section>
</template>
