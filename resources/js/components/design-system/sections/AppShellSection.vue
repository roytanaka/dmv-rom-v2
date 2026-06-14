<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { groupMenus, zoneA } from '@/chrome/fixture';
import { trans } from 'laravel-vue-i18n';
import {
    PhBuildings,
    PhCaretDown,
    PhChartBar,
    PhList,
    PhMagnifyingGlass,
    PhSignOut,
    PhTranslate,
    PhUserCircle,
    PhUsersThree,
} from '@phosphor-icons/vue';

// App-shell ("Chrome") documentation specimens. The Part 3 shell is stateful and
// contextual, so it is documented here with static fragments + prose — not embedded
// live (a live nav inside a page about the nav would be confusing and would forfeit
// clean breakpoint/state demos). The two contextual tab sets read the REAL chrome
// fixture through the i18n bridge (`trans()`), so the labels match the running app
// and demonstrate that the top-bar section nav changes by context: Zone A on the
// Dashboard, else the active Group's Menu. The active section is fixed here for the
// static specimen.
const shellZoneATabs = zoneA.map((node, i) => ({ label: trans(node.labelKey), active: i === 0 }));
const shellGroupTabs = (groupMenus.docents ?? []).map((node) => ({
    label: trans(node.labelKey),
    // "Schedule" is the illustrative current section (mirrors ADR-0013's example).
    active: node.labelKey === 'section.docents.schedule',
}));
</script>

<template>
    <section id="app-shell" aria-labelledby="app-shell-heading" class="scroll-mt-24">
        <h2 id="app-shell-heading" class="text-xl font-semibold tracking-tight">App shell</h2>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The persistent <strong>Chrome</strong> from Part 3 — the frame that wraps every authenticated screen: a full-width black top bar over a
            row of the charcoal grouping rail and the white content canvas, a slim breadcrumb strip between bar and canvas, and the dark institutional
            footer bracketing the bottom. It is the biggest thing Part 3 built, and it renders nowhere else on this reference page.
        </p>
        <p class="text-muted-foreground mt-3 max-w-2xl text-sm">
            The shell is <em>stateful and contextual</em> — which is why it is documented here with static fragments and prose, not a live embedded
            mini-shell. Nesting the live nav inside a page <em>about</em> the nav would be confusing and would forfeit clean breakpoint and state
            demos. For the same reason this page is deliberately <strong>not</strong> wrapped in <code>AppLayout</code>: it stays standalone and
            English-only. The fragments below are illustrative reconstructions built from the live tokens and the real chrome fixture — the assembled
            components live in <code>AppSidebarLayout</code>.
        </p>

        <!-- Anatomy ─────────────────────────────────────────────────────────── -->
        <h3 class="text-muted-foreground mt-8 text-sm font-semibold tracking-wide uppercase">Anatomy</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            A schematic of the regions and how they stack — full-width top bar above, then the fixed rail beside the scrolling canvas, with the
            breadcrumb strip and footer banding the content.
        </p>

        <div class="border-border mt-4 max-w-3xl overflow-hidden border">
            <div class="bg-rom-ink flex h-10 items-center px-3 text-xs font-medium text-white/80">Top bar — black, full width, sticky</div>
            <div class="flex h-56">
                <div class="bg-sidebar text-sidebar-foreground flex w-40 flex-none items-start p-3 text-xs">Grouping rail</div>
                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="bg-rom-slate-50 text-muted-foreground border-border flex h-8 items-center border-b px-3 text-xs">
                        Breadcrumb strip
                    </div>
                    <div class="bg-background text-muted-foreground flex flex-1 items-start p-3 text-xs">Content canvas (white)</div>
                    <div class="bg-rom-ink flex h-12 items-center px-3 text-xs text-white/60">Footer</div>
                </div>
            </div>
        </div>

        <!-- Top bar ─────────────────────────────────────────────────────────── -->
        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Top bar</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            Left: the <code>☰</code> rail trigger and the ROM/DMV wordmark (the SVG asset, never live text), linking home. Centre: the
            <strong>contextual section nav</strong> — a tab strip whose set <em>changes by context</em>, with exactly one active tab in the
            heritage-blue accent (<code>rom-slate-300</code>) carrying the bottom-border cue. Right: the avatar menu (search moved into the rail; the
            EN/FR control moved into the avatar menu, per ADR-0013 — no inert bar buttons, no notification bell).
        </p>

        <div class="bg-rom-ink mt-4 flex h-16 items-stretch gap-3 px-4 text-white">
            <div class="flex shrink-0 items-center gap-2">
                <span class="flex size-9 items-center justify-center text-white/80"><PhList class="size-5" /></span>
                <BrandLogo variant="white" class="hidden h-8 w-auto sm:block" />
            </div>
            <nav class="flex min-w-0 flex-1 items-stretch gap-1 overflow-hidden" aria-label="Section (illustrative)">
                <span
                    v-for="tab in shellZoneATabs"
                    :key="tab.label"
                    :class="[
                        'flex shrink-0 items-center border-b-2 px-3 text-sm whitespace-nowrap',
                        tab.active ? 'border-rom-slate-300 text-rom-slate-300' : 'border-transparent text-white/70',
                    ]"
                >
                    {{ tab.label }}
                </span>
            </nav>
            <div class="flex shrink-0 items-center">
                <Avatar class="size-10 bg-white/20">
                    <AvatarFallback class="bg-transparent text-sm text-white">AR</AvatarFallback>
                </Avatar>
            </div>
        </div>
        <p class="text-muted-foreground mt-2 text-xs">Above — the <strong>Zone A</strong> set shown on the Dashboard, when no Group is selected.</p>

        <p class="text-muted-foreground mt-4 max-w-2xl text-sm">
            Select a Group and the same strip becomes that Group’s <strong>Menu</strong> — its capability slots, labelled per program (the same slot
            reads differently per Group). The Docents menu, for example:
        </p>
        <div class="bg-rom-ink mt-2 flex h-12 items-stretch gap-1 overflow-hidden px-4 text-white">
            <span
                v-for="tab in shellGroupTabs"
                :key="tab.label"
                :class="[
                    'flex shrink-0 items-center border-b-2 px-3 text-sm whitespace-nowrap',
                    tab.active ? 'border-rom-slate-300 text-rom-slate-300' : 'border-transparent text-white/70',
                ]"
            >
                {{ tab.label }}
            </span>
        </div>

        <!-- Responsive section nav (M1 / ADR-0013) ──────────────────────────── -->
        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Section nav — responsive</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            ADR-0013 reverses the original <strong>M1</strong> all-breakpoints scroll-strip (horizontal scrolling is undiscoverable for the DMV’s
            aging volunteers). At <code>lg</code> and up the strip shows in full (above). <strong>Below <code>lg</code></strong> the whole strip
            collapses into one full-width trigger that <em>names</em> the current section and opens a vertical list of every section.
            <strong>Below <code>sm</code></strong> the wordmark also drops (no square brand mark exists yet); the <code>☰</code> and the black bar
            anchor “home”.
        </p>
        <div class="bg-rom-ink mt-4 max-w-xs p-3">
            <button
                type="button"
                class="border-rom-slate-300/40 text-rom-slate-300 flex h-11 w-full items-center justify-between gap-2 border bg-white/5 px-3 text-base font-medium"
            >
                <span class="flex items-center gap-2 truncate"><PhList class="size-5 shrink-0 opacity-80" /> Schedule</span>
                <PhCaretDown class="size-5 shrink-0 opacity-80" />
            </button>
        </div>
        <p class="text-muted-foreground mt-2 text-xs">The collapsed below-<code>lg</code> trigger, naming the current section.</p>

        <!-- Grouping rail ───────────────────────────────────────────────────── -->
        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Grouping rail</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The charcoal rail carries the grouping nav in three zones: <strong>Zone B</strong> leads with <em>My Groups</em>, then a collapsible
            <em>All Groups</em> browse list; <strong>Zone C</strong> (officer/admin) is pinned to the bottom and appears only when its gated items
            survive. It is built on the restyled shadcn <code>sidebar</code> primitive as its substrate — offcanvas on desktop (the
            <code>☰</code> toggles it) and the primitive’s own mobile sheet as the M1 drawer on small screens — so the substrate is acknowledged
            here, not shown bare. The inert search lives in the rail header.
        </p>

        <div class="bg-sidebar text-sidebar-foreground mt-4 max-w-xs space-y-4 p-3">
            <div class="relative">
                <PhMagnifyingGlass class="text-sidebar-foreground/70 pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                <div class="bg-rom-ink border-sidebar-border text-sidebar-foreground/70 flex h-9 items-center rounded-md border pl-8 text-sm">
                    Search
                </div>
            </div>

            <div>
                <p class="text-sidebar-muted px-2 text-xs font-semibold tracking-wide uppercase">{{ trans('nav.rail.my_groups') }}</p>
                <div class="mt-1 space-y-0.5">
                    <div class="bg-sidebar-active text-sidebar-active-foreground flex h-10 items-center gap-2 px-2 text-sm">
                        <PhUsersThree class="size-4" /> Docents
                    </div>
                    <div class="flex h-10 items-center gap-2 px-2 text-sm"><PhUsersThree class="size-4" /> Gallery Interpreters</div>
                </div>
            </div>

            <div class="text-sidebar-muted flex items-center justify-between px-2 text-xs font-semibold tracking-wide uppercase">
                {{ trans('nav.rail.all_groups') }}
                <PhCaretDown class="size-4" />
            </div>

            <div class="mt-auto">
                <p class="text-sidebar-muted px-2 text-xs font-semibold tracking-wide uppercase">{{ trans('nav.rail.officer') }}</p>
                <div class="mt-1 space-y-0.5">
                    <div class="flex h-10 items-center gap-2 px-2 text-sm"><PhBuildings class="size-4" /> {{ trans('nav.officer.members') }}</div>
                    <div class="flex h-10 items-center gap-2 px-2 text-sm"><PhChartBar class="size-4" /> {{ trans('nav.officer.reports') }}</div>
                </div>
            </div>
        </div>
        <p class="text-muted-foreground mt-2 max-w-2xl text-xs">
            Active row in the heritage-blue <code>sidebar-active</code> token; inactive labels in the <code>sidebar-muted</code> support gray.
            Subcommittees nest under their parent Group, and the split row (label navigates, chevron toggles) is the rail’s pattern.
        </p>

        <!-- Breadcrumb strip ────────────────────────────────────────────────── -->
        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Breadcrumb strip</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            A slim wayfinding band between the black bar and the white canvas, on the faint <code>rom-slate-50</code> wash so it reads as its own
            strip. It is fed by each page’s <code>breadcrumbs</code> prop and renders the restyled breadcrumb primitive (the same one documented
            above). Shown here with a representative trail:
        </p>
        <div class="bg-rom-slate-50 border-border mt-4 flex h-12 max-w-3xl items-center border px-6">
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink href="#">Docents</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbLink href="#">{{ trans('section.docents.schedule') }}</BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Wed 01 Jul</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </div>

        <!-- Avatar menu ─────────────────────────────────────────────────────── -->
        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Avatar menu</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The top bar’s right slot (#69) — an avatar-only trigger suited to the dark bar, opening the user menu (moved here from the rail footer).
            Square content (ROM identity), with the avatar staying the one circle. The EN/FR language control is folded in here (ADR-0013), disabled
            until bilingual routing (ADR-0008). Shown below in its open state:
        </p>
        <div class="mt-4 flex flex-wrap items-start gap-6">
            <Avatar class="size-10 bg-white/20 ring-2 ring-black/5">
                <AvatarFallback class="bg-rom-ink/80 text-sm text-white">AR</AvatarFallback>
            </Avatar>
            <div class="bg-popover text-popover-foreground border-border w-56 border shadow-md">
                <div class="border-border flex flex-col border-b px-2 py-1.5">
                    <span class="text-sm font-medium">Alex Rivera</span>
                    <span class="text-muted-foreground text-xs">alex.rivera@example.org</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-2.5 text-sm"><PhUserCircle class="size-4" /> My Profile</div>
                <div class="text-muted-foreground flex items-center gap-2 px-2 py-2.5 text-sm opacity-60">
                    <PhTranslate class="size-4" /> Language <span class="ml-auto text-xs">EN / FR</span>
                </div>
                <div class="border-border flex items-center gap-2 border-t px-2 py-2.5 text-sm"><PhSignOut class="size-4" /> Log out</div>
            </div>
        </div>

        <!-- Footer ──────────────────────────────────────────────────────────── -->
        <h3 class="text-muted-foreground mt-10 text-sm font-semibold tracking-wide uppercase">Footer</h3>
        <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
            The dark institutional footer mirrors the top bar, bracketing the white canvas. It carries the institutional voice — the land
            acknowledgement, the DMV inclusion statement, and a copyright line. The copy is keyed for French, but the land acknowledgement and
            inclusion statement use the ROM’s official English wording and are never machine-translated.
        </p>
        <div class="bg-rom-ink mt-4 px-6 py-8 text-white/70">
            <div class="mx-auto flex max-w-5xl flex-col gap-4 text-sm">
                <p>{{ trans('institutional.land_acknowledgement') }}</p>
                <p>{{ trans('institutional.inclusion') }}</p>
                <p class="text-xs text-white/45">© 2011–2026 {{ trans('institutional.department') }}</p>
            </div>
        </div>
    </section>
</template>
