---
status: accepted
date: 2026-06-16
accepted: 2026-06-16
---

# Authorization enforcement: hand-rolled gates/policies over the spine

> **Accepted 2026-06-16.** This ADR fixes the **mechanism** that [ADR-0011](0011-authorization-model.md) deferred ("whether a permissions package vs. hand-rolled policies enforce it is downstream"). ADR-0011 fixed the *model*; this fixes *how it is enforced in Laravel*. Two access-tier mapping details await an organizational confirm but do not block the mechanism.

## Context

[ADR-0011](0011-authorization-model.md) settled the authorization *model* — three composable layers: member `Category` → base tier, per-Group roles via the membership pivot, one super-tier grant plus the Records stewardship. [ADR-0010](0010-group-model.md) settled the Group spine the roles attach to.

The spine is **built**: `Group`, `GroupMember`, `GroupMemberRole` (closed `Role` enum + a write-time capability-gating invariant), `GroupStewardship`, and `Member` with `isAllDmv()` / `membershipIn()` / `holdsRole()` / `hasMemberAdminAuthority()`. What is **absent** is the enforcement layer: no policies, gates, `Gate::before`, `Category`→tier resolution, field-level contact gating, `can`-prop convention, or read-path strategy. Authorization is built fresh, and the posture is **deny-by-default: an action with no server-side authorization is a bug.**

## Decision

Enforce the model with **hand-rolled Laravel gates + policies + form requests reading the spine** — no permissions package. The spine already *is* the permission store; a package would bring overlapping tables to maintain or adapt, against the project's "no new package until a 3× need."

### 1. Super-tier — one short-circuit

```php
Gate::before(fn (Member $m) => $m->isAllDmv() ? true : null);
```

`true` grants, `null` falls through (never `false`). Runs ahead of every gate and policy, so the org-wide tier is never re-checked. Accepted consequences: it bypasses the sign-up floor and any hypothetical "deny everyone" policy (both non-cases or handled outside the Gate system).

### 2. Category → base tier (the floor)

A new `AccessTier` enum (`Full` / `Limited` / `None`) plus **two independent predicates**, since view-access and sign-up-ability are orthogonal:

- `Category::accessTier(): AccessTier`
- `Category::canSignUp(): bool` — the sign-up gate calls this before any role check.

Mapping: Active/Honourary/Sustaining → Full + sign-up; Provisional/PreActive → Limited (still sign-up); LOA → Full-view, no sign-up; Resigned/Withdrawn/Deceased → None. (Two values await an organizational confirm: whether Provisional may sign up, and whether a lifetime tier is wanted.)

### 3. The per-(Member, Group) resolver

```php
Member::canActAs(Role $role, Group $group): bool
```

- Folds in **Chair-implication**: Chair implies every officer role within its own Group **except `Treasurer`** (separation of duties).
- **Group-wide only:** matches role rows scoped to the whole Group, preserving the seam for [ADR-0010](0010-group-model.md)'s subdivision-scoped roles — when those land, `canActAs` matches rows with no subdivision and narrowing lives in the consuming capability's own policy. Scoped roles are **not built now**; only the seam is kept.
- Raw `holdsRole()` stays for "is literally an X" display needs; every gate/policy calls `canActAs`.

### 4. Entry points

- **Resource CRUD → policies** that resolve the owning Group and delegate to `canActAs`.
- **Group-scoped abilities → gates taking `(Member, Group)`.**
- **Mutations are structurally authorized:** each carries a Form Request whose `authorize()` is auto-invoked (default to *self*; another member requires Records/super-tier); never `return true`.
- **Reads** use `can` middleware or explicit `$this->authorize()`.

### 5. Cross-cutting permissions

- **"Can edit news" is a capability-scoped role**: an `announcements` Group capability ([ADR-0010](0010-group-model.md)) gates a **News-editor** role ([ADR-0011](0011-authorization-model.md)). News is one org-wide feed; the capability is multi-Group; each item carries a `posting_group_id`; the read is org-wide (the one capability not read group-scoped).
- **Ad-hoc grants decoupled from any Group** (e.g. `initiate-support-session`, [ADR-0009](0009-user-switching-and-support-impersonation.md)) stay **standalone gates**.
- A **generic named-permission table is not built** until a third case proves the need.

### 6. Field-level visibility — allowlist, least privilege

- A `viewContact($viewer, $target)` ability: Records + super-tier (org-wide); own-Group officers with a contact-need role (default Chair/Scheduler/Secretary, tunable).
- Enforced in **one centralized `MemberResource`** — no hand-built member arrays in controllers.
- **Allowlist, not blocklist:** every logged-in member sees only first/last name, photo (if uploaded), Groups/roles; everything else is gated by default, so new fields are private until deliberately exposed. (Consequence: an in-app, email-native member-to-member messaging path is needed as the directory no longer exposes contact details — separate PRD.)

### 7. Mass-assignment / no client-asserted authority

- Form Request `rules()` is a **whitelist**; member-facing requests omit authority fields. Pass `validated()`, never `all()`.
- Privileged toggles are **single-action controllers**, each separately gated.
- **`super_tier` removed from `$fillable`**, set only by its dedicated action; `Model::shouldBeStrict()` surfaces silent mass-assignment in dev.

### 8. Read path

- **Eager-load the member's memberships+roles once per request**, resolve in-memory; refactor `Member` helpers to read the loaded relation.
- **No cross-request permission cache** — correctness on revocation outweighs the negligible perf gain at this scale.
- Index `group_member(member_id, group_id)` and `group_member_role(group_member_id, role)`; controllers eager-load policy-traversed relations.

### 9. UI hints

- **`can` props are UI hints, never the boundary.**
- Two tiers: a small coarse app-wide `auth.can` via Inertia shared data (chrome/nav) + fine-grained per-resource `can`.
- **No authority logic in Vue.**

### 10. Posture and testing

- **Deny-by-default / fail-closed:** all routes behind `auth`; `can()` denies when no rule matches; Form-Request `authorize()` is the structural guarantee for mutations.
- A per-action **allow *and* deny** role matrix on the spine factories, with wrong-Group officer, Limited/LOA, and unauthenticated cases as **required** denials.

## Considered alternatives

- **spatie/laravel-permission.** Rejected: the spine already stores capability-scoped roles with a write-time invariant; the package can't express capability-scoping or stewardship without custom work and would duplicate the store.
- **Cross-request permission cache.** Rejected: immediate-correctness on revocation matters more than a negligible gain; invalidation is a footgun.
- **Blocklist of contact fields.** Rejected for an allowlist (fails safe).
- **A generic named-permission table now.** Deferred: two cases only; news fits a capability-scoped role, support a standalone gate.
- **News-editing as a stewardship.** Rejected: stewardship is single-owner; news posting is wanted from several Groups — the multi-Group capability shape.
- **Super-tier inside individual policies.** Rejected: `Gate::before` is one place, can't be forgotten.

## Consequences

- **Spine code deltas:** drop the unused `Life` category case; add `AccessTier` + `Category::accessTier()/canSignUp()`; remove `super_tier` from `$fillable` + strict-model mode; refactor `Member` helpers + add `canActAs`; add `has_announcements` + a News-editor `Role` case; add `posting_group_id` to the news table.
- **Amends** [ADR-0010](0010-group-model.md) (`announcements` capability) and [ADR-0011](0011-authorization-model.md) (News-editor role, reworked news example, directory allowlist, Chair-except-Treasurer).
- **Unblocks the officer-action features** (scheduling admin, news, documents, reports, member email) to state predicates in `canActAs`/policy terms.
- **Implies a new feature:** in-app, email-native member-to-member messaging.
- **Organizational confirms outstanding:** may Provisional members sign up? is a lifetime tier wanted?

## References

- [ADR-0011](0011-authorization-model.md) — authorization *model* (this ADR is the *mechanism* it deferred)
- [ADR-0010](0010-group-model.md) — Group model + capability set
- [ADR-0009](0009-user-switching-and-support-impersonation.md) — a standalone-gate cross-cutting permission
- [ADR-0001](0001-authentication-and-identity.md) — authentication & identity
- `docs/conventions.md § Authorization` — the gate/policy/form-request mechanism
