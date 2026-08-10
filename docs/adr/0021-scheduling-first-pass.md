---
status: accepted
date: 2026-08-09
accepted: 2026-08-09
---

# Scheduling first pass: Schedule, Shift, ShiftKind, Sign-up

From the wayfinder map [#323](https://github.com/roytanaka/dmv-rom-v2/issues/323) (_scheduling — Schedule, Sign-up, and the Scheduler_), which the requirement [#306](https://github.com/roytanaka/dmv-rom-v2/issues/306) was deferred to. Eight decision tickets over 2026-08-07 → 2026-08-09 settled the model; this ADR is where their answers land. It **refines [ADR-0015](0015-scheduling-model.md)** — supplying the container ADR-0015 never named, renaming its **Catalog** to **ShiftKind**, retiring its **Repeat rule**, and marking its _group booking_ / _guest manifest_ strategies deferred — and **amends [ADR-0011](0011-authorization-model.md)** (membership is the only path to a per-Group role) and **[ADR-0017 §6](0017-authorization-enforcement.md)** (Sign-up names are readable by anyone who can read the Schedule). Amendment notes are folded back onto those ADRs.

## Context

[ADR-0015](0015-scheduling-model.md) fixed the _architecture_ of scheduling — one capability attached per Group, with per-Group strategies — but it was written before anyone had read the legacy schema end to end. It named four core entities (Shift, Sign-up, Repeat rule, Catalog) and **no container**, which is the gap that made "what is a schedule, exactly?" unanswerable in every downstream discussion.

The legacy system has two irreconcilable scheduling shapes: a **monthly** one keyed `YYYYMM` (Docents, GDR, Gallery Interpreters, Visitor Guides, Reception, ROMBus, Outreach, Walker, ROM Travel) and a **date-range** one for Visitor Wayfinders (`specialEvents` — name, start, end). Each carries its own lifecycle (`edit → signup → freeze → confirm → final` vs a `visible` flag), its own presentation column (`DisplayStyle`), and its own admin screens. A schema inventory across every scheduling Group ([#325](https://github.com/roytanaka/dmv-rom-v2/issues/325), full document on branch `research/legacy-scheduling-schema`) plus a recorded screen-by-screen walkthrough with Adrian established what is load-bearing and what is workaround.

The finding that shaped everything else: **most of legacy's elaboration encodes workarounds for capabilities it lacked**, not requirements. Capacity is N byte-identical rows because there was no capacity integer. Reception's famous two-week red/blue pattern exists because there was no way to express a recurring _sign-up_. `Count` means hours in one table and tours in another, reconciled by a hardcoded per-Group multiplier. The `YYYYMM` column is why there are two shapes at all.

So the first pass is deliberately **three verbs** — _read a Schedule_, _take a Shift_, _build a Schedule_ — and nothing else.

## Decision

Four entities, no strategies, no per-Group special cases.

### 1. `Schedule` — the container ADR-0015 was missing

**One entity covering both legacy shapes.** A monthly Group's "August 2026" and a Visitor Wayfinders occasion are the same thing with different ranges and names, not two types. **A Schedule covers a date range**; a calendar month is the common case, not a separate kind.

| Field         | Notes                                                                     |
| ------------- | ------------------------------------------------------------------------- |
| `group_id`    | required, exactly one                                                     |
| `name`        | **required**, typed by the Scheduler — no derived default, no pre-filling |
| `starts_on`   |                                                                           |
| `ends_on`     | **required**                                                              |
| `state`       | `draft` \| `published`                                                    |
| `description` | optional                                                                  |
| timestamps    |                                                                           |

**No `YYYYMM`**, no monthly marker, no skip-dates, no `visible` flag separate from `state`, and **no `created_by`** — no model in this app carries one, and inventing an authorship convention for a single scheduling table would make it a one-off ([#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) settles it app-wide).

`name` and `description` are typed by an officer, so under [ADR-0004](0004-chrome-only-translation.md) they are **as-authored content**: one column, stored in the language written, **never translated**. The same rule covers a `ShiftKind`'s label.

**Two states, and one audience each:**

- **`draft`** — visible to exactly whoever passes the schedule-admin gate on that Group: `Scheduler` role holders (per [ADR-0011](0011-authorization-model.md), `Chair` implies it within its own Group) and super-tier. Not ordinary Group members, and **not a parent Group's Chair** — parentage carries structural authority, never content read ([ADR-0019](0019-group-listing-visibility-and-parentage-authority.md)).
- **`published`** — the Group's `listing_visibility` audience ([ADR-0019](0019-group-listing-visibility-and-parentage-authority.md)). You cannot read the Schedule of a Group you cannot see.

Legacy's five states are all dropped. **Accepted consequence: there is no way to close sign-ups early** — sign-ups close only when a Shift's date passes. `freeze` returns if a Chair asks for it in anger; it is not built in advance.

**Publication is a visibility switch, not a freeze.** Shifts may be added to a `published` Schedule: adding one is purely additive, nothing already read becomes wrong, and no Sign-up is disturbed.

**Reversibility is by making bad states unreachable, never by unwinding them:**

- **Un-publish is permitted only at zero Sign-ups.** Legacy's UN-publish hides the Schedule and unwinds nothing, leaving Sign-ups invisible and live — a bug. A Chair who published early clears the Sign-ups first, visibly.
- **Deletion mirrors it** — allowed only at zero Sign-ups. A Schedule with history is therefore permanent, which is what makes it safe as a copy-from reference and safe for whatever hours/credit path lands later.
- **The date range is enforced.** A Shift must fall inside `[starts_on, ends_on]`, and shrinking a range is blocked while Shifts sit outside it.

**No `archived` and no `final`.** Past-ness derives from `ends_on`. Schedules are kept indefinitely and stay selectable long after their dates pass.

**Schedules may overlap in time.** No constraint forbids it — which is precisely why `Shift.schedule_id` is a mandatory FK rather than a date-range query, and why there are **no orphan Shifts**: a genuinely one-off Shift is a Schedule with a one-day range, one extra row instead of a nullable FK that branches every read, publish check, and authorization query two ways.

### 2. `Shift` — a slot, not a seat

| Field           | Notes                       |
| --------------- | --------------------------- |
| `schedule_id`   | mandatory FK                |
| `starts_at`     | required                    |
| `ends_at`       | **required**                |
| `capacity`      | integer, default 1          |
| `shift_kind_id` | **nullable** FK             |
| `audience`      | `group` (default) \| `open` |
| timestamps      |                             |

**Nothing else.** No `Count` in either of its senses, no `location`, no state column, no free-text note, no duration column (duration is always derived), and no uniqueness constraint on identical Shifts.

**Capacity is an integer; a Shift is full when its Sign-up count reaches it.** Legacy's N-byte-identical-rows is rejected — it is why changing `Required` on a Wayfinders event runs `DELETE FROM specialSchedule` and destroys every existing sign-up. Three consequences, all wanted: raising capacity is one `UPDATE`; **per-seat data belongs on the Sign-up** (each volunteer's own hours, each volunteer's own visitor count); and **lowering capacity below the current Sign-up count is blocked** — the Scheduler removes people first, visibly.

**A Shift may hold many Sign-ups**, and the legacy evidence for that is not Wayfinders' seat-rows but **ROMBus** (`rombusToTrip`) and **Walker** (`walkerSchtourToWalker`, which carries its own `Visitors` per member per walk — the per-seat rule appearing independently in legacy data).

**One descriptive axis, not two.** Adrian named _location_ as Gallery Interpreters' distinguishing attribute and _activity_ as Wayfinders'. But Wayfinders' activities **are** locations ("Level 1 Oslo gate"), GI's "locations" already smuggle a second meaning, and Reception has no kind at all. Two columns would mean no legacy Group's data splits cleanly between them. A Group whose kinds are places names them that way.

**A Shift has no state.** It inherits everything from its Schedule. **Cancelling a Shift is deleting it, permitted only at zero Sign-ups** — the same rule as un-publish.

**`ends_at` is required**, which makes "has this passed" and "do these overlap" one comparison each. Accepted cost: Docents and GDR store only a start time in legacy, so their migration must supply an end time legacy never recorded. Nothing in the first pass _reads_ duration, so requiring it costs the first pass nothing and hands the eventual hours work an unambiguous field instead of `Count`.

**Two identical Shifts in one Schedule are permitted.** Under the slot model this is almost always a Scheduler slip that should have been `capacity: 2`, but "no two Shifts are ever identical" is the kind of rule that holds until the year a Group wants two separately-managed slots in the same hour. The mistake is visible on screen and fixed by deleting a row.

### 3. `ShiftKind` — ADR-0015's Catalog, renamed and scoped

**Entity `ShiftKind`, table `shift_kinds`, scoped per Group.** Columns: `group_id`, `name`, `active`, sort order. `Shift.shift_kind_id` is nullable — Reception's Shifts carry null, because legacy hardcodes the string `"Desk"` in PHP.

**Why an entity and not a free string:** even Visitor Guides — the Group whose kind _looks_ like free text — reads its vocabulary from a `vgShiftTypes` table. And a free-typed string cannot carry a qualification requirement.

**Why not "Catalog":** the word is already generic in this repo three times over — the **content catalog** is a Group capability ([ADR-0010](0010-group-model.md)), the **role catalog** is [ADR-0011](0011-authorization-model.md)'s closed list, and the **persona catalogue** is [ADR-0009](0009-user-switching-and-support-impersonation.md)'s. It also names the container rather than the row (there is no container object), and in a museum a catalogue is the collection record. `ShiftKind` fans in all five legacy names cleanly — `Activity`, `Role`, `Type`, `TripTypeID`, `TourTypeID` — and ADR-0015's own definition already contained the words: "the small per-Group list of _kinds of shift_".

**The maintenance screen is deferred.** Rows are seeded; the _Update Activities List_ CRUD screen arrives with Visitor Wayfinders, the only Group that churns them. The first pass's Groups have two kinds between them.

**Requirements hang off the kind, never off the Shift.** Legacy Docents put the qualification on the _tour_, and every dated Shift of that tour inherits it; that matches how it is spoken about ("she's cleared for Highlights") and means one edit rather than one per dated Shift. **A requirement points at a named `Qualification`** — the kind _requires_ it, a Member _holds_ it.

**The first pass builds none of it** — not the `qualifications` table, not the kind↔qualification relation, not the member↔qualification relation. What the first pass buys is that `ShiftKind` exists, so the requirement has a home when the credential PRD lands. **The slot is decided; it stays empty.**

### 4. `Sign-up` — one Member on one Shift, two possible actors

**A Sign-up is one Member on one Shift, created by either the Member or a Scheduler, droppable until the Shift starts.** One entity, two actors — not a parallel table that behaves identically in every respect but one. This matches ADR-0015's own wording and matches what legacy does in fact (`_specialSetScheduleMember()` and `_specialaddsignUp()` write the identical row).

**Officer assignment is in the first pass and is not optional.** Reception runs entirely on the Scheduler placing regulars who hold the same slot indefinitely, and Reception is the simplest starting Group. Self-service-only means Reception cannot use the first pass. A Scheduler may also **remove** a Sign-up — assign-without-remove strands a placed regular the first time they stop coming.

**Who may take a Shift is two floors and one field.** The floors answer _"may this person work at all"_; the audience answers _"who sees a Sign-up button"_.

- **DMV-wide floor: `Category::canSignUp()` — already exists, reused unchanged.** [ADR-0017](0017-authorization-enforcement.md) already names it "the sign-up gate, called before any role check"; the first pass invents nothing and adds no consumer-side variation. `Loa`, `Withdrawn`, `Resigned`, `Deceased` → false; `Active`, `Honourary`, `Sustaining`, **`Provisional`, `PreActive`** → true. Provisional and PreActive sign up deliberately: **signing up is how a trainee trains** (Visitor Guides has a live `Shadow` shift type for exactly this). LOA is the one that is genuinely paused, and ADR-0011 already phrases it correctly — "full view, no sign-up". Note this is orthogonal to `accessTier()`, where LOA is `Full`.
- **Per-Group floor: `MembershipStatus::canSignUp()` — new, mirroring the shape above.** Excludes the four that mean _gone or paused_ — `loa`, `inactive`, `resigned`, `deceased`. Permits the other seven — `full`, `trainee`, `transitional`, `auxiliary`, `projects`, `emeritus`, **`donor`**. `donor` is in deliberately: a Friends Group's roster _is_ donors, and excluding them would leave a Friends Committee unable to staff its own Schedule.
- **`audience`, on the Shift.** `group` (default) — Members of the owning Group, per the floor above. `open` — any Member who can see the Schedule.

**Cross-Group participation is a read concern — a query, never a relationship.** A Member may take any Shift whose audience includes them, wherever it lives. No second row, no synchronisation, no ownership ambiguity. This is what **permanently closes** the cross-Group shared-Shift question rather than merely deferring it: legacy's row-stapling (claim-or-create on publish, six call sites kept in step, never unwound on un-publish) is not replaced by anything, because the filter gives back everything it bought.

Four of legacy's five audience values are dropped: `0` "nobody" (that is an unpublished Schedule), `99` "MIS" (a hardcoded union of six committees that nobody maintains), a committee id, and a subcommittee id + 100. **The default is `group`, not legacy's `everyone`** — legacy's permissive default is a Visitor Wayfinders artifact, and Wayfinders is out of the first pass. Two values are chosen so the enum widens to a Group-list without reshaping `Sign-up`.

**A Scheduler is bound by both floors but not by `audience`** — placing a named person she has already spoken to is exactly the case the discovery filter is not for. **Capacity binds everyone, Scheduler included.** No override: a Scheduler who wants a sixth person on a capacity-5 Shift raises capacity to six, and the number on screen stays honest.

**One Member, one seat:**

|     | Case                                               | Rule                                                         |
| --- | -------------------------------------------------- | ------------------------------------------------------------ |
| 1   | Two identical **Shifts** in one Schedule           | allowed                                                      |
| 2   | One Member, two Sign-ups on **overlapping** Shifts | **allowed, unchecked**                                       |
| 3   | One Member, two Sign-ups on the **same** Shift     | **blocked** — unique constraint on (`shift_id`, `member_id`) |

Case 2 is allowed on purpose — a Member may hold a morning desk slot and an afternoon tour that run into each other, and the alternative stops a willing volunteer on our guess about their calendar. Case 3 is not overlap; it is one Member consuming two seats of one slot, which legacy made structurally impossible and the slot model would otherwise permit as a plain insert.

**Cancel: no deadline.** A Member may drop a Shift for as long as they could have taken it. This follows live legacy rather than departing from it — the `+2 days` guard is still computed in `res/special/special.php` but was commented out with `//allow cancel ANY TIME`. There was never a rule to port.

**Notification: one email, and one deliberate silence.** On a Member cancelling, email the Group's Schedulers — **unconditional** (legacy gates it on the same dead 2-day threshold). This is the single event where otherwise nobody finds out until the shift is empty. **Officer assign and remove send nothing, deliberately**: the Scheduler is by definition already in contact with the person. Recorded as a decision rather than an omission. This is one transactional email, not a reminder pipeline.

**Provenance — who created a Sign-up — is not decided here.** Deciding it would make `sign_ups` the one table in the app with an authorship column; it is point 2 of [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334).

### 5. Bulk writing replaces recurrence — and adds nothing to the schema

**The aid a Scheduler gets is bulk writing, never a stored rule.** ADR-0015's **Repeat rule** is retired. There is no pattern object, no recurrence column on `Sign-up`, no series to truncate, and no phase anchor to remember.

A future reader looking for the pattern engine should find this note and stop looking: **this section adds no entity, no column, and no table.** "Bulk operations" reads like a feature; it is a loop over a date range.

|          | Bulk-create Shifts                                       | Bulk-place a Member                                               |
| -------- | -------------------------------------------------------- | ----------------------------------------------------------------- |
| Writes   | `Shift` rows                                             | `Sign-up` rows                                                    |
| Inputs   | kind, start/end time, capacity, days of week, date range | Member, target Shifts (days of week + time), date range, interval |
| Interval | **none** — every matching day in the range               | **weekly or biweekly**, from a chosen start date                  |
| Run from | the Schedule builder                                     | a Member-in-Schedule context                                      |

Two entry points, not one form with a mode toggle: they take different inputs and are run at different moments.

**Every bulk write has a symmetric bulk undo on the same filter** — bulk-remove Sign-ups (a placed regular stops coming) and bulk-delete Shifts (which does legacy's skip-dates job without a field: generate the month, bulk-delete the statutory holiday).

**Per-row semantics are the whole specification.** A bulk run is **N single writes plus a report — skip-and-report, never all-or-nothing, never a privileged path.** It honours both floors, `capacity`, the (`shift_id`, `member_id`) unique constraint, and the zero-Sign-ups delete rule _per row_. The skipped list is useful output, not an error. Under all-or-nothing, one full Shift in a twenty-row month forces the Scheduler to narrow the range by hand and re-run — which is the typing this exists to remove.

**Bulk-place is a Scheduler action in the first pass.** Member-initiated bulk sign-up is a permission widening, not a redesign; deferred, not rejected.

**This is what retires Reception's fortnight.** The red/blue two-week pattern is an _assignment_ carrier, not a shift pattern — confirmed by schema, not only by testimony: `Colour` appears in exactly the two Groups that name a person on a pattern row (`receptionweeklySchedule.commID`, `vgweeklySchedule.VgID`) and nowhere else. It was scaffolding for a capability legacy lacked. Reception's month is now two form runs.

### 6. One surface, and how a reader gets to a Schedule

**A single `Scheduling` section tab on the Group page.** The Scheduler — and the Chair, by implication — sees authoring affordances **inline**, gated by a `can` prop. There is no `Scheduler` tab, and manage-schedule does **not** go in the rail.

This is application of [ADR-0013](0013-app-shell-section-nav.md) plus [ADR-0017 §9](0017-authorization-enforcement.md), not a new pattern: Overview (inline About-Us edit, `can.update`), Roster (add-member / edit dialogs, `canManage`) and Meetings ("New meeting" + per-row edit, `meeting.can.update`) all already work this way, and there is **no admin-tab precedent anywhere in the repo**. Rail placement was never available: [ADR-0018 §4](0018-server-driven-grouping-rail.md) reserves "Officer Tools" for _org-wide_ administration and forbids conflating it with a Group's officers.

**Escape hatch if authoring genuinely overflows:** a nested route _under_ the section (`/groups/{slug}/scheduling/edit`), never a sibling tab.

**The section is org-open.** It follows `listing_visibility` for published Schedules with the schedule-admin gate over drafts — explicitly _not_ Meetings' members-only gate, which is documented as the exception ("unlike the org-open Overview and Roster"). **The tab renders whenever `has_scheduling` is on, empty state included** — a tab that appears and disappears with content teaches Members the section is unreliable, and a Scheduler needs it visible precisely when it is empty.

**Navigation: list, then open.**

- **Current** = `ends_on >= today`.
- **Exactly one current _published_ Schedule → open it directly.** Otherwise show the list, current and upcoming first.
- **Drafts never count toward the "exactly one" test** — a Scheduler's in-progress draft must not change where a Member lands.
- **URL `/groups/{slug}/scheduling/{id}`** — the Schedule has no slug, so the id addresses it. Needs a French segment per [ADR-0008](0008-bilingual-url-routing.md).

Legacy's dropdown assumed one-per-month and no overlap; both premises are gone.

**A per-Group role requires a membership; grant one.** A non-member role grant is not merely unbuilt, it is **unrepresentable** — a role is a row on the _membership_ (`GroupMemberRole` belongs to `GroupMember`), and `canActAs` returns false the moment `membershipIn($group)` is null. So the Visitor Wayfinders Chair, who currently builds schedules for seven Friends Committees, takes **a plain membership on each Friends Group carrying the `Scheduler` role**, granted by _that_ Group's own Chair through the existing roster CRUD (super-tier as fallback). Requiring the receiving Group to grant it is a feature: it makes the arrangement consensual and visible, which legacy's cross-Group authoring never was, and it gives the cutover conversation a concrete ask. **She appears on that Group's roster like any other member** — accepted cost, see _Consequences_.

### 7. What a Schedule looks like

**Two views the reader chooses between: an Agenda (day-grouped list) or a Calendar (month grid + day sheet).** Both ship, and both serve both legacy shapes. A third variant — a days-across / time-or-activity-down **Matrix** — was built and rejected.

**This is what retires legacy's `DisplayStyle`.** Activities-Across and Activities-Down are one matrix with the row axis swapped, so of legacy's three, only Calendar survives — and it survives as a _viewer_ choice, not a stored column on the event.

- **The viewer picks, not the Scheduler.** A Schedule setting would be `DisplayStyle` again: a presentation choice in the authoring form, letting one Scheduler impose a layout on 500 people.
- **Remembered client-side (localStorage).** No member column, no Schedule column, no settings screen, nothing to migrate, and nothing to unwind if nobody switches. The toggle is presentation-only — no route and no server state.
- **Agenda is the default.** The tempting "Calendar for a month, Agenda for an event" rule would have to re-derive from the date range exactly the monthly marker §1 deliberately killed. Agenda reads at 3 days or 30 and never breaks on a phone.
- **Sign-up names are visible to every viewer who can read the Schedule, non-members included.** This is _not_ inherited from legacy — §6 made the read audience org-wide, which is wider than legacy's, so drawing it is what forced the question. A Schedule is a roster of who is on the floor, no more exposing than the Directory, which is already org-open. **Named explicitly in the [ADR-0017 §6](0017-authorization-enforcement.md) allowlist** rather than riding in on inheritance.
- **Other Groups' `open` Shifts are advertised, not hidden.** In Agenda, a per-day band that is _always present_ but collapsed to one line — "2 more open to you — Visitor Wayfinders" — expandable per day, with a master open/close-all beside the filters. In Calendar, where a grid cell has no room for a band, the master switch alone with chips off until clicked. The first draw hid them behind an off-by-default toggle, which was the wrong instinct: nobody turns on a thing they do not know exists. Foreign Shifts are **never interleaved, always attributed, and never carry authoring affordances**.
- **No "My shifts" filter on a Group's Schedule.** That is My Calendar's question — a built top-bar nav destination, currently a `ComingSoon` stub — and a per-Schedule copy would answer it worse, one Group at a time.

The prototype for all three variants, the rejected one included, is on branch [`prototype/schedule-views`](https://github.com/roytanaka/dmv-rom-v2/tree/prototype/schedule-views), unmerged. It is not production code.

## Considered alternatives

- **Two Schedule types — monthly and date-range**, mirroring legacy. Rejected: the `YYYYMM` column is _why_ legacy has two irreconcilable shapes. A month is a date range.
- **Perpetual Schedules** (nullable `ends_on`, or a `published_through` watermark). Genuinely a better fit for the six monthly Groups: publication becomes a date you push forward, with the Scheduler's working tail invisible behind it. Rejected because Visitor Wayfinders' Schedules are **named, discrete occasions** copied years later, and a watermark over one endless stream has no unit to name, publish, or copy. It buys less clicking at the price of two shapes of Schedule. If twelve short forms a year grate, the fix is the app **auto-opening next period's draft** — a Group setting that changes who fills the form, not the model.
- **Capacity as N identical rows** (legacy's shape). Rejected: it makes raising capacity a delete-and-regenerate, destroys existing sign-ups, and gives per-seat data nowhere correct to live.
- **Two descriptive axes — kind _and_ location.** Rejected: no legacy Group's data splits cleanly between them, because the source rows never drew the line. A second axis is cheap to add later against real demand; splitting one populated column into two is not.
- **A free-text kind label** instead of `ShiftKind`. Rejected: the one Group whose kind looks like free text reads it from a table, and a string cannot carry a qualification requirement.
- **Legacy's requirement shape** — a hand-maintained list of Members attached to the kind. Rejected: it is a denormalised cache of "who has trained" that goes stale silently. **A flag on the Member** was also rejected — it is the second half of the chosen shape without the first, so there is nothing for a Shift to filter on.
- **A separate `Assignment` entity** for officer-placed volunteers. Rejected: it behaves identically to a Sign-up in every respect but who pressed the button.
- **Blocking overlapping Sign-ups.** Rejected: it stops a willing volunteer on our guess about their calendar. The required `ends_at` makes overlap computable — we simply choose not to compute it.
- **A stored repeat rule / pattern engine.** Rejected: storage is what drags in skip-dates (which exists only to undo over-generation), "delete this and all following" (only because there is a series), and the phase anchor (only because a rule has to remember where it started). Adrian supplied the argument himself — the Docents standing weekly pattern must be manually reverted after a one-off month, "then for the following month they've got to remember to get rid of it."
- **Duplicating an existing Schedule** (offset the dates, copy the Shifts). Ruled out of the first pass: its entire constituency is Visitor Wayfinders, which is out of the first pass, and **legacy has no such feature to port** — "Copy Pattern from" copies the previous _event's day-pattern template_, never a Schedule or its Shifts. Cheap to add later; §1 already keeps Schedules indefinitely, so the raw material is preserved.
- **A second `Scheduler` tab** for authoring. Rejected: no admin-tab precedent exists in the repo, and it would make ~500 read-only Members learn a menu item they must ignore.
- **Decoupling role from membership**, so a non-member can hold `Scheduler`. Rejected: it breaks `GroupMemberRole`'s write-time capability invariant and reshapes `canActAs`, the roster picker, and ADR-0011 — for one person covering seven Committees. Repurposing `MembershipStatus::Auxiliary` as "serves without belonging" was also rejected: **`Auxiliary` has no definition anywhere** in `CONTEXT.md`, `docs/`, or the seeders.
- **A stored `DisplayStyle`** per Schedule. Rejected: it lets one Scheduler impose a layout on 500 people, which is legacy's mistake exactly.
- **A third, matrix view.** Built in the prototype and rejected on the evidence.

## Consequences

- **Migration is a fan-in, and it is smaller than ADR-0015 implied.** Ten Groups' scheduling tables collapse to `schedules`, `shifts`, `shift_kinds`, `sign_ups`. There is deliberately **no** repeat-rule table.
- **`Count` does not come across in either sense.** Two distinct fields hide in one name — _duration in hours_ and _quantity of things done_ — reconciled outside the tables by a hardcoded per-Group multiplier (`hoursper`: every Group `1`, **Walker `2`**). Any fan-in mapping `Count → Count` silently corrupts every Group's hours.
- **Docents and GDR need an end time their legacy data does not contain.** Accepted at §2.
- **The map records no count of scheduling Groups, deliberately.** It moved twice (eight → nine with Walker → ten with ROM Travel) because legacy has no authoritative list: the roster at `servicesp.php:20` is hardcoded and which of them schedule is _data_ — `SELECT symbol FROM dmv_Committee WHERE Active=1 AND HasSchedule=1`. Any fixed number is a liability; the answer is a query at migration time.
- **Two layouts to build, test and translate**, and every future Shift attribute has to land in both. The accepted cost of shipping Agenda and Calendar.
- **Code deltas:** four new tables + models + policies; a new `MembershipStatus::canSignUp()`; a French route segment for `scheduling`; Sign-up names added to the `MemberResource`/§6 allowlist; one transactional mail. `Category::canSignUp()` and `Role::Scheduler` are **reused unchanged** — no code delta, but the ADR records that scheduling is the consumer ADR-0017 named the gate for.
- **A membership granted solely to confer a role inflates seven Friends Committees' membership counts** and puts a Directory entry that reads as belonging. A Statistician has to be told. The fix, if it is ever wanted, is a roster display filter, and the precedent exists (`Resigned` hides behind `?past=1`). Deliberately not built — better to discover the objection than design around it.
- **Hours and statistics credit is unresolved and now has a second edge.** Legacy sums the per-shift `Count` into `MemberActivity.Total_Scheduled` via the per-Group multiplier, and **ROM Travel has no `_saveScheduledMemberActivity` call anywhere** — it schedules and reports but appears never to credit. A migration that assumes every scheduling Group has credit history will find a hole. And with `audience: open`, "which Group is credited when a Member takes another Group's Shift" generalises beyond the one linked pair legacy handled: the natural rule — hours belong to the Group that owns the Shift — is cleaner than legacy but changes reported numbers.
- **Outreach and ROMBus cannot turn scheduling on in the first pass** — group booking is their only shape (see below). Docents, GDR, Walker and ROM Travel keep their shift half and lose their booking half.
- **The first pass is not a demonstration.** Charting accepted a risk that no Group would run a real month on hand-entry; bulk writing retires it, and a pattern engine returns only if a Group demonstrates a need that N plain rows cannot meet.

## Deliberately out of the first pass

Recorded here because a closed map is a bad place to keep them. None is rejected on merit.

- **Cross-Group shared Shifts** — legacy links a Wayfinders Shift to a Visitor Guides Shift on publish and syncs the pair across six call sites, never unwinding on un-publish. **Closed, not deferred:** `audience` _is_ the answer, so there is no mechanism this comes back as. Visitor Guides do not lose sight of those positions — they see them as another Group's `open` Shifts. What is genuinely lost is legacy's credit rule (a non-Visitor-Guide filling a linked slot is flagged a guest and excluded from that Group's statistics).
- **Cross-Group scheduling authority** — authoring several Groups' Schedules from one place. Out; §6's membership grant is the replacement.
- **The "current and next month" window** for non-members ([#306](https://github.com/roytanaka/dmv-rom-v2/issues/306)) — dropped. Read-only is the whole rule.
- **All persisted recurrence** — Group-wide shift templates, per-event day patterns (`specialDayPattern`), and a recurrence column on `Sign-up` alike.
- **Duplicating an existing Schedule.** See _Considered alternatives_.
- **Audience values beyond `group` and `open`** — a Group-list audience (legacy's hardcoded `MIS` union) and subgroup audiences. Deferred to the Wayfinders port, the only Group that uses them.
- **Swap, assistants (delegated Scheduler access), and the daily reminder pipeline.**
- **The group-booking capability** — an outside organization engages a Group for a dated engagement. **Booking is a capability, not a Group shape**: five Groups have it (Outreach, ROMBus, Walker, **and Docents and GDR**, who are squarely shift-shaped), so it switches on independently of scheduling exactly as [ADR-0010](0010-group-model.md) models capabilities. What is deferred is the **client half**, none of which is staffing: `Client_Name`, `Client_Address`, contact fields, `OrgType`, `PayType`, `Earned` (honorarium), the billing class `*groupTourType` with `RatePerVisitor` / `RatePerDocentHour`, `OrderNumber` / `OrderDate` / `Posted`, `ObjectsTaken`, and `rombusGuests` (an external non-member manifest CSV-imported from an outside ticketing system). **The staffing half is not lost** — a Group that wants a booking staffed authors it as a Schedule with the Shifts it needs. Note legacy is not a design to port: **there is no organization table at all**; the client is re-entered on every booking and auto-filled by a `LIKE` search of prior bookings, so the organization is emergent from booking history.
- **ADR-0015's other strategies** — object reservation, guest manifest, event linkage. [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262) tracks the reservation/manifest end.
- **Qualification gating — enforcement and the credential system.** Training programmes, completion records, who grants a qualification, expiry, and the screens to manage any of it. Its own PRD, and it reaches into Member records, so the Records Group has a stake. Only the _shape_ is in (§3).
- **Sign-in kiosk, attendance, and visitor counts** — legacy's `Confirmed` / `Attended` / `Visitors` columns and the `*signin` surfaces. A separate capability. Recorded honestly: the per-shift visitor-interaction count is a field volunteers fill in **today** on every visitor-facing Group, so a Shift that cannot carry it is visibly less capable than the thing it replaces. The slot decision at least gives it a correct home when it lands — the Sign-up, per volunteer.
- **Print and export of a Schedule** — legacy has per-committee print views and `res/special/exportevent.php`. ADR-0015 says publishing a Schedule as a downloadable file is the **document** capability, so this may not be a scheduling question at all.
- **A shared occasion spanning several Groups' Schedules.** Wayfinders and Visitor Guides both staffing Doors Open yields two Schedules of the same name with nothing joining them. Cheap to reverse: `Schedule` gains a nullable FK to an `Occasion` and nothing reshapes.
- **Whether Docents' (and Outreach's) shift kinds are `ShiftKind` rows or the content catalog's Tours.** [ADR-0010](0010-group-model.md) makes the docents' content catalog `Category → Section → Tour`, and Docents' shift kinds _are_ tours; Outreach's are _presentations_ (`outreachGalleryTheme`). Either their `ShiftKind` rows duplicate that list, or a Shift's kind points into another capability's data, taking the qualification requirement with it. Named plainly because it dents §3's own reasoning — part of the case for an entity was that it settles the question before Docents arrive, and Docents may reopen it from a direction that argument did not check. Not decided here, because answering it means specifying the content catalog inside a scheduling decision, and no first-pass Group has one.
- **Legacy scheduling data migration** — handled by the separate migration plan. This ADR takes _facts_ about legacy from research, never the data itself.

## References

- Wayfinder map [#323](https://github.com/roytanaka/dmv-rom-v2/issues/323) and its decision tickets: [#324](https://github.com/roytanaka/dmv-rom-v2/issues/324) (Schedule), [#325](https://github.com/roytanaka/dmv-rom-v2/issues/325) (legacy schema inventory), [#326](https://github.com/roytanaka/dmv-rom-v2/issues/326) (Shift, ShiftKind, requirements), [#327](https://github.com/roytanaka/dmv-rom-v2/issues/327) (Sign-up, audience, floors), [#328](https://github.com/roytanaka/dmv-rom-v2/issues/328) (surface, navigation, the membership grant), [#329](https://github.com/roytanaka/dmv-rom-v2/issues/329) (bulk writing), [#330](https://github.com/roytanaka/dmv-rom-v2/issues/330) (Agenda and Calendar), [#333](https://github.com/roytanaka/dmv-rom-v2/issues/333) (booking is a capability)
- Requirement [#306](https://github.com/roytanaka/dmv-rom-v2/issues/306) — "Scheduling menu", the ask this answers
- [ADR-0015](0015-scheduling-model.md) — scheduling model (refined here: container supplied, Catalog → ShiftKind, Repeat rule retired, group booking / guest manifest deferred)
- [ADR-0011](0011-authorization-model.md) — authorization (amended here: membership is the only path to a per-Group role)
- [ADR-0017](0017-authorization-enforcement.md) — enforcement (amended here: §6 allowlist gains Sign-up names; §2's `Category::canSignUp()` gains its first consumer)
- [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md) — `listing_visibility`, which gates published-Schedule reads
- [ADR-0010](0010-group-model.md) — scheduling (and booking) as Group capabilities
- [ADR-0013](0013-app-shell-section-nav.md) / [ADR-0018](0018-server-driven-grouping-rail.md) — section tabs in the page body; "Officer Tools" is org-wide only
- [ADR-0004](0004-chrome-only-translation.md) — `name` / `description` are as-authored content
- [ADR-0008](0008-bilingual-url-routing.md) — the French `scheduling` path segment
- Research: `docs/research/legacy-scheduling-schema.md` on branch `research/legacy-scheduling-schema`; prototype on branch `prototype/schedule-views`
