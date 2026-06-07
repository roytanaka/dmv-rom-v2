<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { PhCaretRight } from '@phosphor-icons/vue';
import { snippets } from '../snippets';
</script>

<template>
    <section id="collapsible" aria-labelledby="collapsible-heading" class="mb-12 scroll-mt-24">
        <h2 id="collapsible-heading" class="text-xl font-semibold tracking-tight">Collapsible</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            A disclosure that toggles a region open and closed. The trigger caret rotates on
            <code>data-[state=open]</code>; the content animates its height. Shown open by default below.
        </p>

        <div class="mt-6 max-w-md">
            <Collapsible v-slot="{ open }" default-open>
                <CollapsibleTrigger
                    class="border-border hover:bg-accent flex w-full items-center justify-between gap-2 border px-4 py-3 text-left text-sm font-medium transition-colors"
                >
                    <span>What should I bring on tour day?</span>
                    <PhCaretRight class="size-4 shrink-0 transition-transform" :class="open ? 'rotate-90' : ''" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="border-border border border-t-0 px-4 py-3 text-sm">
                        Wear your volunteer badge and comfortable shoes. Arrive fifteen minutes before your tour starts to check in at the Bloor
                        Street entrance.
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>
        <CodeSnippet :code="snippets.collapsible" />
    </section>
</template>
