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

**Only the super-tier administers Groups and Stewardships.** No new stewardship, no reuse of the unused `website` one. One explicit org-wide grant exists and this is org-wide work. ADR-0019 §B's cascade to parent officers stays unbuilt; this ADR neither builds nor withdraws it. If a real holder outside the super-tier asks for the job, that is the moment to add a stewardship for it. _(Amended 2026-10-29, Tester feedback items 92 and 93: a Group's Secretary or Chair now administers the structure and roster of the Groups below their own. That builds ADR-0019 §B for those operations. See §11. Top-level Groups, a Group's own structure, and Stewardships stay super-tier only.)_

### 2. Create, edit and archive in-app. Never delete.

**A Group is created, edited and archived in-app.** There is no delete. A Group that was ever real has Memberships, Hours, Documents and Shifts hanging off it, and history must stay readable. Archive is the retirement path and it is reversible.

**Archive makes the Group read-only.** Today it only hides the Group from the rail. From now on an archived Group, or a time-boxed Group past its end date, refuses every write to its content: no Sign-up, no Shift, no Document, no Meeting, no roster change. Reading stays open to whoever could read it before. The super-tier may unarchive. One `isActive()` check in the policies' base path covers this. _(Amended 2026-10-25: the check lives in `Gate::before`, not in the policies. A policy cannot deny the super-tier, because [ADR-0017 §1](0017-authorization-enforcement.md)'s grant short-circuits it. So `Gate::before` returns `false` for a write ability on an inactive Group, before that grant. The write abilities are listed in `App\Support\Authorization\GroupWrites::WRITE_ABILITIES`; a new Group-scoped write ability must be added there. DMV Settings authorizes on the Group-less `administer-groups` ability, so archive and unarchive are never refused this way.)_

**A Group that holds a Stewardship cannot be archived.** Reassign the stewardship first. Otherwise archiving Records would leave nobody able to administer Members.

### 3. Where it lives: DMV Settings, two sections

**Everything lands in Officer Tools → DMV Settings**, the rail item ADR-0018 §4 reserved. It is one rail item with two sections:

- **Groups.** The org tree, a Create button, and the full structure form for each Group. Archived Groups are listed under their parent, marked as archived.
- **Stewardships.** The page #815 describes.

**The Group's own Settings tab is unchanged.** Officers still find reminders, empty-desk, self-serve, Shift kinds and Objects there. Structure is not a Group officer's setting, so it does not go on that tab. The super-tier administers every Group from one place and never leaves Officer Tools to do it. ADR-0027's rule stands: Group-scoped settings on the Group, org-wide settings in the rail. Which Groups exist, where they sit and what they can do is an org-wide fact.

The route gates on the super-tier, as ADR-0027 §4 requires of every Officer Tools page. _(Amended 2026-10-29: DMV Settings also opens to a Member who administers any Group, scoped to the subtrees below their Groups. See §11.)_

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

### 11. Parent officers administer their sub-groups (amended 2026-10-29)

From a grilling session on 2026-10-29, on Tester feedback items 92 and 93. **This is parity, not new scope.** The Context above says the Legacy app had no screen to add a Group. That is true for top-level Groups only. On Docents, Gallery Interpreters and Guides du ROM, the Chair had a SubCommittees button on Who's Who. On Membership, the Chair or the Records officer had it. Behind it, the Chair listed, added, changed and deleted sub-committees, and managed their members (`servicesp.php`, `_showSubCommittees` and `_secretaryManageMembers_sub`). Legacy kept sub-committees in their own tables. In v2 they are Groups.

**Who.** A Group's Secretary or Chair (Chair-implication as usual) administers every Group in the subtree below it, to any depth. This is ADR-0019 §B as written. One rule for every Group, not only the four Legacy ones. Membership gets no Records special case: a Records person who needs it gets the Secretary role. **Their own Group is not in scope.** They cannot rename, move or archive it. That stays with the super-tier. The root and Containers have no ordinary officers, so the cascade from them grants nothing.

**What: structure and roster only.** A parent officer may create a sub-group, rename it, move it within their subtree, archive and unarchive it, and manage its roster: add, change standing, assign and revoke roles, resign, hard-remove. **Content is not conferred.** A parent officer gets no Meetings, Documents, About text or banner in the sub-group from parentage. To read or write them, they add themselves to the sub-group, which shows on its roster. This is ADR-0019 §B's structure/content split. The mechanism is a separate ancestor check used only by the structure and roster abilities. `administers()` stays scoped to the Group itself, because it also gates content.

**Where.** Officer Tools → DMV Settings → Groups, the same place the super-tier works. A parent officer sees the rail item, and the Groups section shows only the subtrees below their Groups. They do not see the Stewardships section. Group Settings gets one "Manage sub-groups" link that opens DMV Settings on that Group, because Testers looked there first. ADR-0027's split stands: the link goes to the feature, it does not move it.

**The Create form for a parent officer.** Three fields:

- **Name.** A sibling with the same name under the same parent is refused.
- **Parent.** A select of their own Group and its active descendants.
- **Chair** (optional). A search over the parent Group's active roster. The chosen Member joins the sub-group as Full with the Chair role, in the same save.

**The slug is generated, never shown.** It comes from the name. If it is taken, the parent's slug goes in front (`docents-sections`). If that is taken too, a number follows (`docents-sections-2`). A parent officer cannot fix a slug clash, so the form never asks them to. The super-tier keeps the editable slug on its own form.

**Defaults.** Kind is Working group. Meetings and Documents are on, and every other capability flag is off. Listing visibility is Group, the hours multiplier is 1, and there is no time-box. This matches a Legacy sub-committee, which had its own Meetings, Publications, Who's Who and Extra Hours. Hours need no flag (ADR-0022). Anything else is a request to the super-tier.

**The structure form for a parent officer.** Name and parent only. The Chair is changed on the sub-group's roster, like any role. Kind, capabilities, collection flags, visibility, display order, logo, time-box and hours multiplier stay super-tier only.

**Deviations from Legacy, each raised and accepted:**

- **Archive, not delete.** Legacy deleted a sub-committee. §2 stands: a sub-group soon carries Memberships and Hours, and history must stay readable.
- **The description is on the Overview.** Legacy set it on the create form. It is content, so the sub-group's own officers write it on the Overview.
- **The parent Chair joins to read content.** Legacy let the parent Chair open a sub-committee's Meetings and Documents without membership. ADR-0019 §B forbids that, and a `Private` sub-group stays private from the parent Chair.
- **Chair on create only.** Legacy also changed the Chair on its edit screen. The roster already handles a Chair change, including the old Chair's role.

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
- **Widen `administers()` up the ancestor chain** (§11). Rejected: it also gates the About text, the banner and Meetings, so the parent Chair would get the sub-group's content, which ADR-0019 §B forbids.
- **Sub-group management in Group Settings** (§11). Rejected: it splits Group administration across two places. A link from Group Settings covers discoverability.
- **A slug field for parent officers, with a clash error** (§11). Rejected: a Chair cannot know what a slug is or how to fix one.
- **Every capability off for a new sub-group** (§11). Rejected: Legacy sub-committees had Meetings and Documents, so every new one would need a super-tier request.
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
- **ADR-0019 §B** remains deferred. This ADR does not build the parent-officer cascade. _(Amended 2026-10-29: §11 builds it for structure and roster.)_
- **DMV Settings gains a scoped mode** for parent officers (§11): the rail item, the Groups section filtered to their subtrees, a three-field Create form and a two-field structure form. A Group Settings link opens it. The roster policy gains the ancestor check for the roster abilities. Add a help article for Chairs, and a demo persona who chairs a sub-group.

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
