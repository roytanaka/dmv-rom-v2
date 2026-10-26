---
status: accepted
date: 2026-10-25
accepted: 2026-10-25
---

# Group administration: Groups and Stewardships under DMV Settings

From a grilling session on 2026-10-25. It settles how an authorized Member creates, configures and retires **Groups**, and assigns **Stewardships**, without a developer and a deploy. It takes [#815](https://github.com/roytanaka/dmv-rom-v2/issues/815) (a Stewardships page) as written, closes [#288](https://github.com/roytanaka/dmv-rom-v2/issues/288) (root membership) and [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) (authorship), **amends [ADR-0010](0010-group-model.md)** (the Scope axis is withdrawn) and **[ADR-0021 §1](0021-scheduling-first-pass.md)** (the "no `created_by`" note), and **enforces [ADR-0020 §B](0020-groups-nav-partition-and-sidebar-shape.md)** (root membership is derived). See `GLOSSARY.md` for **Stewardship**, **DMV Settings** and the amended **Group** and **Admin** entries.

## Context

A Group officer can change two things about their Group in-app: the About text and the banner. Everything else that makes a Group what it is lives in `database/seeders/OrgTreeSeeder.php`: its name, slug, parent, kind, listing visibility, display order, logo, time-box, six capability flags, four collection flags, the hours multiplier, and its lifecycle state. Stewardships (which Group holds member administration, which Groups are org-wide senders) are seeded too. No screen shows them. To add a Group, retire one, or move a stewardship, a developer edits a seeder and deploys.

**The Legacy app had none of this.** Each legacy Group was a folder of hard-coded PHP under `public_html/res/`. There was no screen to add or configure a Group. Group administration is **new scope, not parity**, and this ADR says so. It is ranked second on the build list, after the Records office tools, because the alternative is a developer in the loop for the life of the app.

Facts that shaped the decision:

- ADR-0018 §4 reserves the rail's **Officer Tools** cluster for org-wide tools and names **DMV Settings** as one of them. It is still a `ComingSoon` stub. ADR-0027 reaffirmed the split: Group-scoped settings on the Group's Settings tab, org-wide settings in Officer Tools.
- ADR-0011 has one explicit org-wide grant, the **super-tier**, and `Gate::before` gives it every ability (ADR-0017 §1).
- ADR-0019 §B gives a parent Group's officers authority over the structure of its subtree. That cascade is not built. Nothing in the app lets anyone but a seeder change structure.
- `scope` (organization, program, subteam) is a column and an enum with **no reader anywhere in app code**. `kind` is what the app reads.
- The root Group `dmv` is modelled two ways at once: ADR-0020 §B says its membership is derived from **Category**, and the demo seeder writes a membership row for every Member anyway. Two Other-Groups regressions came from that (#288).
- Archiving a Group hides it from the rail and nothing else. Its content still takes writes.
- Capability flags are enforced in policies. Turning one off with data behind it cascades nothing: the data stays, the routes answer 404, and a role that needs the flag stays attached because the check runs only on save.
- A Group's slug is the route key for about 28 routes, is hard-coded in 41 help articles, and `dmv` and `executive` are constants the app reads.
- No model carries `created_by`. Three tables carry a one-off authorship column each (`hours_adjustments`, `sign_ups`, `documents`). #334 asked for an app-wide answer and has waited since August.

## Decision

### 1. Super-tier only

**Only the super-tier administers Groups and Stewardships.** No new stewardship, no reuse of the unused `website` one. One explicit org-wide grant exists and this is org-wide work. ADR-0019 §B's cascade to parent officers stays unbuilt; this ADR neither builds nor withdraws it. If a real holder outside the super-tier asks for the job, that is the moment to add a stewardship for it.

### 2. Create, edit and archive in-app. Never delete.

**A Group is created, edited and archived in-app.** There is no delete. A Group that was ever real has Memberships, Hours, Documents and Shifts hanging off it, and history must stay readable. Archive is the retirement path and it is reversible.

**Archive makes the Group read-only.** Today it only hides the Group from the rail. From now on an archived Group, or a time-boxed Group past its end date, refuses every write to its content: no Sign-up, no Shift, no Document, no Meeting, no roster change. Reading stays open to whoever could read it before. The super-tier may unarchive. One `isActive()` check in the policies' base path covers this. _(Amended 2026-10-25: the check lives in `Gate::before`, not in the policies. A policy cannot deny the super-tier, because [ADR-0017 §1](0017-authorization-enforcement.md)'s grant short-circuits it. So `Gate::before` returns `false` for a write ability on an inactive Group, before that grant. The write abilities are listed in `App\Support\Authorization\GroupWrites::WRITE_ABILITIES`; a new Group-scoped write ability must be added there. DMV Settings authorizes on the Group-less `administer-groups` ability, so archive and unarchive are never refused this way.)_

**A Group that holds a Stewardship cannot be archived.** Reassign the stewardship first. Otherwise archiving Records would leave nobody able to administer Members.

### 3. Where it lives: DMV Settings, two sections

**Everything lands in Officer Tools → DMV Settings**, the rail item ADR-0018 §4 reserved. It is one rail item with two sections:

- **Groups.** The org tree, a Create button, and the full structure form for each Group. Archived Groups are listed under their parent, marked as archived.
- **Stewardships.** The page #815 describes.

**The Group's own Settings tab is unchanged.** Officers still find reminders, empty-desk, self-serve, Shift kinds and Objects there. Structure is not a Group officer's setting, so it does not go on that tab. The super-tier administers every Group from one place and never leaves Officer Tools to do it. ADR-0027's rule stands: Group-scoped settings on the Group, org-wide settings in the rail. Which Groups exist, where they sit and what they can do is an org-wide fact.

The route gates on the super-tier, as ADR-0027 §4 requires of every Officer Tools page.

### 4. The Create form

**Four fields: name, slug, parent, kind.** The slug is proposed from the name and may be edited on this form only. Everything else takes a default and is set on the structure form afterwards. Parent is a select of active Groups, root included. Kind is a select of the six kinds. _(Amended 2026-10-26, [#850](https://github.com/roytanaka/dmv-rom-v2/issues/850): the root is never a parent. A Group always sits under one of the root's children: a Container, or a real Group such as Special Projects. The parent is picked in two selects. **Section** lists the root's active children. **Parent** lists the chosen section's subtree, the section first. The server refuses the root as a parent, so a crafted request fails too. A Container is still made by the seeder only, because it sits directly under the root. The Create form leaves Container out of Kind, and the server refuses it.)_

### 5. The structure form

**Super-tier edits:** name, parent, kind, listing visibility, display order (a number), logo, time-boxed plus start and end dates, the six capability flags, the four visitor-figure collection flags, the hours multiplier, and the archived state. A "last changed by X on date" line sits at the foot.

**Group officers keep description and banner only**, edited inline on the Overview as ADR-0027 §2 decided.

**The slug is immutable after create.** It is the key in bookmarked and emailed links, in every help article, in the French route map (ADR-0008), and two slugs are constants in code. A redirect table for renamed slugs is not worth building for a rename nobody has asked for.

**Root and Container Groups are not editable past description and banner.** The root is the organisation and super-tier territory by definition. A Container is a nav peer with no page (PRD #289). The root is a valid parent in the Create form. _(Amended 2026-10-26, [#850](https://github.com/roytanaka/dmv-rom-v2/issues/850): the root is not a parent choice in either form. The structure form uses the same Section and Parent selects as the Create form and opens on the Group's current pair. A Group already directly under the root keeps the root as its current parent and can be saved in place, but no Group moves under the root. Changing a Group's Kind to Container on this form stays as it is.)_

**Parent and kind changes are allowed, with no preview.** The parent select excludes the Group itself and its descendants. No ADR constrains which kind may sit under which, and the code checks none, so the form does not either. The rail rebuilds on the next request and the change is reversible. The form notes that the Container kind makes the page a 404.

**A capability flag cannot be turned off while a Member holds a role that needs it.** Scheduler, Booker, Librarian and the rest each require a flag (`Role::requiredCapability()`). The form blocks the save and names the roles; the officer reassigns, then turns the flag off. The Group's records (Shifts, Documents, Meetings) stay in place with a one-line count on the form, unreachable until the flag comes back. No cascade code and nothing is destroyed.

### 6. Scope is withdrawn

**The `scope` column and the `Scope` enum are removed.** ADR-0010 defined Scope (Organization, Program, Sub-team) as "where does it sit in the tree". The tree answers that itself, through `parent_id`, and nothing ever read the column. The form does not show it. One migration drops it.

### 7. Stewardships, as #815 proposed

**#815 is adopted as written.** The Stewardships section lists each stewardship with a plain description of the authority it gives and the Groups that hold it. `member_admin` is a single select, exactly one holder, and the last holder cannot be removed. `org_mail` is a multi-select of active Groups. Before a save, the page shows the Members who gain or lose the authority. **`statistics` and `website` stay hidden** until a feature reads them; the enum cases remain for that day.

Roster changes still decide who is in each Group. This page decides only which Groups hold a job.

### 8. Authorship: `created_by` and `updated_by`, on these two tables, and that closes #334

**A column convention, not an audit log.** `groups` and `group_stewardships` gain nullable `created_by` and `updated_by` foreign keys to `members`, plus the existing timestamps. The structure form and the Stewardships page show "last changed by X on date". Seeded rows have null authors and show no line.

**#334 is settled as: authorship is recorded where a screen shows it, as columns on that table.** No general observer, no package, no activity table. The three one-off columns that already exist stay. A later feature that needs "who changed this" adds the same two columns and shows them. The day someone needs "what happened to this row over time", that is a new decision, not this one stretched.

ADR-0021 §1's note that "no model in this app carries one" is amended: the convention now exists.

### 9. Root membership is derived only, and that closes #288

**Root membership is derived from Category and never stored.** The demo seeder stops writing `group_members` rows for `dmv`. The seeded rows go. The rail already ignores them (it drops the root row and re-adds a derived node); now nothing needs to. The root's Roster is the Directory, as ADR-0020 §B says. In-app Group creation would have been a third place to keep those rows in step; now there is none.

### 10. Roles stay an enum

**No roles catalog.** Roles are the `Role` enum and change with a deploy. The grill confirmed that is fine: role names are the vocabulary of ADR-0011 and the policies read them by case. A roles screen would be a new permission system, and nobody has asked for one.

## Interaction with Beta and the import

Staging reseeds on every push (ADR-0007), so a Group created on staging lives until the next deploy. The demo seed stays the source of the demo org tree. Group administration earns its keep on Beta and after Cutover (ADR-0035).

**Every Group field is v2-only.** The Legacy app has no Group table; the import never writes `groups` or `group_stewardships`. The migration spec must list them as v2-only so the nightly import leaves them alone: `name`, `slug`, `parent_id`, `kind`, `listing_visibility`, `display_order`, `logo_key`, `banner_key`, `description`, `time_boxed`, `start_date`, `end_date`, `has_*` (six), `collects_*` (three) and `visitor_figures_await_booking`, `hours_multiplier`, `lifecycle_state`, `created_by`, `updated_by`, and every `group_stewardships` row.

## Considered alternatives

- **A new `org_admin` stewardship, or reusing `website`.** Rejected: a second org-wide grant beside the super-tier, with no holder in sight. Easy to add later.
- **Seed-only until Cutover; build only the Stewardships page.** Rejected: ranked second on the build list because the alternative is a developer forever.
- **The structure form on the Group's own Settings tab, super-tier only.** Rejected after an ASCII mock-up of both layouts: it puts the same Group's settings in two tabs for two audiences, and the super-tier must open each Group to administer it. One admin area wins.
- **A preview of the nav effect before a parent move.** Rejected: UI work for a rare, reversible change.
- **Strip roles on save when a flag is turned off.** Rejected for a block: roles are few and visible, so a block costs one reassignment, and nothing is removed behind the officer's back.
- **Block a flag turn-off if any data exists.** Rejected: a Group with one old Document could never turn Documents off.
- **A full activity log (own table or a package).** Rejected: infrastructure ahead of use, for a sole engineer with no live users.
- **Keep `scope`, hidden.** Rejected: dead code is excised, not hidden.
- **Root membership stored and canonical.** Rejected: ADR-0020 §B already chose derived, and the two regressions came from keeping both.
- **Slug editable with a redirect table.** Rejected: nobody has asked to rename a Group, and 41 help articles would still go stale.

## Consequences

- **Officer Tools → DMV Settings** stops being a stub and gates on the super-tier. Two sections, Groups and Stewardships, with French route segments (ADR-0008).
- **`UpdateGroupRequest` stays as it is** (description and banner). Structure writes go through new super-tier endpoints.
- **Policies deny writes to inactive Groups.** Expired time-boxed Groups become read-only, which they were not before.
- **A migration drops `scope`** and the `Scope` enum, its cast, and its seeder uses go with it.
- **A migration adds `created_by` and `updated_by`** to `groups` and `group_stewardships`.
- **`DemoSeeder::enrollEveryoneInRoot` is deleted.** Tests that assert on a stored root row change to assert on the derived node.
- **Archive gains a guard** (no stewardship held) and unarchive exists.
- **#815, #288 and #334 close** when the tickets ship. #262 (Manage Events for Gallery Interpreters) is checked against ADR-0026 at ticket time.
- **Help articles** (ADR-0025): one for Groups administration, one for Stewardships, super-tier required role.
- **The design pass**: the Groups tree and structure form get a dedicated design ticket before build, so the UI is right the first time.
- **ADR-0019 §B** remains deferred. This ADR does not build the parent-officer cascade.

## References

- [ADR-0008](0008-bilingual-url-routing.md) — French route segments; slugs in the route map
- [ADR-0010](0010-group-model.md) — the Group axes; Scope withdrawn here
- [ADR-0011](0011-authorization-model.md) — super-tier and stewardships
- [ADR-0017 §1](0017-authorization-enforcement.md) — `Gate::before` grants super-tier everything
- [ADR-0018 §4](0018-server-driven-grouping-rail.md) — Officer Tools reserves DMV Settings
- [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md) — listing visibility; parentage cascade still deferred
- [ADR-0020 §B](0020-groups-nav-partition-and-sidebar-shape.md) — root membership derived from Category; enforced here
- [ADR-0021 §1](0021-scheduling-first-pass.md) — the "no `created_by`" note; amended here
- [ADR-0027](0027-group-settings-tab.md) — Group-scoped settings on the Group, org-wide in the rail
- [ADR-0035](0035-beta-and-cutover.md) — the import overwrites Legacy-sourced fields only
- [#815](https://github.com/roytanaka/dmv-rom-v2/issues/815), [#288](https://github.com/roytanaka/dmv-rom-v2/issues/288), [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) — the issues this settles
- `app/Enums/Role.php` `requiredCapability()` — the role-to-flag map the turn-off guard reads
