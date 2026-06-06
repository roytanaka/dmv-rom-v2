<script setup lang="ts">
import CopyButton from '@/components/CopyButton.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
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
import { NavigationMenu, NavigationMenuItem, NavigationMenuLink, NavigationMenuList } from '@/components/ui/navigation-menu';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import DesignSystemLayout from '@/layouts/DesignSystemLayout.vue';
import { Head } from '@inertiajs/vue3';
import { PhCaretDown, PhCaretRight, PhPlus } from '@phosphor-icons/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Local state for the interactive component specimens (checkbox, dropdown
// menu). These exist only to make the specimens demonstrable on the page.
const checkboxChecked = ref(true);
const notify = ref(true);
const density = ref<'comfortable' | 'compact'>('comfortable');

// In-page wayfinding. The single source of truth for the sticky section rail;
// each entry's `id` is the stable, deep-linkable anchor on the matching
// `<section>` element below. Append here as later slices add sections.
const sections = [
    { id: 'brand', label: 'Brand' },
    { id: 'neutral', label: 'Neutral' },
    { id: 'semantic', label: 'Semantic' },
    { id: 'sidebar', label: 'Sidebar' },
    { id: 'typography', label: 'Typography' },
    { id: 'radius', label: 'Radius' },
    { id: 'elevation', label: 'Elevation' },
    { id: 'spacing', label: 'Spacing' },
    { id: 'components', label: 'Components' },
    { id: 'avatar', label: 'Avatar' },
    { id: 'breadcrumb', label: 'Breadcrumb' },
    { id: 'collapsible', label: 'Collapsible' },
    { id: 'navigation-menu', label: 'Navigation menu' },
    { id: 'separator', label: 'Separator' },
    { id: 'sheet', label: 'Sheet' },
] as const;

// Scrollspy: highlight the rail entry for the section currently in view. The
// rootMargin biases the "active" band to the upper third of the viewport so a
// heading lights up as it reaches the top, not the middle.
const activeSection = ref<string>(sections[0].id);
let observer: IntersectionObserver | undefined;

onMounted(() => {
    const hash = window.location.hash.slice(1);
    if (sections.some((s) => s.id === hash)) activeSection.value = hash;

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) activeSection.value = entry.target.id;
            }
        },
        { rootMargin: '-10% 0px -80% 0px' },
    );

    for (const section of sections) {
        const el = document.getElementById(section.id);
        if (el) observer.observe(el);
    }
});

onBeforeUnmount(() => observer?.disconnect());

// Colour-token specimens. Each swatch fills its chip from the live CSS variable
// (`var(--token)`) so the page renders the actual token value — the palette is
// verified here by eye, not asserted in tests (PRD #37 / #41). Hex labels mirror
// the values in resources/css/app.css; `note` flags a token that resolves
// through another (e.g. --info → --rom-slate) or its role.
type Swatch = { token: string; hex: string; note?: string };
type SwatchGroup = { id: string; title: string; desc: string; swatches: Swatch[] };

const colorGroups: SwatchGroup[] = [
    {
        id: 'brand',
        title: 'Brand',
        desc: 'ROM black & white with one muted heritage-blue accent (after rom.on.ca). Black for actions, heritage blue for wayfinding — neither is decorative.',
        swatches: [
            { token: '--rom-ink', hex: '#000000', note: 'Black — text, logo, top bar, buttons' },
            { token: '--background', hex: '#ffffff', note: 'White — the canvas does the work' },
            { token: '--rom-gray', hex: '#f5f5f5', note: 'Support gray — quiet fills, zebra rows' },
            { token: '--rom-slate', hex: '#516d80', note: 'Heritage blue — the single accent' },
        ],
    },
    {
        id: 'neutral',
        title: 'Neutral',
        desc: 'A clean greyscale on a pure-white canvas, anchored on ROM’s #8b8b85 support gray. Never tinted by the accent.',
        swatches: [
            { token: '--background', hex: '#ffffff' },
            { token: '--foreground', hex: '#1a1a1a' },
            { token: '--card', hex: '#ffffff' },
            { token: '--card-foreground', hex: '#1a1a1a' },
            { token: '--popover', hex: '#ffffff' },
            { token: '--popover-foreground', hex: '#1a1a1a' },
            { token: '--primary', hex: '#000000' },
            { token: '--primary-foreground', hex: '#ffffff' },
            { token: '--secondary', hex: '#f5f5f5' },
            { token: '--secondary-foreground', hex: '#1a1a1a' },
            { token: '--muted', hex: '#f5f5f5' },
            { token: '--muted-foreground', hex: '#686868' },
            { token: '--accent', hex: '#efefef' },
            { token: '--accent-foreground', hex: '#1a1a1a' },
            { token: '--border', hex: '#e3e3e3' },
            { token: '--input', hex: '#e3e3e3' },
            { token: '--ring', hex: '#8b8b85' },
        ],
    },
    {
        id: 'semantic',
        title: 'Semantic / status',
        desc: 'Status & feedback — one colour each, used sparingly. Each pairs with a -foreground (text on the solid colour) and a soft -bg tint for banners and badges.',
        swatches: [
            { token: '--success', hex: '#1b7a4b' },
            { token: '--success-foreground', hex: '#ffffff' },
            { token: '--success-bg', hex: '#e8f4ed' },
            { token: '--warning', hex: '#f08c00' },
            { token: '--warning-foreground', hex: '#422700' },
            { token: '--warning-bg', hex: '#fff3e0' },
            { token: '--destructive', hex: '#d4322b' },
            { token: '--destructive-foreground', hex: '#ffffff' },
            { token: '--destructive-bg', hex: '#fbeae9' },
            { token: '--info', hex: '#516d80', note: 'via --rom-slate' },
            { token: '--info-bg', hex: '#eef1f4', note: 'via --rom-slate-50' },
        ],
    },
    {
        id: 'sidebar',
        title: 'Sidebar',
        desc: 'A neutral charcoal rail beneath the black top bar. Inactive labels use the support gray; the active row takes the slate accent.',
        swatches: [
            { token: '--sidebar', hex: '#262626' },
            { token: '--sidebar-foreground', hex: '#f4f3f2' },
            { token: '--sidebar-muted', hex: '#8b8b85', note: 'inactive label' },
            { token: '--sidebar-accent', hex: '#383838', note: 'hover row' },
            { token: '--sidebar-active', hex: '#516d80', note: 'active row — via --rom-slate' },
            { token: '--sidebar-active-foreground', hex: '#ffffff' },
            { token: '--sidebar-border', hex: '#383838' },
        ],
    },
];

// Type-scale specimens. Each row renders sample text at the live `text-*`
// utility (size + coupled line-height from the remapped --text-* ramp) and
// labels it with the px size and role. Values mirror resources/css/app.css —
// the scale is verified by eye here, not asserted in tests (PRD #37).
type TypeStep = { name: string; px: number; lh: string; class: string; role: string };

const typeScale: TypeStep[] = [
    { name: 'text-5xl', px: 54, lh: '1.2', class: 'text-5xl', role: 'hero / login title' },
    { name: 'text-4xl', px: 42, lh: '1.2', class: 'text-4xl', role: 'page title (h1)' },
    { name: 'text-3xl', px: 34, lh: '1.2', class: 'text-3xl', role: 'page heading (h2)' },
    { name: 'text-2xl', px: 28, lh: '1.35', class: 'text-2xl', role: 'section heading (h3)' },
    { name: 'text-xl', px: 23, lh: '1.35', class: 'text-xl', role: 'card title (h4)' },
    { name: 'text-lg', px: 20, lh: '1.55', class: 'text-lg', role: 'lead paragraph' },
    { name: 'text-base', px: 18, lh: '1.55', class: 'text-base', role: 'body — the default' },
    { name: 'text-sm', px: 15, lh: '1.55', class: 'text-sm', role: 'secondary UI, cells' },
    { name: 'text-xs', px: 13, lh: '1.55', class: 'text-xs', role: 'micro-labels, timestamps' },
];

// Element specimens — bare <h1>–<h4> and the .eyebrow / .caption helpers prove
// the @layer base element defaults render correctly with no utility classes.
const headingSpecimens = [
    { tag: 'h1', spec: '42 · 700', text: 'Museum Volunteers' },
    { tag: 'h2', spec: '34 · 700', text: 'July, 2026 Sign up' },
    { tag: 'h3', spec: '28 · 600', text: 'Scheduled Tours' },
    { tag: 'h4', spec: '23 · 600', text: 'Card title' },
] as const;

// The radius ramp is kept from the starter kit intentionally (ADR / PRD #37):
// the square identity is applied at the component level in PR #2, so this
// proportional scale stays as a working escape hatch for deliberate rounding.
// Each step maps to a `rounded-*` utility via the `--radius-*` theme tokens.
const radiusSteps = [
    { name: 'sm', class: 'rounded-sm' },
    { name: 'md', class: 'rounded-md' },
    { name: 'lg', class: 'rounded-lg' },
    { name: 'xl', class: 'rounded-xl' },
] as const;

// Elevation ramp. Each tile applies the live `shadow-*` utility so the scale
// stays correct by construction rather than being a hand-maintained table. Roles
// reflect actual usage across the customised components (card/button → xs, etc.).
// Full class strings are written as literals so Tailwind's scanner keeps them.
const shadowSteps = [
    { class: 'shadow-xs', role: 'cards & buttons — resting' },
    { class: 'shadow-sm', role: 'sidebar, raised buttons' },
    { class: 'shadow-md', role: 'dropdowns, tooltips' },
    { class: 'shadow-lg', role: 'dialogs, sheets, menus' },
] as const;

// Spacing scale. The bar width comes from the live `w-*` utility (4px base step),
// so the specimen renders the real spacing rather than asserting px in a table.
// `px` is the resolved value at the 16px root, shown only as a label.
const spacingSteps = [
    { class: 'w-0.5', px: 2 },
    { class: 'w-1', px: 4 },
    { class: 'w-2', px: 8 },
    { class: 'w-3', px: 12 },
    { class: 'w-4', px: 16 },
    { class: 'w-6', px: 24 },
    { class: 'w-8', px: 32 },
    { class: 'w-10', px: 40 },
    { class: 'w-12', px: 48 },
    { class: 'w-16', px: 64 },
    { class: 'w-20', px: 80 },
    { class: 'w-24', px: 96 },
] as const;

// Component-gallery specimens. This section is established here (PRD #44, slice
// #47) and grown by later slices. Each row renders one Button variant across the
// sm / default / lg / icon sizes plus a disabled state — squareness and the
// bumped size scale are verified by eye, not asserted in tests.
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

// Avatar image specimen. A self-contained SVG data URI (no network) so the
// image branch always renders in the gallery, visibly distinct from the
// initials fallback shown alongside it.
const avatarImage = `data:image/svg+xml,${encodeURIComponent(
    "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' fill='#516d80'/><circle cx='32' cy='24' r='12' fill='#ffffff'/><path d='M12 58a20 20 0 0 1 40 0z' fill='#ffffff'/></svg>",
)}`;

// NavigationMenu specimen — mirrors the top-bar section nav (SectionTabs): a
// horizontal strip of section links on the black bar, exactly one active in the
// heritage-blue accent with the bottom-border cue. Shown on a `bg-rom-ink`
// surface because that is the context the section nav actually lives in.
const navMenuItems = [
    { label: 'Dashboard', active: false },
    { label: 'Tours', active: true },
    { label: 'Volunteers', active: false },
    { label: 'Reports', active: false },
];
</script>

<template>
    <Head title="Design System" />

    <DesignSystemLayout>
        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight">Design System</h1>
            <p class="text-muted-foreground mt-2 text-base">
                Internal reference for the DMV-ROM design tokens. Specimens are added here as each token slice lands.
            </p>
        </header>

        <div class="lg:grid lg:grid-cols-[10rem_minmax(0,1fr)] lg:gap-x-12 xl:gap-x-16">
            <!-- Sticky in-page wayfinding: a horizontal scroller below lg, a vertical rail at lg+. -->
            <nav
                aria-label="Page sections"
                class="bg-background/90 supports-[backdrop-filter]:bg-background/70 sticky top-0 z-20 -mx-6 mb-8 border-b backdrop-blur md:-mx-10 lg:top-12 lg:z-0 lg:mx-0 lg:mb-0 lg:self-start lg:border-0 lg:bg-transparent lg:backdrop-blur-none"
            >
                <ul class="flex gap-x-1 overflow-x-auto px-6 py-3 md:px-10 lg:flex-col lg:gap-x-0 lg:gap-y-0.5 lg:overflow-visible lg:px-0 lg:py-0">
                    <li v-for="section in sections" :key="section.id" class="flex-none">
                        <a
                            :href="`#${section.id}`"
                            :aria-current="activeSection === section.id ? 'location' : undefined"
                            :class="[
                                'block border-l-2 px-3 py-1.5 text-sm whitespace-nowrap transition-colors',
                                activeSection === section.id
                                    ? 'border-rom-slate text-rom-slate font-medium'
                                    : 'text-muted-foreground hover:text-foreground border-transparent',
                            ]"
                        >
                            {{ section.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="min-w-0">
                <section
                    v-for="group in colorGroups"
                    :key="group.id"
                    :id="group.id"
                    :aria-labelledby="`${group.id}-heading`"
                    class="mb-12 scroll-mt-24"
                >
                    <h2 :id="`${group.id}-heading`" class="text-xl font-semibold tracking-tight">{{ group.title }}</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">{{ group.desc }}</p>

                    <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        <li v-for="swatch in group.swatches" :key="`${group.id}-${swatch.token}`" class="border-border overflow-hidden border">
                            <div class="h-20 w-full" :style="{ background: `var(${swatch.token})` }" />
                            <div class="px-3 py-2.5">
                                <div class="flex flex-col items-start gap-0.5">
                                    <CopyButton :value="swatch.token"
                                        ><code class="text-foreground text-sm font-medium">{{ swatch.token }}</code></CopyButton
                                    >
                                    <CopyButton :value="swatch.hex"
                                        ><span class="text-muted-foreground font-mono text-xs uppercase">{{ swatch.hex }}</span></CopyButton
                                    >
                                </div>
                                <p v-if="swatch.note" class="text-muted-foreground mt-1.5 text-xs leading-snug">{{ swatch.note }}</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section id="typography" aria-labelledby="type-heading" class="mb-12 scroll-mt-24">
                    <h2 id="type-heading" class="text-xl font-semibold tracking-tight">Typography</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        One native system-font stack, no webfont. The scale is sized up for the DMV’s retiree volunteers — body is
                        <strong>18px</strong>; persistent UI text never drops below <strong>15px</strong>. Each step carries a coupled line-height
                        (body 1.55, headings 1.2–1.35).
                    </p>

                    <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Scale</h3>
                    <ul class="border-border mt-4 divide-y border-y">
                        <li v-for="step in typeScale" :key="step.name" class="flex items-baseline gap-6 py-3">
                            <code class="text-muted-foreground w-24 flex-none font-mono text-xs">{{ step.name }}</code>
                            <span :class="[step.class, 'min-w-0 flex-1 truncate font-medium']">The quick brown fox</span>
                            <span class="text-muted-foreground flex-none font-mono text-xs">{{ step.px }}px · {{ step.lh }}</span>
                            <span class="text-muted-foreground hidden w-44 flex-none text-xs sm:block">{{ step.role }}</span>
                        </li>
                    </ul>

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Headings</h3>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Bare <code>h1</code>–<code>h4</code> elements — the <code>@layer base</code> defaults, no utility classes.
                    </p>
                    <dl class="mt-4 space-y-4">
                        <div v-for="h in headingSpecimens" :key="h.tag" class="flex items-baseline gap-6">
                            <component :is="h.tag" class="min-w-0 flex-1">{{ h.text }}</component>
                            <span class="text-muted-foreground flex-none font-mono text-xs">{{ h.tag }} · {{ h.spec }}</span>
                        </div>
                    </dl>

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Body &amp; helpers</h3>
                    <div class="mt-4 max-w-2xl space-y-4">
                        <p class="text-lg">Lead paragraph — sign up for a maximum of three tours this month.</p>
                        <p class="text-base">
                            Body copy at 18px with a roomy 1.55 line-height. Enter your email and password below to log in to your account, then
                            choose the tours you would like to lead.
                        </p>
                        <p class="text-sm">Secondary UI · 01 WED @ 11:00 — Museum Highlights</p>
                        <p class="text-xs">Micro-label · last updated 2 hours ago</p>
                        <p class="eyebrow">Department of Museum Volunteers</p>
                        <p class="caption">Caption — sized at 15px in the muted foreground tone.</p>
                    </div>
                </section>

                <section id="radius" aria-labelledby="radius-heading" class="mb-12 scroll-mt-24">
                    <h2 id="radius-heading" class="text-xl font-semibold tracking-tight">Radius</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        The proportional <code>sm/md/lg/xl</code> ramp from the <code>--radius</code> scale, kept intentionally.
                    </p>

                    <ul class="mt-6 flex flex-wrap gap-6">
                        <li v-for="step in radiusSteps" :key="step.name" class="flex flex-col items-center gap-2">
                            <div :class="['bg-muted border-border size-20 border', step.class]" />
                            <span class="text-muted-foreground text-sm">{{ step.name }}</span>
                        </li>
                    </ul>
                </section>

                <section id="elevation" aria-labelledby="elevation-heading" class="mb-12 scroll-mt-24">
                    <h2 id="elevation-heading" class="text-xl font-semibold tracking-tight">Elevation</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        The shadow ramp the components lift on, rendered live from the <code>shadow-*</code> utilities — the calm container stays at
                        <code>shadow-xs</code>; popovers and dialogs lift progressively. Shadows are deliberately soft on the white canvas.
                    </p>

                    <ul class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                        <li v-for="step in shadowSteps" :key="step.class" class="flex flex-col items-center gap-3">
                            <div :class="['bg-card border-border size-20 border', step.class]" />
                            <div class="text-center">
                                <CopyButton :value="step.class"
                                    ><code class="text-foreground text-sm font-medium">{{ step.class }}</code></CopyButton
                                >
                                <p class="text-muted-foreground mt-1 text-xs leading-snug">{{ step.role }}</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section id="spacing" aria-labelledby="spacing-heading" class="mb-12 scroll-mt-24">
                    <h2 id="spacing-heading" class="text-xl font-semibold tracking-tight">Spacing</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        The 4px base step (<code>0.25rem</code>) and the utilities built on it. Each bar is sized by the live
                        <code>w-*</code> utility, so the scale is correct by construction — the px label is the resolved value at the 16px root.
                    </p>

                    <ul class="mt-6 space-y-2">
                        <li v-for="step in spacingSteps" :key="step.class" class="flex items-center gap-4">
                            <CopyButton :value="step.class" class="w-16 flex-none"
                                ><code class="text-muted-foreground font-mono text-xs">{{ step.class }}</code></CopyButton
                            >
                            <div :class="['bg-rom-slate h-4 flex-none', step.class]" />
                            <span class="text-muted-foreground font-mono text-xs">{{ step.px }}px</span>
                        </li>
                    </ul>
                </section>

                <section id="components" aria-labelledby="components-heading" class="scroll-mt-24">
                    <h2 id="components-heading" class="text-xl font-semibold tracking-tight">Components</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Customised <code>shadcn-vue</code> primitives. Corners are square (<code>rounded-none</code>) by default and the size scale is
                        bumped for the DMV’s audience, floored at 15px. Each component is added here as its slice lands.
                    </p>

                    <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Button</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        The six variants across the <code>sm</code> / <code>default</code> / <code>lg</code> / <code>icon</code> sizes, with a
                        disabled state. The <code>link</code> variant carries the heritage-blue accent.
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
                        </div>
                    </div>

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Form inputs</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        <code>Input</code> is square (<code>rounded-none</code>), 44px tall, and holds 18px text at every breakpoint. Focus is the one
                        heritage-blue exception — a <code>rom-slate</code> border with a soft <code>rom-slate-50</code> glow — while the error state
                        is driven by the <code>aria-invalid</code> attribute, not a custom prop. <code>Label</code> stays 15px / medium and
                        <code>InputError</code> renders on the <code>destructive</code> token.
                    </p>

                    <div class="mt-6 grid max-w-2xl gap-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ds-input-default">Default</Label>
                            <Input id="ds-input-default" placeholder="Volunteer name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ds-input-focus">Focus</Label>
                            <Input id="ds-input-focus" placeholder="Volunteer name" class="border-rom-slate ring-rom-slate-50 ring-2" />
                            <p class="text-muted-foreground text-sm">
                                Shown statically — the resting <code>:focus</code> state needs interaction to render.
                            </p>
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

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Badge</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Status chips, square (<code>rounded-none</code>) like every component. The four status tones (<code>info</code>,
                        <code>success</code>, <code>warning</code>, <code>destructive</code>) use the soft-tint pattern — a tinted
                        <code>-bg</code> surface with the solid token for text. Note the deliberate divergence: <code>destructive</code> is soft here
                        (calm status), unlike the solid-red destructive <em>Button</em>. The optional <code>dot</code> is a tone-matched true circle
                        (<code>rounded-full</code>) — the documented exception to square-by-default.
                    </p>

                    <div class="mt-6 space-y-5">
                        <div v-for="row in badgeRows" :key="row.variant" class="flex flex-wrap items-center gap-4">
                            <code class="text-muted-foreground w-24 flex-none font-mono text-xs">{{ row.variant }}</code>
                            <Badge :variant="row.variant">{{ row.label }}</Badge>
                            <Badge :variant="row.variant" dot>{{ row.label }}</Badge>
                        </div>
                    </div>

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Table</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        The ROM listing identity is baked into the defaults — a <strong>2px black top rule</strong>, uppercase bold black column
                        labels on a 1px black underline (no muted-gray band), <strong>18px</strong> rows with generous padding, hairline separators,
                        and no zebra striping. Hover tints the whole row a quiet neutral; the selected row (first below) takes the heritage-blue wash.
                        The above-table toolbar (filters, search, count, sort) is a per-screen composition, not part of the primitive.
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

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Card</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Square (<code>rounded-none</code>) with a 1px border and a <code>shadow-xs</code> whisper — the calm container for grouped
                        content.
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

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Checkbox</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Square (<code>rounded-none</code>) and bumped to <strong>20px</strong> (<code>size-5</code>) for the audience, with a Phosphor
                        check glyph. Shown checked, unchecked, and disabled.
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

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Dropdown menu</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Square content and items with Phosphor icons, lifted on a <code>shadow-md</code> (popovers lift off the page). The radio dot
                        stays a true circle.
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

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Dialog &amp; tooltip</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        The dialog is square with a Phosphor <code>×</code> close glyph over the dimming overlay. The tooltip is square on its
                        on-brand black surface. Both are shown by their triggers.
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

                    <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Skeleton</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Square (<code>rounded-none</code>) loading placeholders that animate while content is fetched — shown here as a card-shaped
                        shimmer.
                    </p>

                    <div class="mt-6 flex max-w-sm items-center gap-4">
                        <Skeleton class="size-12" />
                        <div class="flex-1 space-y-2">
                            <Skeleton class="h-4 w-3/4" />
                            <Skeleton class="h-4 w-1/2" />
                        </div>
                    </div>
                </section>

                <section id="avatar" aria-labelledby="avatar-heading" class="mb-12 scroll-mt-24">
                    <h2 id="avatar-heading" class="text-xl font-semibold tracking-tight">Avatar</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        The one deliberately-round element on the page — a true circle (<code>rounded-full</code>) against the square component
                        identity. It shows an image when one loads and falls back to initials when it doesn’t, across the
                        <code>sm</code> / <code>base</code> / <code>lg</code> sizes.
                    </p>

                    <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Sizes (image)</h3>
                    <div class="mt-4 flex flex-wrap items-end gap-6">
                        <div v-for="size in ['sm', 'base', 'lg'] as const" :key="size" class="flex flex-col items-center gap-2">
                            <Avatar :size="size">
                                <AvatarImage :src="avatarImage" alt="Volunteer portrait" />
                                <AvatarFallback>RT</AvatarFallback>
                            </Avatar>
                            <code class="text-muted-foreground font-mono text-xs">{{ size }}</code>
                        </div>
                    </div>

                    <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Initials fallback</h3>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        With no image source the fallback renders the volunteer’s initials on the secondary surface.
                    </p>
                    <div class="mt-4 flex flex-wrap items-end gap-6">
                        <div v-for="size in ['sm', 'base', 'lg'] as const" :key="size" class="flex flex-col items-center gap-2">
                            <Avatar :size="size">
                                <AvatarFallback>RT</AvatarFallback>
                            </Avatar>
                            <code class="text-muted-foreground font-mono text-xs">{{ size }}</code>
                        </div>
                    </div>
                </section>

                <section id="breadcrumb" aria-labelledby="breadcrumb-heading" class="mb-12 scroll-mt-24">
                    <h2 id="breadcrumb-heading" class="text-xl font-semibold tracking-tight">Breadcrumb</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        A wayfinding trail with Phosphor caret separators. Long trails collapse the middle to an ellipsis (the Phosphor
                        <code>⋯</code> glyph), keeping the root and the current page in view. The current page is the unlinked
                        <code>BreadcrumbPage</code>.
                    </p>

                    <div class="mt-6 space-y-6">
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">Dashboard</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">Volunteers</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbPage>Roy Tanaka</BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>

                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">Dashboard</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbEllipsis />
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbLink href="#">Tours</BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbPage>Museum Highlights</BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </section>

                <section id="collapsible" aria-labelledby="collapsible-heading" class="mb-12 scroll-mt-24">
                    <h2 id="collapsible-heading" class="text-xl font-semibold tracking-tight">Collapsible</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        A disclosure that toggles a region open and closed. The trigger caret rotates on
                        <code>data-[state=open]</code>; the content animates its height. Shown open by default below.
                    </p>

                    <div class="mt-6 max-w-md">
                        <Collapsible v-slot="{ open }" default-open>
                            <CollapsibleTrigger
                                class="border-border hover:bg-accent flex w-full items-center justify-between gap-2 border px-4 py-3 text-left text-sm font-medium transition-colors"
                            >
                                <span>What should I bring on tour day?</span>
                                <PhCaretRight class="size-4 shrink-0 transition-transform" :class="open ? 'rotate-90' : ''" />
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <div class="border-border border border-t-0 px-4 py-3 text-sm">
                                    Wear your volunteer badge and comfortable shoes. Arrive fifteen minutes before your tour starts to check in at the
                                    Bloor Street entrance.
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </section>

                <section id="navigation-menu" aria-labelledby="navigation-menu-heading" class="mb-12 scroll-mt-24">
                    <h2 id="navigation-menu-heading" class="text-xl font-semibold tracking-tight">Navigation menu</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        Shown in the form the top-bar section nav uses it — a horizontal strip of section links on the black bar, with exactly one
                        active in the heritage-blue accent (<code>rom-slate-300</code>) carrying the bottom-border cue. Rendered on the
                        <code>rom-ink</code> surface because that is the context it lives in.
                    </p>

                    <div class="bg-rom-ink mt-6 flex px-4 py-3">
                        <NavigationMenu>
                            <NavigationMenuList class="gap-1">
                                <NavigationMenuItem v-for="item in navMenuItems" :key="item.label">
                                    <NavigationMenuLink
                                        href="#navigation-menu"
                                        :active="item.active"
                                        :aria-current="item.active ? 'page' : undefined"
                                        :class="[
                                            'flex items-center border-b-2 px-3 py-1 text-sm whitespace-nowrap transition-colors',
                                            item.active
                                                ? 'border-rom-slate-300 text-rom-slate-300'
                                                : 'border-transparent text-white/70 hover:text-white',
                                        ]"
                                    >
                                        {{ item.label }}
                                    </NavigationMenuLink>
                                </NavigationMenuItem>
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>
                </section>

                <section id="separator" aria-labelledby="separator-heading" class="mb-12 scroll-mt-24">
                    <h2 id="separator-heading" class="text-xl font-semibold tracking-tight">Separator</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        A hairline rule on the <code>border</code> token, in both orientations. An optional <code>label</code> centres text on the
                        rule for “or”-style dividers.
                    </p>

                    <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Horizontal</h3>
                    <div class="mt-4 max-w-md">
                        <p class="text-sm">Museum Highlights</p>
                        <Separator class="my-4" />
                        <p class="text-sm">Egyptian Galleries</p>
                        <Separator class="my-4" label="or" />
                        <p class="text-sm">Dinosaurs &amp; Fossils</p>
                    </div>

                    <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Vertical</h3>
                    <div class="text-muted-foreground mt-4 flex h-6 items-center gap-3 text-sm">
                        <span>Tours</span>
                        <Separator orientation="vertical" />
                        <span>Volunteers</span>
                        <Separator orientation="vertical" />
                        <span>Reports</span>
                    </div>
                </section>

                <section id="sheet" aria-labelledby="sheet-heading" class="mb-12 scroll-mt-24">
                    <h2 id="sheet-heading" class="text-xl font-semibold tracking-tight">Sheet</h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                        A panel that slides in over a dimming overlay, from any of the four edges. It lifts on <code>shadow-lg</code> and carries the
                        Phosphor <code>×</code> close glyph. Each trigger below opens the sheet from its named side.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-4">
                        <Sheet v-for="side in ['top', 'right', 'bottom', 'left'] as const" :key="side">
                            <SheetTrigger as-child>
                                <Button variant="outline" class="capitalize">{{ side }}</Button>
                            </SheetTrigger>
                            <SheetContent :side="side">
                                <SheetHeader>
                                    <SheetTitle>Filter tours</SheetTitle>
                                    <SheetDescription>
                                        Narrow the listing by date, gallery, and remaining capacity. Opened from the
                                        <strong>{{ side }}</strong> edge.
                                    </SheetDescription>
                                </SheetHeader>
                                <SheetFooter class="mt-6 gap-3">
                                    <SheetClose as-child>
                                        <Button variant="outline">Cancel</Button>
                                    </SheetClose>
                                    <SheetClose as-child>
                                        <Button>Apply filters</Button>
                                    </SheetClose>
                                </SheetFooter>
                            </SheetContent>
                        </Sheet>
                    </div>
                </section>
            </div>
        </div>
    </DesignSystemLayout>
</template>
