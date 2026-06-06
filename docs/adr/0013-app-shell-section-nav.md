---
status: accepted
date: 2026-06-06
---

# App-shell section nav: a labeled dropdown on small screens, not a scroll-strip

## Context

The Part 3 app shell (PRD #65) has two navigation surfaces: a charcoal **rail** (grouping nav — which Group/area) and a black **top bar** (contextual *section* nav — which section within the active context). This ADR is about the **top-bar section nav only** (`components/SectionTabs.vue`). It does **not** decide the broader two-layer rail/top-bar topology — PRD #65 deliberately left that unsettled ("an app-shell-topology ADR was drafted and withdrawn … Revisit once the shape settles"), and it stays unsettled here.

PRD #65 shipped decision **M1**: the section tabs are a **horizontal scroll-strip across every breakpoint** — each section reachable by scrolling sideways, nothing behind a "More" menu. M1 explicitly *rejected* overflow/"More" patterns on the grounds that they "hide most sections."

Real use surfaced two problems, one a bug and one a design fault:

1. **Layout bug.** In the top bar the strip is `flex-1 min-w-0`, squeezed between the left cluster (☰ + the wide wordmark) and the right cluster (an *inert* EN/FR button + avatar). At ~375px the strip gets ~65px and its two `2rem` edge-fade masks eat half of that — roughly one partial tab visible, the other 6–9 sections behind a horizontal scroll **with no scrollbar on touch**.

2. **Design fault for this audience.** Even at full width, a horizontal scroll-strip is a poor fit for the DMV's aging volunteers: horizontal scrolling is among the least-discoverable web interactions, and worse without a visible scrollbar. PRD #65's own readability user stories (≥15px persistent UI text, comfortable hit targets, "aging eyesight") sit awkwardly against a nav whose contents are mostly invisible and silently scrollable.

The two problems are separable — the squeeze is fixable independently — but the design fault is what reopens M1. A throwaway prototype (`resources/js/pages/prototype/`, local-only `prototype/section-nav` route) compared three patterns at real widths against a faithful repro of the chrome.

## Decision

**Replace the all-breakpoints scroll-strip with a labeled section dropdown below a single breakpoint, keeping the strip where it fits.** This reverses M1.

- **`lg` and up (≥1024px):** the horizontal tab strip, unchanged. The full Group Menu (~10 items) fits; this preserves the legacy-faithful "tabs across the top" with one active tab in heritage-blue.
- **Below `lg`:** the whole strip collapses into **one full-width trigger that names the current section** (`Schedule ▾`), opening a vertical list of **every** section — large full-width rows, icons, a consistent set every time.

Supporting top-bar decisions resolved alongside (so the small-screen bar is `☰ · [Section ▾] · avatar`):

- **Logo on small screens.** No square brand *mark* exists — only the wide ~4.8:1 wordmark. **Drop the wordmark below `sm`** (the ☰ and the black square identity anchor "home"); a compact square mark is a flagged follow-up for the ROM brand team, **not** a blocker, and lands as a one-line `<BrandMark>`-below-`sm` swap. We do **not** fake a mark by cropping the wordmark.
- **Language (EN/FR) toggle.** Removed as a standalone top-bar button **at every width** and folded into the **avatar/user menu** (disabled until bilingual routing, ADR-0008). The avatar menu is the width-independent home for a preference control; this keeps the bar permanently clean rather than re-litigated when ADR-0008 lands.
- **Corners** stay square (ROM identity — `Button`, `DropdownMenu*` are already `rounded-none`); the **avatar stays a circle** (it depicts a person, not a chrome surface).

## Considered options

- **A — Fixed scroll-strip (M1, the baseline).** Rejected: the horizontal-scroll discoverability fault is the whole reason for this ADR, and on touch there is no scrollbar to hint at the hidden sections.
- **B — Priority-plus / progressive overflow** (visible tabs + a growing `More (k) ▾`). This was the initial proposal. Rejected: it earns its keep only in the tablet band (~600–1000px); at phone width it degenerates to ~2 arbitrary tabs + "More (8)" — i.e. *most sections hidden behind a vague label*, the exact outcome M1 set out to avoid — while costing the most code (per-tab width measurement, recompute on resize **and** on locale change since French labels are longer, plus keeping the active tab out of the overflow).
- **C — Labeled section dropdown (chosen).** Always names where you are, one obvious tap to a consistent full list with large targets, far less code (a media query + the existing `DropdownMenu`, no measurement). Trade-off accepted: in the tablet band it hides all sections behind one tap where B would show ~5 live tabs — judged the right trade for an aging audience that values discoverability and consistency over density.

## Consequences

- **This supersedes M1 from PRD #65.** A reader who finds #65 stating "M1 = scroll-strip, rejected the 'More' menu" and then sees a dropdown in `SectionTabs.vue` should read this ADR: the reversal is deliberate, and C is a *labeled current-section* dropdown, not the unlabeled overflow "More" #65 rejected.
- `SectionTabs.vue` gains two render modes gated by one `lg` media query; `TopBar.vue` drops the standalone EN/FR button and the wordmark hides below `sm`; the EN/FR control moves into `UserMenuContent`/`TopBarUser`.
- **Contrast was checked against the pure-black bar (`--rom-ink: #000000`) for the aging audience:** active tab `--rom-slate-300` (#93a6b3) = **8.35:1**, inactive `white/70` = **9.90:1**, hover white = 21:1 — all AAA. The only sub-AA value is the decorative "/FR" hint (3.66:1), now a *disabled* menu item (WCAG-exempt). Note the active tab is *lower* contrast than inactive, so the "selected" cue rides on the heritage-blue hue **and the bottom border**, not luminance — the underline must always render.
- A square logo mark for `< sm` is outstanding (sourced from the ROM brand team, never machine-cropped). Until then phones show no logo, only ☰ + the black bar.
- The prototype (`resources/js/pages/prototype/` + the local-only route) is deleted once C is folded into the real components.

## References

- PRD #65 — Part 3 app shell; decision **M1** (this ADR reverses it)
- `resources/js/components/SectionTabs.vue`, `TopBar.vue` — the surfaces changed
- ADR-0008 (bilingual URL routing) — gates the now-deferred language toggle
- `resources/js/pages/prototype/NOTES.md` — the prototype that produced this decision (to be deleted)
