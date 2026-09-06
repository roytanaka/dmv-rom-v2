---
status: proposed
date: 2026-09-06
---

# Scheduling second pass: what a Sign-up records after the shift

From the wayfinder map [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398) (_scheduling second pass — what a Sign-up records after the shift_). Nine decision tickets over 2026-08-23 → 2026-09-06 settled the model; this ADR is where their answers land.

It **refines [ADR-0021](0021-scheduling-first-pass.md)** — closing three of its deferrals (attendance, visitor counts, the sign-in kiosk) and answering the cross-Group credit question its Consequences raised — and **refines [ADR-0015](0015-scheduling-model.md)**, which named no after-the-shift record at all. It **amends [ADR-0022](0022-hours-and-statistics-model.md)** in three places: the visitor counter is no longer out of scope, `hours_records` gains an `extra_interactions` column, and _recalculate this month_ is stated with the roster join it never had. Amendment notes are folded back onto ADR-0021 and ADR-0022.

## Context

[ADR-0021](0021-scheduling-first-pass.md) built three verbs: read a Schedule, take a Shift, build a Schedule. It stopped at the moment a shift begins. Nothing in the model says whether the volunteer turned up, and nothing records how many visitors they served. [ADR-0022](0022-hours-and-statistics-model.md) then took the hours half — the monthly bucket, _recalculate this month_, the fiscal year, and the whole report set — leaving this map a narrower question than it was chartered with.

What remained is one live practice and one seam. The per-shift visitor count is a field volunteers fill in **today**, in nine of the ten scheduling Groups, current through the present month. It has no home in the rebuild. And ADR-0022's recalculation reads Sign-ups without ever saying which Group's row it writes when a Member takes another Group's `open` Shift — a case ADR-0021 created by making cross-Group participation a read filter.

Three findings shaped everything below.

**Legacy's attendance is not attendance.** `Attended` appears once in the whole codebase, inside a comment block. The live column is `Confirmed`, and Roy's read of the practice is that volunteers press Sign Out to file the visitor number, not to assert they were present. There is no no-show marker anywhere in legacy, and no consequence a no-show carries.

**Forced entry is what makes the data usable.** Visitor Guides, Visitor Wayfinders and Gallery Interpreters disable the Sign Out button until a number is typed, and carry a count on **96-98%** of signed-out shifts. GDR and ROMWalks have no such gate and manage **54-59%**. Same volunteers, same year, same form. The button is the difference.

**Three separate passes recorded a live column as dead.** `MemberActivity.Interactions` was written off by [#399](https://github.com/roytanaka/dmv-rom-v2/issues/399), [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400) and [#404](https://github.com/roytanaka/dmv-rom-v2/issues/404) on a plausible code reading, and production contradicts all three: 98 rows across twelve Groups, current through 2026. Six Groups have no other route into the department's headline number. Every claim below that could be checked against production data was checked against production data.

## Decision

Six decisions. One removes an entity nobody needed, four add columns to `sign_ups`, and one restores a report.

### 1. Attendance is not recorded

**No `attended` column, no attendance capability, no per-Group flag, no kiosk.** A `Sign-up` says a Member took a Shift. Nothing says they turned up, and nothing will.

**A no-show is corrected by removing the Sign-up.** ADR-0021 already gives a Scheduler that power and does not time-bound it; only *Member* cancellation stops when the Shift starts. Removing the Sign-up drops the credit with it, because ADR-0022's _recalculate this month_ reads Sign-ups. The correction path was already in the model. It had not been named as one.

Every argument attendance had was taken from it before the question was worked. It does not make hours correct: ADR-0022 ships the entire hours feature without it. It is not service awards, which run on **years of service** from `Entry_Year`, hand-entered by Records, with no code joining them to hours. It has no consistent legacy behaviour to port: `Confirmed` is live in six of ten Groups and gates credit in only four of those. And it has no workflow behind it — no marker, no consequence, no procedure.

The precedent is ADR-0022 §3, which withdrew `has_hours_stats`: a flag that is always true should not exist, and a column nothing reads is worse.

### 2. A Sign-up carries two nullable integers

**`visitor_count`** — visitors this Member served on this Shift. Every collecting Group.

**`extra_interaction_count`** — visitors served outside a tour they led. Tour-leading Groups only; for a desk or gallery Group the visitor count already is an interaction count.

Both are columns on `sign_ups`. **No new entity, no new table, no lifecycle.** ADR-0021 already fixed the home as the Sign-up, per volunteer, and the migration comment reserved the slot. For a nullable integer a separate table is a join bought for nothing.

**Null means nobody has recorded a value. Zero means someone recorded zero visitors.** Both are real states and neither collapses into the other. That rule makes the _shifts still needing a number_ list free: it is past Shifts whose Sign-up has a null `visitor_count`. No boolean, no second column, no state machine.

**Nullable in storage, required at the entry surface, validated on the server.** The 96-98% against 54-59% split is the whole argument for the requirement. Legacy's version of it is a disabled button and nothing else — the endpoint accepts blank, accepts anything, and interpolates it raw.

**No authorship column.** [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) stays app-wide. ADR-0022 §6 could afford `created_by` on `hours_adjustments` because an Hours record is overwritten in place, so a mistyped 200 is otherwise unattributable. Neither reason holds here: this is one number on one row, corrected by retyping it, and the prior value carries no reporting weight.

**Two rather than one, because `Visitors` does not mean the same thing across Groups.** On a Visitor Guide's row it is _people I talked to at the desk_. On a Docent's row it is _people on my tour_, with the talked-to number in a second column beside it. Docents and GDR have typed that second number for years — 2,533 and 364 rows in 24 months — into a column no legacy report has ever read.

Both names are new on purpose. Two legacy words name four columns and both words are spent; see the `Visitors` / `Interactions` entry in `CONTEXT.md`.

### 3. Visitor provenance: five more integers, GDR only

`visitors_france_europe`, `visitors_quebec`, `visitors_toronto`, `visitors_rest_of_canada`, `visitors_other_countries` — five nullable integers on the same `Sign-up`, switched on per **Group** exactly as the visitor count is.

**The five must sum to `visitor_count`, validated on the server.** Legacy enforces this already, in the browser only, and production honours it on **712 of 712 rows since 2022**. `visitor_count` therefore holds derived data for GDR. It stays: it is the column every other Group uses and every report reads, and the precedent for a stored derived total is ADR-0022's `total_hours`.

**One Group, and general machinery is not built for it.** `gdrscheduledTours` is the only table in the production database with breakdown columns — ten Groups, fifteen years, one instance. A one-to-one breakdown table was rejected as a table, a model and a relation for one Group. A JSON column was rejected because there is no JSON column anywhere in this codebase and CLAUDE.md bars a new pattern until a need appears three times. This is need number one. A second Group asking is when this gets generalised.

This is the one place the port is not literal. Legacy gives GDR its own table, where five extra columns cost nothing; we have one shared `sign_ups` serving ten Groups. The columns are accepted as visible GDR-only noise on a shared table.

The provenance data is captured and **no reader is built for it**. GDR's Tour Provenance PDF is out with the rest of the report family (see _Deliberately out of the second pass_). The history has to have somewhere to land; one PDF is a small job whenever someone asks for it.

### 4. Hours belong to the Group that owns the Shift

_Recalculate this month_, run by an officer of Group A for month M, sums the durations of past Shifts **A owns** on which a Member holds a Sign-up, applies A's `hours_multiplier`, and writes that Member's `hours_records` row for A. It never writes a row under any other Group.

```
for each Sign-up S on a Shift H where
    H.group_id = A
    and H.ends_at is in month M
    and H.ends_at < now
  accumulate duration(H) × A.hours_multiplier
    into hours_records(S.member_id, A, M).scheduled_hours

overwrite; do not add.
no roster join. no audience test. no membership test.
```

**Membership is not tested.** A Member with no standing in the owning Group is credited for the Shift they worked. This is a faithful port: Visitor Wayfinders writes `MemberActivity` keyed on raw `MemberID` with no roster join, and every Group total in the legacy reports is a `SUM ... WHERE committee='X'` with no roster join either. Non-members' hours already count toward a Group's legacy total today.

`audience` is a **read** concern. It decides who sees a Sign-up button. By the time a Sign-up exists the audience question has been answered, so it plays no part in crediting.

**The "guest flag" this question was charted against does not exist.** Legacy's one linked pair credits the taker's home Group, but not by policy: Visitor Wayfinders and Visitor Guides store the same shift as two rows in two tables, and the month-close `DELETE` against one half (`special.php:2438`) is what stops the department reporting the hours twice. It is plumbing. ADR-0021 already retired the row-stapling it defended, so there is nothing left for it to defend. Legacy's *other* path — the six Friends Committees swept out of the same table with no exclusion at all — is already owner-credit. Legacy is inconsistent between its two paths; we take the one that generalises.

**Rejected: credit the taker's home Group.** It contradicts the Friends path, it needs a definition of "home Group" for a Member of several and legacy has none, and it makes an officer standing on Group A write rows under Group B, so a Member's total would depend on which officer recalculates first. Owner-credit has no ordering hazard.

**[#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) story 68 needs one word.** It reads *"a Group's total to always equal the sum of its **Members'** records"*, which under owner-credit is ambiguous and, read as the roster, false. Restate it as: **a Group's total equals the sum of the Hours records filed under that Group.** Same number legacy computes, and it holds by construction.

### 5. Entry lives on the Group's Scheduling tab, in two places

**No new route, no new top-bar destination, no admin tab.** This is ADR-0021 §6 applied unchanged: authoring inline, gated by `can` props, the same shape as Overview, Roster and Meetings.

**The volunteer has two ways in, both on that tab.**

1. **Inline on the Agenda.** A Shift the viewer holds a Sign-up on grows a sign-out panel from five minutes before its `ends_at`: the number boxes, and the Sign Out button disabled until a number is typed.
2. **A personal outstanding-shifts panel** on the same tab — *my* Sign-ups, upcoming plus any past Shift inside the 28-day window that still has no number.

**The panel is date-ranged, so it crosses Schedules.** This is the part the Agenda alone cannot do. A Shift's `schedule_id` is mandatory and its date must sit inside the Schedule's range (ADR-0021 §1-2), so a three-week-old Shift is routinely on last month's Schedule, a different page. A pure-Agenda answer strands it.

This is a faithful port. Legacy already puts the box in both places, in every scheduling Group: a `WHERE Date = today` board (`vg.php:200`), and a *My … Calendar* personal list present in **nine** Groups under eight labels, whose query is `Date >= today OR (Date >= today - 28 days AND Confirmed = 0)`. We have no `Confirmed`, so the outstanding marker is a **null `visitor_count`** — exactly what §2 reserves it for.

**An officer corrects inline**, a pencil on each seat chip, no deadline. Every write is re-checked server-side against a policy.

**Nothing prompts outside the Group page, deliberately.** Legacy has no reminder, no badge and no email, and still reaches 96-98% wherever the button is disabled. The forcing function is the prompt. Building a notification pipeline to beat a number legacy already hits would be building the expensive half of the answer first.

**Nothing at all is built for a Group that collects nothing.** Reception renders no panel, no boxes and no column. The switch is the Group-level setting, not the `ShiftKind`.

### 6. Summary Visitor Interactions returns, with one composition rule

A Group's figure for one month is:

> the sum of that Group's **Sign-ups** (`visitor_count` + `extra_interaction_count`), plus its **extra interactions**, rolled up through the whole sub-Group subtree.

Two sources, no per-Group special cases. Legacy assembles the same figure five different ways and none survive. It also double-counts in two places, adds a booking audience on top for two Groups, never reads the per-shift second column at all, and cuts its month at a different point than the hours grid beside it.

**`hours_records` gains `extra_interactions`**, a whole integer outside `total_hours`, entered beside extra hours on ADR-0022's Hours tab and carrying an append-only `hours_adjustments` row. This **amends ADR-0022 §1**. It is the only route into the report for six Groups — ROM Travel, Hands-on Tours, ROMBus, Friends of Palaeo, Friends of Global South Asia and DMV itself — and ROM Travel does not schedule at all, so these rows are its entire presence in the department's headline number.

The adjustment log fixes a defect rather than porting one. Legacy's extra-interactions entry only adds, only accepts values above zero, and has no second writer anywhere. A member who types 2280 instead of 228 can never correct it, and neither can a Statistician.

**Open to any signed-in Member.** Legacy shows this one report to non-executives deliberately — it is the sole item in the `else` branch at `Members.php:3524` while everything else needs an officer role. It is an aggregate with no personal data. A **named exception to [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) story 59**, not a hole in it.

**One month window for the whole screen**, matching #406. Detailed Committee Statistics gains a fourth row beside shifts, meetings and extra hours, which **amends #406 story 51**.

**A Group whose figures are incomplete says so.** ROMForYou's only visitor source is a booking table, so under this rule it reads 293 instead of 1,055 in fiscal 2026 — a 72% drop with no visible cause. The report carries a per-Group marker saying booking audiences are not yet counted. Honest, and it puts visible pressure on the group-booking work.

## Considered alternatives

- **An after-the-shift entity.** The ticket chartered to design one resolved by not building one. Hours had already gone to `hours_records` and attendance had gone nowhere, leaving one candidate value whose home ADR-0021 had already fixed. A table for one nullable integer would need exactly the lifecycle rules the attendance decision had just finished deleting.
- **One visitor number instead of two.** Rejected on evidence: Docents and GDR already collect two, in separate columns, at the same dialog, and have for years.
- **A per-Group breakdown table or a JSON column** for GDR's provenance. Rejected at §3.
- **A Schedule close-out table** for officers. Officer-only so it can never ship alone, scrolls sideways on a phone, and is Schedule-shaped, so it answers "what is missing this month" and never "what is missing". Revisit if a Statistician asks for bulk entry; legacy's grids say they might.
- **A personal top-bar destination** for outstanding shifts. Its *content* is what the panel holds, but `nav.personal.calendar` stays a stub: deciding what My Calendar is — one Member's whole schedule across every Group — is a bigger question than this map, and legacy's own personal list is per-Group.
- **The Hours tab as the entry surface.** It puts scheduling data on a tab every Group has, including the ones that run no scheduling, reached once a month for a different reason. A volunteer at 17:00 is on the Schedule.
- **Skipping non-members in recalculation.** It would make every report agree, at the price of the hours vanishing entirely — the owning Group refuses them and the home Group does not own the Shift. That is the lost-credit bug legacy already has with shadow sign-ups.
- **Reproducing legacy's two month windows** on one screen. Porting a defect.

## Consequences

- **`sign_ups` gains seven nullable integer columns**, two of them used by nine Groups and five by one. No new table, no new model, no new entity in the scheduling model.
- **`hours_records` gains `extra_interactions`**, outside `total_hours`. ADR-0022 §1's column list is no longer complete as written.
- **Four Groups' reported hours go up.** Visitor Guides, Visitor Wayfinders, Docents and Reception filter credit on `Confirmed=1` today. Under §1, shifts nobody signed out of now count. A cutover communication item, not a decision.
- **Hours move between two Groups' columns.** A Visitor Guide working a Visitor Wayfinders shift is credited to Wayfinders, where today it is the reverse. Visitor Guides falls, Wayfinders rises, **no Member's total changes** — My Hours sums across every Group. This partly offsets the movement above, so the net direction for Visitor Guides is not predictable from code alone.
- **Six Friends Committees stop depending on another Group's officer.** Today their hours are written only when a Visitor Guides officer presses Confirm. Under owner-credit each owns its Shifts and runs its own recalculation.
- **A Group's total can exceed the sum of the names its own reports list.** Member History and the Zero Hours reports are roster-driven and stay roster-driven, while the total is not. Same behaviour as legacy; recorded so it is not rediscovered as a bug.
- **Reported visitor figures move at cutover.** Fiscal 2026 under this rule: Docents 28,203 → 27,526, GDR 1,076 → 1,668 (up 55%), ROMWalks 5,506 → 4,654, ROMForYou 1,055 → 293. Docents' currently-dropped second column is 40% of its reported total and is the single largest number the legacy report is missing.
- **Nothing surfaces a month nobody has recalculated.** Under owner-credit an outsider's hours depend on an officer of a Group they do not belong to pressing a button. Legacy has the hole already — ROM Travel has no recalculation call site anywhere and Visitor Wayfinders' is commented out. Left open; it may belong to ADR-0022's spec rather than here.
- **Nothing prompts a Member who worked another Group's `open` Shift.** They have a home — the Scheduling section is org-open, so their panel is on that Group's tab — but no surface they visit for their own reasons mentions it. A gap ADR-0021 created and this ADR does not close.
- **The per-Group sign-up floor has nothing to read for an outsider.** ADR-0021 gates sign-up on two floors and the per-Group one is `MembershipStatus::canSignUp()`, which has no row for a Member of another Group taking an `open` Shift. Only the DMV-wide `Category::canSignUp()` binds them. An ADR-0021 gap made visible by §4, not created by it.
- **Seat chips get dense on a two-number Group.** Live with it, or move the officer's correction into a small dialog off the chip.
- **This decision is agreed on evidence and not yet validated with the Groups who will use it.** Roy: *"the real test is getting it in front of leadership."* The prototype on `prototype/after-shift-entry` is what a walkthrough runs against. It joins [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400) §10's fifteen person-questions rather than blocking this ADR.
- **Code deltas:** seven columns on `sign_ups` and one on `hours_records`; a Group-level switch for the visitor count and a second for provenance; a Form Request enforcing the required rule and GDR's sum; a policy for the officer's correction; the sign-out panel and the outstanding-shifts panel on the Scheduling tab; the Summary Visitor Interactions report and its incomplete-Group marker.

## Legacy defects this port does not inherit

Recorded so the parity and migration work does not rediscover them. This ADR changes no legacy code.

1. **Anyone can sign out anyone.** `_remoteSignIn` takes a row id from POST and updates `WHERE ID=$id` with no ownership check — `vg.php:313`, `gi.php:497`, `special.php:279`, `docent.php:554`, `gdr.php:483`. Any logged-in Member can confirm another Member's shift and set their visitor count. §5 puts a correction affordance on screen for the first time, so this is named again there.
2. **SQL injection on every sign-out handler.** `$update .= ", Visitors=" . $_POST['visitors']` is raw interpolation, and GDR's five provenance values go the same way on both its write paths (`gdr.php:492`, `gdr.php:3031`). Eloquent removes it.
3. **Every required-field rule is browser-only.** The disabled Sign Out button is the whole enforcement, and GDR's provenance sum is a JavaScript alert. The server validates nothing and accepts blank.
4. **The org-level report double-counts.** It adds a booked group size for Docents and GDR on top of the per-shift figures, and sums ROMWalks' two `Visitors` columns with no guard — 27 of 31 booked walks in fiscal 2026 carry both.
5. **A fourteenth report disagreement**, beyond the thirteen [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400) documented: `interactions.php:110` restricts group tours to `Date<='$today'` and `detailedactivity.php:204` does not, so Detailed Committee Statistics counts tours that have not happened.
6. **The Statistician's row edit sets `Confirmed=1` unconditionally** (`gdr.php:3034`), so correcting a number marks the shift signed out. Moot under §1.
7. **Extra-interactions entry cannot be corrected by anyone** — see §6.

## Deliberately out of the second pass

- **The sign-in kiosk.** Legacy's six `*signin` surfaces were a shared museum-network device with a member-list dropdown and the last four digits of a Museum ID as a PIN. **Closed, not deferred:** the hub says *"no longer in use"* in writing, the network gate is dead in all six Groups behind an unconditional `$ipok = TRUE;`, and the 2024 member procedure PDF describes only the main website. Shared-device authentication is unlike anything else in the rebuild. If a bookmarked iPad turns out to still be in use at some desk, the answer is a phone, not a rebuilt kiosk.
- **Qualification gating** — the credential system, training programmes, expiry, and every screen. Its own map; it reaches into Member records, so Records has a stake. `ShiftKind` already holds the slot and it stays empty.
- **Group booking** — the client half. Its own map, and it owes this ADR's report a per-Booking actual audience for Docents, GDR, ROMForYou and ROMWalks. Until it lands those Groups read low, ROMForYou by 72%. Note that a booked group tour has **no other visitor record**, and that legacy's `Earned = RatePerVisitor × Visitors + …` makes the booked figure a **billing quantity**, so the booking map should treat it as an actual worth reporting rather than a forecast to discard.
- **The Visitor Wayfinders port** — audiences beyond `group` / `open`, the ShiftKind maintenance screen, and `Occasion`. Its own map.
- **The per-Group money-and-visitor reports** — Docents' Treasurer report, GDR's Tour Provenance, GI's Object Usage, Outreach's and ROMWalks' summaries. Every `$` figure in them belongs to the group-booking map. GDR's Tour Provenance carries no `$` figure at all and is out anyway: this ADR captures the data and builds no reader for it.
- **Retention** — how far back visitor-count records stay readable, and to whom.
- **Service awards.** They run on **years of service** from `Entry_Year` against a twelve-row vocabulary table, hand-entered by Records, and no code joins them to hours. ADR-0022 puts them out of its scope too, so they currently belong to no map. Nothing here may be justified by them.
- **Legacy scheduling data migration and any backfill of historical credit** — the separate migration plan owns it. Facts it needs are recorded here and in #406's *Migration notes*; the data itself is never taken from this map.

### Facts the migration plan needs

- **`dmv_romtravelscheduledTours` does not exist in production.** ROM Travel has no scheduling table at all, and no `_saveScheduledMemberActivity` call anywhere.
- **ROMBus has zero scheduling rows in 24 months.** Not partly used.
- **`dmv_receptionscheduledTours` has a `Visitors` column and it is dormant.** Nothing writes it, no report reads it. That is what makes Reception the tenth Group rather than a Group missing a column.
- **`dmv_MemberActivity.Visitors` (`Click Count`) is dead by data as well as by grep** — 0 of 12,521 rows. Do not import.
- **`dmv_MemberActivity.Interactions` is live** — 98 of 71,388 rows across twelve Groups, current through 2026. It imports into `extra_interactions`.
- **Hands-on Tours schedules and has no visitor table.**
- **GDR's provenance history imports** — 2,535 rows carry a visitor count, and every one since 2020 carries provenance with it.

## References

- Wayfinder map [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398) and its decision tickets: [#399](https://github.com/roytanaka/dmv-rom-v2/issues/399) (what legacy records after a shift), [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400) (what the DMV reports today), [#401](https://github.com/roytanaka/dmv-rom-v2/issues/401) (attendance), [#402](https://github.com/roytanaka/dmv-rom-v2/issues/402) (the entity), [#403](https://github.com/roytanaka/dmv-rom-v2/issues/403) (cross-Group credit), [#404](https://github.com/roytanaka/dmv-rom-v2/issues/404) (the visitor count), [#405](https://github.com/roytanaka/dmv-rom-v2/issues/405) (the entry surface), [#425](https://github.com/roytanaka/dmv-rom-v2/issues/425) (Summary Visitor Interactions), [#426](https://github.com/roytanaka/dmv-rom-v2/issues/426) (GDR provenance)
- [ADR-0015](0015-scheduling-model.md) — scheduling model, refined here
- [ADR-0021](0021-scheduling-first-pass.md) — Schedule, Shift, ShiftKind, Sign-up; refined here (attendance, visitor counts and the kiosk closed; the cross-Group credit question answered)
- [ADR-0022](0022-hours-and-statistics-model.md) — hours and statistics; amended here (`extra_interactions` on `hours_records`; Summary Visitor Interactions ships)
- [ADR-0017](0017-authorization-enforcement.md) — enforcement; the Form Request and the officer's policy sit under it
- [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) — the buildable spec for ADR-0022, amended in three places by §4 and §6
- [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) — does the app record authorship? Left app-wide by §2
- `prototype/after-shift-entry` — the entry-surface prototype, screenshots and README
- `docs/research/legacy-after-shift-record.md` and `docs/research/legacy-scheduling-reports.md`, on their `research/` branches
