<script setup lang="ts">
// The Send feedback dialog (#676, ADR-0029), opened from the top-bar Help menu outside
// production. The Tester types a name, picks a type, and writes a message. The client
// context (page URL, user agent, viewport) rides along on submit; the server fills the
// rest. Label, control, and error only — no helper text.
//
// The name is remembered in the browser (§5, `@/feedback/testerName`).
//
// Screenshots (#678, §9): up to 3 images, added by the file picker, by a drop on the drop
// zone, or by a paste anywhere in the form. All three go through `addScreenshots`, which
// refuses a file before sending for the server's reasons. No package: the browser's own
// drop and paste events.
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { buildClientContext } from '@/feedback/clientContext';
import { screenshotSize } from '@/feedback/display';
import { addScreenshots, MAX_SCREENSHOTS, SCREENSHOT_TYPES, type ScreenshotError } from '@/feedback/screenshots';
import { rememberedName, rememberName } from '@/feedback/testerName';
import { useForm } from '@inertiajs/vue3';
import { PhCheckCircle, PhUploadSimple, PhX } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    // The Feedback page: the dialog posts here and links here.
    href: string;
}>();

const open = defineModel<boolean>('open', { required: true });

// FeedbackType values (ADR-0029 §6), in picker order.
const TYPES = ['bug', 'feature-request', 'translation', 'missing-from-new-site', 'confusing', 'other'] as const;

const form = useForm({
    tester_name: '',
    type: '',
    message: '',
});

// Each screenshot with the preview URL its thumbnail shows. The URL is revoked when the
// screenshot leaves the list.
const shots = ref<{ file: File; url: string }[]>([]);
const fileErrors = ref<ScreenshotError[]>([]);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

form.transform((data) => ({ ...data, ...buildClientContext(window), screenshots: shots.value.map((shot) => shot.file) }));

// The server's screenshot errors: `screenshots` for the count, `screenshots.N` per file.
const serverFileErrors = computed(() =>
    Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key === 'screenshots' || key.startsWith('screenshots.'))
        .map(([, message]) => message),
);

const sent = ref(false);

const shotName = (file: File): string => file.name || trans('feedback.screenshots.pasted');

function addFiles(files: File[]): void {
    const current = shots.value.map((shot) => shot.file);
    const result = addScreenshots(current, files, trans('feedback.screenshots.pasted'));

    shots.value = [...shots.value, ...result.files.slice(current.length).map((file) => ({ file, url: URL.createObjectURL(file) }))];
    fileErrors.value = result.errors;
}

function removeShot(index: number): void {
    URL.revokeObjectURL(shots.value[index].url);
    shots.value = shots.value.filter((_, i) => i !== index);
    fileErrors.value = [];
}

function clearShots(): void {
    shots.value.forEach((shot) => URL.revokeObjectURL(shot.url));
    shots.value = [];
    fileErrors.value = [];
}

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
    const files = Array.from(event.clipboardData?.files ?? []);

    if (files.length === 0 || event.clipboardData?.types.includes('text/plain')) {
        return;
    }

    event.preventDefault();
    addFiles(files);
}

onBeforeUnmount(clearShots);

// Each opening starts a fresh form with the remembered name. The fields are cleared by
// hand: after a successful send, useForm's defaults are the sent values, so reset()
// would bring the last message back.
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    sent.value = false;
    form.clearErrors();
    form.tester_name = rememberedName();
    form.type = '';
    form.message = '';
    clearShots();
});

function submit(): void {
    form.post(props.href, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            rememberName(form.tester_name);
            sent.value = true;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ trans('feedback.dialog.title') }}</DialogTitle>
            </DialogHeader>

            <div v-if="sent" class="flex flex-col gap-4" role="status">
                <p class="flex items-center gap-2 text-base">
                    <PhCheckCircle class="text-success size-5 shrink-0" />
                    {{ trans('feedback.dialog.sent') }}
                </p>
                <DialogFooter class="items-center gap-4 sm:justify-between">
                    <TextLink :href="href" @click="open = false">{{ trans('feedback.dialog.see_all') }}</TextLink>
                    <Button type="button" size="sm" @click="open = false">{{ trans('feedback.dialog.close') }}</Button>
                </DialogFooter>
            </div>

            <form v-else class="flex flex-col gap-4" @submit.prevent="submit" @paste="onPaste" @dragover="onFormDragOver" @drop="onFormDrop">
                <div class="grid gap-2">
                    <Label for="feedback-name">{{ trans('feedback.dialog.name') }}</Label>
                    <Input
                        id="feedback-name"
                        v-model="form.tester_name"
                        required
                        maxlength="100"
                        autocomplete="name"
                        :aria-invalid="form.errors.tester_name ? true : undefined"
                    />
                    <InputError :message="form.errors.tester_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="feedback-type">{{ trans('feedback.dialog.type') }}</Label>
                    <Select v-model="form.type" required>
                        <SelectTrigger id="feedback-type" :aria-invalid="form.errors.type ? true : undefined">
                            <SelectValue :placeholder="trans('feedback.dialog.type_placeholder')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in TYPES" :key="type" :value="type">{{ trans(`feedback.type.${type}`) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="grid gap-2">
                    <Label for="feedback-message">{{ trans('feedback.dialog.message') }}</Label>
                    <Textarea
                        id="feedback-message"
                        v-model="form.message"
                        required
                        maxlength="5000"
                        :rows="5"
                        :aria-invalid="form.errors.message ? true : undefined"
                    />
                    <InputError :message="form.errors.message" />
                </div>
                <div class="grid gap-2">
                    <Label for="feedback-screenshots">{{ trans('feedback.screenshots.title') }}</Label>
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
                            id="feedback-screenshots"
                            ref="fileInput"
                            type="file"
                            class="sr-only"
                            tabindex="-1"
                            multiple
                            :accept="SCREENSHOT_TYPES.join(',')"
                            @change="onPick"
                        />
                    </div>
                    <ul v-if="shots.length" :aria-label="trans('feedback.screenshots.list')" class="grid grid-cols-3 gap-2">
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
                            <span class="text-muted-foreground text-xs">{{ screenshotSize(shot.file.size) }}</span>
                        </li>
                    </ul>
                    <ul v-if="fileErrors.length || serverFileErrors.length" role="alert" class="text-destructive flex flex-col gap-1 text-sm">
                        <li v-for="error in fileErrors" :key="`${error.key}-${error.name}`">
                            {{ trans(error.key, { name: error.name, max: String(MAX_SCREENSHOTS) }) }}
                        </li>
                        <li v-for="message in serverFileErrors" :key="message">{{ message }}</li>
                    </ul>
                </div>

                <DialogFooter class="items-center gap-4 sm:justify-between">
                    <TextLink :href="href" @click="open = false">{{ trans('feedback.dialog.see_all') }}</TextLink>
                    <div class="flex gap-2">
                        <Button type="button" variant="ghost" size="sm" :disabled="form.processing" @click="open = false">
                            {{ trans('feedback.dialog.cancel') }}
                        </Button>
                        <Button type="submit" size="sm" :disabled="form.processing">{{ trans('feedback.dialog.send') }}</Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
