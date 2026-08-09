---
status: accepted
date: 2026-06-16
accepted: 2026-06-16
---

# Authorization enforcement: hand-rolled gates/policies over the spine

> **Accepted 2026-06-16.** This ADR fixes the **mechanism** that [ADR-0011](0011-authorization-model.md) deferred ("whether a permissions package vs. hand-rolled policies enforce it is downstream"). ADR-0011 fixed the _model_; this fixes _how it is enforced in Laravel_. Two access-tier mapping details await an organizational confirm but do not block the mechanism.

## Context

[ADR-0011](0011-authorization-model.md) settled the authorization _model_ — three composable layers: member `Category` → base tier, per-Group roles via the membership pivot, one super-tier grant plus the Records stewardship. [ADR-0010](0010-group-model.md) settled the Group spine the roles attach to.

The spine is **built**: `Group`, `GroupMember`, `GroupMemberRole` (closed `Role` enum + a write-time capability-gating invariant), `GroupStewardship`, and `Member` with `isAllDmv()` / `membershipIn()` / `holdsRole()` / `hasMemberAdminAuthority()`. What is **absent** is the enforcement layer: no policies, gates, `Gate::before`, `Category`→tier resolution, field-level contact gating, `can`-prop convention, or read-path strategy. Authorization is built fresh, and the posture is **deny-by-default: an action with no server-side authorization is a bug.**

## Decision

Enforce the model with **hand-rolled Laravel gates + policies + form requests reading the spine** — no permissions package. The spine already _is_ the permission store; a package would bring overlapping tables to maintain or adapt, against the project's "no new package until a 3× need."

### 1. Super-tier — one short-circuit

```php
Gate::before(fn (Member $m) => $m->isAllDmv() ? true : null);
```

`true` grants, `null` falls through (never `false`). Runs ahead of every gate and policy, so the org-wide tier is never re-checked. Accepted consequences: it bypasses the sign-up floor and any hypothetical "deny everyone" policy (both non-cases or handled outside the Gate system). _(Corollary, 2026-07-02: any capability that must **exclude** super-tier likewise cannot live in the Gate system — this short-circuit would re-grant it. The dev switcher's `support_operator` marker is the worked example; see §5.)_

### 2. Category → base tier (the floor)

A new `AccessTier` enum (`Full` / `Limited` / `None`) plus **two independent predicates**, since view-access and sign-up-ability are orthogonal:

- `Category::accessTier(): AccessTier`
- `Category::canSignUp(): bool` — the sign-up gate calls this before any role check.

Mapping: Active/Honourary/Sustaining → Full + sign-up; Provisional/PreActive → Limited (still sign-up); LOA → Full-view, no sign-up; Resigned/Withdrawn/Deceased → None. (~~Two values await an organizational confirm: whether Provisional may sign up~~, and whether a lifetime tier is wanted.)

- _(Confirmed 2026-08-09, [ADR-0021](0021-scheduling-first-pass.md).)_ **Provisional and PreActive do sign up** — the mapping above is right as written. Signing up is _how_ a trainee trains (Visitor Guides runs a `Shadow` shift type for exactly that); LOA is the only standing that is genuinely paused, which ADR-0011 already phrases correctly as "full view, no sign-up". Note the orthogonality is load-bearing here: LOA's `accessTier()` is `Full` — the tier is not the sign-up gate.
- _(Added 2026-08-09.)_ **`Category::canSignUp()` now has a consumer: scheduling.** It is reused **unchanged**, as the DMV-wide floor on taking a Shift, and it binds a Scheduler placing a volunteer exactly as it binds the volunteer. A **second, per-Group floor** sits beside it — `MembershipStatus::canSignUp()`, excluding `loa` / `inactive` / `resigned` / `deceased` — because a Member in good DMV standing may still be paused in one Group. Both floors are independent of a Shift's `audience`, which answers "who sees a Sign-up button", not "may this person work at all". See ADR-0021.

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
- **Mutations are structurally authorized:** each carries a Form Request whose `authorize()` is auto-invoked (default to _self_; another member requires Records/super-tier); never `return true`.
- **Reads** use `can` middleware or explicit `$this->authorize()`.

### 5. Cross-cutting permissions

- **"Can edit news" is a capability-scoped role**: an `announcements` Group capability ([ADR-0010](0010-group-model.md)) gates a **News-editor** role ([ADR-0011](0011-authorization-model.md)). News is one org-wide feed; the capability is multi-Group; each item carries a `posting_group_id`; the read is org-wide (the one capability not read group-scoped).
- **Ad-hoc grants decoupled from any Group** (e.g. `initiate-support-session`, [ADR-0009](0009-user-switching-and-support-impersonation.md)) stay **standalone gates**.
- A **generic named-permission table is not built** until a third case proves the need.
- _(Amended 2026-07-02 — one exception to "standalone gates": a cross-cutting check that must_ **exclude** _super-tier cannot be a gate at all. §1's `Gate::before` short-circuits super-tier to `true` before any gate runs, so a gate ability can never_ deny _super-tier. The dev switcher's `support_operator` marker ([ADR-0009](0009-user-switching-and-support-impersonation.md)) is therefore read as a direct `Member::isSupportOperator()` predicate,_ **outside the Gate system** _— the only way to stop the President's org authority from conferring the maintainer's impersonation power. Standalone_ gates _remain correct for ad-hoc grants that super-tier may legitimately hold, such as the production `initiate-support-session`.)_

### 6. Field-level visibility — allowlist, least privilege

- A `viewContact($viewer, $target)` ability: Records + super-tier (org-wide); own-Group officers with a contact-need role (default Chair/Scheduler/Secretary, tunable).
- Enforced in **one centralized `MemberResource`** — no hand-built member arrays in controllers.
- **Allowlist, not blocklist:** every logged-in member sees only first/last name, photo (if uploaded), Groups/roles; everything else is gated by default, so new fields are private until deliberately exposed. (Consequence: an in-app, email-native member-to-member messaging path is needed as the directory no longer exposes contact details — separate PRD.)
- _(Added 2026-08-09, [ADR-0021](0021-scheduling-first-pass.md).)_ **A Sign-up's Member name is allowlisted to every viewer who can read the Schedule it sits on** — including non-members of the owning Group, since a published Schedule follows the Group's `listing_visibility` and is therefore org-open by default. Named here explicitly rather than left to ride in on the always-public name tier, because ADR-0021 **widened** the read audience relative to legacy's: legacy showed a schedule to the Group, we show it to the org, so legacy's behaviour was not automatically safe. The justification is that a Schedule is a roster of who is on the floor, no more exposing than the Directory. Nothing else about a Sign-up is exposed by this entry.
- **Name is stored split as `first_name` + `last_name`** (no middle, preferred, or display name) — the Directory sorts and jumps by surname and the legacy migration source already separates the two. `MemberResource` exposes both fields, not a composed `name`; UI composes the display form it needs ("First Last" on a profile, "Last, First" in the roster). (#168)

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
- A per-action **allow _and_ deny** role matrix on the spine factories, with wrong-Group officer, Limited/LOA, and unauthenticated cases as **required** denials.

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

- [ADR-0011](0011-authorization-model.md) — authorization _model_ (this ADR is the _mechanism_ it deferred)
- [ADR-0021](0021-scheduling-first-pass.md) — scheduling first pass (§2's first consumer; adds a Sign-up-names entry to the §6 allowlist)
- [ADR-0010](0010-group-model.md) — Group model + capability set
- [ADR-0009](0009-user-switching-and-support-impersonation.md) — a standalone-gate cross-cutting permission
- [ADR-0001](0001-authentication-and-identity.md) — authentication & identity
- `docs/conventions.md § Authorization` — the gate/policy/form-request mechanism
