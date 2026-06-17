# DMV-ROM

Volunteer management for the Department of Museum Volunteers at the Royal Ontario Museum. The app supports ~500 bilingual (EN/FR) members who staff tours, programs, and committees. This document is the canonical glossary for domain terms — when language in the codebase, ADRs, or issues drifts, this file is the tiebreaker.

## Language

**Member**:
A person who belongs to the Department of Museum Volunteers and uses the app — the canonical identity of the system (the `members` table, model `Member`). A Member *has memberships* in Groups; "Member" is the person, never the join-row (see **Membership**).
_Avoid_: Volunteer (the department's name contains "Volunteers," but the person/identity term is **Member**), user (too generic; survives only as the framework concept — `Auth::user()` returns a `Member`).

**Membership**:
A Member's join-row in a **Group** — carrying that Member's status within the Group and the **role(s)** they hold there. A Member may hold many memberships; each is scoped to exactly one Group.
_Avoid_: reusing **Member** for this (the person is the Member; the relationship is the Membership). "Group member" is fine prose for a person in a Group, but is never the schema name for the identity.

**Category** (of a Member):
A Member's standing in DMV *as a whole* — Active, Sustaining, Honourary, Provisional, LOA, Resigned, etc. Lives once on the `members` record and sets the base access tier (Full / Limited / None). **Independent of the per-Group status a Membership carries** — a Member can be DMV-wide Active yet on leave from one Group.
_Avoid_: conflating with a **Membership**'s within-Group status; they are different facts set by different officers.

**Officer**:
A Member who holds at least one authority-bearing **role** (Chair, Secretary, Scheduler, Statistician, Vetting, Librarian, Content-maintainer, Treasurer) in a given **Group**. Always **per-Group** — a Member can be an officer of one Group and an ordinary member of another; there is no global officer status. See [ADR-0011](docs/adr/0011-authorization-model.md).
_Avoid_: using "officer" as an org-wide rank or as a synonym for the all-DMV grant (that is the **super-tier** — see **Admin**).

**DMV Executive**:
A specific **Group** (Kind: standing committee) — DMV's top governance body, one committee under the DMV root. Its leadership offices (President, VP1, VP2) are the Members seeded into the **super-tier**, but "Executive" names the *Group*, not the grant.
_Avoid_: equating "DMV Executive" with all-DMV access (that is the super-tier), or treating it as the org root (the root Group is DMV itself).

**Admin** (disambiguated — never use the bare word):
"Admin" means three different things; use the precise one:
- **Super-tier** — the single org-wide "see and do everything" grant, seeded with President / VP1 / VP2 (a flag on the Member; [ADR-0011](docs/adr/0011-authorization-model.md)).
- **Member administration** — managing member records and all-DMV reports; authority comes from holding a **role in the Records Group** (a stewardship), not a flag.
- **Support administration** — a maintainer administering data or impersonating for support; the explicit `initiate-support-session` permission ([ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md)), deliberately *not* super-tier.
_Avoid_: "admin" unqualified; admin-ness is a role or grant, never a membership standing.

**Directory**:
The org-wide, read-only roster of **Members** — every Member whose DMV-wide **Category** grants a listing (Active, Honourary, Sustaining, LOA), shown to any logged-in Member at `/directory`. Each row carries avatar, name, **Group** memberships, and standing — but never contact details (email/phone are a profile-only concern, gated by `viewContact`). The departed (Resigned/Withdrawn/Deceased) and the not-yet-activated (PreActive/Provisional) are excluded.
_Avoid_: treating the Directory as a member-administration or editing surface — it is read-only; managing member records is a separate authority (see **Admin** → member administration).

**Login**:
The act of authenticating into the app with email + password. The only authentication flow in the rebuild.

**Group**:
The single organizing entity in DMV. Every committee, subcommittee, program, working group, project, and event cohort is a **Group** — a named set of people, each holding **role(s) in that Group**, with one parent, a lifecycle state, and a set of capabilities it switches on (roster, meetings, documents, scheduling, content, stats). "Subcommittee" is not a separate noun: it is a **Group whose parent is another Group**. Authorization and document visibility are decided from a Member's Group memberships and the roles they carry there. See [ADR-0010](docs/adr/0010-group-model.md) for the Kind / Scope / Lifecycle axes and the capability set, and [ADR-0011](docs/adr/0011-authorization-model.md) for how authorization reads from it.

**Committee**:
A **Kind** of **Group**: a standing, org-scoped group that meets and holds documents but runs no shift scheduling (governance, operations, social). One Kind among several — not the central entity.
_Avoid_: treating "committee" as the organizing entity, or "subcommittee" as a separate noun. Both are Groups (see **Group**).

**Program**:
A **Kind** of **Group**: the member-facing operating units that run scheduling, content, and stats (docents, gallery guides, GDR, reception, special events). Distinguished from a **Committee** mainly by having the scheduling + stats capabilities turned on.

**Shift**:
A dated thing a **Member** signs up to staff — a tour, a desk slot, an event role. Has a date, time, capacity, location, and (depending on the **Program**) an optional reserved object or qualification requirement. The unit of scheduling. Programs name it differently — docents say "tour," Visitor Guides say "shift" — but **Shift** is the canonical umbrella term. A recurring **Shift** is spawned from a repeat rule; a Group may also create one-off Shifts directly.

**Sign-up**:
The record that a **Member** has taken (or been assigned) a **Shift**. Carries cancel / swap / assistant state.
_Avoid_: confusing with **Login** (authentication) — a Sign-up is *staffing a Shift*, not authenticating.

**Chrome**:
The application's persistent **frame** — the top bar, side rail, breadcrumb strip, and footer that wrap every screen and stay put while the page content changes. A UI term (after [GUI chrome](https://www.nngroup.com/articles/browser-and-gui-chrome/)), unrelated to the web browser. The Part 3 app shell *is* the chrome; product screens render inside it.
_Avoid_: confusing with the Google Chrome browser. Synonyms "shell" / "frame" are fine.

**Locale**:
The technical identifier for a language + regional convention pair. The app supports two locales: `en` (English, default) and `fr` (Canadian French, `fr-CA`). A **Member**'s `locale` column captures their saved preference. Laravel's `app()->setLocale()` consumes it.
_Avoid_: Language (the user-facing label is "Language" or "Langue," but in code and ADRs, use **Locale**).

**Chrome / content translation boundary**:
The line that decides what gets translated. **Chrome** (the frame's own words — UI labels, navigation, system emails) is translated from `lang/{en,fr}` files. **Content** (anything a **Member** authors into a DB row — **Group** names, news, document titles) is single-column and rendered **as-authored**, identical in both locales — never translated, no `_en`/`_fr` columns. See [ADR-0004](docs/adr/0004-chrome-only-translation.md).
_Avoid_: "bilingual content," "translatable field" — content is as-authored, not bilingual.

**Default locale**:
English (`en`). It is the canonical, unprefixed locale — English URLs live at the root, French URLs live under `/fr/`. See [ADR-0008](docs/adr/0008-bilingual-url-routing.md).

**PRD** (product requirements document):
A scoped chunk of product work — large enough to need its own document, small enough to be implementable. PRDs are drafted as GitHub issues labeled `prd`, then broken into implementation tickets.
_Avoid_: epic, spec, brief, initiative — all refer to the same artifact in other vocabularies; in this project, call it a PRD.

## Relationships

- A **Member** has exactly one identity (one unique email, one password)
- A **Member** may belong to zero or more **Groups**; each **Membership** carries the **role(s)** that Member holds in that Group
- A **Group** has one parent (one tree, DMV at the root); **Committee** and **Program** are Kinds of Group
- A **Program** that runs scheduling offers **Shifts**; a **Member** takes a **Shift** via a **Sign-up**
- **Login** to the app grants the **Member** their session and their authorization scope

## Example dialogue

> **Dev:** "When a **Member** logs into the app, what determines what they can do?"
> **Domain:** "Their **Group** memberships and the roles they carry in each. Authorization isn't a global role flag — it's per-Group. The one exception is DMV's top leadership, who hold an explicit org-wide grant."
>
> **Dev:** "And households where two **Members** share an inbox?"
> **Domain:** "Not supported. Each **Member** has a unique email."

## Flagged ambiguities

- _"Member" vs "Membership"_ — **Member** is the person (the identity record); a **Membership** is that Member's join-row in a Group. Keep them distinct in schema names: the identity table is `members`; the Group join-table is `group_member`, never `members` again. "Group member" is acceptable prose for a person in a Group.
- _"Volunteer"_ — superseded as the person term by **Member**. Still correct only inside the proper noun "Department of Museum Volunteers." Earlier ADR prose may still say "Volunteer"; treat **Member** as canonical wherever they conflict.
