<script setup lang="ts">
// A Saved query's mailing labels (#924, ADR-0038 §7): the Avery 5160 sheet, three across and ten
// down, printed by the browser with no PDF package. The page runs without the app chrome so the
// sheet geometry is the whole printout. The printer select shifts the top margin and the line
// height as the Legacy app's printer choice did: the Xerox sits 3pt higher with tighter lines.
// The server sends each label as its four lines, the country rule already applied.
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Head, Link } from '@inertiajs/vue3';
import { PhArrowLeft, PhPrinter } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

type Printer = 'xerox' | 'inkjet' | 'laser';

const props = defineProps<{
    query: { id: number; name: string };
    printer: Printer;
    printers: Printer[];
    sheets: string[][][];
}>();

const printer = ref<Printer>(props.printer);

// Top margin and line height in points, from the Legacy app's label offsets.
const geometry = computed(() => (printer.value === 'xerox' ? { top: 33, line: 13 } : { top: 36, line: 14 }));

const print = () => window.print();
</script>

<template>
    <Head :title="trans('saved_queries.labels_page.title', { query: query.name })" />

    <div class="bg-muted min-h-screen print:bg-white">
        <div class="bg-background flex flex-wrap items-end gap-4 border-b p-4 print:hidden">
            <Button variant="outline" as-child>
                <Link :href="route('officer.members.queries.run', { savedQuery: query.id })">
                    <PhArrowLeft aria-hidden="true" />
                    {{ trans('saved_queries.labels_page.back') }}
                </Link>
            </Button>
            <div class="grid gap-2">
                <Label for="labels-printer">{{ trans('saved_queries.labels_page.printer') }}</Label>
                <NativeSelect id="labels-printer" v-model="printer">
                    <option v-for="choice in printers" :key="choice" :value="choice">
                        {{ trans(`saved_queries.labels_page.printers.${choice}`) }}
                    </option>
                </NativeSelect>
            </div>
            <Button type="button" @click="print">
                <PhPrinter aria-hidden="true" />
                {{ trans('saved_queries.labels_page.print') }}
            </Button>
        </div>

        <div class="flex flex-col items-center gap-6 py-6 print:block print:p-0">
            <section
                v-for="(sheet, index) in sheets"
                :key="index"
                class="label-sheet bg-white font-serif text-black shadow print:shadow-none"
                :style="{ paddingTop: `${geometry.top}pt`, lineHeight: `${geometry.line}pt` }"
                :aria-label="trans('saved_queries.labels_page.sheet', { number: String(index + 1) })"
            >
                <div v-for="(label, cell) in sheet" :key="cell" class="label-cell">
                    <span v-for="(line, row) in label" :key="row" class="block truncate">{{ line }}</span>
                </div>
            </section>
        </div>
    </div>
</template>
