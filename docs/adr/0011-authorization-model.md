---
status: accepted
date: 2026-05-30
accepted: 2026-05-31
---

# Authorization model: per-Group roles, one org-wide grant, and a base access tier

## Context

[ADR-0001](0001-authentication-and-identity.md) deferred authorization ("who can see which members, which committee, which documents is a separate concern"). It is now the central remaining decision: the per-Group feature work (scheduling admin, news, document upload, reports, member email) all gate officer-only actions, and they cannot be scoped consistently until the model is fixed once.

This ADR uses **Group** as the organizing entity (see [ADR-0010](0010-group-model.md) and `CONTEXT.md`): every committee, program, working group, and event cohort is a **Group** — a named set of people, each holding **role(s) within that Group**, with one parent and a set of capabilities switched on. "Role" always means "role within a Group."

The defining shape of DMV authorization is that it is **per-Group** — not inheritance down an org tree, and not a single global role. A role grants _operational_ authority (acting as an officer, reading content) only within its own Group; leading a parent Group confers no _operational_ authority over a child Group. (Parentage does carry _structural_ authority — managing a child's shell and roster — refined 2026-07-06 in [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md); see the _Cross-cutting rules_ Parentage bullet.) There is exactly one genuine org-wide override (top leadership) and one org-wide responsibility (member administration), and both are explicit rather than emergent from tree position.

## Decision

Model authorization as **three composable layers**, evaluated together.

### 1. Membership status → base access tier

A volunteer's membership status resolves to **Full / Limited / None**, with **Leave-of-Absence = "full view, no sign-up."** This is the floor: a Limited or LOA volunteer cannot perform sign-up actions regardless of any roles they hold.

### 2. Per-Group roles via a membership pivot

Group membership is a join row carrying the role(s) a volunteer holds _within that Group_. Policies receive both the volunteer and the Group and decide from the membership. `Chair` implies the other officer roles within its **own** Group, **except `Treasurer`** (separation of duties — a Chair is not auto-granted finance authority).

_(Clarified 2026-08-09 — see [ADR-0021](0021-scheduling-first-pass.md).)_ **Membership is the only path to a per-Group role, and a membership may be granted solely to confer one.** A role is structurally a row on the _membership_ (`GroupMemberRole` belongs to `GroupMember`), and `canActAs` returns false the moment `membershipIn($group)` is null — so a non-member role grant is not merely unbuilt, it is **unrepresentable**. When someone needs authority in a Group they do not otherwise belong to, the answer is a plain membership carrying the role, granted through the receiving Group's own roster CRUD by that Group's officers (super-tier as fallback). Requiring the receiving Group to grant it is a feature: the arrangement is consensual and visible. Such a membership is displayed like any other — **no marker, no filter, no new status** — and the holder is counted on that Group's roster; the accepted cost is recorded in ADR-0021. The worked case is the Visitor Wayfinders Chair, who builds Schedules for seven Friends Committees.

- **Roles are capability-scoped — not a flat enum and not free text.** A role exists only where the Group has the capability that gives it meaning: _Scheduler_ with scheduling, _Statistician_ with stats, _Vetting_ with the vetting workflow, _Librarian_ with the document library, _content maintainer_ with the content catalog, _news-editor_ with announcements. Core roles (Chair, Secretary) exist on every Group. This makes "Scheduler on a Group with no scheduling" **unrepresentable** rather than something to validate against — the role catalog stays small, closed, and finite.
- **`Treasurer` is a per-Group role only.** DMV-level finance is the Treasury Group plus org-wide reporting.
- **`Donor` is a Group-scoped status** used only by the Friends Groups, not a global role.

### 3. Org-wide reach — one explicit grant, plus one stewardship Group

Org-wide reach comes from exactly two places. Both are explicit; neither is inherited from tree position:

- **Super-tier — the one org-wide grant.** Everything, everywhere. A named, editable grant held by DMV's top leadership (President / VP1 / VP2). The _kind_ of grant is fixed in code; _who holds it_ is data, so a leadership change is a data edit, not a deploy.
- **Member administration + all-DMV reports is a Group capability, not a standalone bit.** It is a **stewardship** responsibility owned by the **Records Group**: holding a role there is what grants it. Org-wide in _effect_, per-Group in _authority_.

Technical/support administration is deliberately **not** a third org-wide tier: a maintainer who needs to administer data or impersonate a user for support sits in the Records Group plus the explicit `initiate-support-session` permission ([ADR-0009](0009-user-switching-and-support-impersonation.md)), never in super-tier — least privilege.

_(Refined 2026-07-02 — this principle is now realised, and the two impersonation tools use two distinct gates, both outside super-tier: the **dev** role-switcher is gated on a dedicated `support_operator` marker on `Member`, split out of super-tier ([ADR-0009](0009-user-switching-and-support-impersonation.md) amendment); the **production** view-as tool keeps the `initiate-support-session` permission named above. The dev marker is read as a direct `Member::isSupportOperator()` predicate rather than a gate, because a gate would let super-tier's `Gate::before` short-circuit re-grant it — see [ADR-0017 §5](0017-authorization-enforcement.md).)_

### Cross-cutting rules

- **Parentage carries structure, not _operational_ authority** _(refined 2026-07-06 — see [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md))_. A role grants **operational/content** reach — acting as an officer, or reading a Group's content — only within its own Group; that is **never an ambient consequence of the tree** (cross-Group operational reach is either the explicit org-wide grant (super-tier) or an explicit _scoped-down_ role, e.g. a section head over their own section). **Structural** authority is the exception ADR-0019 carves: a parent's administering officers (Secretary | Chair) _do_ administer the shell and roster of their whole subtree (create / re-parent / roster / role-assign / `listing_visibility`), but that confers **no content read** — a `Private` child's meetings stay hidden from a non-member parent Chair.
- **Cross-cutting permissions are explicit, not derived from bare Group membership.** "Can edit news" is the worked example. _(Refined 2026-06-16.)_ It is modelled as a **capability-scoped role** — a news-editor role on a Group whose `announcements` capability is on ([ADR-0010](0010-group-model.md)) — **not** bare membership of a communications group, and not a separate permission table. Truly ad-hoc grants decoupled from any Group (e.g. support-session impersonation, [ADR-0009](0009-user-switching-and-support-impersonation.md)) remain standalone gates; a generic named-permission table is introduced only if a third case proves the need.
- **Directory contact-visibility is scoped, defaulting to hidden.** Email/phone are released only to (a) super-tier and the Records Group, org-wide, and (b) a member's **own-Group officers** — never officers of unrelated Groups. Any logged-in volunteer sees the **non-contact view**: name, photo, and the roles/Groups the member holds. The exact own-Group roles that count as "officer" for the contact gate are a small policy value the member-directory work sets (default: Chair, plus roles with an inherent contact-need such as Scheduler/Secretary).
    - **Refinement (2026-06-16): allowlist, not blocklist — least privilege by construction.** The member resource exposes a fixed small **allowlist** to every logged-in member — first name, last name, photo (if uploaded), and the Groups/roles held — and gates **everything else by default** (email, phone, and any other personal field). A newly-added field is therefore private until deliberately added to the allowlist; the failure mode is "withheld," never "leaked." PII is released only to those who genuinely need it for a role (the `viewContact` test above). **Consequence:** because the directory does not surface contact details, the rebuild needs an **in-app, email-native member-to-member messaging** path so members can still reach one another — flagged for a later PRD. _(Paid 2026-09-07 by [ADR-0024](0024-emailing-model.md): the **Direct message**, any Member to one Member, sent by the app with the sender as Reply-To; the recipient's address is never shown.)_

Implementation mechanism: Laravel gates + policies + form requests, with the per-Group decision taking `(User, Group)`. Whether to adopt a permissions package vs. hand-rolled policies is an implementation-time call — see alternatives.

## Considered alternatives

- **Copy role flags onto the user row.** Rejected: bakes in the global-flag model, can't express "Chair of A, member of B," and makes capability-specific roles second-class.
- **Pure global RBAC (a flat set of named roles, no Group dimension).** Simple and well-supported by packages. Rejected: it loses the per-Group scoping that is the defining feature of DMV authorization.
- **A flat closed enum of every role.** Rejected: it lets "Scheduler" be assigned to a Group with no scheduling — the illegal state capability-scoping makes unrepresentable.
- **Free per-Group role text.** Rejected: policies can't reason about arbitrary strings ("can this person schedule?"), and it reintroduces drift.
- **Model the super-tier as "leader of the root Group" (inherited down the tree).** Rejected: it introduces tree-inheritance the model deliberately avoids and relocates the magic-flag conflation.
- **Hardcode the super-tier offices in code.** Rejected: officer turnover is annual, and a leadership change should be a data edit, not a deploy.
- **Carry member-administration as a standalone org-wide bit.** Rejected: deriving it from the Records Group is simpler and collapses a grant plus a group into one concept.
- **Adopt a permissions package as the model (teams feature for the Group dimension).** Tempting and battle-tested. Deferred to implementation per "no new package until a need is demonstrated": this ADR fixes the _model_; the enforcement choice is downstream.

## Consequences

- **Unblocks the officer-action work.** Scheduling admin, news editing, document upload, reports, and member-email features can all state their authorization predicate in this model's terms.
- **The Group-membership pivot becomes a central table.** It carries roles and membership status and is read on nearly every authorization decision — index and cache accordingly.
- **[ADR-0009](0009-user-switching-and-support-impersonation.md) depends on this model.** Its `initiate-support-session` gate is a concrete instance of the explicit-named-permission mechanism, and its read-mostly view-as boundary is an authorization predicate enforced at the policy layer.
- **Org-wide reach is operable without engineering.** Super-tier membership and the Records Group roster are data, so role turnover is an admin action.
- **The member directory's contact-visibility boundary is decided** (scoped, default hidden); the member-directory work sets the precise officer-role list against that default.

## References

- [ADR-0001](0001-authentication-and-identity.md) — authentication & identity (this ADR is the authorization counterpart it deferred)
- [ADR-0010](0010-group-model.md) — Group model (the entity and capability set this model's roles attach to; scoped-down roles use its capability-subdivision representation)
- [ADR-0009](0009-user-switching-and-support-impersonation.md) — user switching and support impersonation (downstream consumer of this model)
- `docs/conventions.md § Authorization` — the Laravel enforcement mechanism (gates/policies/form requests)
