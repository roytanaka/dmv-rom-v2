<script setup lang="ts">
// The dark institutional footer (Chrome) — wraps every authenticated page, carrying
// the institutional voice: the land acknowledgement, the DMV inclusion statement,
// and a copyright line. Black bracketing the white canvas, mirroring the top bar.
//
// Copy is keyed in the Laravel lang files (institutional.php / footer.php) so French
// drops in without restructuring. The land acknowledgement, inclusion statement, and
// department name are institutional strings, excluded from machine translation.
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const page = usePage<SharedData>();

// Copyright spans a fixed start year to the current year, computed at render.
const startYear = '2011';
const currentYear = String(new Date().getFullYear());

// The deployed version (#673): short commit id and deploy time in the org timezone.
// Null in local development, which reads "dev".
const version = computed(() => {
    const appVersion = page.props.appVersion;
    if (appVersion === null) {
        return trans('footer.version_dev');
    }
    const date = new Intl.DateTimeFormat(page.props.locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: page.props.timezone,
    }).format(new Date(appVersion.deployedAt));

    return trans('footer.version', { commit: appVersion.commit, date });
});
</script>

<template>
    <footer class="bg-rom-ink mt-auto px-6 py-8 text-white/70 print:hidden">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 text-sm">
            <p>{{ trans('institutional.land_acknowledgement') }}</p>
            <p>{{ trans('institutional.inclusion') }}</p>
            <p class="flex flex-wrap gap-x-4 text-white/45">
                <span>{{ trans('footer.copyright', { start: startYear, current: currentYear, org: trans('institutional.department') }) }}</span>
                <span>{{ version }}</span>
            </p>
        </div>
    </footer>
</template>
