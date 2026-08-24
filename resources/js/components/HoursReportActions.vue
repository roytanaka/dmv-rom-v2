<script setup lang="ts">
// The print-and-export toolbar shared by every Hours report (#414, PRD #406, ADR-0022 §8). The
// one deviation the port takes is "HTML styled for print, plus CSV": Print hands the page to the
// browser's own print-to-PDF (the chrome is hidden by the print stylesheet), and Export CSV is a
// plain download link to the report's `.csv` sibling — the same numbers, behind the same gate.
//
// It carries `print:hidden` so it never lands in the printout it triggers. All chrome is
// translated (ADR-0004).
import { Button } from '@/components/ui/button';
import { trans } from 'laravel-vue-i18n';

defineProps<{ csvHref: string }>();

// The browser's own print-to-PDF is the "no new PDF package" half of the deviation (§8).
const print = () => window.print();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 print:hidden">
        <Button variant="outline" size="sm" type="button" @click="print">{{ trans('hours.export.print') }}</Button>
        <!-- A real navigation, not an Inertia visit — the response is a file download, so the -->
        <!-- browser must handle it, and the server's Content-Disposition makes it a save. -->
        <Button as="a" variant="outline" size="sm" :href="csvHref">{{ trans('hours.export.csv') }}</Button>
    </div>
</template>
