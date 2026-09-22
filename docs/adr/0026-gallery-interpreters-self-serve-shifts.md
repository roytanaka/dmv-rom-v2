---
status: accepted
date: 2026-09-22
accepted: 2026-09-22
---

# Gallery Interpreters: self-serve Shifts, Objects, and the off-site hold

A grilling session on 2026-09-22, run against the legacy Gallery Interpreters code (`res/gi/gi.php` and its two JS files) and a schema query pass over the legacy clone. It **refines [ADR-0021](0021-scheduling-first-pass.md)** — a Member may now author a Shift, under one Group setting — and **lands the object-reservation strategy [ADR-0015](0015-scheduling-model.md) §Strategies named** and ADR-0021 deferred to [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262). It **amends [ADR-0022 §7](0022-hours-and-statistics-model.md)**: the per-Group hours multiplier becomes a decimal. Amendment notes are folded back onto those ADRs. The visitor count of [ADR-0023](0023-scheduling-second-pass.md) is unchanged and fits.

The trigger was a seed. Reshaping the Docents demo Schedule to match the real roster ([#566](https://github.com/roytanaka/dmv-rom-v2/pull/566)) was a data exercise; doing the same for Gallery Interpreters was not, because their legacy rows do not fit ADR-0021's Shift. This ADR settles the model before any seed or import touches it. Nothing here is urgent: GI is not in the September 2026 Exec demo.

## Context

Gallery Interpreters (GI) staff carts of handling objects in the galleries. The legacy flow, read from the code and confirmed by seven months of rows (November 2025 to May 2026, 820 filled rows):

- **One legacy row is a shift and a sign-up in one.** A GI picks a start time, a **station** (the gallery and cart, "Egypt - GI # 1"), one or more **objects** from the handling collection, and a **number of 45-minute units** (`Count`, 1 to 8). The row is written with their id on it. Nobody else authors it.
- **90% of rows are made on the day**, through a logged-in member page called _Sign In OR Sign up for a shift_. It is not the shared-device kiosk ADR-0023 closed: it takes the session's member, and its museum-network gate is dead code behind `$ipok = TRUE;`. It lets the GI pick any start from 10:00 to 22:00 in 15-minute steps, including a start already passed. The advance path, used 10% of the time, offers only the empty template rows for the date.
- **Objects are the point.** 819 of 820 rows carry objects, typically three. Object clash is the **one hard block** in the whole flow: the same object on two rows whose times overlap on one date, regular or event. Station clash is a **warning with an override**: same station, exact same start, "Another GI will be in this Location at this time", confirm to proceed. Six kinds are skipped by hardcoded id.
- **The weekly template is invisible to volunteers.** `giweeklySchedule` holds 13 half-hour starts from 10:00 to 16:00, Tuesday to Sunday. Opening a month inserts one empty row per template slot; taking a slot re-inserts a fresh empty one, so capacity per start is unbounded; closing a month deletes every row still empty. The template's only effect a GI ever sees is the list of start times.
- **Off-site events** hold objects. An event is a station of category _Special Events_, a date range, and an object window from the day before to the day after. The Scheduler adds event shifts by date and time; a GI takes one and picks objects. Any object on an event is blocked for other sign-ups across the window. About 140 event rows a year, 63 of them at the CNE.
- **Credit is units, not hours.** Month close sums `Count` per member straight into `MemberActivity.Total_Scheduled` with no factor. The reports print it as "Total Shifts". The same column is 45 minutes on the sign-out gate and a whole hour in the clash arithmetic: three readings of one number.
- **Sign-out** enters the visitor count, only on your own row and only once now is past start plus 45 × `Count`. The Sign Out button is disabled until a number is typed. ADR-0023 already ports this.

ADR-0021's Shift is Scheduler-authored with a kind and a length fixed at authoring. None of the four GI facts — station chosen by the taker, length chosen by the taker, made on the day, objects — fits it. Visitor Wayfinders, whose kinds are also places, does fit: the Scheduler authors every slot there with a capacity. So this is a GI decision, and the ADR stays GI-sized.

## Decision

### 1. A Member may author their own Shift in a self-serve Group

**A Group setting, `self-serve shifts`, lets any Member who passes both sign-up floors of ADR-0021 §4 create a Shift in that Group's published Schedules, and it creates their Sign-up on it in the same action.** The Shift is an ordinary row: `schedule_id`, `starts_at`, `ends_at`, `capacity` 1, `shift_kind_id` the station, `audience` `group`. Kind and length stay on the Shift, exactly where ADR-0021 put them. Nothing moves onto the Sign-up except the objects of §3.

This is the shape of the legacy row, read correctly: not a slot somebody else opened, but a record the volunteer wrote about themselves. It maps one to one for import (§10), it keeps `Shift.shift_kind_id` the single home of the kind so [#567](https://github.com/roytanaka/dmv-rom-v2/issues/567) (kind maintenance), the empty-desk alert of ADR-0024 §7 and _recalculate this month_ of ADR-0022 all work unchanged, and the Scheduler keeps every power ADR-0021 gave them: they still author Shifts (training, ROMForYou, events), still place and remove people, still edit or delete any Shift.

**The Member owns what they wrote, until it starts.** They may edit their own self-authored Shift — station, objects, units — and delete it, which drops their Sign-up with it, for as long as they could have taken it. After the start only the Scheduler edits or removes. A Member may not create a Shift with a capacity above one or an `open` audience: those are Scheduler affordances.

**Ownership is derived, not stored.** No table in this app records who created a row, and [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) settles that question app-wide; this ADR does not pre-empt it. A Member may edit or delete a Shift when the Group is self-serve, the Shift's capacity is 1, and the Shift's only Sign-up is theirs. Accepted edge: in a self-serve Group, a capacity-1 Shift the Scheduler authored and placed a Member on is editable by that Member too. In GI that is a Training or ROMForYou row, and the Scheduler who placed them is one message away. If #334 later records authorship, the rule narrows to "the Shift they authored" without any other change.

The setting is on the **Group**, not the Schedule. GI is self-serve as a Group, not month by month, and a second Group that works this way switches it on without code. It is off everywhere but GI.

### 2. Start, length, and the unit

**Start is any time on the day in 15-minute steps.** No template. Legacy's desk offers 10:00 to 22:00 in quarter hours; its advance path offers the template's half-hours; the data uses :00 and :30 from 10:00 to 21:00. A free picker covers both paths, and ADR-0021 already retired stored patterns. The weekly template is not ported (§9).

**Length is a count of units, 1 to 8.** The picker offers units, not an end time, because that is what a GI has typed for years and what the sign-out timing runs on. `ends_at` is derived: `starts_at` plus units × the unit length. The Shift stores start and end as always; the unit count is never stored.

**The unit length is a Group setting, `self-serve unit minutes`, 45 for GI.** The maximum of 8 is a constant. One setting rather than two because 8 is a dropdown bound in legacy with no meaning behind it, while 45 is the number every GI knows.

### 3. Objects: a Group's handling collection, reserved on the Sign-up

**Entity `Object`, group-scoped, with a name, an active flag and a sort order** — the same three columns and the same maintenance screen shape as `ShiftKind`, under the same schedule-admin gate as #567. **A Sign-up carries zero or more Objects.** This is [ADR-0015](0015-scheduling-model.md)'s object-reservation strategy, landing where that ADR said it would: on the Sign-up, because several volunteers may share one Shift and each reserves their own.

**When the Group has any active Object, a self-serve Sign-up must carry at least one.** Legacy's desk refuses to submit without an object, and one row in 820 lacks one. A Group with no Objects never sees the field.

**Object clash is a hard block, on the server.** A Sign-up may not carry an Object that another Sign-up carries on a Shift whose time range overlaps, on any Shift in the Group, regular or event, excluding the row being edited. Legacy runs the same check three ways in the browser and once more against events; one overlap query replaces all four, and it runs where legacy's does not, which is the security half of the faithful-port rule.

**What an Object is not.** Legacy's `giArtefact` is 452 rows of a GI content catalog — gallery themes, resources, links to files — of which 144 are flagged _has object_. The 144 are what scheduling needs, by name. Photos, gallery themes and the catalog stay with the content catalog capability of [ADR-0010](0010-group-model.md), which is not built; when it lands, it links to this list rather than replacing it.

### 4. Off-site kinds and the object hold

**A flag on the shift kind, `off site`.** An Object on a Sign-up whose Shift has an off-site kind is held from **the start of the day before the Shift to the end of the day after**, and the clash check of §3 treats that window as the Sign-up's time range. Legacy computes exactly this window (`ObjectStart` = start − 1 day, `ObjectEnd` = end + 1 day) when an event is created; putting it on the kind makes it automatic in the same way, at the cost of one boolean column. The kinds that carry it are the event stations and _Off site_.

### 5. Station clash: warn, then let them through

**When another Sign-up sits on a Shift of the same kind whose time overlaps, the Member is warned and may proceed.** Legacy warns on the exact same start only, and skips six kinds by hardcoded id. Two deviations, both small and both named: **overlap rather than exact start**, because a 11:00 to 12:30 shift and a 11:30 start on the same cart are the case the exact match misses; and **no skip list**, because a warning on _Training_ costs one click and a flag on the kind costs a column and a screen. The Scheduler is not warned when placing someone; they know.

This is a warning, not a block, because two GIs may share a gallery on purpose and legacy lets them.

### 6. On the day

- **A Member may create a Shift whose start has already passed, on the current day only.** Arriving at 11:10 and writing an 11:00 start is the whole of the desk case. Earlier days are closed.
- **Edit and delete of one's own Shift close at its start**, the rule [#558](https://github.com/roytanaka/dmv-rom-v2/pull/558) just set for take and drop. Legacy lets a GI delete on the day after the start; that half is not ported, because a Shift that already carries a visitor count should not vanish by its owner's hand. The Scheduler removes it.
- **Sign-out is unchanged** from ADR-0023: the panel opens five minutes before `ends_at`, the number is required. `ends_at` is start plus units × 45, which is the legacy gate.

### 7. Hours credit: the multiplier becomes a decimal

**`groups.hours_multiplier` becomes a decimal, and GI's is 4/3 (stored as 1.333).** ADR-0022 §7 made the multiplier an integer because the one non-unit case was Walker's 2. GI is the second: a unit is 45 minutes on the Shift and one whole credit in the report. 45 × 4/3 = 60, so _recalculate this month_ produces the legacy number to the unit — 6 units in a month is 6, not the 4.5 that rounds to 5 — through the existing "multiply the summed minutes, then convert to whole hours" path. No code path changes; one column type and one seeded value do.

The alternatives were to accept a 25% drop in every GI's reported number, or to make a GI unit an hour on the Shift, which would move the sign-out gate 15 minutes late on every unit. Both were rejected: the first restates a decade of history, the second breaks the one timing GIs rely on.

### 8. Events need no entity

**An event is Scheduler-authored Shifts with an event station as their kind**, capacity 1 each unless the Scheduler says otherwise, in the month's Schedule or a Schedule of their own; the Scheduler chooses. A GI takes one as any Shift and picks objects on the Sign-up; §4's hold does the rest. Legacy's `giEvent` row is a name, a station, a date range and the object window, and §4 carries the window on the kind, so nothing remains for a table to hold. This settles the model half of [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262); the Events subgroup's screen, if it still wants one, is a filtered view of the Group's Shifts.

### 9. Not ported

- **The weekly template** (`giweeklySchedule`) and the **month-close prune**. The first only ever supplied start times (§2); the second deletes rows this model never creates.
- **The station categories** (General Museum, Natural History, World Cultures, Special Exhibition, Special Events). The picker sorts by category then name in legacy; ours is a flat, searchable list. A column for grouping is #567's call if 95 names prove too many to scan.
- **The sign-in page's network gate.** Dead in legacy, and the kiosk is closed (ADR-0023).
- **Photos, comments and the rest of `giArtefact`.** Content catalog, not scheduling (§3).
- **Legacy's three ObjectID string searches and the `override` flag.** Replaced by §3 and §5.

### 10. Import mapping

Recorded here because the shape was chosen partly to make it trivial; the legacy migration plan owns the work.

- Every **filled** `gischeduledTours` row → one Shift (`shift_kind_id` from `TourID`, `starts_at` from `Date` + `Time`, `ends_at` = start + 45 × `Count`, `capacity` 1) and one Sign-up (`visitor_count` from `Visitors` when `Confirmed`).
- The row's `ObjectID` list → the Sign-up's Objects; `giArtefact` rows with `hasObject = 1` → the Object list.
- **Empty rows are dropped.** Closed months hold none; the open month's are the template, which is not ported.
- `gitour` → `shift_kinds`, all categories, with `off_site` set on category 5 and on _Off site_. `giEvent` → nothing; its rows are already the event Shifts.
- The credit history in `giHistory` and `MemberActivity` imports as ADR-0022 says, and the 4/3 multiplier makes recalculated months agree with it.

## Considered alternatives

- **An open block per start time, with kind, start and end on the Sign-up.** The Scheduler bulk-creates one wide Shift per half-hour and the taker fills in the rest. Rejected: it gives the kind two homes, so every reader of `Shift.shift_kind_id` (the alert, the pickers, the import, #567) grows a second branch, and once start and end are on the Sign-up too, the Sign-up is a Shift with extra steps.
- **Scheduler pre-authored station Shifts.** 95 stations × 13 starts × 6 days. Not a schedule anyone would build or read.
- **A manual Object hold record** the Scheduler writes per event. Rejected for a flag on the kind (§4): legacy's window is automatic, and a record the Scheduler must remember to write is the kind of thing that stops being written.
- **A hard block on station clash.** Rejected: legacy lets two GIs share a gallery, and the data shows it happens.
- **Porting the weekly template as Group data.** Rejected: its whole visible effect is a list of starts, and a free picker also covers the desk path the template never served.
- **Waiting for the content catalog** before Objects exist. Rejected: scheduling needs 144 names and an active flag, not a catalog.
- **A Schedule-level self-serve flag.** Rejected: GI is self-serve as a Group, and a month-by-month switch is a mistake waiting for the month someone forgets.

## Consequences

- **Groups gain two settings** (`self_serve_shifts`, `self_serve_unit_minutes`) and **`hours_multiplier` changes type** to a decimal; the seeded GI values are on, 45, and 1.333. Walker's 2 and every 1 are unaffected.
- **Shift kinds gain `off_site`.** A new table `objects` (group-scoped: name, active, sort order) and a pivot between Sign-ups and Objects.
- **Policy grows three abilities** on `Shift` for a Member in a self-serve Group: create, and update and delete of a Shift they own by the derived rule of §1, all closed at the start. No authorship column; [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) gains one more consumer to weigh.
- **Two server-side checks** on Sign-up write: the Object overlap block and the station overlap warning. The warning needs a confirm step in the create form, which is new UI.
- **The Objects maintenance screen** is #567's shape a second time. Build them together.
- **Help articles**: the self-serve create flow for GIs, and Objects maintenance for their Scheduler (ADR-0025).
- **Docents' "which tour did I give" question ([#568](https://github.com/roytanaka/dmv-rom-v2/issues/568)) is not answered here.** It is the opposite shape: a Scheduler-authored slot on which the taker names a sub-kind. Nothing in this ADR moves a kind onto the Sign-up, and #568 should not read it as precedent.
- **Visitor Wayfinders is untouched.** Their kinds are places and the Scheduler authors every slot; ADR-0021 already fits.
- **The demo seed** can now shape a GI month like the real roster: about 4.5 self-authored Shifts a day, one to three units, three objects each, and visitor counts on past days.

## References

- [ADR-0015](0015-scheduling-model.md) — the object-reservation strategy this lands
- [ADR-0021](0021-scheduling-first-pass.md) — Schedule, Shift, ShiftKind, Sign-up; refined here (a Member may author a Shift; object reservation settled)
- [ADR-0022](0022-hours-and-statistics-model.md) — hours; §7 amended here (decimal multiplier)
- [ADR-0023](0023-scheduling-second-pass.md) — visitor count and the sign-out gate; unchanged and relied on
- [ADR-0024](0024-emailing-model.md) — the empty-desk alert, which keeps working because the kind stays on the Shift
- [#262](https://github.com/roytanaka/dmv-rom-v2/issues/262) — GI events (model half settled in §8), [#567](https://github.com/roytanaka/dmv-rom-v2/issues/567) — shift kind maintenance, [#568](https://github.com/roytanaka/dmv-rom-v2/issues/568) — Docents' tour given
- Legacy: `res/gi/gi.php` — `_showSignInSchedule` (~275), `_get_dialog_shiftSignUp` (~372), `_giaddsignUp` (~441), `_checkForConflict` (~3131), `_checkForEventConflict` (~3102), `_confirm_statistics_scheduled` (~4248); `res/gi/gi_data_25-02-22.js` and `gi_data_signin_18_12_27.js`
