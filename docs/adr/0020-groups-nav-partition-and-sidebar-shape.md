---
status: accepted
date: 2026-07-07
accepted: 2026-07-07
---

# Groups navigation: the My Groups / Other Groups partition and the four-peer sidebar shape

From a design pass on issue [#267](https://github.com/roytanaka/dmv-rom-v2/issues/267) (sidebar redesign / the overloaded term "DMV"), split out of the [#266](https://github.com/roytanaka/dmv-rom-v2/issues/266) design that produced [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md). This ADR settles the **shape and pruning of the two Groups nav zones**. It **reverses part of the shipped [ADR-0018](0018-server-driven-grouping-rail.md)** ("Option C" promoted the programs to the browse list's top level), **refines [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md)** (the `Group`-visibility "shown to parent-members" rail clause now lands in My Groups), and **clarifies [ADR-0010](0010-group-model.md)** (root-DMV membership is derived from `Category`). The `listing_visibility` **prune already shipped** (PR #274) and is **not** touched here. Labels and visual presentation are design details, finalized at design time.

## Context

The left rail carries two Groups zones: **My Groups** (the Groups the signed-in Member belongs to) and **All Groups** (a browse view of the org). The shipped rail ([ADR-0018](0018-server-driven-grouping-rail.md), "Option C") builds and prunes both on the server, and PR #274 added the `listing_visibility` prune ([ADR-0019](0019-group-listing-visibility-and-parentage-authority.md)) beneath it.

Issue #267 names a real defect: **"DMV" is overloaded.** In _My Groups_ it means the whole organization; in _All Groups_ it is the root node under which Option C **folded the Governance & Operations committees**. Same word, two different objects — and Option C is precisely what **merged** them into one "DMV" node. The fix: rename the All-Groups "DMV" node to a governance label, put it alongside Programs / Special Projects / Friends as **peer containers** each exploding one level, **stop showing the sub-groups** of Programs and Friends, and **never list a Group the Member belongs to** in the All/Other zone (those live in My Groups).

Two things had to be reconciled before that picture could ship:

1. **It contradicts shipped Option C.** Option C kept **DMV as the tree root** of All Groups and **promoted the programs to the top level** (dissolving the Programs container). #267 removes the root and **re-nests** the programs under a Programs peer — a real reshape of a tested, shipped builder.
2. **"Never show a Group you belong to" interacts with ADR-0019.** Pruning your own Groups out of the browse zone orphans the `Group`-visibility sub-teams ADR-0019 said were "shown to parent-members in the rail" — so #267 does not merely _consume_ the listing-visibility facet, it **refines** where that clause is honoured.

## Decision

### A. Two zones, one partition

The Groups nav is a **partition**, not a superset plus a subset. Every Group a Member may see appears in **exactly one** zone:

- **My Groups** — the Groups the Member belongs to (Full / LOA standing).
- **Other Groups** — everything else the Member is allowed to see: the **visible** org **minus** the Member's own Groups.

"All Groups" is retired: once own-Groups are pruned it is no longer _all_, so the honest name is **Other Groups** (working label). The partition replaces two older rules ("All Groups shows everything" + "subtract your Groups") with one invariant. "Visible" is load-bearing: a `Group` / `Private` node the Member is not entitled to see is in **neither** zone (pruned entirely by PR #274), not "in Other."

### B. Root DMV is the org node, and lives only in My Groups

The **root DMV Group is real** — every active Member belongs to it — but its membership is **derived from the Member's `Category`** (active standing), **not** a stored membership row, exactly as the Directory already resolves (it _is_ the root's Roster). It is the **My Groups org node**: it reaches the org-wide Directory and org-wide meetings, and it is a **leaf** — it does **not** explode into the whole org tree (it "will not provide information on any subgroups"). Because every Member belongs to it, the own-Groups prune keeps it out of Other Groups; it is a My-Groups-only node. The All-Groups "DMV" node ceases to exist — what was folded under it becomes the Governance & Operations peer (§C).

### C. Other Groups top level = four org-scope container peers, exploding one level

The top level of Other Groups is the four **organization-scope container** children of the root, each an expandable node:

- **Governance & Operations** — the ex-"DMV root" node, relabeled; it already carries the governance/operations committees as children.
- **Programs** — **re-nested** as a container: it explodes to the programs. This **reverses** Option C's promotion of programs to the top level.
- **Special Projects** — its projects (unchanged from Option C).
- **Friends** — the Friends-of committees (see §G).

The program-promotion familiarity Option C chased ("volunteers are used to programs at the top level") is **preserved in My Groups**, where a Member's own programs sit one click away. Other Groups is a **browse/discover** surface for Groups the Member is _not_ in, where four collapsed peers is tidier than a dozen flat nodes — so the two goals stop competing.

### D. Depth is flag-driven, never positional

Each peer bottoms out at one level **because of the data, not a depth cap.** A Program's or Friends committee's working groups are `listing_visibility=Group`, so PR #274 prunes them from an outsider's Other Groups. The builder emits the full pruned tree; it does **not** enforce "Programs never expand." Consequence, accepted: a working group deliberately set `Public` **will** surface under its parent in Other Groups — the correct reading of a deliberate `Public` flag ("advertise me"), and consistent with ADR-0019's rejection of positional derivation. "Programs / Friends show no sub-groups" is therefore a **property of the seed/migration data**, not a rule the builder guarantees.

### E. Own-Groups prune

Other Groups **excludes any Group the Member belongs to** (Full / LOA — the same standing rule My Groups and PR #274 use; departed standings grant nothing). Container peers are never "yours" (they have no roster), so they always remain; only _leaf_ Groups the Member belongs to vanish from Other Groups. This is the mechanism of the §A partition.

### F. My Groups nests one level (the DMV node excepted)

My Groups changes from flat to **nested one level**: each Group the Member belongs to shows, beneath it, **every child of that Group the Member is entitled to see** — the _same_ `listing_visibility` rule §D/PR #274 prunes Other Groups with, applied to the row's children: `Public` children, `Group`-visibility children (the Member is a parent-member by construction), and any `Private` child the Member belongs to. **The root DMV node is the sole exception** — it stays a leaf (§B).

This is where ADR-0019's _"`Group` = shown to parent-Group members in the rail"_ now lands. Under the §E own-Groups prune, a `Group`-visibility node is shown to **nobody** in Other Groups (parent-members have the parent pruned; non-parent-members have the child pruned), so without nesting the facet would have **no rail effect at all**. Nesting My Groups restores it: a program's members see their sub-teams in the rail — in the zone that holds "their world."

**Amended (fix, 2026-08-09).** This section originally enumerated only `Group`-visibility and belonged-`Private` children, excluding `Public` ones on the reasoning that they "stay browsable in Other Groups." They do not: §E removes the belonged parent from Other Groups **with its whole subtree**, so a `Public` child of a Group you belong to was visible to every Member _except_ that Group's own — reported from staging as Reception's `Public` "Library" sub-group missing from a Reception member's rail while an outsider saw it under `Programs ▸ Reception`. Entitlement here is now the shared prune, not a restatement of it, which closes the hole and keeps §A a true partition.

### G. Friends is a real container; Associated Friends is a committee

**"Friends" (the fourth peer) is a real, structural container Group** grouping the five Friends-of committees. It is created and the five re-parented under it — on staging via the seeder now (§ Consequences), and as one shaping rule of the already-deferred whole-tree migration later. Structure, not a rail heuristic: a name-match ("starts with 'Friends of'") would **miss** the one Friends committee whose name does not begin that way, which is exactly why the grouping is modelled, not string-matched.

**"Associated Friends" is a distinct thing** — a coordinating committee that _represents_ the Friends committees — and **stays under Governance & Operations**, where it already sits. It is not the container. (In Other Groups a Member will thus see "Associated Friends" under `Governance & Operations ▸` and a sibling `Friends ▸` peer; structurally correct, a naming paper-cut to smooth at design time.)

### H. Rail filter searches the pruned client set only

A type-ahead **rail filter** is added: as the Member types, matching nav nodes are revealed as a **flat list** (each with a path breadcrumb for context); clearing the box restores the nested rail. It runs **entirely client-side over the already-delivered, already-pruned visible node set** ([ADR-0018](0018-server-driven-grouping-rail.md) ships only visible nodes), so it **cannot leak** the existence of a `Private` / `Group` node the Member was not already allowed to see. This is a hard invariant: **the filter must never issue a raw server-side all-Groups query** — that would surface pruned names. A separate **content/document search** (across documents, pages, meeting notes) is a much larger feature — server-side index, per-result authorization — and is **parked as its own future PRD**, not part of this work.

## What this is NOT (guardrails)

- **Not a depth cap.** One-level-and-stop is the `listing_visibility` data doing the work (§D), not a positional rule.
- **Not a change to `listing_visibility` or its prune.** PR #274 is untouched; this ADR only reshapes and adds the own-Groups prune above it.
- **Not content search.** Only the client-side rail _filter_ is in scope (§H).
- **Not the production migration.** Re-parenting the real Friends rows is one rule of the deferred whole-tree migration, captured as a shaping note — not a #267 work item.

## Considered alternatives

- **Keep Option C's program promotion in Other Groups** (programs at the top level). Rejected: it mixes _containers_ (Gov & Ops, Special Projects, Friends) with _leaf programs_ at one level; the uniform four-peer shape is cleaner, and the familiarity argument is satisfied by My Groups (§C), where a Member's own programs already sit.
- **A structural depth cap — "a Program node never expands in Other Groups."** Rejected: it re-introduces the positional rule ADR-0019 rejected, and forecloses ever advertising a genuinely `Public` sub-group. Flag-driven depth (§D) is consistent and reuses the shipped prune.
- **Keep My Groups flat; reach sub-teams from the Group's page.** Rejected: it hollows out `listing_visibility=Group`, which would then have _no_ rail effect for anyone (§F).
- **Friends as a rail-only synthetic grouping** (builder gathers "Friends" nodes). Rejected: the non-conforming committee name defeats name-matching; structure beats heuristic (§G).
- **"Administration" as the governance-peer label.** Not adopted as the working label: it collides with the disambiguated **Admin** vocabulary and with the **Officer Tools** org-admin cluster. Working label is **Governance & Operations** (the existing model term); final wording is a design detail.
- **Rail filter backed by a server query.** Rejected: it would surface pruned / `Private` names — a leak the client-side-over-visible-set design forecloses (§H).

## Consequences

- **Amends [ADR-0018](0018-server-driven-grouping-rail.md).** Option C's program-promotion is reversed (programs re-nest under a Programs container peer); "All Groups" (superset) becomes "Other Groups" (partition); the own-Groups prune is added. The shipped `RailNavTest` rail-shape assertions are rewritten to match.
- **Refines [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md).** The `Group`-visibility "shown to parent-Group members in the rail" clause is now realized in **My Groups nesting** (§F), not the Other-Groups rail. The facet and PR #274 prune are otherwise unchanged.
- **Clarifies [ADR-0010](0010-group-model.md).** Root-DMV membership is **derived from `Category`**, not a membership row; the root is the My-Groups org node and a nav leaf.
- **Seeder gains the Friends container** and re-parents the five under it (staging); My Groups builder nests one level; the Other-Groups builder emits four container peers.
- **The whole-tree migration gains one shaping note** — target shape has a Friends container; parent the Friends committees under it (set confirmed against the live data at scope time). No separate Friends data-pass.
- **New client capability:** the rail filter (§H). **Content/document search** is parked as its own future PRD.
- **Delivered by the #267 PRD** — slices: Other-Groups reshape, own-Groups prune, My-Groups nesting, Friends container + seed, rail filter (separable).

## References

- Issues [#267](https://github.com/roytanaka/dmv-rom-v2/issues/267), [#266](https://github.com/roytanaka/dmv-rom-v2/issues/266), [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262)
- PR #274 — `listing_visibility` prune (the prune this reshape sits above)
- [ADR-0018](0018-server-driven-grouping-rail.md) — server-driven grouping rail (Option C, amended here)
- [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md) — listing-visibility & parentage authority (the `Group` rail clause refined here)
- [ADR-0010](0010-group-model.md) — Group model (root-membership clarification)
