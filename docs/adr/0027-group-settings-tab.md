---
status: accepted
date: 2026-09-22
accepted: 2026-09-22
---

# Group Settings tab: where an officer's configuration lives

A design conversation on 2026-09-22, prompted by the Gallery Interpreters Scheduling tab as a Scheduler sees it. It **amends [ADR-0021 §6](0021-scheduling-first-pass.md)**, which said Group scheduling would never grow a sibling tab, and **[ADR-0023 §5](0023-scheduling-second-pass.md)**, which restated it. It **reaffirms [ADR-0018 §4](0018-server-driven-grouping-rail.md)** (Officer Tools is org-wide only) and **[ADR-0013](0013-app-shell-section-nav.md)** (chrome carries cross-domain navigation, the body carries within-domain navigation). Amendment notes are folded back onto ADR-0021 and ADR-0023.

## Context

The Scheduling tab was built to ADR-0021 §6: one section tab, with everything an officer can do rendered inline behind `can` props. Six slices later that page is `GroupScheduling.vue`, 2,078 lines, and a Scheduler who opens the list view scrolls past five configuration cards before the Schedules they came for:

- Reminders settings ([#486](https://github.com/roytanaka/dmv-rom-v2/issues/486), ADR-0024 §7)
- Empty-desk settings ([#487](https://github.com/roytanaka/dmv-rom-v2/issues/487), ADR-0024 §7)
- Self-serve settings ([#582](https://github.com/roytanaka/dmv-rom-v2/issues/582), ADR-0026 §1 and §2)
- Shift-kind maintenance, with the off-site flag ([#567](https://github.com/roytanaka/dmv-rom-v2/issues/567), [#587](https://github.com/roytanaka/dmv-rom-v2/issues/587))
- Objects maintenance ([#584](https://github.com/roytanaka/dmv-rom-v2/issues/584), ADR-0026 §3)

A Member never sees these cards, so the Member's page is fine. The officer's page is not. The cards are touched a few times a year. Schedules and Shifts are touched every week. Putting the rare thing above the frequent thing is the wrong order, and the inline pattern has no place else to put it.

The question raised was wider than scheduling: should every admin function leave the main area and live in the rail's **Officer Tools** cluster, so the main area is for Members and the rail is for officers? That is a clean mental model. It runs into two facts.

- **Most administration in this app is Group-scoped.** Shift kinds, Objects, reminders, the roster, meetings: each belongs to one Group, and a Chair of two Groups administers two of everything. The rail answers "which Group am I in". A rail entry for Group-scoped administration would need a Group picker of its own, and ADR-0013's rule that chrome is cross-domain and the body is within-domain would be gone.
- **Officer Tools already has a meaning.** ADR-0018 §4 defines it as the org-wide cluster (Members, Communications, Reports, Flash Messages, DMV Settings) and forbids conflating it with a Group's officers. All five of its routes are still `ComingSoon` stubs, which is why the cluster looks unused. It is reserved, not spare.

ADR-0021 §6 rejected a Group admin tab on one ground: "no admin-tab precedent anywhere in the repo". Two things have changed. The stubbed Group Menu fixture has carried a Chair-gated `schedule_admin` tab since ADR-0018, so the precedent existed in the design all along. And GitHub's repository Settings tab, visible only to those who can administer the repository, is the pattern most of this app's builders already know.

## Decision

**Split by scope, not by who is looking.** Org-wide administration lives in the rail's Officer Tools cluster, as ADR-0018 §4 says. Group-scoped administration lives on the Group page, in a new section tab called **Settings** that only officers see. The section tabs are operations, for everyone who can open the Group. The Settings tab and Officer Tools are administration, each at its own scope.

### 1. One Settings tab per Group, officers only

**A section tab labelled `Settings`, last in the strip, at `/groups/{slug}/settings`.** It renders when the viewer holds at least one Group-scoped configuration right on that Group. Today that is any of `can.update`, `manageReminders`, `manageEmptyDesk`, `manageSelfServe`, `manageShiftKinds` or `manageObjects`. A Member with none of them never sees the tab, and the route answers 403, the Meetings shape, because the Group exists and they may open it.

The tab appears with authority, not with data. ADR-0021 §6's rule that a tab must not come and go with content still holds: a Chair sees Settings on every visit, empty or full.

The label is `Settings` and not `Admin` or `Manage`. `Admin` is the word `CONTEXT.md` forbids unqualified, and ADR-0020 rejected `Administration` for colliding with it. `Settings` now appears at three scopes in the app, and the three line up: **Account settings** (the user menu, me), **Group Settings** (this tab, this Group), **DMV Settings** (Officer Tools, the organisation). Each says whose settings from where it sits.

### 2. What moves to the tab, and what stays inline

**The rule: a setting moves, a record stays.** A setting is a value an officer sets once and the app reads on every later action: a switch, a unit length, a list of kinds. A record is a row the app stores per event: a Shift, a Sign-up, a Meeting, a roster line. Settings go on the Settings tab. Records are authored inline on the section where they are read, exactly as ADR-0021 §6 and ADR-0023 §5 built them.

Moves to the Settings tab, as cards, each rendered only when its own gate passes:

| Card                        | Today                          | Gate                |
| --------------------------- | ------------------------------ | ------------------- |
| About text and banner       | Overview, inline edit          | `can.update`        |
| Reminders                   | Scheduling list view           | `manageReminders`   |
| Empty-desk alert            | Scheduling list view           | `manageEmptyDesk`   |
| Self-serve shifts and unit  | Scheduling list view           | `manageSelfServe`   |
| Shift kinds, with off-site  | Scheduling list view           | `manageShiftKinds`  |
| Objects                     | Scheduling list view           | `manageObjects`     |

The scheduling cards render only while `has_scheduling` is on, the same condition as the Scheduling tab itself.

Stays inline, unchanged:

- Scheduling: new Schedule, edit, publish, delete; new Shift, bulk Shifts, bulk place; place a Member; the officer's visitor-count correction; recalculate this month.
- Roster: add a Member, edit roles, remove.
- Meetings: new meeting, per-row edit.
- Hours: the officer's report links.

These are frequent, and each acts on a record the officer is looking at. Moving them out would send a Chair to another page to place one Member on one Shift. The inline pattern was right for them and stays.

About text and the banner are the one judgment call in the table. They are content Members read, not behaviour, and an inline edit on the Overview is defensible. They move because the Overview is the Group's front door and the edit controls are the only officer-only thing on it. With them gone, every section tab renders the same page for an officer as for a Member, apart from record authoring. That is the division the tab exists to create.

### 3. One page, cards in order

**The Settings tab is one page of cards** in the order of the table above: Group first, then Scheduling. No sub-navigation and no nested routes on day one. If a card outgrows the page, it takes a nested route under the tab, `/groups/{slug}/settings/shift-kinds`, following ADR-0021 §6's escape hatch one level down. The tab is the entry point that escape hatch was missing.

On small screens the tab is one more row in ADR-0013's labelled dropdown. Nothing new.

### 4. Officer Tools stays org-wide, and gets its two orphans

**Nothing Group-scoped goes in the rail.** ADR-0018 §4 stands as written.

Two super-tier pages exist today with no home in this model: `/mail-status` (ADR-0024 §10, reached from the user menu) and `/help-status` (ADR-0025 §9, linked from nowhere). Both are org-wide administration. **Both become Officer Tools items**, under the same super-tier gate they carry now. The user menu keeps personal things: profile, account settings, language, renew, sign out. This amends ADR-0024 §10's placement; the page is unchanged.

**The Officer Tools routes gate on the same ability as their rail item.** Today the five stub routes require only `auth`; the rail hides an item its viewer may not use, but the URL does not. Each real page lands behind its gate when it replaces its stub. This is ADR-0017's boundary rule applied, not a new rule.

## Considered alternatives

- **All administration in the rail's Officer Tools cluster**, the proposal that opened the conversation. Rejected for Group-scoped administration: the rail is where a Member picks a Group, and a Group-scoped tool in it needs a second Group picker. Kept for org-wide administration, where it already lives.
- **A nested route under Scheduling, `/groups/{slug}/scheduling/edit`**, ADR-0021 §6's own fallback. Rejected: it needs a gear button somewhere on the Scheduling tab as its entry point, it covers only scheduling settings, and a second such route for the Overview's edit would follow. The tab is the entry point, covers the Group, and is one fewer thing to build.
- **A per-section Settings sub-tab** (Scheduling → Settings). Rejected: it puts a second tab strip inside a tab strip, which is what ADR-0013 was written to keep simple on a phone.
- **A mode switch on the Scheduling tab** ("Manage" on/off). Rejected: it keeps the 2,078-line component and adds a state to it. The problem is what is on the page, not when it shows.
- **Keep everything inline and reorder the page**, cards below the Schedules. Rejected: it fixes the scroll and nothing else. The officer still reads five settings cards on every visit, and the Objects screen ADR-0026 §3 asks for has nowhere better to land.

## Consequences

- **Amends ADR-0021 §6 and ADR-0023 §5.** "Never a sibling tab" is withdrawn for Group-scoped settings. Inline authoring of records is unchanged.
- **A new section `settings`** on `GroupController::show`, gated 403 by any of the six `can` values, and a French path segment per ADR-0008. The controller loads shift kinds and Objects for the Settings section, not the Scheduling one.
- **`GroupScheduling.vue` loses five cards** and the six props that drive them. `Show.vue` loses the About edit and the banner picker. A new `GroupSettings.vue` gains them. The write endpoints are unchanged.
- **The Group Menu fixture's `schedule_admin` stub** is retired; the Settings tab is what it was a placeholder for.
- **`/mail-status` and `/help-status` move from the user menu and from nowhere** into the Officer Tools rail cluster, super-tier gated. `RailNavTest` grows two items.
- **Help articles** (ADR-0025): the Objects and shift-kind maintenance articles ([#600](https://github.com/roytanaka/dmv-rom-v2/pull/600)) and any reminder or empty-desk article re-shoot on the Settings tab. One new article for the tab itself.
- **The Officer Tools stubs** each land behind their rail gate when built. No change until then.
- **Visitor Wayfinders' ShiftKind maintenance** (ADR-0023 deferred list) has a home before it is built.

## References

- [ADR-0013](0013-app-shell-section-nav.md) — chrome is cross-domain, the body is within-domain; the small-screen dropdown the tab joins
- [ADR-0017 §9](0017-authorization-enforcement.md) — `can` props are hints; the route is the boundary
- [ADR-0018 §4](0018-server-driven-grouping-rail.md) — Officer Tools is org-wide; reaffirmed
- [ADR-0020](0020-groups-nav-partition-and-sidebar-shape.md) — `Administration` rejected as a label
- [ADR-0021 §6](0021-scheduling-first-pass.md) — one surface, inline authoring; amended here
- [ADR-0023 §5](0023-scheduling-second-pass.md) — "no admin tab"; amended here
- [ADR-0024 §7 and §10](0024-emailing-model.md) — the reminder and empty-desk settings; the mail status page's placement, amended here
- [ADR-0026 §3](0026-gallery-interpreters-self-serve-shifts.md) — the Objects maintenance screen this gives a home to
- `resources/js/components/GroupScheduling.vue` — the page that prompted this
- `resources/js/chrome/fixture.ts` — the `schedule_admin` stub this retires
