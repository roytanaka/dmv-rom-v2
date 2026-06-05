---
status: accepted
date: 2026-06-05
---

# App shell: contextual top-bar nav over a group-list rail

## Context

Part 3 of the design-system foundation (issue #44) builds the authenticated app shell — the frame every product screen sits inside. Three reference points disagreed on **where navigation lives**, and the disagreement had to be settled before any of it could be built:

- **The starter kit** ships a single collapsible shadcn `Sidebar` with the logo at its head and a flat page nav in the rail; there is no top-bar navigation. This is the generic SaaS-dashboard shape.
- **The DMV-ROM design system** (`chrome.jsx`) reproduces the **legacy** information architecture: a black top bar carrying a *contextual* set of section tabs, a charcoal side rail listing groups/programs, a breadcrumb strip, and a dark footer.
- **A later-written nav spec** reorganized that IA into a single sidebar *tree* — a group list whose rows each expand to a nested per-Group menu — pushing the section concerns down into the rail.

The legacy app's actual behaviour (confirmed with the maintainer) is the `chrome.jsx` model, not the spec's reorg: the side rail is the **grouping** navigation, and the **top bar is the section navigation, which changes with context** — on the Dashboard it shows personal/global items (My Calendar, Directory, Documents…); inside a Group it shows that Group's sections (About, Roster, Schedule, Publications, Meetings… plus role-gated officer items). They are two different lists with two different jobs.

The IA is acknowledged as not-great UX. But the structural-rebuild mandate is to preserve current functionality first; redesigning the IA is a later, UI/UX-committee-led concern. The authorization model this nav will eventually consume (per-Group capabilities + roles, [ADR-0010](0010-group-model.md) / [ADR-0011](0011-authorization-model.md)) is not built yet, and the product screens the tabs point at do not exist.

## Decision

Build the shell as **two distinct navigation surfaces**, faithful to `chrome.jsx`:

1. **Charcoal side rail = the grouping navigation** ("*which* Group / area"). Built on the shadcn `sidebar` primitive, restyled to the charcoal identity. Lists Home/Dashboard, then the Volunteer's groups (My Groups + a collapsible All Groups browse, subcommittees nesting), and an officer/admin zone pinned at the bottom. The rail selects the context.

2. **Black top bar = the contextual section navigation** ("*which section within* the selected context"). Hand-built. Its tab set is contextual and reflects the rail selection: Dashboard → the personal/global items; a Group → that Group's capability- and role-driven section menu. One active tab at a time (heritage-blue). The logo returns Home; the right cluster is an inert search field, an inert language button, and the avatar menu. No notification bell.

**Mobile (decision M1, chosen 2026-06-04):** the same two-layer model holds across breakpoints — ☰ opens the rail as a left sheet (the shadcn sidebar's built-in mobile sheet), and the section tabs become a horizontal scroll-strip under the top bar. No per-breakpoint IA reshaping; nothing is hidden behind an overflow menu.

**Gating is stubbed to show-all** behind a clear TODO. Every nav node carries optional `requiresCapability` / `requiresRole` (hide-if-unset). The **server** resolves a Volunteer's roles — role flags are never echoed from the client into requests (the legacy app did the opposite; we do not copy it). Real gating lands with the authorization model.

This deliberately reproduces the legacy IA and **deviates from** both alternatives on the table: the starter-kit shape (one sidebar, logo-in-rail, no contextual top nav) and the nav-spec reorg (section concerns folded into a nested sidebar tree).

## Why (the trade-off)

- **Faithful-first.** The rebuild's job is same-features, new-architecture. Enshrining a *different* IA now would couple a UX redesign to a structural port and pre-empt the committee that owns that redesign.
- **The split matches the data.** The rail is Group membership/browse; the top tabs are the selected Group's switched-on capabilities plus the Volunteer's roles there. Section menus get long (officer roles add items) and nest (subcommittees) — but the top bar only ever shows **one** Group's sections at a time and the rail handles the tree, so neither surface is overloaded. The spec's reorg would stack a deep, role-heavy menu under every group row in a single rail.
- **Reuse buys responsiveness for free.** Building the rail on the shadcn `sidebar` gets the mobile sheet, collapse, and keyboard/a11y machinery without hand-rolling it — and consumes the sidebar/sheet styling that Part 2 explicitly deferred to "real assembled usage."

## Consequences

- The starter kit's `AppHeaderLayout` variant, the GitHub/Docs links in `NavFooter`, and `PlaceholderPattern` are deleted as dead scaffolding rather than left dormant.
- The shell ships with a frontend **stub nav fixture**. Wiring it to a server-shared Inertia prop driven by real Group/role data is a later slice that lands with (or just before) the first product screen that needs it.
- The language button and search are visible but **inert** until the bilingual routing ([ADR-0008](0008-bilingual-url-routing.md)) and a search backend exist.
- The footer's land-acknowledgement and inclusion copy ship **English now, French left as a TODO** — the ROM has official French wording to use; it is not machine-translated.
- A future IA redesign becomes a deliberate, committee-led project (its own ADR), not a side effect of this port.

## References

- Issue #44 — design-system foundation PRD (names the four parts; Part 3 = app shell + nav + layout)
- DMV-ROM design system (`chrome.jsx`, `colors_and_type.css`) — the legacy-faithful shell target this follows
- [ADR-0010](0010-group-model.md), [ADR-0011](0011-authorization-model.md) — the Group + authorization model the stubbed resolver will consume
- [ADR-0008](0008-bilingual-url-routing.md) — bilingual routing the inert language button awaits
- [ADR-0012](0012-no-dark-mode.md) — sibling design-system-foundation decision (single light identity)
- `CONTEXT.md` — Volunteer / Group / Program / Committee / Locale glossary
