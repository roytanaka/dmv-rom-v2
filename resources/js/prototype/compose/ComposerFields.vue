<script setup lang="ts">
// PROTOTYPE (#467) — the composer's fields, shared by every variant: subject, a rich-text
// body (a Textarea behind a fake toolbar; tiptap is not installed yet), and up to two
// attachments totalling 10 MB (#465). Layout is the variant's business; this is the form.
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { PhLink, PhListBullets, PhPaperclip, PhTextB, PhTextItalic, PhX } from '@phosphor-icons/vue';
import { formatBytes, type useDraft } from './model';

defineProps<{ draft: ReturnType<typeof useDraft>; compact?: boolean }>();
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -- PROTOTYPE: the draft is a shared store passed in, not data to keep pure -->
    <div class="flex flex-col gap-4">
        <div class="grid gap-1.5">
            <Label for="proto-subject">Subject</Label>
            <Input id="proto-subject" v-model="draft.subject.value" placeholder="What is this about?" />
        </div>

        <div class="grid gap-1.5">
            <Label for="proto-body">Message</Label>
            <div class="border-input border">
                <div class="bg-muted/60 text-muted-foreground flex items-center gap-0.5 border-b px-1 py-0.5">
                    <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="Bold"><PhTextB class="size-4" /></Button>
                    <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="Italic"><PhTextItalic class="size-4" /></Button>
                    <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="List"><PhListBullets class="size-4" /></Button>
                    <Button type="button" variant="ghost" size="icon" class="size-7" aria-label="Link"><PhLink class="size-4" /></Button>
                    <span class="ml-auto pr-2 text-xs">Rich text · sent in one language as written</span>
                </div>
                <Textarea
                    id="proto-body"
                    v-model="draft.body.value"
                    :rows="compact ? 6 : 10"
                    class="border-0 focus-visible:ring-0"
                    placeholder="Write your message…"
                />
            </div>
        </div>

        <div class="grid gap-1.5">
            <div class="flex items-center justify-between">
                <Label>Attachments</Label>
                <span class="text-muted-foreground text-xs">Up to 2 files, 10 MB total · {{ formatBytes(draft.totalBytes.value) }} used</span>
            </div>
            <ul v-if="draft.files.value.length" class="flex flex-col gap-1 text-sm">
                <li v-for="(f, i) in draft.files.value" :key="f.name + i" class="bg-muted/50 flex items-center justify-between gap-2 px-2 py-1">
                    <span class="truncate"
                        >{{ f.name }} <span class="text-muted-foreground">· {{ formatBytes(f.size) }}</span></span
                    >
                    <button type="button" class="hover:text-destructive" aria-label="Remove attachment" @click="draft.removeFile(i)">
                        <PhX class="size-3.5" />
                    </button>
                </li>
            </ul>
            <label
                v-if="draft.files.value.length < 2"
                class="text-rom-slate inline-flex w-fit cursor-pointer items-center gap-1.5 text-sm underline-offset-4 hover:underline"
            >
                <PhPaperclip class="size-4" /> Attach a file
                <input type="file" class="sr-only" multiple @change="draft.addFiles(($event.target as HTMLInputElement).files)" />
            </label>
            <p v-if="draft.tooBig.value" class="text-destructive text-sm">Attachments exceed 10 MB.</p>
        </div>
    </div>
</template>
