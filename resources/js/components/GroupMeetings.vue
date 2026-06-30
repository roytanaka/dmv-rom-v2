<script setup lang="ts">
// Group Meetings tab (#190, PRD #186) — the Group's first own-data surface. Lists
// the Group's meetings (upcoming and past, newest first), each showing its date and
// time, location, the optional video link, and the agenda / minutes / report links.
//
// Read-only here: the members-only gate and the published/hidden filter are resolved
// server-side, so this surface renders exactly what it's given and computes no
// authority. Officer drafting and CRUD affordances land in #193. Meeting title,
// description, and location are member-authored content, rendered as-authored; the
// link labels and everything else are translated chrome (ADR-0004).
import TextLink from '@/components/TextLink.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type Meeting, type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { PhFileText, PhMapPin, PhVideoCamera } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';

defineProps<{ meetings: Meeting[] }>();

const page = usePage<SharedData>();
const formatDateTime = (iso: string) => new Intl.DateTimeFormat(page.props.locale, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <div class="flex flex-col gap-4">
        <Card v-for="meeting in meetings" :key="meeting.id">
            <CardHeader>
                <CardTitle class="text-rom-ink text-lg">{{ meeting.title }}</CardTitle>
                <p class="text-muted-foreground text-sm">{{ formatDateTime(meeting.held_at) }}</p>
            </CardHeader>
            <CardContent class="flex flex-col gap-3">
                <p v-if="meeting.description" class="text-rom-ink text-base whitespace-pre-line">{{ meeting.description }}</p>

                <p v-if="meeting.location" class="text-muted-foreground flex items-center gap-1.5 text-sm">
                    <PhMapPin class="size-4 shrink-0" />
                    {{ meeting.location }}
                </p>

                <p v-if="meeting.video_url" class="text-sm">
                    <TextLink :href="meeting.video_url" class="inline-flex items-center gap-1.5">
                        <PhVideoCamera class="size-4 shrink-0" />
                        {{ trans('group.meetings.video') }}
                    </TextLink>
                </p>

                <ul v-if="meeting.links.length" class="flex flex-wrap gap-x-4 gap-y-1.5 text-sm">
                    <li v-for="link in meeting.links" :key="`${link.kind}-${link.url}`">
                        <TextLink :href="link.url" class="inline-flex items-center gap-1.5">
                            <PhFileText class="size-4 shrink-0" />
                            {{ trans(`group.meetings.link.${link.kind}`) }}
                        </TextLink>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <p v-if="!meetings.length" class="text-muted-foreground py-12 text-center text-base">{{ trans('group.meetings.empty') }}</p>
    </div>
</template>
