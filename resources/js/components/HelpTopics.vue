<script setup lang="ts">
// The Help topics list beside a Help article (#619, PRD #615, ADR-0025). Every
// published section, each a Collapsible: the current section opens, the current
// article is highlighted, and the other sections open and close. Inside a section the
// overview row leads ("Overview"), then the task articles grouped under their Required
// role — the badge's role labels joined by "or". Titles
// and hrefs arrive resolved at the request locale (ADR-0008); the rows follow the
// settings-sidebar pattern (ghost Buttons, `bg-muted` on the current one).
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useLocalizedHref } from '@/composables/useLocalizedHref';
import { cn } from '@/lib/utils';
import { type HelpTopic } from '@/types';
import { Link } from '@inertiajs/vue3';
import { PhCaretDown } from '@phosphor-icons/vue';
import { trans } from 'laravel-vue-i18n';

defineProps<{ topics: HelpTopic[] }>();

const localizeHref = useLocalizedHref();

function roleLabel(requires: string[]): string {
    return requires.map((role) => trans(`help.required_role.role.${role}`)).join(` ${trans('help.required_role.or')} `);
}

function rowClass(current: boolean): string {
    return cn('h-auto w-full justify-start py-1.5 text-left whitespace-normal', current && 'bg-muted font-semibold');
}
</script>

<template>
    <nav :aria-label="trans('help.topics.title')" class="flex flex-col gap-3">
        <TextLink :href="localizeHref('/help')" class="text-sm">{{ trans('help.topics.all') }}</TextLink>

        <div class="flex flex-col gap-1">
            <Collapsible v-for="topic in topics" :key="topic.key" :default-open="topic.current" class="group/topic">
                <CollapsibleTrigger as-child>
                    <Button variant="ghost" class="h-auto w-full justify-between py-1.5 text-left whitespace-normal">
                        {{ topic.label }}
                        <PhCaretDown class="transition-transform group-data-[state=open]/topic:rotate-180" aria-hidden="true" />
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <ul class="flex flex-col gap-0.5 pt-0.5 pb-2 pl-3">
                        <li v-if="topic.overview">
                            <Button variant="ghost" :class="rowClass(topic.overview.current)" as-child>
                                <Link :href="topic.overview.href" :aria-current="topic.overview.current ? 'page' : undefined">
                                    {{ trans('help.topics.overview') }}
                                </Link>
                            </Button>
                        </li>
                        <li v-for="group in topic.groups" :key="group.requires.join(',')" class="flex flex-col gap-0.5">
                            <p v-if="group.requires.length" class="eyebrow px-4 pt-2">{{ roleLabel(group.requires) }}</p>
                            <ul class="flex flex-col gap-0.5">
                                <li v-for="article in group.articles" :key="article.slug">
                                    <Button variant="ghost" :class="rowClass(article.current)" as-child>
                                        <Link :href="article.href" :aria-current="article.current ? 'page' : undefined">
                                            {{ article.title }}
                                        </Link>
                                    </Button>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </CollapsibleContent>
            </Collapsible>
        </div>
    </nav>
</template>
