# DMV-ROM

Volunteer management for the Department of Museum Volunteers at the Royal Ontario Museum. The app supports ~500 bilingual (EN/FR) volunteers who staff tours, programs, and committees. This document is the canonical glossary for domain terms — when language in the codebase, ADRs, or issues drifts, this file is the tiebreaker.

## Language

**Volunteer**:
A person who participates in DMV programs and uses the app. The canonical user of the system.
_Avoid_: Member (legacy schema artifact — the `Members` table holds volunteers; in product code and UI, say **Volunteer**), user (too generic outside framework code).

**Login**:
The act of authenticating into the app with email + password. The only authentication flow in the rebuild.

**Group**:
The single organizing entity in DMV. Every committee, subcommittee, program, working group, project, and event cohort is a **Group** — a named set of people, each holding **role(s) in that Group**, with one parent, a lifecycle state, and a set of capabilities it switches on (roster, meetings, documents, scheduling, content, stats). "Subcommittee" is not a separate noun: it is a **Group whose parent is another Group**. Authorization and document visibility are decided from a Volunteer's Group memberships and the roles they carry there. See [ADR-0010](docs/adr/0010-group-model.md) for the Kind / Scope / Lifecycle axes and the capability set, and [ADR-0011](docs/adr/0011-authorization-model.md) for how authorization reads from it.

**Committee**:
A **Kind** of **Group**: a standing, org-scoped group that meets and holds documents but runs no shift scheduling (governance, operations, social). One Kind among several — not the central entity.
_Avoid_: treating "committee" as the organizing entity, or "subcommittee" as a separate noun. Both are Groups (see **Group**).

**Program**:
A **Kind** of **Group**: the volunteer-facing operating units that run scheduling, content, and stats (docents, gallery guides, GDR, reception, special events). Distinguished from a **Committee** mainly by having the scheduling + stats capabilities turned on.

**Shift**:
A dated thing a **Volunteer** signs up to staff — a tour, a desk slot, an event role. Has a date, time, capacity, location, and (depending on the **Program**) an optional reserved object or qualification requirement. The unit of scheduling. Programs name it differently — docents say "tour," Visitor Guides say "shift" — but **Shift** is the canonical umbrella term. A recurring **Shift** is spawned from a repeat rule; a Group may also create one-off Shifts directly.

**Sign-up**:
The record that a **Volunteer** has taken (or been assigned) a **Shift**. Carries cancel / swap / assistant state.
_Avoid_: confusing with **Login** (authentication) — a Sign-up is *staffing a Shift*, not authenticating.

**Chrome**:
The application's persistent **frame** — the top bar, side rail, breadcrumb strip, and footer that wrap every screen and stay put while the page content changes. A UI term (after [GUI chrome](https://www.nngroup.com/articles/browser-and-gui-chrome/)), unrelated to the web browser. The Part 3 app shell *is* the chrome; product screens render inside it.
_Avoid_: confusing with the Google Chrome browser. Synonyms "shell" / "frame" are fine.

**Locale**:
The technical identifier for a language + regional convention pair. The app supports two locales: `en` (English, default) and `fr` (French). A **Volunteer**'s `locale` column captures their saved preference. Laravel's `app()->setLocale()` consumes it.
_Avoid_: Language (the user-facing label is "Language" or "Langue," but in code and ADRs, use **Locale**).

**Default locale**:
English (`en`). It is the canonical, unprefixed locale — English URLs live at the root, French URLs live under `/fr/`. See [ADR-0008](docs/adr/0008-bilingual-url-routing.md).

**PRD** (product requirements document):
A scoped chunk of product work — large enough to need its own document, small enough to be implementable. PRDs are drafted as GitHub issues labeled `prd`, then broken into implementation tickets.
_Avoid_: epic, spec, brief, initiative — all refer to the same artifact in other vocabularies; in this project, call it a PRD.

## Relationships

- A **Volunteer** has exactly one identity (one unique email, one password)
- A **Volunteer** may belong to zero or more **Groups**; each membership carries the **role(s)** that Volunteer holds in that Group
- A **Group** has one parent (one tree, DMV at the root); **Committee** and **Program** are Kinds of Group
- A **Program** that runs scheduling offers **Shifts**; a **Volunteer** takes a **Shift** via a **Sign-up**
- **Login** to the app grants the **Volunteer** their session and their authorization scope

## Example dialogue

> **Dev:** "When a **Volunteer** logs into the app, what determines what they can do?"
> **Domain:** "Their **Group** memberships and the roles they carry in each. Authorization isn't a global role flag — it's per-Group. The one exception is DMV's top leadership, who hold an explicit org-wide grant."
>
> **Dev:** "And households where two **Volunteers** share an inbox?"
> **Domain:** "Not supported. Each **Volunteer** has a unique email."

## Flagged ambiguities

- _"Member"_ — the legacy database calls volunteers `Members` and the table is `dmv_Members`. In product language and new code, prefer **Volunteer**. "Member" is acceptable only when literally referring to the legacy table or column inside migration scripts.
