<script setup lang="ts">
// The Feedback image control (ADR-0029 §9): the send dialog's screenshots (#678) and a
// comment's images (#778). Up to 3 images, added by the file picker, by a drop on the drop
// zone, or by a paste. All three go through `addScreenshots`, which refuses a file before
// sending for the server's reasons. No package: the browser's own drop and paste events.
//
// The form that holds this control should take a paste or a file drop anywhere in it, so
// it binds the exposed `onPaste`, `onFormDragOver`, and `onFormDrop` to its own events.
// Setting the model to an empty list clears the control.
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { screenshotSize } from '@/feedback/display';
import { addScreenshots, MAX_SCREENSHOTS, SCREENSHOT_TYPES, type ScreenshotError } from '@/feedback/screenshots';
import { PhUploadSimple, PhX } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    inputId: string;
    label: string;
    // The accessible name of the list of chosen images.
    listLabel: string;
    // The server's errors for these files, already in the page's language.
    serverErrors: string[];
    // The lang key for a file past the limit; the send dialog's "screenshots" wording by default.
    limitErrorKey?: string;
}>();

const files = defineModel<File[]>({ required: true });

// Each image with the preview URL its thumbnail shows. The URL is revoked when the image
// leaves the list.
const shots = ref<{ file: File; url: string }[]>([]);
const fileErrors = ref<ScreenshotError[]>([]);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const shotName = (file: File): string => file.name || trans('feedback.screenshots.pasted');

function sync(): void {
    files.value = shots.value.map((shot) => shot.file);
}

function addFiles(incoming: File[]): void {
    const current = shots.value.map((shot) => shot.file);
    const result = addScreenshots(current, incoming, trans('feedback.screenshots.pasted'), props.limitErrorKey);

    shots.value = [...shots.value, ...result.files.slice(current.length).map((file) => ({ file, url: URL.createObjectURL(file) }))];
    fileErrors.value = result.errors;
    sync();
}

function removeShot(index: number): void {
    URL.revokeObjectURL(shots.value[index].url);
    shots.value = shots.value.filter((_, i) => i !== index);
    fileErrors.value = [];
    sync();
}

function clearShots(): void {
    shots.value.forEach((shot) => URL.revokeObjectURL(shot.url));
    shots.value = [];
    fileErrors.value = [];
}

watch(files, (next) => {
    if (next.length === 0 && shots.value.length > 0) {
        clearShots();
    }
});

onBeforeUnmount(clearShots);

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    addFiles(Array.from(input.files ?? []));
    input.value = '';
}

const dragsFiles = (event: DragEvent): boolean => event.dataTransfer?.types.includes('Files') ?? false;

// The zone lights only for files, and stays lit while the pointer moves over its children.
function onZoneDragOver(event: DragEvent): void {
    if (dragsFiles(event)) {
        dragging.value = true;
    }
}

function onZoneDragLeave(event: DragEvent): void {
    if (!(event.currentTarget as Node).contains(event.relatedTarget as Node | null)) {
        dragging.value = false;
    }
}

// The whole form takes a file drop, so a file that misses the zone is added, not opened
// by the browser over the form. A drop of text goes into the field as usual.
function onFormDragOver(event: DragEvent): void {
    if (dragsFiles(event)) {
        event.preventDefault();
    }
}

function onFormDrop(event: DragEvent): void {
    dragging.value = false;

    if (!dragsFiles(event)) {
        return;
    }

    event.preventDefault();
    addFiles(Array.from(event.dataTransfer?.files ?? []));
}

// A paste of an image adds it. A paste that carries text too (a copy from Word, say, which
// adds a picture of the text) goes into the field as text.
function onPaste(event: ClipboardEvent): void {
    const pasted = Array.from(event.clipboardData?.files ?? []);

    if (pasted.length === 0 || event.clipboardData?.types.includes('text/plain')) {
        return;
    }

    event.preventDefault();
    addFiles(pasted);
}

defineExpose({ onPaste, onFormDragOver, onFormDrop });
</script>

<template>
    <div class="grid gap-2">
        <Label :for="inputId">{{ label }}</Label>
        <div
            class="flex flex-col items-center gap-2 border-2 border-dashed p-4 text-center text-sm"
            :class="dragging ? 'border-rom-slate bg-rom-slate-50' : 'border-input bg-muted'"
            @dragenter="onZoneDragOver"
            @dragover="onZoneDragOver"
            @dragleave="onZoneDragLeave"
        >
            <PhUploadSimple class="text-muted-foreground size-6" aria-hidden="true" />
            <div class="flex flex-wrap items-center justify-center gap-2">
                <span>{{ trans('feedback.screenshots.drop') }}</span>
                <Button type="button" variant="outline" size="sm" @click="fileInput?.click()">
                    {{ trans('feedback.screenshots.choose') }}
                </Button>
            </div>
            <input
                :id="inputId"
                ref="fileInput"
                type="file"
                class="sr-only"
                tabindex="-1"
                multiple
                :accept="SCREENSHOT_TYPES.join(',')"
                @change="onPick"
            />
        </div>
        <ul v-if="shots.length" :aria-label="listLabel" class="grid grid-cols-3 gap-2">
            <li v-for="(shot, index) in shots" :key="shot.url" class="flex min-w-0 flex-col gap-1 border p-1">
                <div class="bg-muted relative aspect-video overflow-hidden">
                    <img :src="shot.url" alt="" class="size-full object-cover" />
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        class="absolute top-0 right-0"
                        :aria-label="trans('feedback.screenshots.remove', { name: shotName(shot.file) })"
                        @click="removeShot(index)"
                    >
                        <PhX aria-hidden="true" />
                    </Button>
                </div>
                <span class="truncate text-sm">{{ shotName(shot.file) }}</span>
                <span class="text-muted-foreground text-sm">{{ screenshotSize(shot.file.size) }}</span>
            </li>
        </ul>
        <ul v-if="fileErrors.length || serverErrors.length" role="alert" class="text-destructive flex flex-col gap-1 text-sm">
            <li v-for="error in fileErrors" :key="`${error.key}-${error.name}`">
                {{ trans(error.key, { name: error.name, max: String(MAX_SCREENSHOTS) }) }}
            </li>
            <li v-for="message in serverErrors" :key="message">{{ message }}</li>
        </ul>
    </div>
</template>
