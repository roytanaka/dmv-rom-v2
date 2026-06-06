<script setup lang="ts">
// The dark institutional footer (Chrome) — wraps every authenticated page, carrying
// the institutional voice: the land acknowledgement, the DMV inclusion statement,
// and a copyright line. Black bracketing the white canvas, mirroring the top bar.
//
// Copy is keyed (see chrome/messages.ts) so French drops in without restructuring.
// The land acknowledgement and inclusion statement are CLEARLY-MARKED PLACEHOLDERS:
// they must use the ROM's official wording and are never machine-translated.
import { t } from '@/chrome/messages';

// Copyright year is computed at render — only the org name is translated copy.
const year = new Date().getFullYear();
</script>

<template>
    <footer class="bg-rom-ink mt-auto px-6 py-8 text-white/70">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 text-sm">
            <p>{{ t('footer.land_ack') }}</p>
            <p>{{ t('footer.inclusion') }}</p>
            <p class="text-xs text-white/45">© {{ year }} {{ t('footer.org') }}</p>
        </div>
    </footer>
</template>
