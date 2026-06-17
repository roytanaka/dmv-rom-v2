<script setup lang="ts">
// Member profile (#172, PRD #167). A read profile over the centralized
// MemberResource: the allowlist decides what `member` carries, so contact details
// simply aren't in the payload when the viewer lacks `viewContact` — the template
// renders a restricted note rather than computing authority client-side. Read-only:
// no edit affordance, and no field beyond name/photo/standing/Groups.
import StandingBadge from '@/components/StandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

interface MemberGroup {
    name: string;
    slug: string;
    roles: string[];
}

interface Member {
    id: number;
    first_name: string;
    last_name: string;
    photo: string | null;
    standing: string;
    groups?: MemberGroup[];
    email?: string;
    phone?: string;
}

const props = defineProps<{ member: Member }>();

const fullName = computed(() => `${props.member.first_name} ${props.member.last_name}`);

// Two-initial fallback (no photos exist yet), matching the Directory list.
const initials = computed(() => `${props.member.first_name.charAt(0)}${props.member.last_name.charAt(0)}`.toUpperCase());

// A real Directory → {name} trail: the root links back to the roster, the leaf is
// the current page (rendered un-linked by the breadcrumb chrome).
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('directory.title'), href: route('directory') },
    { title: fullName.value, href: '#' },
]);

// Contact is present only when the server's allowlist included it.
const hasContact = computed(() => props.member.email !== undefined || props.member.phone !== undefined);
</script>

<template>
    <Head :title="fullName" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <header class="flex items-center gap-4">
                <Avatar size="base">
                    <AvatarFallback>{{ initials }}</AvatarFallback>
                </Avatar>
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-rom-ink text-2xl font-semibold">{{ fullName }}</h1>
                    <StandingBadge :standing="member.standing" class="self-start" />
                </div>
            </header>

            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('member.contact') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl v-if="hasContact" class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                            <template v-if="member.email !== undefined">
                                <dt class="text-muted-foreground">{{ trans('member.email') }}</dt>
                                <dd class="text-rom-ink">{{ member.email }}</dd>
                            </template>
                            <template v-if="member.phone !== undefined">
                                <dt class="text-muted-foreground">{{ trans('member.phone') }}</dt>
                                <dd class="text-rom-ink">{{ member.phone }}</dd>
                            </template>
                        </dl>
                        <p v-else class="text-muted-foreground text-sm">{{ trans('member.contact_restricted') }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('member.groups') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="member.groups && member.groups.length" class="flex flex-col gap-3 text-sm">
                            <li v-for="group in member.groups" :key="group.slug" class="flex flex-col gap-1.5">
                                <TextLink :href="route('groups.show', { group: group.slug })" class="font-medium">{{ group.name }}</TextLink>
                                <div v-if="group.roles.length" class="flex flex-wrap gap-1.5">
                                    <Badge v-for="role in group.roles" :key="role" variant="secondary">{{ role }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-muted-foreground text-sm">{{ trans('member.no_groups') }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
