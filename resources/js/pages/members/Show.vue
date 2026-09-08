<script setup lang="ts">
// Member profile (#172, PRD #167). A read profile over the centralized
// MemberResource: the allowlist decides what `member` carries, so contact details
// simply aren't in the payload when the viewer lacks `viewContact` — the template
// renders a restricted note rather than computing authority client-side. The one
// write affordance is the Records-only no-email control (#483, ADR-0024 §9), shown
// only when the payload carries `no_email` — i.e. only to a member administrator.
import StandingBadge from '@/components/StandingBadge.vue';
import TextLink from '@/components/TextLink.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { type AudienceOption, type Recipient } from '@/emailing/composer';
import ComposerSheet from '@/emailing/ComposerSheet.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { PhEnvelopeSimple } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

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
    // Records-only: present only when the viewer may administer members (ADR-0024 §9).
    // Its presence, not a separate flag, is what gates the control below.
    no_email?: boolean;
}

const props = defineProps<{ member: Member }>();

// The no-email control is Records-only. The MemberResource omits `no_email` entirely
// for anyone who may not see it, so its mere presence is the authority signal — the
// page never recomputes who may administer members client-side.
const canManageNoEmail = computed(() => props.member.no_email !== undefined);

// A dedicated, member-administration-gated action (the server re-checks on every PUT).
// The flag is not part of any member form, so it has its own tiny form here.
const noEmailForm = useForm({ no_email: props.member.no_email ?? false });

const toggleNoEmail = (checked: boolean) => {
    noEmailForm.no_email = checked;
    noEmailForm.put(route('members.no-email-flag.update', { member: props.member.id }), {
        preserveScroll: true,
    });
};

const fullName = computed(() => `${props.member.first_name} ${props.member.last_name}`);

// Two-initial fallback shown when the member has no photo (radix swaps to the sibling
// AvatarFallback when `src` is null or fails to load), matching the Directory list.
const initials = computed(() => `${props.member.first_name.charAt(0)}${props.member.last_name.charAt(0)}`.toUpperCase());

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('directory.title'), href: route('directory') },
    { title: fullName.value, href: '#' },
]);

const hasContact = computed(() => props.member.email !== undefined || props.member.phone !== undefined);

// The Direct-message affordance (#491, ADR-0024 §6): any Member may write to one other, never
// seeing their address — the mail routes through the queue. Hidden on the viewer's own profile,
// where writing to yourself makes no sense; the server enforces the rest.
const page = usePage<SharedData>();
const isSelf = computed(() => page.props.auth.user.id === props.member.id);

const messageOpen = ref(false);

// The sheet opens as a Direct message: the fixed recipient the profile is for, and the OneMember
// Audience the server resolves to just them. The From line the recipient will see is the sender's
// own name, so the Message step names the viewer.
const recipient = computed<Recipient>(() => ({
    id: props.member.id,
    first_name: props.member.first_name,
    last_name: props.member.last_name,
    photo: props.member.photo,
    standing: props.member.standing,
}));

const directAudience = computed<AudienceOption>(() => ({
    key: 'one_member',
    parameter: null,
    label: fullName.value,
    count: 1,
}));

const senderName = computed(() => `${page.props.auth.user.first_name} ${page.props.auth.user.last_name}`);
</script>

<template>
    <Head :title="fullName" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 sm:p-6">
            <header class="flex items-center gap-4">
                <Avatar size="base">
                    <AvatarImage v-if="member.photo" :src="member.photo" :alt="fullName" />
                    <AvatarFallback>{{ initials }}</AvatarFallback>
                </Avatar>
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-rom-ink text-2xl font-semibold">{{ fullName }}</h1>
                    <StandingBadge :standing="member.standing" class="self-start" />
                </div>
                <Button v-if="!isSelf" type="button" variant="outline" size="sm" class="ml-auto gap-1.5" @click="messageOpen = true">
                    <PhEnvelopeSimple class="size-4" />
                    {{ trans('member.message', { name: member.first_name }) }}
                </Button>
            </header>

            <ComposerSheet
                v-if="!isSelf"
                v-model:open="messageOpen"
                context="member"
                :context-subject="String(member.id)"
                :group-name="senderName"
                :roster="[recipient]"
                :audience="directAudience"
                :fixed="recipient"
            />

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

                <Card v-if="canManageNoEmail">
                    <CardHeader>
                        <CardTitle class="text-sm font-semibold tracking-wide uppercase">{{ trans('member.administration') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="no-email"
                                :checked="noEmailForm.no_email"
                                :disabled="noEmailForm.processing"
                                @update:checked="toggleNoEmail"
                            />
                            <div class="flex flex-col gap-1">
                                <Label for="no-email" class="font-medium">{{ trans('member.no_email') }}</Label>
                                <p class="text-muted-foreground text-sm">{{ trans('member.no_email_help') }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
