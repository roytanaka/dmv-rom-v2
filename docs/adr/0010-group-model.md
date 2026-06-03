---
status: accepted
date: 2026-05-23
accepted: 2026-06-01
---

# Group model: one entity for every committee, program, team, and cohort

## Context

DMV's organizational structure is large and varied: governance and operations committees, volunteer-facing programs, functional sub-teams, time-boxed projects, exhibition volunteer pools, a monthly committee of chairs, and federation nodes that group other committees. The legacy app expresses all of this as flat lists with no notion of *what kind* of thing each entry is or *whether it is still alive*, so the lists accumulate duplicates, test entries, and pools left "active" years after their event closed.

But the legacy is *almost* right: every one of those things is the **same underlying shape** — a named group of people, with roles, that can meet, hold documents, and has an "About Us." The problem is not a missing structure; it is one good idea expressed without the two attributes (kind, lifecycle) that keep it from rotting.

## Decision

Adopt the **Group** as the single organizing entity. Everything — committee, program, working group, project, event cohort — is a Group. "Subcommittee" is not a separate noun: it is *a Group whose parent is another Group.*

### One core entity: the Group

A Group always has a **name** and **description / About Us**, a **parent** (one hierarchy), **members** carrying **role(s) in that group**, and a **lifecycle state**. Everything else is a *capability* switched on per Group.

### Three orthogonal axes — and Kind is a stored, explicit attribute

Rather than one `type` column with many values, three small independent axes describe any group:

| Axis | Values | Answers |
|---|---|---|
| **Scope** | Organization · Program · Sub-team | *Where does it sit in the tree?* |
| **Kind** | Standing committee · Program · Working group · Project · Cohort | *What is it for?* |
| **Lifecycle** | Standing vs. Time-boxed; Active vs. Archived (+ optional start/end) | *Is it permanent? Is it still alive?* |

**Kind is an explicit stored enum, never derived from capability flags.** Kind expresses a group's *intent*; capabilities express *what is switched on right now*. They are orthogonal and may legitimately diverge — a Program with scheduling temporarily off, or a committee that runs a document library, is not a contradiction. Deriving Kind from capabilities would collapse two axes into one and make a group's identity a brittle function of its feature toggles.

The payoff: *"which groups are dead?"* becomes a **query** (`lifecycle = time-boxed AND no recent activity`), not a manual audit.

There is intentionally **no "sub-purpose" attribute** — no behaviour reads one, so it is not modelled until a concrete need appears.

### Capabilities — flags on the Group; each capability's data lives in its own tables

A Group **enables only the capabilities it needs**, from a **small, fixed set**: roster & roles (always on), meetings (agenda/minutes), document library, shift/tour scheduling, content catalog, vetting workflow, and hours & stats.

**Physical shape: one `groups` table holding core identity plus a handful of boolean/enum capability flags.** A capability is *not* a configuration blob and *not* a polymorphic per-capability config table. Each capability is a feature with **its own tables, foreign-keyed to the group** — scheduling has shifts / sign-ups / repeat rules / catalog ([ADR-0012](0012-scheduling-model.md)); documents has the library ([ADR-0003](0003-document-storage-architecture.md)); meetings has agendas / minutes; hours has its records. The group row carries only the **flag** that a feature is on; the feature's data lives where data belongs, and per-capability *settings* live on that capability's own tables. This is ADR-0003's rule applied: explicit foreign keys, no polymorphism, no premature abstraction. A `group_capabilities` pivot was considered and **deferred** — flags fit a fixed set of ~7; promote later only if settings proliferate.

Build each capability **once**; attach it per group. A new exhibition cohort is a *new row with scheduling on*, not new code.

### Relationship kinds

1. **Membership** — a person belongs to a group with **role(s)**, group-wide or scoped below the group (see *Scoped roles*).
2. **Parentage** — a group has one parent; one tree, the organization at the root. Per [ADR-0011](0011-authorization-model.md), **parentage carries structure, not authority** — leading a parent confers no authority over a child.
3. **Stewardship** — a group is **responsible for operating a system function** for the whole org (e.g. statistics, the website, member administration). A responsibility it owns, not a capability it consumes.
4. **Qualification / assignment (overlay)** — belonging to a Cohort means "**eligible to be scheduled**" for an event, not primary belonging; the scheduling capability filters to the qualified set.
5. **Derived membership** — membership **computed from a rule** with a **manual add/remove overlay**, rather than maintained by hand. **It confers no roles and no authority**, so it never enters the authorization pivot; it exists for meetings, display, and notices, and is system-computed so it cannot go stale.

### Scoped roles — a subdivision *inside a capability*, not a sub-group

A role may be **scoped to a subdivision inside a capability**, not to the Group as a whole. The demonstrated case is a docent **Section**: a slice of the docents **content catalog** (Category → Section → Tour) that groups tours and names a single **Section Head** — the *Statistician* role narrowed to one section.

The scope target is **strictly less than a Group**: no roster, no capabilities, no children, no node in the Group tree. Because the depth lives inside one capability's own structure rather than as additional Group rows, **there are no extra Groups to nest** — which is what forecloses "arbitrary nested groups." Demonstrated once; this ADR fixes the **concept and its ceiling**, and the **physical shape** is settled with the docent roster/scheduling work. This is the representation [ADR-0011](0011-authorization-model.md)'s scoped-down role rests on.

## Outliers — and how the model absorbs them

- **A monthly committee of chairs** is a real Standing committee (Meetings on) whose roster is **derived** — the current chairs and co-chairs, by rule, plus a manual overlay for guests. It confers no authority (members are there *because* they chair something elsewhere, where their authority already lives). Hand-maintaining such a list is exactly what makes it rot.
- **A federation node** (a group that groups other committees) is a **parent Group with little/no capability of its own** — a scoping node whose "members" are its child groups.
- **Event pools never closed out** are **Cohorts whose lifecycle should have flipped to Archived**. A Cohort gets an end date; reaching it prompts archival, retaining history while clearing active views.
- **Duplicates, test entries, and stale rows** are **not modelled — dropped at migration.** A rename is a rename, a superseded group is merged, a finished group is archived; the model makes duplicates hard to create.

## What this buys us

- **Cleanup is a standing query, not a one-time audit.** Lifecycle + activity make "what's dead?" answerable forever.
- **No duplicate drift.** Merge/archive replace copy-another-row.
- **New things rarely need new code.** Next exhibition = one Cohort row with scheduling on.
- **Graceful endings.** Time-boxed + Archived retains history while clearing active views.
- **Permissions get a real home.** Roles live on membership, aligning with [ADR-0011](0011-authorization-model.md).

## What this is NOT (guardrails)

- **Not a full schema.** This ADR fixes the entity, axes, capability set, and relationships; column- and table-level shape is settled with the data-model and PRD work, under ADR-0003's rules.
- **Not a generic workflow/plugin engine.** Capabilities are a small fixed set (~7), not user-defined plugins.
- **Not a mandate to over-unify.** "Everything is a Group" is a *modelling* insight, not an instruction to collapse the UI.

## Considered alternatives

- **Capabilities as a JSON config blob on the group row.** Rejected: unqueryable, no foreign keys, contradicts ADR-0003.
- **Capabilities as a polymorphic "core + per-capability config table" scheme.** Rejected as premature: each capability already owns its own FK-linked tables.
- **A `group_capabilities` pivot from day one.** Deferred: flags fit a fixed set of ~7; promote only if settings proliferate.
- **Kind derived from capability flags.** Rejected: collapses two orthogonal axes and makes identity a function of feature toggles.
- **A "sub-purpose" attribute.** Rejected/omitted: nothing reads it.
- **Sections as child Groups.** Rejected: a section has no roster, capabilities, or lifecycle; a capability-internal subdivision is strictly less than a Group and avoids arbitrary nesting.
- **A chairs' committee as a pure messaging audience (no Group), or as a hand-maintained roster.** Rejected: it is a real meeting committee (so, a Group), and a hand-maintained roster is what rots.

## Consequences

- **The Group entity and its capability set anchor the rebuild's domain model.** Authorization ([ADR-0011](0011-authorization-model.md)) and scheduling ([ADR-0012](0012-scheduling-model.md)) both state their decisions in these terms.
- **The group-membership pivot is a central table** (roles + membership status), read on nearly every authorization decision — index and cache accordingly.
- **Capabilities are built once and attached per group**, rather than reimplemented per committee.

## References

- [ADR-0001](0001-authentication-and-identity.md) — identity and the per-group role model membership builds on
- [ADR-0003](0003-document-storage-architecture.md) — explicit FKs, no polymorphism, no premature abstraction
- [ADR-0011](0011-authorization-model.md) — authorization (roles attach to this model's membership)
- [ADR-0012](0012-scheduling-model.md) — scheduling (one capability a Group enables)
