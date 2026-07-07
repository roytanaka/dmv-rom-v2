---
status: accepted
date: 2026-06-30
accepted: 2026-06-30
---

# The grouping rail is server-driven and server-pruned

> **Accepted 2026-06-30.** Closes out PRD #209 (slices #210–#213). The rail's data
> now comes from the server, already pruned to what the signed-in Member may see; the
> client fixture/gating path that stood in during the chrome build is decommissioned.

> **Amendment (2026-07-07, [ADR-0020](0020-groups-nav-partition-and-sidebar-shape.md)).** The "Option C" All-Groups shape in §Decision is **superseded** for the browse zone: the root **DMV** node is dropped (it becomes the My-Groups-only org node); **Governance & Operations** is promoted to a top-level container peer; the **programs are re-nested** under a **Programs** container (reversing the promotion decided here); and **Friends** becomes a real container peer. "All Groups" becomes **Other Groups** — a partition that **never lists a Group the Member belongs to** — and My Groups **nests one level**. A client-side rail filter is added. The `listing_visibility` prune (PR #274) is unchanged. See ADR-0020.

## Context

The grouping rail (left sidebar) carries the app's primary navigation: the Groups a
Member belongs to, a browse list of the whole org, and an officer/admin cluster. When
the chrome was first built ([ADR-0013](0013-app-shell-section-nav.md)) the rail was fed
by a hand-written TypeScript fixture (`resources/js/chrome/fixture.ts`) and filtered by
a **stubbed show-all gate** (`resources/js/chrome/gating.ts`) — every node visible,
nothing actually hidden — so the layout could be designed before the Group spine
([ADR-0010](0010-group-model.md)) and authorization model
([ADR-0011](0011-authorization-model.md) / [ADR-0017](0017-authorization-enforcement.md))
existed.

Those now exist. PRD #209 wired each rail zone to real data, built in
`HandleInertiaRequests` alongside `chromeNav` and shared as the `rail` Inertia prop:
My Groups (#210), All Groups (#211), Officer Tools (#212). With all three zones
server-built, the client fixture and the show-all gate were dead code that could
silently diverge from the server's view of the org and of the Member's authority.

## Decision

**The rail is built and pruned on the server; the client receives only visible nodes
and renders them verbatim.** Concretely:

1. **Server-driven, server-pruned.** Each zone is assembled per signed-in Member in
   `HandleInertiaRequests::rail()`. Pruning is authorization, so it happens
   server-side: Officer Tools items are each gated by a real authority and the cluster
   is omitted whole when none survive; a zone absent for a Member is omitted entirely.
   The client never decides visibility — there is no client gate. This honours the
   security contract that roles resolve on the server and the client never echoes role
   flags back into requests.

2. **Hrefs arrive pre-localized.** The server localizes every rail href to the active
   locale ([ADR-0008](0008-bilingual-url-routing.md)), mirroring `chromeNav`, so the
   client uses them verbatim — no client `useLocalizedHref` step on the rail, and no
   risk of double-prefixing the locale. Group destinations already resolve to their
   localized `/groups/{slug}` twin in the prop.

3. **All Groups uses the curated Option-C live shape.** Rather than mirror the raw
   `parent_id` tree, the server reshapes the active org into the shape the rail should
   present: **Governance & Operations is folded into the root DMV node** (its
   committees become DMV's children), the **Programs container is dissolved and its
   programs promoted to the top level** (each keeping its own subcommittees), and
   **Special Projects and the Friends-of committees stay as ordinary top-level
   expandable nodes**. Archived/stale Groups never appear. This transform lives
   server-side so the rail and the Dashboard launcher (fed by the same prop) can never
   disagree.

4. **Vocabulary: "Officer Tools" ≠ a Group's officers.** "Officer Tools" denotes the
   **org-wide administration cluster** (Members, Communications, Reports, Flash
   Messages, DMV Settings) — the tools a Member with org-level authority reaches. It is
   deliberately distinct from a **Group's officers** (Chair / Secretary / Treasurer),
   who hold roles _within_ a single Group. The two senses of "officer" must not be
   conflated in code, copy, or future nav work.

The client fixture/gating path is removed: `chrome/gating.ts` is deleted, and
`chrome/fixture.ts` is reduced to `groupMenus` only. The rail wire types no longer
carry `requiresCapability` / `requiresRole` (gating happened server-side).

## Scope / what stays stubbed

The **Group Menu** layer — the per-Group section tabs revealed _inside_ a Group
([ADR-0013](0013-app-shell-section-nav.md) amendment) — is **out of scope** and stays a
client stub (`groupMenus` in `fixture.ts`), with its per-capability / per-role gating
fields retained on `NavNode`, until its dependent features ship. Only the _rail_ is
server-driven here, not the in-body section strip.

## Consequences

- The rail can never show a Group or tool the Member may not see: visibility is decided
  once, on the server, by the same authorization spine that enforces every action.
- One source of truth for the org shape. The Option-C transform and the localized hrefs
  are computed once and shared; the launcher and the rail stay in lockstep.
- The client nav code shrinks: no gate, no rail-side localization, no fixture for the
  three zones. Less surface to drift.
- The Group-Menu section-tab stub remains the one place a client-side nav fixture still
  lives; it is consciously deferred, not forgotten.
