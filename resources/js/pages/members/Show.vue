<script setup lang="ts">
// Member record page (#154). A thin view over the centralized MemberResource: the
// allowlist decides what `member` carries, so contact details simply aren't in the
// payload when the viewer lacks `viewContact` — the template renders a restricted
// note rather than computing authority client-side. The richer directory/profile
// UI is a later slice.
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
    groups?: MemberGroup[];
    email?: string;
    phone?: string;
}

const props = defineProps<{ member: Member }>();

// Profile renders the member as "First Last".
const fullName = computed(() => `${props.member.first_name} ${props.member.last_name}`);

const breadcrumbs: BreadcrumbItem[] = [{ title: fullName.value, href: '#' }];

// Contact is present only when the server's allowlist included it.
const hasContact = computed(() => props.member.email !== undefined || props.member.phone !== undefined);
</script>

<template>
    <Head :title="fullName" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <h1 class="text-rom-ink text-lg font-semibold">{{ fullName }}</h1>

            <section>
                <h2 class="text-rom-ink mb-3 text-sm font-semibold tracking-wide uppercase">{{ trans('member.contact') }}</h2>
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
            </section>

            <section>
                <h2 class="text-rom-ink mb-3 text-sm font-semibold tracking-wide uppercase">{{ trans('member.groups') }}</h2>
                <ul v-if="member.groups && member.groups.length" class="flex flex-col gap-1 text-sm">
                    <li v-for="group in member.groups" :key="group.slug" class="text-rom-ink">
                        {{ group.name }}
                        <span v-if="group.roles.length" class="text-muted-foreground">— {{ group.roles.join(', ') }}</span>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">{{ trans('member.no_groups') }}</p>
            </section>
        </div>
    </AppLayout>
</template>
