<script setup lang="ts">
import CodeSnippet from '@/components/CodeSnippet.vue';
import DesignNote from '@/components/DesignNote.vue';
import InputError from '@/components/InputError.vue';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { PhCaretDown, PhPlus } from '@phosphor-icons/vue';
import { ref } from 'vue';
import { snippets } from '../snippets';

// Local state for the interactive component specimens (checkbox, dropdown
// menu). These exist only to make the specimens demonstrable on the page.
const checkboxChecked = ref(true);
const notify = ref(true);
const density = ref<'comfortable' | 'compact'>('comfortable');

// Component-gallery specimens. This section is established here (PRD #44, slice
// #47) and grown by later slices. Each row renders one Button variant across the
// sm / default / lg / icon sizes plus disabled and loading states — squareness and
// the bumped size scale are verified by eye, not asserted in tests.
type ButtonVariant = NonNullable<ButtonVariants['variant']>;
const buttonRows: { variant: ButtonVariant; label: string }[] = [
    { variant: 'default', label: 'Default' },
    { variant: 'destructive', label: 'Destructive' },
    { variant: 'outline', label: 'Outline' },
    { variant: 'secondary', label: 'Secondary' },
    { variant: 'ghost', label: 'Ghost' },
    { variant: 'link', label: 'Link' },
];

// Badge tone taxonomy (slice #49). Status variants use the soft-tint pattern;
// `destructive` is deliberately soft (not upstream's solid red) so chips stay
// calm. Each row is shown with and without the leading `dot`.
type BadgeVariant = NonNullable<BadgeVariants['variant']>;
const badgeRows: { variant: BadgeVariant; label: string }[] = [
    { variant: 'default', label: 'Default' },
    { variant: 'secondary', label: 'Secondary' },
    { variant: 'info', label: 'Info' },
    { variant: 'success', label: 'Success' },
    { variant: 'warning', label: 'Warning' },
    { variant: 'destructive', label: 'Destructive' },
    { variant: 'outline', label: 'Outline' },
];

// Representative Table specimen (slice #50). The ROM listing identity is baked
// into the component defaults — a 2px black top rule, uppercase bold black heads
// on a black underline, 18px rows, hairline separators, no zebra, and a quiet
// whole-row hover. One row is flagged `selected` to show the heritage-blue wash
// (`data-[state=selected]:bg-rom-slate-50`). The above-table toolbar (filters /
// search / count / sort) is a per-screen composition, not part of the primitive.
type TableSpecimenRow = { when: string; tour: string; capacity: string; status: { variant: BadgeVariant; label: string }; selected?: boolean };
const tableRows: TableSpecimenRow[] = [
    { when: 'Wed 01 Jul · 11:00', tour: 'Museum Highlights', capacity: '2 / 4', status: { variant: 'success', label: 'Signed up' }, selected: true },
    { when: 'Thu 02 Jul · 14:00', tour: 'Egyptian Galleries', capacity: '4 / 4', status: { variant: 'warning', label: 'At capacity' } },
    { when: 'Sat 04 Jul · 10:30', tour: 'Dinosaurs & Fossils', capacity: '1 / 5', status: { variant: 'info', label: 'Open' } },
    { when: 'Sun 05 Jul · 13:00', tour: 'Bloor Street Entrance', capacity: '0 / 3', status: { variant: 'secondary', label: 'Not started' } },
];
</script>

<template>
    <section id="components" aria-labelledby="components-heading" class="scroll-mt-24">
        <h2 id="components-heading" class="text-xl font-semibold tracking-tight">Components</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Customised <code>shadcn-vue</code> primitives. Corners are square (<code>rounded-none</code>) by default and the size scale is bumped for
            the DMV’s audience, floored at 16px. Each component is added here as its slice lands.
        </p>
        <DesignNote title="Square by default — a few true circles">
            Square corners are the house identity, so every component is <code>rounded-none</code> unless roundness carries meaning. The deliberate
            <code>rounded-full</code> exceptions are small, round-by-nature marks: the <strong>avatar</strong>, the <strong>badge dot</strong>, the
            dropdown <strong>radio dot</strong>, and <strong>spinners</strong>. If a new element needs a radius, default to square and justify the
            curve.
        </DesignNote>

        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Button</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The six variants across the <code>sm</code> / <code>default</code> / <code>lg</code> / <code>icon</code> sizes, with the
            <code>disabled</code> and <code>loading</code> states. The <code>loading</code> prop shows a spinning <code>PhCircleNotch</code> (a
            documented <code>rounded-full</code> exception) and disables the button so a pending submit can't be re-triggered. The
            <code>link</code> variant carries the heritage-blue accent.
        </p>

        <div class="mt-6 space-y-5">
            <div v-for="row in buttonRows" :key="row.variant" class="flex flex-wrap items-center gap-4">
                <code class="text-muted-foreground w-24 flex-none font-mono text-xs">{{ row.variant }}</code>
                <Button :variant="row.variant" size="sm">{{ row.label }}</Button>
                <Button :variant="row.variant">{{ row.label }}</Button>
                <Button :variant="row.variant" size="lg">{{ row.label }}</Button>
                <Button :variant="row.variant" size="icon" :aria-label="`${row.label} icon`">
                    <PhPlus class="size-4" />
                </Button>
                <Button :variant="row.variant" disabled>{{ row.label }}</Button>
                <Button :variant="row.variant" loading>{{ row.label }}</Button>
            </div>
        </div>
        <CodeSnippet :code="snippets.button" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Form inputs</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            <code>Input</code> is square (<code>rounded-none</code>), 44px tall, and holds 18px text at every breakpoint. Focus is the one
            heritage-blue exception — a <code>rom-slate</code> border with a soft <code>rom-slate-50</code> glow — while the error state is driven by
            the <code>aria-invalid</code> attribute, not a custom prop. <code>Label</code> stays 16px / medium and <code>InputError</code> renders on
            the <code>destructive</code> token.
        </p>

        <div class="mt-6 grid max-w-2xl gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="ds-input-default">Default</Label>
                <Input id="ds-input-default" placeholder="Volunteer name" />
            </div>
            <div class="grid gap-2">
                <Label for="ds-input-focus">Focus</Label>
                <Input id="ds-input-focus" placeholder="Volunteer name" class="border-rom-slate ring-rom-slate-50 ring-2" />
                <p class="text-muted-foreground text-sm">Shown statically — the resting <code>:focus</code> state needs interaction to render.</p>
            </div>
            <div class="grid gap-2">
                <Label for="ds-input-error">Error</Label>
                <Input id="ds-input-error" aria-invalid="true" default-value="not-an-email" />
                <InputError message="Enter a valid email address." />
            </div>
            <div class="grid gap-2">
                <Label for="ds-input-disabled">Disabled</Label>
                <Input id="ds-input-disabled" placeholder="Volunteer name" disabled />
            </div>
        </div>
        <CodeSnippet :code="snippets.input" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Badge</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Status chips, square (<code>rounded-none</code>) like every component. The four status tones (<code>info</code>,
            <code>success</code>, <code>warning</code>, <code>destructive</code>) use the soft-tint pattern — a tinted <code>-bg</code> surface with
            the solid token for text. Note the deliberate divergence: <code>destructive</code> is soft here (calm status), unlike the solid-red
            destructive <em>Button</em>. The optional <code>dot</code> is a tone-matched true circle (<code>rounded-full</code>) — the documented
            exception to square-by-default.
        </p>

        <div class="mt-6 space-y-5">
            <div v-for="row in badgeRows" :key="row.variant" class="flex flex-wrap items-center gap-4">
                <code class="text-muted-foreground w-24 flex-none font-mono text-xs">{{ row.variant }}</code>
                <Badge :variant="row.variant">{{ row.label }}</Badge>
                <Badge :variant="row.variant" dot>{{ row.label }}</Badge>
            </div>
        </div>
        <CodeSnippet :code="snippets.badge" />
        <DesignNote variant="do" title="Do — keep the destructive badge soft">
            A <code>destructive</code> badge is a calm <em>status</em> (“Cancelled”, “Overdue”), so it uses the soft-tint pattern — the
            <code>destructive-bg</code> wash with solid-token text — like the other status tones. It reads as information, not alarm.
        </DesignNote>
        <DesignNote variant="dont" title="Don’t make it the solid red of the button">
            The destructive <em>Button</em> is solid red because it triggers an irreversible action and should stop you. Don’t carry that weight onto
            a badge: a wall of solid-red chips cries wolf and drowns out the one button that genuinely needs the alarm.
        </DesignNote>

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Table</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The ROM listing identity is baked into the defaults — a <strong>2px black top rule</strong>, uppercase bold black column labels on a 1px
            black underline (no muted-gray band), <strong>18px</strong> rows with generous padding, hairline separators, and no zebra striping. Hover
            tints the whole row a quiet neutral; the selected row (first below) takes the heritage-blue wash. The above-table toolbar (filters,
            search, count, sort) is a per-screen composition, not part of the primitive.
        </p>

        <div class="mt-6 max-w-3xl">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>When</TableHead>
                        <TableHead>Tour</TableHead>
                        <TableHead>Capacity</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in tableRows" :key="row.tour" :data-state="row.selected ? 'selected' : undefined">
                        <TableCell class="font-medium whitespace-nowrap">{{ row.when }}</TableCell>
                        <TableCell>{{ row.tour }}</TableCell>
                        <TableCell class="tabular-nums">{{ row.capacity }}</TableCell>
                        <TableCell>
                            <Badge :variant="row.status.variant">{{ row.status.label }}</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <CodeSnippet :code="snippets.table" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Card</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Square (<code>rounded-none</code>) with a 1px border and a <code>shadow-xs</code> whisper — the calm container for grouped content.
        </p>

        <div class="mt-6 max-w-sm">
            <Card>
                <CardHeader>
                    <CardTitle>Museum Highlights</CardTitle>
                    <CardDescription>Wed 01 Jul · 11:00 — Bloor Street Entrance</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-base">A one-hour walk through the museum’s signature galleries. Two of four guide spots are filled.</p>
                </CardContent>
                <CardFooter class="gap-3">
                    <Button>Sign up</Button>
                    <Button variant="outline">Details</Button>
                </CardFooter>
            </Card>
        </div>
        <CodeSnippet :code="snippets.card" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Checkbox</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Square (<code>rounded-none</code>) and bumped to <strong>20px</strong> (<code>size-5</code>) for the audience, with a Phosphor check
            glyph. Shown checked, unchecked, and disabled.
        </p>

        <div class="mt-6 space-y-4">
            <div class="flex items-center gap-3">
                <Checkbox id="ds-check-on" v-model:checked="checkboxChecked" />
                <Label for="ds-check-on">Email me when a tour I lead changes</Label>
            </div>
            <div class="flex items-center gap-3">
                <Checkbox id="ds-check-off" :default-checked="false" />
                <Label for="ds-check-off">Also send a calendar invitation</Label>
            </div>
            <div class="flex items-center gap-3">
                <Checkbox id="ds-check-disabled" :default-checked="true" disabled />
                <Label for="ds-check-disabled" class="opacity-50">Locked by your coordinator</Label>
            </div>
        </div>
        <CodeSnippet :code="snippets.checkbox" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Dropdown menu</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Square content and items with Phosphor icons, lifted on a <code>shadow-md</code> (popovers lift off the page). The radio dot stays a true
            circle.
        </p>

        <div class="mt-6">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline">
                        Options
                        <PhCaretDown class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-56" align="start">
                    <DropdownMenuLabel>Tour preferences</DropdownMenuLabel>
                    <DropdownMenuItem>Edit details</DropdownMenuItem>
                    <DropdownMenuItem>Duplicate</DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem v-model:checked="notify">Email reminders</DropdownMenuCheckboxItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel>Density</DropdownMenuLabel>
                    <DropdownMenuRadioGroup v-model="density">
                        <DropdownMenuRadioItem value="comfortable">Comfortable</DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="compact">Compact</DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <CodeSnippet :code="snippets.dropdown" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Dialog &amp; tooltip</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The dialog is square with a Phosphor <code>×</code> close glyph over the dimming overlay. The tooltip is square on its on-brand black
            surface. Both are shown by their triggers.
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <Dialog>
                <DialogTrigger as-child>
                    <Button>Cancel sign-up</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel your sign-up?</DialogTitle>
                        <DialogDescription>
                            You are leading Museum Highlights on Wed 01 Jul. Cancelling frees your spot for another volunteer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-3">
                        <DialogClose as-child>
                            <Button variant="outline">Keep my spot</Button>
                        </DialogClose>
                        <DialogClose as-child>
                            <Button variant="destructive">Cancel sign-up</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button variant="outline">Hover for a tooltip</Button>
                    </TooltipTrigger>
                    <TooltipContent>Tours lock 24 hours before they start.</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
        <CodeSnippet :code="snippets.dialog" />
        <CodeSnippet :code="snippets.tooltip" />

        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Skeleton</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Square (<code>rounded-none</code>) loading placeholders that animate while content is fetched — shown here as a card-shaped shimmer.
        </p>

        <div class="mt-6 flex max-w-sm items-center gap-4">
            <Skeleton class="size-12" />
            <div class="flex-1 space-y-2">
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-4 w-1/2" />
            </div>
        </div>
        <CodeSnippet :code="snippets.skeleton" />
    </section>
</template>
