<script setup lang="ts">
// A minimal rich-text body editor for the composer (#489, ADR-0024 §6). The ADR names
// tiptap; that package is flagged for review under the hard rule and not installed here,
// so this stands in with a `contenteditable` region and a bold / italic / list / link
// toolbar built on the browser's own formatting commands. It emits sanitizable HTML the
// server re-sanitizes on the way in (HtmlSanitizer), never trusting the markup.
//
// Swap for tiptap once the package is approved: the contract is a `v-model` of HTML, so
// the sheet above it does not change.
import { Button } from '@/components/ui/button';
import { PhLink, PhListBullets, PhListNumbers, PhTextB, PhTextItalic } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ modelValue: string }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const editor = ref<HTMLElement | null>(null);

// Keep the DOM in sync when the model is reset from outside (e.g. a fresh compose) without
// clobbering the caret while the sender types, so only mirror a value the editor doesn't hold.
watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.innerHTML !== value) {
            editor.value.innerHTML = value;
        }
    },
);

onMounted(() => {
    if (editor.value) {
        editor.value.innerHTML = props.modelValue;
    }
});

const emitHtml = () => {
    if (editor.value) {
        emit('update:modelValue', editor.value.innerHTML);
    }
};

// The browser's document commands are deprecated but universally supported and need no
// package; the placeholder editor uses them until tiptap lands.
const exec = (command: string, value?: string) => {
    editor.value?.focus();
    document.execCommand(command, false, value);
    emitHtml();
};

const insertLink = () => {
    const href = window.prompt(trans('broadcasts.composer.link_prompt'));
    if (href) {
        exec('createLink', href);
    }
};
</script>

<template>
    <div class="border-input border">
        <div class="border-input bg-muted/40 flex flex-wrap gap-1 border-b p-1">
            <Button type="button" variant="ghost" size="icon" :title="trans('broadcasts.composer.bold')" @click="exec('bold')">
                <PhTextB class="size-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :title="trans('broadcasts.composer.italic')" @click="exec('italic')">
                <PhTextItalic class="size-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :title="trans('broadcasts.composer.bullet_list')" @click="exec('insertUnorderedList')">
                <PhListBullets class="size-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :title="trans('broadcasts.composer.ordered_list')" @click="exec('insertOrderedList')">
                <PhListNumbers class="size-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :title="trans('broadcasts.composer.link')" @click="insertLink">
                <PhLink class="size-4" />
            </Button>
        </div>
        <div
            ref="editor"
            contenteditable="true"
            class="prose prose-sm min-h-40 max-w-none px-3 py-2 focus:outline-hidden"
            role="textbox"
            aria-multiline="true"
            @input="emitHtml"
        />
    </div>
</template>
