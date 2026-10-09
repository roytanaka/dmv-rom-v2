<script setup lang="ts">
// The image viewer (#776, spec #773): a full-screen overlay that shows one entry as large as
// fits the screen, with its name, a Download button, and a Close button. Escape and a click
// on the backdrop also close it.
//
// Built on the shadcn-vue Dialog root with radix-vue's content primitives, not our
// DialogContent: that one is a small padded box with its own corner X. Here the content box
// shrinks to the name, the entry, and the buttons, centred on the screen, so a click
// anywhere else lands on the dark overlay, which radix counts as outside and closes.
//
// Previous and next (#781): with more than one entry, Previous and Next buttons and the Left
// and Right arrow keys step through the list, and the position ("2 of 5") shows. A PNG,
// JPEG, WebP or GIF file shows as the image. Any other file, an SVG included, shows a file
// card (icon, name, type, size); it never shows inline. A link shows a card with its address
// and an Open button that goes through the gated route, and has no Download.
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { formatFileSize } from '@/documents/fileSize';
import { fileTypeIcon } from '@/documents/fileTypeIcon';
import { type SharedData } from '@/types';
import { isViewableImage, stepIndex, type ViewerEntry } from '@/viewer/entries';
import { usePage } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { PhArrowSquareOut, PhCaretLeft, PhCaretRight, PhDownloadSimple, PhX } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogTitle } from 'radix-vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    entries: ViewerEntry[];
    startIndex: number;
}>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage<SharedData>();

// The entry on screen. Each opening starts at `startIndex`.
const index = ref(props.startIndex);

watch(open, (isOpen) => {
    if (isOpen) index.value = props.startIndex;
});

const entry = computed<ViewerEntry | undefined>(() => props.entries[index.value]);
const stepping = computed(() => props.entries.length > 1);
const atFirst = computed(() => index.value <= 0);
const atLast = computed(() => index.value >= props.entries.length - 1);

const title = computed(() => (entry.value?.kind === 'link' ? entry.value.title : (entry.value?.filename ?? '')));
const cardIcon = computed(() =>
    entry.value?.kind === 'link'
        ? fileTypeIcon({ kind: 'link', mimeType: null })
        : fileTypeIcon({ kind: 'file', mimeType: entry.value?.mimeType ?? null }),
);
const cardFacts = computed(() =>
    entry.value?.kind === 'file' ? [entry.value.typeLabel, formatFileSize(entry.value.sizeBytes, page.props.locale)].filter(Boolean).join(' · ') : '',
);

function step(delta: number): void {
    index.value = stepIndex(index.value, delta, props.entries.length);
}

function onKeydown(event: KeyboardEvent): void {
    if (!open.value || !stepping.value) return;
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        step(-1);
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        step(1);
    }
}

// On the window, not the dialog: a Previous or Next button that turns disabled at an end
// drops focus, and the arrow keys must keep working.
useEventListener('keydown', onKeydown);
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
                <DialogTitle class="max-w-full text-center text-sm font-medium break-all">{{ title }}</DialogTitle>

                <img
                    v-if="entry.kind === 'file' && isViewableImage(entry.mimeType)"
                    :key="entry.key"
                    :src="entry.src"
                    :alt="entry.filename"
                    class="max-h-[calc(100dvh-11rem)] min-h-0 max-w-full object-contain"
                />

                <!-- A file card or a link card, in the image's place. -->
                <div v-else :key="entry.key" class="flex w-72 max-w-full flex-col items-center gap-3 rounded-lg bg-white/10 px-6 py-8 text-center">
                    <component :is="cardIcon" class="h-16 w-16 shrink-0" aria-hidden="true" />
                    <p v-if="entry.kind === 'link'" class="max-w-full text-sm break-all text-white/80">{{ entry.address }}</p>
                    <p v-else-if="cardFacts" class="text-sm text-white/80">{{ cardFacts }}</p>
                </div>

                <p v-if="stepping" class="text-sm text-white/80" aria-live="polite">
                    {{ trans('viewer.position', { current: String(index + 1), total: String(entries.length) }) }}
                </p>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <Button v-if="stepping" type="button" variant="secondary" class="min-h-11 min-w-11" :disabled="atFirst" @click="step(-1)">
                        <PhCaretLeft aria-hidden="true" />
                        <span class="sr-only sm:not-sr-only">{{ trans('viewer.previous') }}</span>
                    </Button>
                    <Button
                        v-if="entry.kind === 'link'"
                        as="a"
                        :href="entry.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="secondary"
                        class="min-h-11 min-w-11"
                    >
                        <PhArrowSquareOut aria-hidden="true" />
                        {{ trans('viewer.open') }}
                    </Button>
                    <Button v-else as="a" :href="entry.downloadHref" download variant="secondary" class="min-h-11 min-w-11">
                        <PhDownloadSimple aria-hidden="true" />
                        {{ trans('viewer.download') }}
                    </Button>
                    <Button v-if="stepping" type="button" variant="secondary" class="min-h-11 min-w-11" :disabled="atLast" @click="step(1)">
                        <span class="sr-only sm:not-sr-only">{{ trans('viewer.next') }}</span>
                        <PhCaretRight aria-hidden="true" />
                    </Button>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" class="min-h-11 min-w-11">
                            <PhX aria-hidden="true" />
                            {{ trans('viewer.close') }}
                        </Button>
                    </DialogClose>
                </div>
            </DialogContent>
        </DialogPortal>
    </Dialog>
</template>
