# DMV-ROM

Volunteer management for the Department of Museum Volunteers at the Royal Ontario Museum. The app supports ~500 bilingual (EN/FR) volunteers who staff tours, programs, and committees. This document is the canonical glossary for domain terms — when language in the codebase, ADRs, or issues drifts, this file is the tiebreaker.

## Language

**Volunteer**:
A person who participates in DMV programs and uses the app. The canonical user of the system.
_Avoid_: Member (legacy schema artifact — the `Members` table holds volunteers; in product code and UI, say **Volunteer**), user (too generic outside framework code).

**Login**:
The act of authenticating into the app with email + password. The only authentication flow in the rebuild.

**Program**:
A named area of DMV activity that volunteers staff (e.g., docents, gallery guides, special tours, reception).

**Committee**:
An organizing group within DMV (membership, scheduling, social, communications, etc.). A **Volunteer** can belong to zero or more committees; committee membership drives some authorization decisions and some document visibility.

**PRD** (product requirements document):
A scoped chunk of product work — large enough to need its own document, small enough to be implementable. PRDs are drafted as GitHub issues labeled `prd`, then broken into implementation tickets.
_Avoid_: epic, spec, brief, initiative — all refer to the same artifact in other vocabularies; in this project, call it a PRD.

**Locale**:
The technical identifier for a language + regional convention pair. The app supports two locales: `en` (English, default) and `fr` (French). A **Volunteer**'s `locale` column captures their saved preference. Laravel's `app()->setLocale()` consumes it.
_Avoid_: Language (the user-facing label is "Language" or "Langue," but in code and ADRs, use **Locale**).

**Default locale**:
English (`en`). It is the canonical, unprefixed locale — English URLs live at the root, French URLs live under `/fr/`. See [ADR-0008](docs/adr/0008-bilingual-url-routing.md).

## Relationships

- A **Volunteer** has exactly one identity (one unique email, one password)
- A **Volunteer** may belong to zero or more **Committees** and participate in one or more **Programs**
- **Login** to the app grants the **Volunteer** their session and their authorization scope

## Example dialogue

> **Dev:** "When a **Volunteer** logs into the app, what determines what they can do?"
> **Domain:** "Their **Committee** memberships and any officer roles attached to those memberships. Authorization isn't a global role flag — it's per-committee."
>
> **Dev:** "And households where two **Volunteers** share an inbox?"
> **Domain:** "Not supported. Each **Volunteer** has a unique email."

## Flagged ambiguities

- _"Member"_ — the legacy database calls volunteers `Members` and the table is `dmv_Members`. In product language and new code, prefer **Volunteer**. "Member" is acceptable only when literally referring to the legacy table or column inside migration scripts.
