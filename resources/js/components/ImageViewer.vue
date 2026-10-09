<script setup lang="ts">
// The image viewer (#776, spec #773): a full-screen overlay that shows one image as large as
// fits the screen, with its filename, a Download button, and a Close button. Escape and a
// click on the backdrop also close it.
//
// Built on the shadcn-vue Dialog root with radix-vue's content primitives, not our
// DialogContent: that one is a small padded box with its own corner X. Here the content box
// shrinks to the filename, the image, and the buttons, centred on the screen, so a click
// anywhere else lands on the dark overlay, which radix counts as outside and closes.
//
// It takes a list of entries and the index to open on, so previous/next (#781) can step
// through the list without a rewrite. It shows only `entries[startIndex]` for now. An entry
// whose type is not PNG, JPEG, WebP, or GIF never shows inline: only its filename and
// Download show.
import { Dialog } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { isViewableImage, type ViewerEntry } from '@/viewer/entries';
import { PhDownloadSimple, PhX } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogTitle } from 'radix-vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    entries: ViewerEntry[];
    startIndex: number;
}>();

const open = defineModel<boolean>('open', { required: true });

// The entry on screen. Each opening starts at `startIndex`.
const index = ref(props.startIndex);

watch(open, (isOpen) => {
    if (isOpen) index.value = props.startIndex;
});

const entry = computed<ViewerEntry | undefined>(() => props.entries[index.value]);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/85"
            />
            <DialogContent
                v-if="entry"
                :aria-describedby="undefined"
                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100dvh-2rem)] w-max max-w-[calc(100vw-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-3 text-white focus:outline-hidden"
            >
                <DialogTitle class="max-w-full text-center text-sm font-medium break-all">{{ entry.filename }}</DialogTitle>

                <img
                    v-if="isViewableImage(entry.mimeType)"
                    :key="entry.key"
                    :src="entry.src"
                    :alt="entry.filename"
                    class="max-h-[calc(100dvh-9rem)] min-h-0 max-w-full object-contain"
                />

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <Button as="a" :href="entry.downloadHref" download variant="secondary" class="min-h-11 min-w-11">
                        <PhDownloadSimple />
                        {{ trans('viewer.download') }}
                    </Button>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="min-h-11 min-w-11">
                            <PhX />
                            {{ trans('viewer.close') }}
                        </Button>
                    </DialogClose>
                </div>
            </DialogContent>
        </DialogPortal>
    </Dialog>
</template>
