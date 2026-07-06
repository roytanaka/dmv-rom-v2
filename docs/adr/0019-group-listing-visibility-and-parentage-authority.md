---
status: accepted
date: 2026-07-06
accepted: 2026-07-06
---

# Group listing-visibility and parentage authority

From a design pass on issue [#266](https://github.com/roytanaka/dmv-rom-v2/issues/266) (subgroup display), with the concrete seed [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262) (a members-only subgroup) and the sibling [#267](https://github.com/roytanaka/dmv-rom-v2/issues/267) (sidebar redesign, **split out** — see _Consequences_). This ADR adds a **listing-visibility** facet to the [ADR-0010](0010-group-model.md) Group model, and **refines [ADR-0011](0011-authorization-model.md)**: parentage carries _structural_ authority, not _operational/content_ authority. The 0010 and 0011 amendments are folded back onto those ADRs.

## Context

Three issues, in order:

- **#262 (the seed).** Gallery Interpreters has an **Events** subgroup whose menu is **visible only to members of Events**, not to other Gallery Interpreters members. "Consider adding a class of PRIVATE to subgroups."
- **#266 (the generalization).** A **visibility flag** on subgroups with three values — _Public_ (all members), _Group_ (parent-Group members only), _Private_ (subgroup members only) — plus display rules for the navigation lists that key off whether the parent is an open, recruiting super-group vs. an internal Group. #266 also states that **"the parent Group controls the creation and membership of the subgroup"** — a claim of parent-over-child authority.
- **#267 (the sidebar).** Renames the browse-list root node, puts the super-group containers at one top level, hides the subgroups of Program and Friends Groups from the browse list, and never lists a Group you already belong to under _All/Other_. This supplies the display half of #266 and revises the server-pruned rail ([ADR-0018](0018-server-driven-grouping-rail.md)).

Two decisions fall out, entangled through the Private case:

1. **How is "who sees a Group" modelled?** All three values are real and demonstrated, and #267 shows _Public_ and _Group_ are genuinely distinct (a recruiting super-group's children are advertised; an internal Group's children are not).
2. **Does a parent Group's leadership have authority over its children?** #266 says yes; **ADR-0011 said no** ("parentage carries structure, not authority"). Ruling: ADR-0011's blanket principle was too broad, and #266's framing is correct — parent authority is real, and likely enforced beyond subgroup creation.

**Reconciling with ADR-0011.** The legacy per-committee **role-bit** authorization path has **no parent→child walk**: a chair of A cannot _act as an officer of_ B, even when B nests under A. That finding stands and is **not** overturned here — it concerns **operational/content** authority (acting as a Group's officer, reading its content). What #266 describes — creating a subgroup, setting who belongs — is **structural** authority, a different concern. The refinement below adds structural parent authority while keeping operational authority exactly where ADR-0011 put it. (Whether the legacy also enforces structural parent-control is to be confirmed against the legacy reference implementation at build time; this ADR fixes the rebuild's intent regardless.)

## Decision

### A. Listing visibility — a stored per-Group facet

Add **`listing_visibility` ∈ {`Public`, `Group`, `Private`}** to every Group. It governs **who sees the Group in navigation**, and — for `Private` only — **who may open its page at all**.

| Value       | Navigation (rail / launcher)                             | Page access                                        | Demonstrated by                                                           |
| ----------- | -------------------------------------------------------- | -------------------------------------------------- | ------------------------------------------------------------------------- |
| **Public**  | listed to every Member (per the rail rules)              | org-open (the shipped default)                     | recruiting super-group children (governance committees, special projects) |
| **Group**   | pruned from outsiders; shown to **parent-Group members** | **org-open, unchanged** — reachable by direct link | Program subcommittees, Friends subgroups                                  |
| **Private** | pruned from all but the subgroup's **own members**       | **gated** — non-members get **not-found (404)**    | Gallery Interpreters → Events (#262)                                      |

The load-bearing distinction: **`Public` and `Group` are _navigation-listing_ inputs only** — they change what the server emits into the rail and launcher, and change nothing about page authorization. **`Private` is the only value that adds a _page-access_ check** — a policy branch that returns **not-found** so the Group's very existence stays hidden. So only `Private` touches the ADR-0011 / [0017](0017-authorization-enforcement.md) page policies; `Public` / `Group` are navigation concerns that feed the server-pruned rail ([ADR-0018](0018-server-driven-grouping-rail.md)).

Consequence accepted deliberately: a `Group`-visibility subgroup's page stays **reachable by direct link** even when pruned from navigation. This is **tidiness, not confidentiality** — matching #266's "of no interest to any outsider" framing and preserving the shipped org-open Overview/Roster. The sensitive surface (Meetings) is members-only for every Group regardless. Only `Private` — the one case framed as _secret_ — is a hard boundary.

**Set explicitly, never derived.** The model stores the flag; it does not compute it from tree position. A **migration data pass** assigns each existing subgroup a value (a Board-decided data task), and the create-form defaults a **new** subgroup to `Group` (fail-closed on exposure). "Recruiting super-groups lean Public, internal Groups lean Group, Events is Private" is the **guideline humans apply**, not an algorithm — which keeps this facet decoupled from #267's super-group taxonomy.

**Naming.** This Group-level facet is **`listing_visibility`** to avoid colliding with the per-**Meeting** `visibility` (members / officers) the meetings capability defines — a different axis. One decides whether you see the _Group_; the other decides whether you see a _meeting's minutes_.

### B. Parentage authority — a structure/content split (refines ADR-0011)

Parentage carries authority, decomposed on one axis:

- **Structure & roster of a child** — create it, set its `listing_visibility`, add/remove members, assign roles within it — is conferred **by parentage** on the parent's administering officers (**Secretary | Chair**; Chair implies the officer set except Treasurer), cascading **transitively over the whole subtree**.
- **Content of a child** — reading its Meetings, Documents, and other capability data — is **not** conferred by parentage. It still requires a **membership in that child** (or the org-wide super-tier). **`Private` enforces this even against the parent Chair**, who — controlling the roster — may add _themselves_ as a deliberate, visible act if they need in.

Cascade is self-limiting: the only levels with both a real subtree and an ordinary Chair are Programs and committees. The **container** nodes are capability-less scoping shells with no operational Chair, and the **root** is super-tier territory — so transitive cascade from those levels grants nothing surprising. **Super-tier** is unchanged: everything, everywhere.

Mechanism: this is the existing officer-authority resolver (`administers()` / `canActAs(Role, Group)`, [ADR-0017](0017-authorization-enforcement.md)) **evaluated up the ancestor chain** for structural operations, rather than on `self` only — no new role vocabulary. **Subgroup creation itself is not built here**: it lands in the deferred administration / structural-mutation slice. This ADR fixes _who will be allowed to_: a Group's administering officers over their subtree, plus super-tier.

## What this is NOT (guardrails)

- **Not a repudiation of ADR-0011's finding.** The no-parent-walk finding is about _operational_ authority and is preserved; only _structural_ authority is added.
- **Not confidentiality for `Group`.** `Group` declutters navigation; it does not gate the page. Anything that must be _hidden_ is `Private`.
- **Not an org-tree cascade for content.** Reading a child's content always requires a role _in that child_ (or super-tier). Parentage never grants a content read.
- **Not derived defaults.** No auto-assignment of `listing_visibility` from parent Kind/Scope — humans assign it.
- **Not the sidebar redesign.** #267's rail-shape / renaming is a separate follow-up that _consumes_ this facet.

## Considered alternatives

- **A `Private` boolean only (drop Public/Group).** Rejected once #267 was in view: _Public_ vs _Group_ is a real, demonstrated distinction (advertised vs. internal subgroups), not a rename.
- **Derive visibility from tree position (#267's "any Program group" phrasing).** Rejected: the `Private` cases are _exceptions_ to their parent's default, which a positional rule cannot express — you need a stored, overridable flag.
- **One mechanism for all three values: each value gates navigation _and_ page.** Rejected: it walks back the shipped org-open Overview/Roster for every `Group`-visibility subcommittee and treats "disinterest" as "confidentiality." Only `Private` earns a page boundary.
- **Parent Chair is a full operational officer of every descendant (content included).** Rejected: it makes `Private` content visible to the whole parent chain, gutting the one confidentiality case. The structure/content split keeps both #266 statements true.
- **Keep ADR-0011's "no parentage authority" and model parent-control as a delegated permission only.** Rejected: the authority is real and likely enforced elsewhere; model it as the general principle.

## Consequences

- **Amends [ADR-0010](0010-group-model.md)** — `listing_visibility` joins the Group facets; the Parentage relationship kind gains the structure/content split. (Amendment note added there.)
- **Amends [ADR-0011](0011-authorization-model.md)** — the "parentage carries structure, not authority" rule is refined to "structural authority yes, operational/content authority no." (Amendment note added there.)
- **Will amend [ADR-0018](0018-server-driven-grouping-rail.md)** — the rail now also prunes on `listing_visibility` (`Group` → non-parent-members; `Private` → non-members). Handled with the #267 follow-up.
- **Only `Private` adds enforcement.** The page policy grows one check + a not-found path; `Public` / `Group` are rail/launcher data only. Small blast radius.
- **The administration / structural-mutation slice inherits the gate.** When subgroup create / re-parent / archive / visibility-set is built, its authorization is already decided: administering officers over the subtree + super-tier.
- **Migration owes a data pass.** Each existing subgroup gets a `listing_visibility` value (Board-decided). Default for ambiguous rows: `Group` (fail-closed).
- **#267 is a separate follow-up** — root-node rename + super-group flattening + own-Groups-never-in-All/Other; consumes this facet and amends ADR-0018.

## References

- Issues [#266](https://github.com/roytanaka/dmv-rom-v2/issues/266), [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262), [#267](https://github.com/roytanaka/dmv-rom-v2/issues/267)
- [ADR-0010](0010-group-model.md) — Group model (this facet joins its axes; parentage relationship refined)
- [ADR-0011](0011-authorization-model.md) — authorization model (the parentage principle this refines)
- [ADR-0017](0017-authorization-enforcement.md) — enforcement mechanism (the officer-authority resolver, now evaluated over ancestors for structural ops)
- [ADR-0018](0018-server-driven-grouping-rail.md) — server-driven grouping rail (the prune this facet feeds)
