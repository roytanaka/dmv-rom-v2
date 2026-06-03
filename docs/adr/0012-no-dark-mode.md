---
status: accepted
date: 2026-06-03
---

# No dark mode: ship a single light identity

## Context

The Inertia + Vue starter kit ships a complete light/dark/system theming feature: a `.dark` token block and `@custom-variant dark` in `resources/css/app.css`, an Appearance toggle (`AppearanceTabs.vue`), a `useAppearance` composable that applies the `dark` class and persists the choice, an Appearance settings page, and an `initializeTheme()` call wired into app boot.

The DMV-ROM design system is a **single black-and-white identity** (see `colors_and_type.css` and its README): a white canvas, black ink, hairline borders doing the structural work, a black top bar, and a charcoal side rail *within* a light app. It never contemplates a dark theme, and it supplies **no dark values** for any token. The starter kit's `.dark` block is a generic neutral-gray theme — it is not, and never was, a ROM dark identity.

So the practical choice was not "keep vs. retune dark" — there is nothing to retune it *to*. It was "ship a half-broken generic dark theme that contradicts the brand" vs. "commit to light-only."

## Decision

**Ship a single light identity. Remove dark mode entirely** as part of the design-system foundation work (PR #1, the token PR):

- the `.dark { … }` block and `@custom-variant dark` in `app.css`
- `AppearanceTabs.vue`, the `useAppearance` composable, and the Appearance settings page
- the Appearance route and its settings-nav link
- the `initializeTheme()` boot call (`app.ts` / `ssr.ts`) and any `dark`-class handling in `app.blade.php`

We excise the whole chain rather than hiding the toggle, because a dormant toggle plus a stale off-brand `.dark` block is a live trap: a future re-enable yields a broken gray theme that looks nothing like the design.

## Reversal path

If dark mode is ever requested, the work divides cleanly, and excision preserves the part that matters:

- **The expensive, irreversible half — brand-correct dark token values — was never going to be salvageable.** The starter kit's gray theme would have been thrown out regardless; designing a ROM dark identity (what the black bar, charcoal rail, and white canvas *become* in dark, plus accessibility) is a design effort for the UI/UX committee.
- **The cheap, recoverable half — the plumbing** (toggle component, `useAppearance` composable, `dark`-class application, route) — lives in git history. It is present in full in the starter-kit code at the `staging` tip this branch forked from (`a96112a`) and every commit prior to this PR. Restoring it is a `git show` / cherry-pick of those files, not a rewrite.

Hiding would have optimized for the half that is already free to recover while failing to preserve the expensive half — so excision is strictly better.

## Consequences

- One identity to design, build, and maintain; no dark counterpart to author for every future token or component.
- The Appearance settings page disappears; settings nav loses one entry.
- A future dark mode is a deliberate, committee-led design project (new dark tokens + ADR), with the mechanical plumbing lifted from this branch's parent commit.

## References

- `resources/css/app.css` — where the `.dark` block and `dark` variant lived
- DMV-ROM design system (`colors_and_type.css`, README) — the single black-and-white identity this decision follows
- Starter-kit dark-mode implementation — git history at/before `staging` tip `a96112a`
