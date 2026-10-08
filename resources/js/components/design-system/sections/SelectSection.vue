<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ref } from 'vue';
import { snippets } from '../snippets';

const hour = ref(12);
const status = ref('open');
</script>

<template>
    <section id="select" aria-labelledby="select-heading" class="mb-12 scroll-mt-24">
        <h2 id="select-heading" class="text-xl font-semibold tracking-tight">Select</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Use <code>NativeSelect</code> in forms, and for long lists such as hours. It opens the system picker on a phone. Use the shadcn
            <code>Select</code> only for a short filter list that needs styled items.
        </p>

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">NativeSelect</h3>
        <div class="mt-4 grid max-w-xs gap-2">
            <Label for="ds-native-select">Start hour</Label>
            <NativeSelect id="ds-native-select" v-model="hour">
                <option v-for="value in 24" :key="value - 1" :value="value - 1">{{ String(value - 1).padStart(2, '0') }}:00</option>
            </NativeSelect>
        </div>
        <CodeSnippet :value="snippets.nativeSelect" />

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Select</h3>
        <div class="mt-4 grid max-w-xs gap-2">
            <Label for="ds-select">Status</Label>
            <Select v-model="status">
                <SelectTrigger id="ds-select">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="open">Open</SelectItem>
                    <SelectItem value="closed">Closed</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <CodeSnippet :value="snippets.select" />
    </section>
</template>
