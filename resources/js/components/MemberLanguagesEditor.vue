<script setup lang="ts">
// The languages on the Member record (#918, ADR-0038 §3). Each add, change and remove saves at
// once, on its own route, and keeps the state of the record form this sits inside: an unsaved edit
// there survives. The server stores ASL and LSQ as speak-only, so their flags show but do not move.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

export interface MemberLanguage {
    id: number;
    language_code: string;
    speaks: boolean;
    reads: boolean;
    writes: boolean;
}

const KNOWN = ['en', 'fr', 'asl', 'lsq'];
const SPEAK_ONLY = ['asl', 'lsq'];
const FLAGS = ['speaks', 'reads', 'writes'] as const;
type Flag = (typeof FLAGS)[number];

const props = defineProps<{ memberId: number; languages: MemberLanguage[] }>();

const stayOnPage = { preserveScroll: true, preserveState: true };

const form = useForm({ language_code: '', speaks: true, reads: false, writes: false });

const choices = computed(() => [...KNOWN.filter((code) => !props.languages.some((language) => language.language_code === code)), 'other']);
const choice = ref('');
const otherName = ref('');

const isSpeakOnly = (code: string) => SPEAK_ONLY.includes(code);
const nameOf = (code: string) => (KNOWN.includes(code) ? trans(`records.languages.name.${code}`) : code);

const add = () => {
    form.language_code = choice.value === 'other' ? otherName.value : choice.value;
    form.post(route('members.languages.store', { member: props.memberId }), {
        ...stayOnPage,
        onSuccess: () => {
            form.reset();
            choice.value = '';
            otherName.value = '';
        },
    });
};

const setFlag = (language: MemberLanguage, flag: Flag, checked: boolean) => {
    router.patch(
        route('members.languages.update', { member: props.memberId, language: language.id }),
        { speaks: language.speaks, reads: language.reads, writes: language.writes, [flag]: checked },
        stayOnPage,
    );
};

const remove = (language: MemberLanguage) => {
    router.delete(route('members.languages.destroy', { member: props.memberId, language: language.id }), stayOnPage);
};
</script>

<template>
    <ul v-if="languages.length" class="flex flex-col gap-3 text-sm">
        <li v-for="language in languages" :key="language.id" class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="min-w-40 font-medium">{{ nameOf(language.language_code) }}</span>
            <div v-for="flag in FLAGS" :key="flag" class="flex items-center gap-2">
                <Checkbox
                    :id="`language-${language.id}-${flag}`"
                    :checked="language[flag]"
                    :disabled="isSpeakOnly(language.language_code)"
                    @update:checked="(checked: boolean) => setFlag(language, flag, checked)"
                />
                <Label :for="`language-${language.id}-${flag}`">{{ trans(`records.languages.${flag}`) }}</Label>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="ml-auto"
                :aria-label="trans('records.languages.remove', { language: nameOf(language.language_code) })"
                @click="remove(language)"
            >
                {{ trans('records.remove') }}
            </Button>
        </li>
    </ul>
    <p v-else class="text-muted-foreground text-sm">{{ trans('records.languages.none') }}</p>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label for="new_language">{{ trans('records.languages.add') }}</Label>
            <NativeSelect id="new_language" v-model="choice">
                <option value="">{{ trans('records.none') }}</option>
                <option v-for="code in choices" :key="code" :value="code">{{ trans(`records.languages.name.${code}`) }}</option>
            </NativeSelect>
            <InputError :message="form.errors.language_code" />
        </div>
        <div v-if="choice === 'other'" class="grid gap-2">
            <Label for="new_language_other">{{ trans('records.languages.other_name') }}</Label>
            <Input id="new_language_other" v-model="otherName" maxlength="64" @keydown.enter.prevent="add" />
        </div>
    </div>
    <div v-if="choice" class="flex flex-wrap items-center gap-4 text-sm">
        <div v-for="flag in FLAGS" :key="flag" class="flex items-center gap-2">
            <Checkbox
                :id="`new-language-${flag}`"
                :checked="isSpeakOnly(choice) ? flag === 'speaks' : form[flag]"
                :disabled="isSpeakOnly(choice)"
                @update:checked="(checked: boolean) => (form[flag] = checked)"
            />
            <Label :for="`new-language-${flag}`">{{ trans(`records.languages.${flag}`) }}</Label>
        </div>
        <Button type="button" variant="outline" size="sm" class="ml-auto" :disabled="form.processing" @click="add">
            {{ trans('records.add') }}
        </Button>
    </div>
</template>
