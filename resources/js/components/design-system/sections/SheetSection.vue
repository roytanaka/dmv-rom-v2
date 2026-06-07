<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { snippets } from '../snippets';
</script>

<template>
    <section id="sheet" aria-labelledby="sheet-heading" class="mb-12 scroll-mt-24">
        <h2 id="sheet-heading" class="text-xl font-semibold tracking-tight">Sheet</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            A panel that slides in over a dimming overlay, from any of the four edges. It lifts on <code>shadow-lg</code> and carries the Phosphor
            <code>×</code> close glyph. Each trigger below opens the sheet from its named side.
        </p>

        <div class="mt-6 flex flex-wrap gap-4">
            <Sheet v-for="side in ['top', 'right', 'bottom', 'left'] as const" :key="side">
                <SheetTrigger as-child>
                    <Button variant="outline" class="capitalize">{{ side }}</Button>
                </SheetTrigger>
                <SheetContent :side="side">
                    <SheetHeader>
                        <SheetTitle>Filter tours</SheetTitle>
                        <SheetDescription>
                            Narrow the listing by date, gallery, and remaining capacity. Opened from the
                            <strong>{{ side }}</strong> edge.
                        </SheetDescription>
                    </SheetHeader>
                    <SheetFooter class="mt-6 gap-3">
                        <SheetClose as-child>
                            <Button variant="outline">Cancel</Button>
                        </SheetClose>
                        <SheetClose as-child>
                            <Button>Apply filters</Button>
                        </SheetClose>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </div>
        <CodeSnippet :code="snippets.sheet" />
    </section>
</template>
