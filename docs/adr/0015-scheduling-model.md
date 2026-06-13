---
status: accepted
date: 2026-05-30
accepted: 2026-06-07
---

# Scheduling model: one scheduling capability, with per-Group strategies

## Context

Scheduling is the largest single domain in the rebuilt app, and it is not one feature. The legacy system implements several distinct per-committee scheduling shapes — tour sign-up, desk-shift claim/swap, event-role staffing, and a collection-object-reservation shape — each historically built and maintained separately, with its own tables and its own admin screens.

Underneath the variety, the data reveals a single shape: a handful of weekly patterns generate tens of thousands of individually dated, separately signable events, with presentation and per-program rules layered on top. In [ADR-0010](0010-group-model.md) terms a Program is just a **Group with the scheduling capability switched on**, so "per-committee scheduling" is really **the scheduling capability, attached per Group** — which points at a shared core with per-Group extensions rather than one rigid schema or N rebuilt-from-scratch variants.

Fixing the model once, here, is cheaper than re-deciding it inside every scheduling feature.

## Decision

Build scheduling as **one capability**, attached to the Groups that enable it, with **per-Group strategy extensions**. The core models what every shape shares; committee-specific behaviour attaches as strategies (the same capability-scoping principle as the roles in [ADR-0011](0011-authorization-model.md)).

**Core entities — a recurring calendar event, no special vocabulary:**

- **Shift** — the dated thing a volunteer signs up for: date, time, capacity/limit, location, freeze/confirm state, and (where a strategy adds them) optional reserved objects or a qualification-required flag. Programs name it differently ("tour," "shift," "trip"); **Shift** is the umbrella term.
- **Sign-up** — links one volunteer to one Shift; carries cancel / swap / assistant state. A Sign-up may be self-service or created by an officer assigning a volunteer.
- **Repeat rule** *(optional)* — "this kind of shift, every \<day\> at \<time\>," which spawns dated Shifts. **Optional**: a Group can create a one-off Shift directly, like a non-recurring calendar event. Some programs run entirely on one-off shifts with no repeat rule at all — the optionality is load-bearing, not theoretical.
- **Catalog** — the small per-Group list of "kinds of shift" that repeat rules and Shifts point at.

**Built once against the core:** the member side (view / sign up / cancel / swap), the admin side (build the schedule, set limits, freeze sign-ups, assign volunteers, name assistants), and the daily reminder pipeline.

**Per-Group strategies attach to the core:**

- **Qualification gating** — only vetted volunteers may take a Shift.
- **Swap** — volunteers trade Shifts.
- **Event linkage** — Shifts tied to an event, with cross-Group reminder suppression (skip a reminder where another Group already covers the shift) and an RSVP flow.
- **Object reservation** — a volunteer's **Sign-up** reserves specific collection object(s) from an inventory, with a **hard constraint that the same object cannot be double-booked across overlapping shifts**. Multiple volunteers may share one date/time slot, each reserving their own objects, so the reserved objects attach to the **Sign-up** and the uniqueness/overlap constraint ranges **across overlapping Sign-ups** on (object, date, time-range) — not over a single-claimant Shift.
- **Group booking** — an external client books a **one-off** Shift; an officer **assigns** volunteers to it via Sign-ups, and the Shift carries client / order / billing fields. This is the core's "assign volunteers" admin action plus booking fields, not a second relationship.
- **Guest manifest** — a one-off Shift carries **external, non-volunteer attendees** imported from a file that is **both a document and a data source**, plus a per-event coordinator. Additive; attaches to the Shift.

A **membership attribute**, not a strategy: a short-notice **availability** flag marks a volunteer as available for fill-ins — a boolean on Group membership, not a scheduling entity. Publishing a schedule as a downloadable document is the **document capability** ([ADR-0003](0003-document-storage-architecture.md)), not scheduling.

## Considered alternatives

- **One unified model.** A single shape parameterised by Group and type. Rejected: a lowest-common-denominator schema contorts to fit object reservation and the event-suppression rules.
- **Preserve per-Group variants.** Each Group rebuilt 1:1. Rejected: it re-implements the shared admin UI and reminder pipeline per Group — and the data shows the core (catalog → repeat rule → dated shift → sign-up) is the *same* shape every Group needs.
- **Flat dated shifts only, no repeat rule.** Tempting as the simplest model. Rejected by the data: a handful of weekly patterns generate tens of thousands of dated events — real recurrence; a flat-only model forces hand-entry of every dated shift or re-deriving the pattern elsewhere. The repeat rule stays — but **optional**, so one-off shifts cost nothing.
- **Defer and let the first scheduling feature set the pattern implicitly.** Rejected: the shapes diverge enough (object reservation, suppression, qualification gating) that an implicit pattern from one Group would mis-fit the others.
- **Model object-reservation programs entirely separately, outside the core.** Kept as a *fallback* if object reservation proves to strain the core — not the starting position.

## Consequences

- **The shared admin UI and the daily reminder pipeline are built once.** The reminder pipeline is a per-Group opt-in — model that opt-in as a flag on the Group.
- **Migration is a fan-in.** Many per-Group scheduling tables collapse to a few core tables (Shifts, Sign-ups, Repeat rules, Catalog).
- **Object double-booking is a hard requirement** — expressible as a uniqueness/overlap constraint on object × time across overlapping Sign-ups, not dropped as an edge case.
- **New strategy instances are additive.** A paused program that resumes, or a brand-new program, attaches its strategy to the core without reshaping it.
- **Reminder deep-links** (the "click to sign in" links in emails) are replaced with single-use tokens per [ADR-0001](0001-authentication-and-identity.md).
- **Authorization** is by [ADR-0011](0011-authorization-model.md): the schedule-admin gate is the per-Group **Scheduler** role (capability-scoped to scheduling).
- **Bilingual:** the core's user-facing strings must be translatable.

## References

- [ADR-0001](0001-authentication-and-identity.md) — single-use token replacement for auto-login links
- [ADR-0003](0003-document-storage-architecture.md) — explicit FKs, no premature abstraction; the document capability
- [ADR-0010](0010-group-model.md) — Group model (scheduling is one capability a Group enables)
- [ADR-0011](0011-authorization-model.md) — authorization (the per-Group Scheduler role gates admin actions)
