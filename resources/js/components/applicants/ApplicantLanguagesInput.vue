<script setup lang="ts">
// An application's languages (#928, ADR-0038 §8), sent with the form as rows in the
// `member_languages` shape. English and French take speak, read and write; ASL and LSQ are
// speak-only, so one box; any other language is a name and its three flags. A language with no box
// ticked is not sent. Shared by Add applicant, the Applicant record and the public form (#930),
// which may cap the other languages at one with `maxOther`.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

export interface LanguageRow {
    language_code: string;
    speaks: boolean;
    reads: boolean;
    writes: boolean;
}

const FLAGGED = ['en', 'fr'];
const SPEAK_ONLY = ['asl', 'lsq'];
const KNOWN = [...FLAGGED, ...SPEAK_ONLY];
const FLAGS = ['speaks', 'reads', 'writes'] as const;
type Flag = (typeof FLAGS)[number];

withDefaults(defineProps<{ errors: Partial<Record<string, string>>; disabled?: boolean; maxOther?: number }>(), {
    disabled: false,
    maxOther: 10,
});

const languages = defineModel<LanguageRow[]>('languages', { required: true });

const rowOf = (code: string) => languages.value.find((row) => row.language_code === code);
const others = computed(() => languages.value.filter((row) => !KNOWN.includes(row.language_code)));

const setFlag = (code: string, flag: Flag, checked: boolean) => {
    const row = rowOf(code) ?? { language_code: code, speaks: false, reads: false, writes: false };
    const next = { ...row, [flag]: checked };
    const rest = languages.value.filter((candidate) => candidate.language_code !== code);
    languages.value = next.speaks || next.reads || next.writes ? [...rest, next] : rest;
};

const setOther = (index: number, change: Partial<LanguageRow>) => {
    const target = others.value[index];
    languages.value = languages.value.map((row) => (row === target ? { ...row, ...change } : row));
};

const addOther = () => {
    languages.value = [...languages.value, { language_code: '', speaks: true, reads: false, writes: false }];
};

const removeOther = (index: number) => {
    const target = others.value[index];
    languages.value = languages.value.filter((row) => row !== target);
};
</script>

<template>
    <ul class="flex flex-col gap-3 text-sm">
        <li v-for="code in FLAGGED" :key="code" class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="min-w-40 font-medium">{{ trans(`records.languages.name.${code}`) }}</span>
            <div v-for="flag in FLAGS" :key="flag" class="flex items-center gap-2">
                <Checkbox
                    :id="`language-${code}-${flag}`"
                    :checked="rowOf(code)?.[flag] ?? false"
                    :disabled="disabled"
                    @update:checked="(checked: boolean) => setFlag(code, flag, checked)"
                />
                <Label :for="`language-${code}-${flag}`">{{ trans(`records.languages.${flag}`) }}</Label>
            </div>
        </li>
        <li v-for="code in SPEAK_ONLY" :key="code" class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="min-w-40 font-medium">{{ trans(`records.languages.name.${code}`) }}</span>
            <div class="flex items-center gap-2">
                <Checkbox
                    :id="`language-${code}-uses`"
                    :checked="rowOf(code) !== undefined"
                    :disabled="disabled"
                    @update:checked="(checked: boolean) => setFlag(code, 'speaks', checked)"
                />
                <Label :for="`language-${code}-uses`">{{ trans('applicants.languages.uses') }}</Label>
            </div>
        </li>
        <li v-for="(row, index) in others" :key="index" class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <div class="grid min-w-40 gap-1">
                <Label :for="`language-other-${index}`" class="sr-only">{{ trans('applicants.languages.other') }}</Label>
                <Input
                    :id="`language-other-${index}`"
                    :model-value="row.language_code"
                    :placeholder="trans('applicants.languages.other')"
                    maxlength="64"
                    :disabled="disabled"
                    @update:model-value="(value: string | number) => setOther(index, { language_code: String(value) })"
                />
            </div>
            <div v-for="flag in FLAGS" :key="flag" class="flex items-center gap-2">
                <Checkbox
                    :id="`language-other-${index}-${flag}`"
                    :checked="row[flag]"
                    :disabled="disabled"
                    @update:checked="(checked: boolean) => setOther(index, { [flag]: checked })"
                />
                <Label :for="`language-other-${index}-${flag}`">{{ trans(`records.languages.${flag}`) }}</Label>
            </div>
            <Button v-if="!disabled" type="button" variant="ghost" size="sm" class="ml-auto" @click="removeOther(index)">
                {{ trans('records.remove') }}
            </Button>
        </li>
    </ul>
    <div v-if="!disabled && others.length < maxOther">
        <Button type="button" variant="outline" size="sm" @click="addOther">{{ trans('applicants.languages.add_other') }}</Button>
    </div>
    <InputError :message="errors.languages" />
    <InputError v-for="(message, key) in errors" v-show="String(key).startsWith('languages.')" :key="key" :message="message" />
</template>
