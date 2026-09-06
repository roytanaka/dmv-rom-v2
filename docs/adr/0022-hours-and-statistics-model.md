---
status: accepted
date: 2026-08-23
accepted: 2026-08-23
---

# Hours and statistics: a faithful port of the legacy monthly bucket

Settles the two questions [#320](https://github.com/roytanaka/dmv-rom-v2/issues/320) and [#321](https://github.com/roytanaka/dmv-rom-v2/issues/321) left open when the sub-group wayfinder map ([#314](https://github.com/roytanaka/dmv-rom-v2/issues/314)) closed — _what does "Hours" mean on a sub-group page, and by what mechanism does every Group get one?_ — against a legacy requirement restated by the committee: every Group and sub-Group carries an Hours menu, independent of its parent, and sub-Group hours always total up to the parent in reporting.

This ADR **amends [ADR-0010](0010-group-model.md)** (the `hours & stats` capability is withdrawn; hours joins roster as always-on) and **amends [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md)** (reporting reads cross the parentage boundary, unlike content reads). Amendment notes are folded back onto both.

## Context

Unlike scheduling — where [ADR-0021](0021-scheduling-first-pass.md) found that most of legacy's elaboration encoded workarounds for capabilities it lacked — the legacy hours feature is **small, coherent, and in daily use**. One table, 74,248 rows, current through May 2026. The decision taken here is therefore the opposite one: **port it faithfully**, and deviate only where legacy has a security defect or a fundamental flaw.

### What legacy does

`dmv_MemberActivity` is the whole feature:

```sql
CREATE TABLE `dmv_MemberActivity` (
  `ID`              int(11) NOT NULL AUTO_INCREMENT,
  `committee`       varchar(12) NOT NULL,       -- Committee.symbol, not an FK
  `subCommitteeID`  int(11) NOT NULL DEFAULT 0, -- 0 = the committee itself
  `MemberID`        int(11) NOT NULL DEFAULT 0,
  `YYYYMM`          varchar(8) DEFAULT NULL,    -- the bucket; there are no dates
  `MeetingID`       int(11) NOT NULL DEFAULT 0, -- non-zero = meeting attendance
  `Total_Scheduled` int(11) NOT NULL DEFAULT 0,
  `Extra_Hours`     int(11) NOT NULL DEFAULT 0,
  `Total_Hours`     int(11) NOT NULL DEFAULT 0,
  `Visitors`        int(11) NOT NULL DEFAULT 0 COMMENT 'Click Count',
  `Interactions`    int(11) NOT NULL DEFAULT 0,
  `lastUpdate`      timestamp NOT NULL DEFAULT current_timestamp()
                    ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM;
```

Five findings from reading the code and the 2026-06-29 production dump, each of which changed a decision below:

1. **Three sources, one row.** `Total_Scheduled` is written by each Group's own _recalculate this month_ action on its scheduling screen (`docent.php:3570`, `vg.php:2034`, `gdr.php:3087`, and seven more), always at `subCommitteeID=0`. `Extra_Hours` is self-entered through one dialog. **Meeting attendance is also stored in `Extra_Hours`**, on rows where `MeetingID` is set, entered by a chair from a meeting roster.
2. **`Total_Hours` is derived by database trigger,** not by PHP — which is why no application code writes it. Two triggers, `dmv_MemberActivity_UpdateHours` (before insert) and `dmv_MemberActivity_AddHours` (before update), both set `NEW.Total_Hours = NEW.Total_Scheduled + NEW.Extra_Hours`. The identity holds on all 44,913 rows dated 2018-04 or later.
3. **The rollup is free, and accidental.** A sub-committee's row stores the **parent's** symbol in `committee`. Every report that groups by `committee` therefore already includes its sub-committees, and there is no rollup code anywhere. Sub-committee rows are a small minority (5,190 under the DMV, 1,558 under ROM Travel, 373 under Special Projects, and under a hundred each elsewhere), and they only ever carry `Extra_Hours`.
4. **`Interactions` is dead data.** Non-zero on **zero** of 74,248 rows, despite having its own entry dialog and its own column in the detailed report.
5. **Walker multiplies by two.** `walker.php:2298` calls `_saveScheduledMemberActivity('walker', …, 2*$total)`, converting walks to hours in PHP. Every other Group multiplies by one. This is the `hoursper` trap already recorded under **Count** in `CONTEXT.md`.

### The security defect

`remap()` — the single entry point for every request in `servicesp.php` — has its session check **commented out** (lines 628–637). `_MemberActivityUpdate()` then takes `mid`, `committee`, and `subCommitteeID` straight from `$_POST`, interpolates them into SQL unescaped, and never verifies that `mid` is the logged-in Member. Anyone who can reach the URL can write hours for any Member in any Group, and can inject SQL. The DMV-level report gate has the same shape: `get_statsReport()` reads `isChair`, `isStatistician`, `isRecords`, and `isSecretary` from `$_POST`, so the client asserts its own authority.

This is the one place the port deviates, and it costs nothing: hours are written for `Auth::user()`, and Eloquent parameterises. No screen changes.

## Decision

### 1. The grain is legacy's grain

One **Hours record** per Member, per Group, per calendar month, per optional Meeting. Model `HoursRecord`, table `hours_records`: `member_id`, `group_id`, `year_month`, `meeting_id` (nullable), `scheduled_hours`, `extra_hours`, `total_hours`.

**Whole hours, stored as integers.** The legacy form rejects decimals outright — _"Extra hours should be to the nearest hour. We are not concerned with minutes."_ — and nothing in the reporting needs finer.

`total_hours` stays derived (`scheduled_hours + extra_hours`), computed in the model rather than by a database trigger. Same identity, visible in the code.

Not adopted: dated entries. A dated `hours_entries` table would give a natural audit trail and correction-by-delete, but it changes the unit the department has reported in since 2013, complicates the import, and buys nothing the adjustment log below does not.

### 2. Three sources, ported as-is

- **Scheduled hours** are **stored, not derived.** A Group officer runs _recalculate this month_ and the total is overwritten. Deriving from Sign-ups at report time was considered and rejected: editing or deleting a Shift years later would silently restate a closed fiscal year.
  **Bounded, unlike legacy:** recalculation is permitted for the current fiscal year only. Prior years are closed.
- **Extra hours** are self-entered, through the legacy dialog: the current month and the previous month, one input each, showing the hours on file and the date they were last touched. Entry is **additive** — the number typed is added to what is there, negatives correct, the result floors at zero.
- **Meeting hours** are **out of this pass.** They live in `extra_hours` on rows carrying a `meeting_id`, they import, and their reports run — but there is no entry path, because entering them needs a meeting attendance roster and the v2 `meetings` table has no attendance concept (`group_id`, `title`, `description`, `held_at`, `location`, `video_url`, `is_published`). That is a Meetings feature, not an Hours feature.

### 3. Hours is always-on; the capability flag is withdrawn

The committee's requirement is that **every** Group and sub-Group carries the Hours menu, which is what legacy does: every branch of `_switchSubCommittee()` emits `Statistician_sub`, including for a viewer who is a member of nothing.

Expressing that as "set `has_hours_stats` to true everywhere" leaves a flag that is always true, which rots. So **`has_hours_stats` is dropped from `groups`**, and hours joins roster as an always-on capability. ADR-0010's capability set goes from seven to six.

Consequence accepted: `Role::Statistician` loses its `requiredCapability()` gate and may attach to any Group. That is correct — any Group can have someone who watches its hours.

### 4. Entry is open; reporting is not

Legacy's Hours menu is ungated, but the **label and payload** differ by role: chairs get _Statistics_ with reports and a member-history picker; everyone else gets a bare _Extra Hours_ button. That split is the decision:

| Surface                                                              | Who                                                                                                 |
| -------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Extra-hours entry, for oneself, on any Group whose page one can open | Any logged-in Member                                                                                |
| One's own hours, across all Groups                                   | The Member themselves (the **My Hours** destination)                                                |
| A Group's reports                                                    | Chair or Statistician **of that Group**, Chair or Statistician **of its parent**, or super-tier     |
| DMV-wide reports                                                     | Chair, Secretary, or Statistician **on the DMV root Group**, the Records stewardship, or super-tier |

There is deliberately **no aggregate view for an ordinary Member** — not even a Group total. Per-Member hours feed service awards and standing conversations, and legacy has never shown them to a peer.

**Entering hours on another Member's behalf is out of this pass.** Legacy has a chair bulk-entry screen, but it shares the unauthenticated endpoint above, so legacy has no on-behalf-of _control_ at all. Self-entry only is deliberately stricter than legacy, pending a real chair-verification workflow.

### 5. Rollup is subtree-wide, and beats ADR-0019

A Group's report shows **its own hours** and **its own hours including every descendant**, to any depth. Legacy's tree is two levels, so "up to the parent" was unambiguous there; the v2 tree is not depth-limited, and rolling only one level would silently drop grandchildren.

This is an **explicit exception to [ADR-0019 §B](0019-group-listing-visibility-and-parentage-authority.md)**, which holds that parentage carries structural authority and that reading a child's _content_ requires a role held in that child. **Hours are not content.** A statistic aggregates; a document does not. A parent's Chair reads descendant hours in aggregate, and a `Private` child's document library stays opaque to that same person. If this exception were refused, a parent's total would stop matching the sum of its children, which is the number the ROM is given.

### 6. An adjustment log behind an unchanged screen

Legacy's additive update leaves no author and no history: one `lastUpdate` timestamp per row, and a mistyped entry is unattributable and fixable only by another delta.

Every write therefore also appends an **Hours adjustment** — model `HoursAdjustment`, table `hours_adjustments`: the record it targets, the delta, and `created_by`. The entry screen is unchanged. This is affordable only because the authentication defect is being fixed anyway: once the writer is known, recording them is one column.

### 7. Per-Group hours multiplier

Walker's `2*$total` moves from PHP to data: an `hours_multiplier` column on `groups`, default `1`, Walker `2`. Same numbers; a future Group with its own ratio becomes a data edit rather than a deploy.

### 8. Reports: same numbers, new mechanism

Every legacy report ships except **Summary Visitor Interactions** (see _Deliberately out of the first pass_):

- **DMV level** — Summary Committee Statistics, Detailed Committee Statistics, Active Members Ranked Hours, Members with Zero Hours / Zero Shift Hours / Zero Extra Hours, Member Extra Hours summary.
- **Group and sub-Group level** — a month picker, a fiscal-year picker, a Member History dropdown, and the Member Extra Hours / Member Meeting Hours summaries.

The **fiscal year runs 1 April to 31 March**, and every report is a Group × twelve-month or Member × twelve-month matrix with a year-to-date column. Summary Committee Statistics lists only Groups with `has_scheduling` in its scheduled section and reports extra hours as a single org-wide row; Detailed Committee Statistics breaks both out per Group. Both are kept as they are.

Legacy renders these through a PHP PDF library. v2 renders **HTML styled for print, plus CSV export**, and lets the browser make the PDF. A mechanism deviation, not a behaviour one, and it avoids adding a package for a report nobody reads on screen.

## Considered alternatives

- **Dated hours entries instead of a monthly bucket.** Rejected: changes the unit the department has reported in since 2013 for a benefit the adjustment log already delivers.
- **Deriving scheduled hours from Sign-ups at report time.** Rejected: a Shift edited or deleted years later would silently restate a closed fiscal year.
- **Keeping `has_hours_stats` and setting it true everywhere.** Rejected: an always-true flag rots, and the next reader cannot tell whether `false` is meaningful.
- **Rendering every Group's Hours tab unconditionally while keeping the flag.** Rejected: contradicts ADR-0010, where tabs follow capability.
- **A sub-Group surfacing its parent's Hours** (the inheritance option in [#320](https://github.com/roytanaka/dmv-rom-v2/issues/320)). Rejected: the committee's requirement is explicit that the menus are independent of any parent, and legacy stores sub-Group hours separately.
- **Rolling up only one level.** Rejected: silently drops grandchildren the first time anyone nests three deep.
- **Restricting the rollup to Groups where the reader holds a role.** Rejected: a parent's total would stop matching the sum of its children.
- **Showing ordinary Members a Group aggregate.** Rejected: legacy has never done it, and per-Member hours feed service awards.
- **A PDF library, to match legacy's output exactly.** Rejected: a new package for a print-only artefact the browser already produces.
- **Porting the unauthenticated write endpoint faithfully.** Rejected on security grounds; this is the deviation the "faithful port" rule explicitly reserves.

## Consequences

- **`groups` loses `has_hours_stats` and gains `hours_multiplier`.** ADR-0010's capability set is six, not seven. `Role::Statistician` becomes a core role by the `requiredCapability()` rule, attachable anywhere.
- **The Hours tab renders on every Group and sub-Group,** with the entry form for everyone and the reports for officers.
- **Reporting reads are subtree-wide.** This is the one place a Group's data crosses the parentage boundary that ADR-0019 draws for content.
- **Migration must respect the Walker multiplier** and must not map legacy `Count → Count` (see **Count** under Legacy vocabulary in `CONTEXT.md`).
- **Meeting hours import but cannot be entered** until Meetings grows an attendance roster. Reports will correctly show nothing for months after cutover.
- **Legacy's database triggers are not ported.** The derivation moves into the model, where it is visible.

## Deliberately out of the first pass

- **Visitor Interactions and the `Visitors` counter.** ~~`Interactions` is zero on every one of 74,248 rows. Historical values import; no entry form is built and Summary Visitor Interactions does not ship.~~

  > **Withdrawn (2026-09-06, [ADR-0023](0023-scheduling-second-pass.md)).** Two things were wrong here. The evidence covered `MemberActivity.Interactions`, while the exclusion named the **scheduling** `Visitors` column — a different table this ADR never examined, live in nine of ten Groups and current through the present month. And `MemberActivity.Interactions` is **not** zero: 98 of 71,388 production rows carry a value across twelve Groups. The UPDATE branch is indeed unreachable, but `mysql_select` returns `FALSE` on zero rows, so every entry lands through the INSERT below it. **ADR-0023 ships all of it**: two nullable integers on the `Sign-up`, an **`extra_interactions`** whole integer on `hours_records` (outside `total_hours`, entered beside extra hours with an `hours_adjustments` row), and **Summary Visitor Interactions returns** with one composition rule for every Group. Six Groups have no route into that report except `extra_interactions`.
- **Meeting-hours entry**, pending a meeting attendance roster.
- **On-behalf-of entry** by a Chair or Statistician, pending a verification workflow. Legacy's _Statistician — Verifying Extra Hours_ procedure is the shape this should eventually take.
- **Locking a closed month.** Members may correct their own hours with no deadline. A lock with nobody able to unlock it is a support ticket; revisit alongside verification.
- **`no_activity.php`**, a fees-and-hours crossover report reachable only by typing its URL. Not linked from any menu; not ported.

## References

- [ADR-0010](0010-group-model.md) — the Group model and its capability set, amended here
- [ADR-0011](0011-authorization-model.md) — per-Group roles, which the reporting gate reads
- [ADR-0019](0019-group-listing-visibility-and-parentage-authority.md) — the structure/content split, given an explicit exception here
- [ADR-0021](0021-scheduling-first-pass.md) — Sign-ups, from which _recalculate this month_ computes scheduled hours
- [#308](https://github.com/roytanaka/dmv-rom-v2/issues/308) — the committee requirement: Documents and Hours on every sub-Group
- [#319](https://github.com/roytanaka/dmv-rom-v2/issues/319) — legacy Programs list and sub-group menu research
- [#320](https://github.com/roytanaka/dmv-rom-v2/issues/320), [#321](https://github.com/roytanaka/dmv-rom-v2/issues/321) — the two questions this ADR answers
