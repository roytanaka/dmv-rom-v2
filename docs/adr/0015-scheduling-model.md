---
status: accepted
date: 2026-05-30
accepted: 2026-06-07
---

# Scheduling model: one scheduling capability, with per-Group strategies

> **Amendment (2026-08-09, [ADR-0021](0021-scheduling-first-pass.md)).** The core-entity list below is **superseded** by the first-pass model, which the wayfinder map [#323](https://github.com/roytanaka/dmv-rom-v2/issues/323) settled against the full legacy schema. Four changes: a **`Schedule`** container — the entity this ADR never named — now owns every Shift by mandatory FK; **Catalog** is renamed **`ShiftKind`** and scoped per Group; the **Repeat rule** is **retired entirely** (bulk writing replaces it — no pattern object, no recurrence column, nothing stored); and **Shift** loses `location`, its freeze/confirm state, and its qualification flag, gaining an integer `capacity` and an `audience`. The _group booking_ and _guest manifest_ strategies are **deferred** — booking is a capability orthogonal to scheduling, not a Group shape. This ADR's architecture (one capability per Group, strategies attach to a core) is unchanged. See ADR-0021.

## Context

Scheduling is the largest single domain in the rebuilt app, and it is not one feature. The legacy system implements several distinct per-committee scheduling shapes — tour sign-up, desk-shift claim/swap, event-role staffing, and a collection-object-reservation shape — each historically built and maintained separately, with its own tables and its own admin screens.

Underneath the variety, the data reveals a single shape: a handful of weekly patterns generate tens of thousands of individually dated, separately signable events, with presentation and per-program rules layered on top. In [ADR-0010](0010-group-model.md) terms a Program is just a **Group with the scheduling capability switched on**, so "per-committee scheduling" is really **the scheduling capability, attached per Group** — which points at a shared core with per-Group extensions rather than one rigid schema or N rebuilt-from-scratch variants.

Fixing the model once, here, is cheaper than re-deciding it inside every scheduling feature.

## Decision

Build scheduling as **one capability**, attached to the Groups that enable it, with **per-Group strategy extensions**. The core models what every shape shares; committee-specific behaviour attaches as strategies (the same capability-scoping principle as the roles in [ADR-0011](0011-authorization-model.md)).

**Core entities — a recurring calendar event, no special vocabulary:** _(the four bullets below are superseded — see the amendment banner and [ADR-0021](0021-scheduling-first-pass.md) for the shipped shape.)_

- _**(Added by [ADR-0021](0021-scheduling-first-pass.md))**_ **Schedule** — the container this list was missing: a **named date range** owned by one Group, in one of two states (`draft` / `published`). A calendar month is the common case, not a separate type. Every Shift carries a mandatory `schedule_id`; there are no orphan Shifts.
- **Shift** — the dated thing a volunteer signs up for: date, time, ~~capacity/limit, location, freeze/confirm state, and (where a strategy adds them) optional reserved objects or a qualification-required flag~~ _(Amended: an integer **`capacity`** — a Shift is a **slot**, not N seat-rows; **one** descriptive axis (`shift_kind_id`), so **no `location`**; **no state of its own** — it inherits the Schedule's; **no qualification flag** — a requirement hangs off the **kind**; plus an **`audience`** (`group` / `open`) that makes cross-Group participation a read filter.)_ Programs name it differently ("tour," "shift," "trip"); **Shift** is the umbrella term.
- **Sign-up** — links one volunteer to one Shift; ~~carries cancel / swap / assistant state~~ _(Amended: it carries **no** state. Cancel is deletion inside a window — until the Shift starts, with no deadline; swap and assistants are out of the first pass. A unique constraint on (`shift_id`, `member_id`) stops one Member taking two seats of one slot; overlapping Shifts are deliberately unchecked.)_ A Sign-up may be self-service or created by an officer assigning a volunteer.
- ~~**Repeat rule** _(optional)_ — "this kind of shift, every \<day\> at \<time\>," which spawns dated Shifts.~~ _**Retired 2026-08-09 ([ADR-0021](0021-scheduling-first-pass.md)).**_ No stored recurrence of any kind: no pattern object, no recurrence column, no series. A Scheduler gets **bulk-create Shifts** and **bulk-place a Member**, each a form that writes N ordinary rows, reports what it skipped, and forgets it ever ran — with a symmetric bulk undo on the same filter. This ADR's "a handful of weekly patterns generate tens of thousands of dated events" reading of the data is correct about legacy and is _why_ a generator was assumed; the first pass writes the rows directly instead. Legacy's own standing pattern has to be manually reverted after every one-off month, which is the argument against storing it.
- ~~**Catalog**~~ — **renamed `ShiftKind`** _([ADR-0021](0021-scheduling-first-pass.md))_ — the small per-Group list of "kinds of shift" that Shifts point at (nullable). "Catalog" is already generic in this repo three times over (content catalog, role catalog, persona catalogue), it named the container rather than the row, and in a museum it means the collection record. A **qualification requirement hangs off the kind**, never off the dated Shift — the shape is decided and nothing is built.

**Built once against the core:** the member side (view / sign up / cancel / swap), the admin side (build the schedule, set limits, freeze sign-ups, assign volunteers, name assistants), and the daily reminder pipeline.

**Per-Group strategies attach to the core:**

- **Qualification gating** — only vetted volunteers may take a Shift.
- **Swap** — volunteers trade Shifts.
- **Event linkage** — Shifts tied to an event, with cross-Group reminder suppression (skip a reminder where another Group already covers the shift) and an RSVP flow.
- **Object reservation** — a volunteer's **Sign-up** reserves specific collection object(s) from an inventory, with a **hard constraint that the same object cannot be double-booked across overlapping shifts**. Multiple volunteers may share one date/time slot, each reserving their own objects, so the reserved objects attach to the **Sign-up** and the uniqueness/overlap constraint ranges **across overlapping Sign-ups** on (object, date, time-range) — not over a single-claimant Shift.
- **Group booking** — an external client books a **one-off** Shift; an officer **assigns** volunteers to it via Sign-ups, and the Shift carries client / order / billing fields. This is the core's "assign volunteers" admin action plus booking fields, not a second relationship. _(**Deferred 2026-08-09**, [ADR-0021](0021-scheduling-first-pass.md) / [#333](https://github.com/roytanaka/dmv-rom-v2/issues/333). Two refinements: booking is a **capability** that switches on independently, not a per-Group strategy — five Groups have it, two of them squarely shift-shaped — and the client half is **not** "booking fields on the Shift" but a customer record with a date on it, none of which is staffing. The staffing half needs nothing: it is a Schedule with the Shifts it needs.)_
- **Guest manifest** — a one-off Shift carries **external, non-volunteer attendees** imported from a file that is **both a document and a data source**, plus a per-event coordinator. Additive; attaches to the Shift. _(**Deferred 2026-08-09**, [ADR-0021](0021-scheduling-first-pass.md) / [#333](https://github.com/roytanaka/dmv-rom-v2/issues/333); rides with group booking.)_

A **membership attribute**, not a strategy: a short-notice **availability** flag marks a volunteer as available for fill-ins — a boolean on Group membership, not a scheduling entity. Publishing a schedule as a downloadable document is the **document capability** ([ADR-0003](0003-document-storage-architecture.md)), not scheduling.

## Considered alternatives

- **One unified model.** A single shape parameterised by Group and type. Rejected: a lowest-common-denominator schema contorts to fit object reservation and the event-suppression rules.
- **Preserve per-Group variants.** Each Group rebuilt 1:1. Rejected: it re-implements the shared admin UI and reminder pipeline per Group — and the data shows the core (catalog → repeat rule → dated shift → sign-up) is the _same_ shape every Group needs.
- **Flat dated shifts only, no repeat rule.** Tempting as the simplest model. Rejected by the data: a handful of weekly patterns generate tens of thousands of dated events — real recurrence; a flat-only model forces hand-entry of every dated shift or re-deriving the pattern elsewhere. The repeat rule stays — but **optional**, so one-off shifts cost nothing.
- **Defer and let the first scheduling feature set the pattern implicitly.** Rejected: the shapes diverge enough (object reservation, suppression, qualification gating) that an implicit pattern from one Group would mis-fit the others.
- **Model object-reservation programs entirely separately, outside the core.** Kept as a _fallback_ if object reservation proves to strain the core — not the starting position.

## Consequences

- **The shared admin UI and the daily reminder pipeline are built once.** The reminder pipeline is a per-Group opt-in — model that opt-in as a flag on the Group.
- **Migration is a fan-in.** Many per-Group scheduling tables collapse to a few core tables (~~Shifts, Sign-ups, Repeat rules, Catalog~~ _(Amended 2026-08-09: `schedules`, `shifts`, `shift_kinds`, `sign_ups` — **no repeat-rule table**. Ten Groups, and the count is deliberately not recorded as a decision: which Groups schedule is data, not a list — see [ADR-0021](0021-scheduling-first-pass.md).)_).
- **Object double-booking is a hard requirement** — expressible as a uniqueness/overlap constraint on object × time across overlapping Sign-ups, not dropped as an edge case.
- **New strategy instances are additive.** A paused program that resumes, or a brand-new program, attaches its strategy to the core without reshaping it.
- **Reminder deep-links** (the "click to sign in" links in emails) are replaced with single-use tokens per [ADR-0001](0001-authentication-and-identity.md).
- **Authorization** is by [ADR-0011](0011-authorization-model.md): the schedule-admin gate is the per-Group **Scheduler** role (capability-scoped to scheduling).
- **Bilingual:** the core's user-facing strings must be translatable.

## References

- [ADR-0021](0021-scheduling-first-pass.md) — the first-pass model (`Schedule` / `Shift` / `ShiftKind` / `Sign-up`); refines this ADR's core-entity list and defers two of its strategies
- [ADR-0001](0001-authentication-and-identity.md) — single-use token replacement for auto-login links
- [ADR-0003](0003-document-storage-architecture.md) — explicit FKs, no premature abstraction; the document capability
- [ADR-0010](0010-group-model.md) — Group model (scheduling is one capability a Group enables)
- [ADR-0011](0011-authorization-model.md) — authorization (the per-Group Scheduler role gates admin actions)
