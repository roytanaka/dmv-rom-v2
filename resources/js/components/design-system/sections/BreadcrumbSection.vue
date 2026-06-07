<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { snippets } from '../snippets';
</script>

<template>
    <section id="breadcrumb" aria-labelledby="breadcrumb-heading" class="mb-12 scroll-mt-24">
        <h2 id="breadcrumb-heading" class="text-xl font-semibold tracking-tight">Breadcrumb</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            A wayfinding trail with Phosphor caret separators. Long trails collapse the middle to an ellipsis (the Phosphor
            <code>⋯</code> glyph), keeping the root and the current page in view. The current page is the unlinked <code>BreadcrumbPage</code>.
        </p>

        <div class="mt-6 space-y-6">
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink href="#">Dashboard</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbLink href="#">Volunteers</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Alex Rivera</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>

            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink href="#">Dashboard</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbEllipsis />
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbLink href="#">Tours</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Museum Highlights</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </div>
        <CodeSnippet :code="snippets.breadcrumb" />
    </section>
</template>
