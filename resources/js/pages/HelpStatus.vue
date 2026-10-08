<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DesignSystemLayout from '@/layouts/DesignSystemLayout.vue';
import { Head } from '@inertiajs/vue3';

// The Help ledger at /help-status (#520, ADR-0025 §9) — the super-tier "what have I lost
// track of" view. English-only on the bare layout, like the Design System page: an
// operations screen, not member-facing chrome. The controller does all the judging; this
// screen only lays the manifest state out in one place.
interface Row {
    slug: string;
    section: string;
    status: string;
    fr: string;
    requires: string[];
    route: string | null;
    enFile: boolean;
    frFile: boolean;
    screenshotsReferenced: number;
    screenshotsPresent: number;
}

interface Props {
    counts: {
        articles: number;
        published: number;
        drafts: number;
        frenchReviewed: number;
    };
    rows: Row[];
    gaps: string[];
}

defineProps<Props>();

const summary = (counts: Props['counts']) => [
    { label: 'Articles', value: counts.articles },
    { label: 'Published', value: counts.published },
    { label: 'Drafts', value: counts.drafts },
    { label: 'French reviewed', value: counts.frenchReviewed },
];
</script>

<template>
    <Head title="Help ledger" />

    <DesignSystemLayout>
        <div class="flex flex-col gap-10">
            <header>
                <h1 class="text-2xl font-semibold">Help ledger</h1>
                <p class="text-muted-foreground mt-1 text-sm">What documentation exists, what state it is in, and which pages have none.</p>
            </header>

            <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div v-for="stat in summary(counts)" :key="stat.label" class="rounded-lg border p-4">
                    <div class="text-2xl font-semibold">{{ stat.value }}</div>
                    <div class="text-muted-foreground mt-1 text-sm">{{ stat.label }}</div>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Articles</h2>
                <div class="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Article</TableHead>
                                <TableHead class="hidden md:table-cell">Section</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="hidden md:table-cell">French</TableHead>
                                <TableHead class="hidden lg:table-cell">Required role</TableHead>
                                <TableHead class="hidden lg:table-cell">Page</TableHead>
                                <TableHead class="hidden md:table-cell">Files</TableHead>
                                <TableHead class="hidden md:table-cell">Screenshots</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in rows" :key="row.slug">
                                <TableCell>
                                    <span class="font-medium">{{ row.slug }}</span>
                                    <!-- On a phone the other columns hide; their facts ride under the name. -->
                                    <div class="text-muted-foreground mt-1 flex flex-col gap-1 text-sm md:hidden">
                                        <span>{{ row.section }}</span>
                                        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <Badge :variant="row.fr === 'reviewed' ? 'success' : 'warning'">
                                                FR {{ row.fr === 'reviewed' ? 'reviewed' : 'machine' }}
                                            </Badge>
                                            <span>
                                                <span :class="row.enFile ? '' : 'text-destructive'">EN</span>
                                                <span> · </span>
                                                <span :class="row.frFile ? '' : 'text-destructive'">FR</span>
                                            </span>
                                            <span :class="row.screenshotsPresent < row.screenshotsReferenced ? 'text-destructive' : ''">
                                                {{ row.screenshotsPresent }} / {{ row.screenshotsReferenced }} screenshots
                                            </span>
                                        </span>
                                    </div>
                                    <div class="text-muted-foreground mt-1 flex flex-col gap-1 text-sm wrap-anywhere lg:hidden">
                                        <span>{{ row.requires.length ? row.requires.join(', ') : 'Every Member' }}</span>
                                        <span v-if="row.route" class="font-mono">{{ row.route }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="hidden md:table-cell">{{ row.section }}</TableCell>
                                <TableCell>
                                    <Badge :variant="row.status === 'published' ? 'success' : 'secondary'">
                                        {{ row.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="hidden md:table-cell">
                                    <Badge :variant="row.fr === 'reviewed' ? 'success' : 'warning'">
                                        {{ row.fr === 'reviewed' ? 'reviewed' : 'machine' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="hidden lg:table-cell">
                                    <span v-if="row.requires.length">{{ row.requires.join(', ') }}</span>
                                    <span v-else class="text-muted-foreground">Every Member</span>
                                </TableCell>
                                <TableCell class="hidden lg:table-cell">
                                    <span v-if="row.route" class="font-mono text-sm">{{ row.route }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="hidden md:table-cell">
                                    <span :class="row.enFile ? '' : 'text-destructive'">EN</span>
                                    <span class="text-muted-foreground"> · </span>
                                    <span :class="row.frFile ? '' : 'text-destructive'">FR</span>
                                </TableCell>
                                <TableCell class="hidden md:table-cell">
                                    <span :class="row.screenshotsPresent < row.screenshotsReferenced ? 'text-destructive' : ''">
                                        {{ row.screenshotsPresent }} / {{ row.screenshotsReferenced }}
                                    </span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Pages without an article</h2>
                <p class="text-muted-foreground text-sm">
                    Localized page routes that no article maps yet. This list leaves out auth pages, CSV exports, and Coming Soon stubs.
                </p>
                <ul v-if="gaps.length" class="flex flex-wrap gap-2">
                    <li v-for="name in gaps" :key="name">
                        <span class="bg-muted rounded px-2 py-1 font-mono text-sm">{{ name }}</span>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">Every page has an article.</p>
            </section>
        </div>
    </DesignSystemLayout>
</template>
