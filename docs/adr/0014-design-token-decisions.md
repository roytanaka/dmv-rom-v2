---
status: accepted
date: 2026-06-06
---

# Design-token decisions: rem-based type scale and a 16px persistent-text floor

## Context

The Part 1 tokens (PRD #37) and Part 2 component customisations (PRD #44) were specified in prose against a **15px** minimum for persistent UI text ("persistent UI text never below 15px", "floored at 15px"). During implementation two decisions were made that **diverged from that prose and were never recorded as decisions** — they survived only as inline comments in `resources/css/app.css` and were contradicted by the `/design-system` gallery, which still claimed 15px. A Part 4 audit (closing PRD #76) surfaced the drift: nine places across the gallery and component comments said 15px while the CSS said 16px.

This ADR records the two decisions so the source of truth is unambiguous and a future reader does not "correct" the code back toward the stale prose.

## Decision

**1. The type scale is expressed in `rem`, not `px`.**
Every `--text-*` step in `app.css` is a `rem` value against the 16px root (`--text-sm: 1rem`, `--text-base: 1.125rem`, …). Consequences intended:
- The scale honours the browser's own font-size preference (an accessibility win for the aging DMV audience).
- The entire scale can grow or shrink from a single root change, which is the hook for a **future in-app text-size control**. No such control exists yet; this keeps the door open at zero ongoing cost.

**2. The persistent-text floor is 16px (`--text-sm: 1rem`), not 15px.**
Persistent UI text — labels, table cells, secondary copy — never drops below **16px**. `text-xs` (13px) remains the sanctioned exception for *passive* micro-labels only (timestamps, table column heads, `.eyebrow`), never for content a Volunteer must read or act on. This supersedes the "15px" language in PRDs #37 and #44.

Already-decided invariants are **not** re-litigated here and keep their existing homes — this ADR only cross-references them: square corners by default (`docs/conventions.md` § Styling), Phosphor as the sole icon set (PRD #44), no dark mode ([ADR-0012](0012-no-dark-mode.md)), native system-font stack with no webfont (PRD #37).

## Consequences

- **Supersedes the "15px floor" wording in PRDs #37 and #44**, and the incidental "≥15px" reference in [ADR-0013](0013-app-shell-section-nav.md) § Context (a historical quote of #65's user stories — left as written, but read it as 16px).
- The `/design-system` gallery is the user-facing mirror of these values; its typography section states the 16px floor and the rem rationale, and links here.
- A future text-size control is *enabled* by the rem scale but is out of scope until a need is demonstrated.
- The audit's mechanical fixes (the nine 15px→16px corrections) bring the gallery and component comments into line with the CSS.

## References

- `resources/css/app.css` — the `--text-*` ramp and the rem/16px comments that were the only prior record
- `resources/js/pages/DesignSystem.vue` — the gallery typography section (mirrors these values)
- PRDs #37 (tokens) and #44 (components) — the superseded 15px prose
- ADR-0012 (no dark mode), `docs/conventions.md` § Styling (square corners), PRD #44 (Phosphor)
