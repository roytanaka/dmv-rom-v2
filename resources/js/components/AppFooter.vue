<script setup lang="ts">
// The dark institutional footer (Chrome) — wraps every authenticated page, carrying
// the institutional voice: the land acknowledgement, the DMV inclusion statement,
// and a copyright line. Black bracketing the white canvas, mirroring the top bar.
//
// Copy is keyed in the Laravel lang files (institutional.php / footer.php) so French
// drops in without restructuring. The land acknowledgement, inclusion statement, and
// department name are institutional strings, excluded from machine translation.
import { trans } from 'laravel-vue-i18n';

// Copyright spans a fixed start year to the current year, computed at render — only
// the org name is translated copy.
const startYear = '2011';
const currentYear = String(new Date().getFullYear());
</script>

<template>
    <footer class="bg-rom-ink mt-auto px-6 py-8 text-white/70">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 text-sm">
            <p>{{ trans('institutional.land_acknowledgement') }}</p>
            <p>{{ trans('institutional.inclusion') }}</p>
            <p class="text-xs text-white/45">
                {{ trans('footer.copyright', { start: startYear, current: currentYear, org: trans('institutional.department') }) }}
            </p>
        </div>
    </footer>
</template>
