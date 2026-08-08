# Legacy scheduling schema — complete inventory across every scheduling Group

Research findings for [#325](https://github.com/roytanaka/dmv-rom-v2/issues/325), a child of the scheduling Wayfinder map [#323](https://github.com/roytanaka/dmv-rom-v2/issues/323).

**Scope is shape, not intent.** The code carries the _what_, not the _why_. Where a column's purpose is not derivable from its call sites it is listed in [§8 Flagged unknowns](#8-flagged-unknowns) rather than guessed at.

**Not in scope:** planning the migration, reading production data, proposing the new schema.

---

## 1. Method, sources, and caveats

### Source

Read-only archaeology against `/Users/roytanaka/Documents/ROM/DMV/dmv-rom-legacy`. All citations are paths relative to that repo root, e.g. `public_html/res/vg/vg.php:916`.

### There is no schema

**No DDL exists for any `dmv_*` scheduling table anywhere in the legacy repo.** Every column list below is _derived from SQL string literals in PHP_ — `INSERT` column lists where available, otherwise `UPDATE … SET`, `WHERE`, `ORDER BY`, and `$row['…']` reads. Consequences that recur throughout:

- **`SELECT *` hides columns.** Where a table is only ever read with `SELECT *`, additional columns may exist that no code names. Flagged per table.
- **DB-side `DEFAULT` values are invisible.** Several columns are _read but never written_ (`docentscheduledTours.Count`, `gdrscheduledTours.Count`, `rombusToTrip.Status`, every `*Schedules.status` at creation). Their behaviour depends entirely on defaults we cannot see.
- The one piece of real DDL in the tree is `public_html/resboot/res/vg/vgSQL.txt`, a **2012 mysqldump**. It is 13 years stale — its `vgscheduledTours` has no `Type`, `Colour`, `VgID`, `Confirmed`, or `SPScheduleID`. Used below only as historical corroboration, never as current truth.

### Which tree is live

`public_html/res/` is the live tree. The repo also contains near-complete stale copies at `public_html/res4test/`, `resboot/`, `resphone/`, `resdocumentation/`, `demo/`, and `new/`. Findings below are from `res/` only; the copies are cited only where they preserve something the live tree has lost.

### The `x` prefix is a disable convention

`find public_html/res -maxdepth 2 -name 'x*.php'` yields `xvg_daily.php`, `xgi.php`, `xspecial_daily.php`, `xinvitation_daily.php`, `xdaily.php`, `xservicesp.php` — each a sibling of a live file with the same name minus the `x`. Confirmed mechanically for the cron jobs: the dispatcher builds the include path from the Group symbol, so an `x`-prefixed file can never be reached by name (`public_html/res/services/daily.php:40-42`).

### One wrapper behaviour that settles several questions

`mysql_select()` **returns `FALSE` on zero rows** — `public_html/res/services/functions.php:32-41`:

```php
$result = @mysqli_query($mysqli,$sql) or die (…);
if(mysqli_num_rows($result) > 0) { return $result; } else { return FALSE; }
```

This matters because the codebase is full of `if(mysql_select(…))` existence tests. They all work correctly. Any reading of this inventory that assumes truthy-on-empty (and therefore concludes that the MIS check, the duplicate-month guards, or the GI conflict checks are broken) is wrong.

### Privacy

No member names, emails, phone numbers, or addresses appear below — column names and structure only. Note for anyone repeating this work: the `docs/` subdirectory under each Group in the legacy repo contains **real member data** (CSV exports, meeting minutes). Stay out of them.

---

## 2. Per-Group table inventory

### 2.0 The Group registry itself — `Committee`

Legacy already has a Group table with a scheduling capability flag, and it drives real behaviour.

| Column                                       | Use                                                                                                                                                                                                   | Cite                                                                                                                             |
| -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `ID`                                         | PK. Doubles as the eligibility code for "this committee only" (§7.3)                                                                                                                                  | `public_html/res/special/special.php:1644`                                                                                       |
| `symbol`                                     | The short name (`vg`, `docent`, `special`…). **Used to construct table names at runtime**                                                                                                             | `public_html/res/services/servicesp.php:36`                                                                                      |
| `Name` / `LongName`                          | Display names                                                                                                                                                                                         | `servicesp.php:36`, `:3011`                                                                                                      |
| `Active`                                     | Group is live                                                                                                                                                                                         | `servicesp.php:473`                                                                                                              |
| `DisplayOrder`                               | Ordering across every cross-Group report                                                                                                                                                              | `servicesp.php:3883`                                                                                                             |
| **`HasSchedule`**                            | **0/1 scheduling capability flag.** Gates whether the Group contributes shift hours to the all-DMV activity report and whether its rows are collected by the cross-Group "Scheduled Activities" panel | `servicesp.php:2784`, `:3270`; `public_html/res/services/detailedactivity.php:52-55`; `public_html/res/services/activity.php:32` |
| **`visitorTable`**                           | **A table-name _suffix_ stored as data.** `NULL` means the Group is not visitor-facing                                                                                                                | `public_html/res/services/interactions.php:35`, `:85`                                                                            |
| `jsVersion`                                  | Cache-buster for the Group's client bundle                                                                                                                                                            | `servicesp.php:735`                                                                                                              |
| `defaultMenu`, `aboutUs`, `aboutUsContactID` | Chrome/content, not scheduling                                                                                                                                                                        | `servicesp.php:397`, `:415`, `:5433`                                                                                             |

`visitorTable` deserves emphasis. `public_html/res/services/interactions.php:85` does:

```php
$commtable = "$committee"."$table";   // e.g. 'special'+'Schedule', 'vg'+'scheduledTours'
```

So the _name of the table holding the visitor count_ is a data value concatenated onto the Group symbol at runtime. This is why a repo-wide grep turns up bare table fragments (`Schedule`, `scheduledTours`) with no prefix.

### 2.1 Visitor Guides — `vg`

Monthly shape. Main file `public_html/res/vg/vg.php` (2,770 lines).

#### `vgscheduledTours` — the dated rows

| Column         | Use                                                                                               | Cite                                                                                     |
| -------------- | ------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `ID`           | PK                                                                                                | `public_html/res/vg/vg.php:276`                                                          |
| `Day`          | Denormalized day-of-week **name string**                                                          | `vg.php:1355`, `:1759`                                                                   |
| `Time`         | Shift start                                                                                       | `vg.php:200`, `:706`                                                                     |
| `Date`         | Shift date                                                                                        | `vg.php:1368`                                                                            |
| `YYYYMM`       | Denormalized month key; the dominant `WHERE` key                                                  | `vg.php:610`, `:788`                                                                     |
| `Colour`       | `'red'`/`'blue'` alternating-week marker; a pure function of `Date` (§5)                          | `vg.php:720`, `:1342-1346`                                                               |
| **`Type`**     | The **shift kind**, stored as a name string, not an FK                                            | `vg.php:218`, `:1238`; matched cross-Group at `public_html/res/special/special.php:1406` |
| **`Count`**    | **Duration in hours** (decimal). Proof in §3.2                                                    | `vg.php:210-214`, `:614-618`, `:706`                                                     |
| `VgID`         | FK → `vg.ID` (the membership row). `0` = vacant, or filled by a Wayfinder                         | `vg.php:909`, `:934`                                                                     |
| `MemberID`     | Denormalized copy of the person behind `VgID`. `0` = vacant                                       | `vg.php:1362`, `:1772`                                                                   |
| `Confirmed`    | 0/1 — **signed out / attendance verified**, not "booked". Reset to `0` on self-removal            | `vg.php:314`, `:935`, `:1983`                                                            |
| **`Visitors`** | The visitor-interaction count. Written **only** by remote sign-out; read only by dead code (§7.9) | write `vg.php:315`; read `vg.php:2163` (dead)                                            |
| `SPScheduleID` | FK → `specialSchedule.ID`. `0` = no cross-Group link (§7.1)                                       | `vg.php:916`, `:1940`                                                                    |
| `TourID`       | Read only inside the never-called `__vgPrintTourSummary`; **never written**                       | `vg.php:2164`                                                                            |

Present in the 2012 dump, zero references in live code: `PostedTour`, `SignupTourID`, `IsVet`, `VetText`, `DocentNOTQualified`, `DocentID` — Docent-lineage fossils.

**There is no eligibility column.** Eligibility is computed at render: `Type='Shadow'` rows are a provisional-only lane (`vg.php:661`, `:865`), and `_CovidProofOK()` is now `return true;` (`servicesp.php:2417-2419`).

#### `vgSchedules` — the monthly container

`ID`, `YYYYMM`, `MONTH_YEAR`, `status`, `notes`, `ExhibitionRevenue`. Created without `status` (`vg.php:1376`), so new months take the DB default. Status values in §4.

#### `vgweeklySchedule` — the named weekly pattern

`ID`, `Name`, `Colour`, `DayOfWeek` (day-name string), `NameOfDayID` (day number), `TimeOfDay`, `Type`, `Count`, **`VgID`**.

Two things make VG's pattern the most elaborate of the eight:

- **Patterns are named**, and the name _is_ the grouping key — there is no pattern table. `SELECT DISTINCT Name` builds the picker (`vg.php:1101`); rename is `UPDATE … SET Name=…` (`vg.php:1413`); delete is `DELETE … WHERE Name=…` (`vg.php:1461`). `"UnNamed"` is the not-yet-saved sentinel.
- **`vgweeklySchedule.VgID` is a permanent assignee** — dialog label "Permanent?" (`vg.php:1530`), column header "Permanent Visitor Guide" (`vg.php:1164`). The generator copies it into the dated row (`vg.php:1362-1368`), so generating a month **pre-fills sign-ups**. This is a recurring _Sign-up_ wearing a shift template's clothes — the same finding as Reception's fortnight (§2.6), in a second Group.

#### `vgShiftTypes` — the shift-kind vocabulary

`ID`, `Shift` (the name — not `Name`), `Active`, possibly `LastUpdateField`. **Exactly one SQL reference in the live tree** (`vg.php:1470`), plus the generic maintenance button (`vg.php:1125`). Detail and usage counts in §7.4.

#### Other

`vgHistory` (`ID`, `VgID`, `YYYYMM`, `Total_Scheduled`, `Total_Group`, `Total_Museum`), `vgExec`, `vg` (membership: `ID`, `MemberID`, `Status`, `StatusID`, `Active`, `FullName`, `LOA`, plus tinyint office flags `Scheduler`, `Statistician`, `Chair`, `Secretary`, `isPrimary`, `GenInfo`).

Dead relative to live code: `vgCategory`, `vgsection`, `vgtour`, `vggroupTours` (all reachable only from `__vgPrintTourSummary`, which **has no caller**), and `vgGalleryTheme`, `vgFactsheet` (zero references).

### 2.2 Visitor Wayfinders — `special`

Date-range shape. Main file `public_html/res/special/special.php` (2,762 lines). The table prefix `special` names **this one Group**; it is not an adjective.

#### `specialEvents` — the date-range container

| Column                  | Use                                                                              | Cite                                       |
| ----------------------- | -------------------------------------------------------------------------------- | ------------------------------------------ |
| `ID`                    | PK                                                                               | `public_html/res/special/special.php:1352` |
| **`committee`**         | **Owning Group symbol.** `'special'`, or one of the six Friends Committees       | `special.php:1350`, picker `:1287-1294`    |
| `Name`                  | Event name                                                                       | `special.php:1350`                         |
| `startdate` / `enddate` | lowercase; the date range                                                        | `special.php:1350`                         |
| `comments`              | The field the dialog labels _Description_                                        | `special.php:1350`, rendered `:582`        |
| **`ScheduleUsing`**     | Authoring mode: `1` = Daily Pattern, `0` = Shift/Activity. **Write-once** (§7.5) | `special.php:1350`, `:1300`, `:1913`       |
| `DisplayStyle`          | `3` Calendar / `2` Activities Across / `1` Activities Down                       | `special.php:1350`, dispatch `:533-547`    |
| `CreatorID`             | MemberID of the author; the notify target on cancel                              | `special.php:1350`, `:566`                 |
| `Confirmed`             | Month closed and credited. Seeded `0` for `special`, `1` for Friends             | `special.php:1348`, set `:2455`            |
| `visible`               | lowercase; the publish gate                                                      | `special.php:1435`                         |
| `Active`                | Soft-delete / archive                                                            | `special.php:419`, `:1190`                 |

**`specialEvents` has no `ObjectID` column** — see §5.1 for the correction to the ticket's premise.

Note that `specialEvents.committee` makes this table a **shared container for seven Groups**. The Friends Committees (`fop`, `fes`, `ftc`, `fcc`, `fsa`, `famis`) have no scheduling code of their own — their directories contain only a 5–8 KB stub `.php` and a data file.

#### `specialSchedule` — the dated rows

Column list is exact, from the generator's `INSERT` (`special.php:1941`, `:1999`):

```
(ID, EventID, ActivityID, shiftDate, YYYYMM, shiftStartTime, shiftEndTime, RoleID, Count, VGActivity, Confirmed)
```

plus `MemberID`, `VgID`, `VGScheduleID`, `Visitors` written later.

| Column                                | Use                                                                                                 | Cite                                                             |
| ------------------------------------- | --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `EventID`                             | FK → `specialEvents.ID`                                                                             | `special.php:1941`                                               |
| **`shiftDate`**                       | The date. **Not** `Date` — unique naming in this Group                                              | `special.php:1941`, `:597`                                       |
| **`shiftStartTime` / `shiftEndTime`** | Both stored; no duration-only model                                                                 | `special.php:1941`                                               |
| `YYYYMM`                              | Denormalized, and **effectively dead** — every report re-derives it with `substring(shiftDate,1,7)` | write `special.php:1940`; re-derived `:2532`, `:2610`            |
| `ActivityID`                          | FK → `specialActivities.ID`. Magic values: `2` = Wayfinding, `3` = Digital/iPad Wayfinding          | `special.php:1941`, `:1018`, `:1029`                             |
| **`RoleID`**                          | FK → `specialRoles.ID` — the **shift kind**                                                         | `special.php:1941`, joins `:166`, `:673`                         |
| `MemberID`                            | Occupant; `0` = empty seat                                                                          | `special.php:241`, `:1002`                                       |
| `VgID`                                | `>0` ⇒ occupant filled it as a Visitor Guide ⇒ excluded from Wayfinder credit (§7.1)                | `special.php:1002`, exclusions `:475`, `:2314`, `:2431`, `:2533` |
| `VGScheduleID`                        | Cross-link → `vgscheduledTours.ID`; `0` = unlinked                                                  | `special.php:1396`, `:1410`                                      |
| `VGActivity`                          | String copy of the paired VG shift-kind name. `''` = not shared (§7.1)                              | `special.php:1941`, consumed `:1396`                             |
| **`Count`**                           | **Shift credit hours.** Dialog labels "Shift Hours" / "Shift credit"                                | `special.php:1451`, `:1828`; summed as hours `:2532`             |
| `Confirmed`                           | 0/1 signed out / credited                                                                           | `special.php:257`, `:2410`                                       |
| **`Visitors`**                        | The visitor-interaction count, entered at sign-out                                                  | write `special.php:284`; read `:2311`, `:2358`                   |

**No capacity column and no eligibility column.** Capacity is N rows (§7.10); eligibility is reached by join to `specialRoles.Eligible` (§7.3).

#### `specialActivities` — the master activity list

`ID`, `Name`, **`VGActivity`**, `Active` (and possibly `LastUpdateField`). Maintained through the **generic** table editor — button at `special.php:1202`, preceded by the comment `// Support for VG link`; `showTable("Activities")` → `servicesp.php:9112-9151`, which composes `{prefix}` + `special` + `Activities`. Because the editor is schema-driven (it iterates `mysqli_fetch_field`), the on-screen columns _are_ the physical columns and no PHP enumerates them. Full treatment in §7.1.

#### Shift kinds — `specialRoles`

Legacy `specialRoles` / `RoleID` means **a kind of position within an event** ("Level 2 greeter", "Plan Your Visit desk"). It is a **kind of Shift**. It confers no authority — Wayfinders' authority flags live on the `special` membership table (`Chair`, `Secretary`, `Scheduler`, `Statistician`, `GenInfo`, `isPrimary`, `special.php:367`).

| Column         | Use                                                                                       | Cite                                 |
| -------------- | ----------------------------------------------------------------------------------------- | ------------------------------------ |
| `ID`           | PK; targeted by `specialSchedule.RoleID`                                                  | `special.php:1698`                   |
| `EventID`      | Rows are scoped to **one event** — this is a per-event template, not a Group-wide catalog | `special.php:1707`                   |
| `Name`         | Copied verbatim from `specialActivities.Name` — denormalized                              | `special.php:1706`                   |
| `Description`  | Free text, per event                                                                      | `special.php:1707`, rendered `:1579` |
| **`Required`** | **Headcount per slot** — drives the row-multiplying loop (§7.10)                          | `special.php:1707`, `:1924`          |
| `Eligible`     | Numeric who-can-sign-up code (§7.3)                                                       | `special.php:1707`                   |
| `VGActivity`   | Copied from `specialActivities.VGActivity`                                                | `special.php:1706`                   |
| `ActivityID`   | FK → `specialActivities.ID`                                                               | `special.php:1707`                   |

Deletion is a **hard** `DELETE` (`special.php:1715-1718`), leaving `specialSchedule.RoleID` dangling — no FK, no cascade.

#### `specialDateTimes` (Shift/Activity mode) and `specialDayPattern` (Daily Pattern mode)

- `specialDateTimes`: `ID`, `EventID`, `shiftDate`, `shiftStartTime`, `shiftEndTime`, `Count` (`special.php:1545`).
- `specialDayPattern`: `ID`, `EventID`, `ShiftStartTime`, `ShiftEndTime`, `Count`, `ActivityID`, `Activity`, `Required`, **`EligibleID`**, **`Eligible`**, `VGActivity` (`special.php:1374`, `:1884`).

⚠ **Naming trap:** `specialRoles.Eligible` is the _numeric code_; `specialDayPattern.Eligible` is a _denormalized human-readable label_ and the numeric code is `EligibleID`. Same column name, two types, in sibling tables.

### 2.3 Docents — `docent`

Monthly shape. Main file `public_html/res/docent/docent.php` (6,507 lines).

#### `docentscheduledTours`

| Column         | Use                                                                                   | Cite                                     |
| -------------- | ------------------------------------------------------------------------------------- | ---------------------------------------- |
| `ID`           | PK                                                                                    | `public_html/res/docent/docent.php:2763` |
| `Day`          | Denormalized `MON`…`SUN`, suffixed `' PM'` when hour > 17                             | `docent.php:2995-2996`                   |
| `Time`         | Stored as a datetime pinned to the Access epoch `'1899-12-30 hh:mm:00'`               | `docent.php:3002`                        |
| `Date`         | Midnight-normalized `'Y-m-d 00:00:00'`                                                | `docent.php:2765`                        |
| `YYYYMM`       | Denormalized month key                                                                | `docent.php:1959`                        |
| `SignupTourID` | FK → `docentsignupTour.ID` — the **shift kind** stamped by the generator              | `docent.php:2763`, `:1957`               |
| `TourID`       | FK → `docenttour.ID` — the **actual tour**; `0` when unclaimed                        | `docent.php:2292`, cleared `:2307`       |
| `DocentID`     | FK → `docent.ID`; `0` = open                                                          | `docent.php:2292`, `:2010`               |
| `Confirmed`    | 0/1 sign-in / statistician confirmation; gates the rollup                             | `docent.php:536`, `:3553`                |
| **`Visitors`** | The visitor-interaction count — "Number of Visitors on the Tour"                      | `docent.php:517`, `:3363`, `:3526`       |
| `Interactions` | A **second** count — "Visitor interactions _excluding_ tour". **Written, never read** | write `docent.php:536`, `:559`           |
| **`Count`**    | **A tour count**, not hours and not capacity (§3.2). **Never written by any code**    | read `docent.php:3550`, `:5829`, `:6079` |
| `IsVet`        | 0/1 — this shift is a vetting tour                                                    | `docent.php:2292`                        |
| `VetText`      | Cosmetic `' -Vet'` suffix, denormalizing `IsVet`                                      | `docent.php:2290-2291`                   |

No `Colour`, no `Type`/`Activity`, no per-row `Status`, no `MemberID` (reached via `DocentID` → `docent.MemberID`).

#### `docentSchedules`

`ID`, `YYYYMM`, `MONTH_YEAR`, `status`, `notes`, `confirmed`, `ExhibitionRevenue`. One magic non-month `YYYYMM` value exists: `'docenttelephoner'`, storing the note/status for the phone-duty roster (`docent.php:2497-2499`).

#### `docentweeklySchedule` — the standing weekly pattern

`ID`, `DayOfWeek`, `NameOfDayID`, `TimeOfDay`, `SignUpTourID`. **Exactly one unnamed global pattern** — no `Name`, no `Colour`, no filtering beyond day-of-week. Generator at `docent.php:2733-2777`.

The warning banner is `public_html/res/docent/docent.php:2726`:

```php
$res .= "<br><b>Please be sure that the weekly pattern is up to date</b><br><br>";
```

It sits between the Month/Year picker and the Create Schedule button and is **purely advisory** — nothing enforces it.

#### The tour → qualified-member cross-link

Three tables on **two different axes**:

| Table                    | Columns                                       | Axis                                  |
| ------------------------ | --------------------------------------------- | ------------------------------------- |
| `docentToTour`           | `TourID`, `DocentID`, `Active`, `LastVetDate` | **person × tour** — the qualification |
| `docentsignupTourToTour` | `SignUpTourID`, `TourID`                      | **slot-kind × tour** — no people      |
| `docentsignupTour`       | `ID`, `Name`, `Active`, `VetTour`             | the slot-kind catalog                 |

**The qualification attaches to the tour KIND (`docenttour.ID`), never to the dated shift.** Eligibility for a dated shift is derived at render by intersecting the shift's `SignupTourID` with the member's `docentToTour` set. Full call-site list in §7.7.

#### Group bookings

`docentgroupTours`: `ID`, `YYYYMM`, `Client_Name`, `GroupLeader`, `TourID`, `groupTourTypeID`, `Date`, `Start_Time`, `End_Time`, `Visitors`, `OrderNumber`, `OrderDate`, `Earned`, `Posted`, `Comments`, `Required`, `NumberOfShifts`, `DocentShifts` (`docent.php:4247-4250`).

`docentgroupToDocent`: exactly `GroupTourID`, `DocentID`, `Shifts` (`docent.php:4259`). Multiple docents per booking — yes. **Per-assignment kind column — no.** `Shifts` is a quantity, not a kind.

`docentgroupTourType`: `ID`, `Name`, `PaidType`, `RatePerVisitor`, `RatePerDocentHour`.

#### Other

`docentTrackTours` (a daily fill-rate snapshot written only by `public_html/res/services/trackTours.php:41`), `docentsection`, `docentCategory`, `allTourDescription` (cross-Group: `ID`, `Language`, `committee`, `TourID`, `Description`, `ShortText`), `docentHistory`, `docentExec`, `docenttelephoner`, `docentFactsheet`, `docent`.

### 2.4 GDR — `gdr`

Monthly shape, and structurally the closest sibling to Docents. Main file `public_html/res/gdr/gdr.php` (5,851 lines).

#### `gdrscheduledTours`

`ID`, `Day`, `Time`, `Date`, `YYYYMM`, `GDRID`, `TourID`, `SignupTourID`, **`Visitors`**, `Visitorsfr`, `Visitorspq`, `Visitorsto`, `Visitorsroc`, `Visitorsoth`, `Interactions`, `Confirmed`, `IsVet`, `VetText`, `Count`.

Distinctive:

- **Five visitor-provenance subtotals** (`Visitorsfr`/`pq`/`to`/`roc`/`oth` — francophone Europe, Quebec, Toronto, rest of Canada, other) alongside the headline `Visitors` (`gdr.php:492`, `:3031`). No other Group has these.
- `Interactions` — written only when POSTed, **never read back** (`gdr.php:492`, `:3033`). Same dead-end as Docents'.
- `Count` — **never written by any code**; read as a _tour count_ (`gdr.php:3067`, `:5298`). A duration reading exists but is commented out (`gdr.php:379-380`).
- `Day` carries **three different value conventions in one column**: `DayFR` (French, from the generator), `DayFR . ' soir'`, and `strtoupper(date('D')) . ' PM'` (`gdr.php:2346`, `:2593`, `:2948`).
- Eligibility gate: `SignupTourID < 3` is open to any qualified GDR; `>= 3` requires the mapped tour in `gdrToTour` (`gdr.php:1830-1838`).

#### Containers and pattern

`gdrSchedules`: `ID`, `YYYYMM`, `MONTH_YEAR` (French month name), `status`, `notes`, `confirmed`.
`gdrweeklySchedule`: `ID`, `DayOfWeek`, `NameOfDayID`, `TimeOfDay`, `SignUpTourID`. **Unnamed, single global pattern.**

#### Qualification and group bookings

`gdrToTour` (`TourID`, `GDRID`, `Status`, `Active`, `LastVetDate`), `gdrsignupTour`, `gdrsignupTourToTour` — the same two-axis shape as Docents.

`gdrgroupTours` (`ID`, `YYYYMM`, `Client_Name`, `TourID`, `GroupTourTypeID`, `Date`, `Start_Time`, `End_Time`, `Visitors`, `OrderNumber`, `OrderDate`, `Comments`, `GroupLeader`, `GDRShifts`, `Earned`, `Required`, `NumberOfShifts`) + `gdrgroupToGDR` (`GroupTourID`, `GDRID`, `Shifts`) + `gdrgroupTourType` (`ID`, `Name`, `PaidType`, `RatePerVisitor`, `RatePerGDRHour`). Again: multiple members per booking, **no per-assignment kind column**.

#### No object reservation

`gdrArtefact` (`ID`, `Name`, `Filename`, `Comment`, `GalleryID`, `Original`) and `gdrGalleryTheme` are a **document library**, not a booking system. Neither is joined to `gdrscheduledTours`, which has no `ObjectID`.

### 2.5 Gallery Interpreters — `gi`

Monthly shape, but diverges sharply from GDR. Main file `public_html/res/gi/gi.php` (4,933 lines). `xgi.php` is a stale twin.

#### `gischeduledTours`

| Column                  | Use                                                                                                                      | Cite                                            |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------- |
| `Day` / `Time` / `Date` | English 3-letter day; **plain** `HH:MM:00` and `Y-m-d` (no Access epoch, no midnight suffix — diverges from GDR)         | `public_html/res/gi/gi.php:3505-3509`           |
| `YYYYMM`                | Month key                                                                                                                | `gi.php:3505`                                   |
| **`MemberID`**          | **FK → `Members.MemberID` directly** — GI skips the membership-table surrogate that every other Group uses               | `gi.php:487`, `:3196`                           |
| `TourID`                | FK → `gitour.ID` = **the gallery/location**, not a tour script                                                           | `gi.php:487`                                    |
| `EventID`               | FK → `giEvent.ID`; `0` for regular shifts                                                                                | `gi.php:2245`                                   |
| **`ObjectID`**          | **Comma-wrapped, comma-joined `giArtefact.ID` list** — the object reservation (§5.1)                                     | `gi.php:459`, `:2846`                           |
| `ObjectText`            | Denormalized `<br>`-joined object names                                                                                  | `gi.php:487`                                    |
| **`Count`**             | **Duration in hours** for conflict math, _and_ a tour count for the history rollup — two incompatible uses of one column | duration `gi.php:3160-3161`; tour count `:4274` |
| **`Visitors`**          | The visitor-interaction count. No provenance breakdown, no `Interactions`                                                | `gi.php:365`, `:3029`                           |
| `Confirmed`             | 0/1                                                                                                                      | `gi.php:364`                                    |

No shift-kind column at all — the member chooses both location and objects at sign-up time. No qualification matrix: eligibility is `committeeToStatus.activeFlag` joined on `gi.statusID` (`gi.php:2511`).

#### `giEvent` — the multi-day event

`ID`, `Name`, `FullName`, `TourID`, `EventStart`, `EventEnd`, `ObjectStart`, `ObjectEnd`, `ObjectID`, `Active`. Events are modelled as a special _category of location_ (`gitour` rows with `CategoryID=5`). `ObjectStart`/`ObjectEnd` are `EventStart - 1 day` / `EventEnd + 1 day` — a travel/handling buffer (`gi.php:2190-2191`). `giEvent.ObjectID` is a **cache of a cache**, rebuilt by union-scanning the child shift rows (`gi.php:2860-2884`).

#### Object double-booking

Enforced in **application code only**, three `LIKE '%,id,%'` layers: `_checkForConflict` (`gi.php:3132-3181`), `_checkForEventConflict` (`gi.php:3103-3130`), `_VerifyEventSignUp` (`gi.php:2810-2839`). No DB constraint is possible on a comma-string column, and the checks are read-then-write with no transaction.

#### Other

`giSchedules`, `giweeklySchedule` (`ID`, `DayOfWeek`, `NameOfDayID`, `TimeOfDay` — **no shift-kind column**, unnamed single pattern), `gitour`, `giCategory`, `giArtefact` (`ID`, `Name`, `Filename`, `Comment`, `GalleryID`, `hasObject`, `Original`), `giGalleryTheme`, `giHistory`, `giExec`, `giFavourite`/`giFavouriteLikes`/`giFavouriteView` (a SQL VIEW), `giNetworks`.

**No group bookings.** `gigroupTours`, `giToTour`, `gisection`, `giLikes` exist only in stale trees.

### 2.6 Reception — `reception`

The simplest Group, and the source of the two-week pattern. Main file `public_html/res/reception/reception.php` (2,156 lines).

#### `receptionweeklySchedule` — the two-week pattern

Exact columns from `public_html/res/reception/reception.php:1117`:

```
(ID, `Colour`, `DayOfWeek`, `NameOfDayID`, `TimeOfDay`, `TimeEnd`, `commID`, `Count`)
```

- **The fortnight discriminator is `Colour`** — the string `'red'`/`'blue'`. There is no `Week`, `WeekNo`, `AB`, or `Odd`/`Even` column. The menu label is literally "2-Week Pattern" (`reception.php:812`).
- `Count` is **hours**, labelled "Hours" in every dialog, default `3`.
- **`commID` is a member reference** — see below.

**How the fortnight phase is anchored** — `reception.php:1014-1020`:

```php
// IS THIS RED OR BLUE ???
// If July 1, 2012 is RED, then what colour is 1st of this New Month - $startdate
$days_between = floor(($startdate - strtotime('2012-07-01')) / 86400);
if((floor($days_between/7)%2)==0)
    $clr="red";
else
    $clr="blue";
```

**A hardcoded epoch of `2012-07-01`, weeks-since-epoch mod 2.** No stored epoch, no flag on the container, no manual toggle. Within the month the phase flips on a **Saturday** boundary (`reception.php:1042-1043`). `__startOfWeek()` is defined at `reception.php:801-806` and never called — a remnant of an earlier week-of-year scheme.

**Note the same 2012-07-01 epoch and the same red/blue mechanism appear in Visitor Guides** (`public_html/res/vg/vg.php:1342-1346`, Saturday flip at `:1373`). Reception's fortnight is not a Reception invention; it is a shared idiom.

**The pattern rows carry a member** — this is the load-bearing fact. `commID` is written from a picker labelled **"Regular Member?"** (`reception.php:1094`, `:1117`), rendered in a column headed **"Regular Member"** (`reception.php:855`), and copied straight into the dated row by the generator (`reception.php:1032-1038`). Generating a month **pre-fills the sign-ups**. `commID = 0` means an open slot, which is what the sign-up grid tests (`reception.php:777-781`) and what month-confirmation garbage-collects (`reception.php:1559`).

This confirms the map's framing: **the two-week pattern is an _assignment_ pattern, not a shift pattern.** The shifts are weekly; the fortnight exists only to express "this member attends on alternate weeks."

#### `receptionscheduledTours`

`ID`, `Day`, `Date`, `Time`, `TimeEnd`, `YYYYMM`, `Colour`, `commID`, `Count` (**hours**), `Confirmed`.

**Confirmed: no visitor-interaction column.** A case-insensitive grep for `Visitors`, `Attendance`, `Adults`, `Children` across `reception.php` returns nothing. Reception is not visitor-facing and `Committee.visitorTable` is `NULL` for it.

Names present that _other_ Groups use for counts: `Count` (here, hours), `Total_Scheduled`/`Total_Group`/`Total_Meeting` on `receptionHistory`, and the SQL alias `TotalTours` which is literally `Sum(Count)`, i.e. total _hours_ (`reception.php:1561`).

**No eligibility column.** Sign-up checks only that the slot is free (`reception.php:775-781`).

The shift kind is not stored at all — the cross-Group activity panel hardcodes the string `"Desk"` in PHP (`public_html/res/services/servicesp.php:2847`).

### 2.7 ROMBus — `rombus`

Trip-based. Main file `public_html/res/rombus/rombus.php` (1,853 lines), last modified 2021 — the most dormant of the eight.

#### `rombusTrip` — the dated thing

`ID`, `Name`, `TripTypeID`, `SectionID`, `Date`, `Start_Time`, `End_Time`, `YYYYMM`, `Client_Name`, `phone`, `email`, **`Visitors`**, `Earned`, `Member_Fee`, `Public_Fee`, `Required`, `CoordinatorID`, `Description`, `Comments`, `ImageFilename`, `ImageText`, `GuestFileName`, `Active`, `Count` (`rombus.php:808`, `:1107-1116`).

`End_Time` is SELECTed in every trip query but **never written by any INSERT or UPDATE**, and its display is commented out — a dead column.

`Visitors` is labelled "No of participants" (`rombus.php:986`), hand-editable, and **overwritten** by the guest-file import with the sum of `rombusGuests.Quantity`.

#### `rombusToTrip`

`TripID`, `RombusID`, `YYYYMM`, `Status`, `Count`. Multiple members per trip — yes (`rombus.php:1088-1094`). **No per-assignment kind column**; the only extra is `Status` (`'Active'`/`'Deleted'`), a lifecycle flag.

The coordinator is stored **twice**: as `rombusTrip.CoordinatorID` and, since a July-2020 change, additionally as an undifferentiated `rombusToTrip` row (`rombus.php:1095-1096`). Display code re-derives which is which by id comparison and skips the duplicate (`rombus.php:527-528`).

**`rombusToTrip.Count` is read but never written** — `rombus.php:1511` computes `Sum(b.Count) AS TotalHours` into `rombusHistory.Total_Scheduled`, but nothing sets it.

#### `rombusGuests` — the guest manifest

`ID`, `MemberID`, `TripID`, `Last_Name`, `First_Name`, `Full_Name`, `Phone`, `Street`, `City`, `Prov_State`, `Postal_Zip_Code`, `Email`, `Comments`, `Quantity` (`public_html/res/rombus/aim_addguestfile.php:44-45`).

Import is a **full replace** (`DELETE … WHERE TripID`, then insert), driven by a CSV with a hardcoded header sentinel and fixed field offsets — i.e. a fixed export format from an external ticketing system. Rows are grouped into **booking parties by consecutive identical phone**, so one row = one party, not one person. `MemberID` is always inserted as an empty string — a vestigial column; guests are strictly external.

#### Other

`rombusSchedules` (lazily auto-created month label; the two creation sites disagree on `status`), `rombusTripType`, `rombusContacts`, `rombusContactToTrip` (exactly `TripID`, `ContactID`), `rombusHistory`, `rombusExec`, `rombus`.

Unreachable from any live path: `rombusCategory`, `rombussection`, `rombusgroupTrips`, `rombusFactsheet`, and the columns `rombusTrip.SectionID` and `rombusSchedules.ExhibitionRevenue` — their only consumer, `__rombusPrintTripSummary`, is entirely commented out (`rombus.php:1560-1745`).

### 2.8 Outreach / ROM for You — `outreach`

**The most divergent Group, and the one that breaks the core model.** Main file `public_html/res/outreach/outreach.php` (4,074 lines), of which roughly **40% is inside `/* */` comment blocks**.

#### The shape, stated plainly

- **There is no `outreachscheduledTours`.** The absence is itself a finding.
- **The dated thing is `outreachgroupTours`** — one row = one booking = one presentation delivered to one organization at one address on one date.
- **There is no self-service sign-up.** Grep for `signup`/`signUp` across `outreach.php` and its JS bundle returns exactly one hit, inside a comment block. Members are _placed_ onto a booking by the Scheduler, Chair, or that booking's Leader (`outreach.php:2035`). The member-facing view is a read-only Details dialog (`outreach.php:1476-1477`).
- **The monthly container is a derived label.** `outreachSchedules` is auto-vivified on booking save and always with `status='group'` (`outreach.php:2533-2536`). No date range, no shift templates, no capacity.

```
outreachSchedules            (month label, auto-created, `confirmed` flag)
   └─ (loose join, by YYYYMM string)
outreachgroupTours           ← the dated thing: booking + org + address + presentation + fee
   ├─ TourID          → outreachGalleryTheme    (the presentation given)
   ├─ GroupTourTypeID → outreachgroupTourType   (billing / engagement class)
   ├─ ObjectsTaken    → comma-joined outreachObjects.ID string
   ├─ LeaderID        → outreach.ID             (duplicate of the 'LD' join row)
   └─ outreachgroupToORMember (GroupTourID, ORMemberID, Shifts, Role)
                                                     ↑hours   ↑2-char shift-kind symbol
```

#### `outreachgroupTours`

Exact column list from `public_html/res/outreach/outreach.php:2514-2516`:

```
(ID, YYYYMM, Client_Name, Client_Address, OrgType, TourID, GroupTourTypeID, `Date`,
 Start_Time, End_Time, Visitors, PayType, ObjectsTaken, Comments, PresenterShifts,
 Earned, PresentersNeeded, PresentersSignedup, ContactName, ContactPhone, ContactEmail, LeaderID)
```

| Column                                                                             | Use                                                                                                                                                        |
| ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ID`                                                                               | **Not stable — reassigned on every edit** (§6, breaker 3)                                                                                                  |
| `YYYYMM`                                                                           | The only link to the month container                                                                                                                       |
| `Client_Name` / `Client_Address` / `ContactName` / `ContactPhone` / `ContactEmail` | The organization; **re-entered on every booking**, auto-filled from the most recent prior match (`outreach.php:2474-2488`). There is no organization table |
| `OrgType`                                                                          | `'G'`/`'A'`/`'C'`/`'P'` — General/Adult/Children/Promotion; a statistics grouping key                                                                      |
| `TourID`                                                                           | FK → `outreachGalleryTheme.ID` — **the presentation given**, misleadingly named "Tour"                                                                     |
| `GroupTourTypeID`                                                                  | FK → `outreachgroupTourType.ID`                                                                                                                            |
| `Date`, `Start_Time`, `End_Time`                                                   | Duration is not stored                                                                                                                                     |
| **`Visitors`**                                                                     | **A forecast, not an outcome** (§7.9)                                                                                                                      |
| `PayType`                                                                          | `'Not-For-Profit'` / `'Profit'`; UI label "Venue Type"                                                                                                     |
| `Earned`                                                                           | Honorarium in dollars                                                                                                                                      |
| `ObjectsTaken`                                                                     | Comma-joined `outreachObjects.ID` string                                                                                                                   |
| `PresenterShifts`                                                                  | Cached SUM of join `Shifts` (= total member hours)                                                                                                         |
| `PresentersNeeded`                                                                 | "Members Required"                                                                                                                                         |
| `PresentersSignedup`                                                               | Cached COUNT of join rows; drives the understaffing colour                                                                                                 |
| `LeaderID`                                                                         | FK → `outreach.ID`; duplicates the join row bearing symbol `'LD'`                                                                                          |

**No status column and no confirmation column.** Confirmation is a one-shot email with zero persisted state (`outreach.php:2571-2635`).

#### Multiple members per booking — `outreachgroupToORMember`

Exact columns, from `public_html/res/outreach/outreach.php:2527`:

```php
INSERT INTO {prefix}outreachgroupToORMember (GroupTourID,ORMemberID,Shifts,Role)
VALUES ( $id, $docid, $docshift, '$docrole')
```

| Column        | Meaning                                                                                                    |
| ------------- | ---------------------------------------------------------------------------------------------------------- |
| `GroupTourID` | FK → `outreachgroupTours.ID`                                                                               |
| `ORMemberID`  | FK → `outreach.ID` (membership row, not `Members.MemberID`)                                                |
| `Shifts`      | **Integer hours**, minimum 1 — despite the name. Header "Member Hours" (`outreach.php:3631`)               |
| `Role`        | **The shift-kind marker**, stored as `outreachRole.Symbol` **verbatim as a string** — no FK, no constraint |

**This is the only Group where each member attached to a booking carries a per-assignment kind.** Every other Group's join table (`docentgroupToDocent`, `gdrgroupToGDR`, `rombusToTrip`, `outreachgroupToORMember`'s peers) carries only a quantity.

#### Shift kinds — `outreachRole`

Legacy `outreachRole` means **a kind of position within a booking** — a kind of Shift, not an authority-bearing role.

`ID`, **`Symbol`**, `Name`, `Active`. Rows with `ID <= 1` are never offered (`outreach.php:2206`, `:2379`). The rendered form is `Firstname Lastname-XX` (`outreach.php:1428`, `:1982`).

**Hard constraint: the symbol must be exactly 2 characters.** The browser round-trips it by fixed string offsets — `public_html/res/outreach/outreach_data_26-06-12.js:205-206`:

```js
shifts = shifts + (i > 0 ? ',' : '') + trim(x.options[i].text.substr(0, 2));
roles = roles + (i > 0 ? ',' : '') + x.options[i].text.substr(3, 2);
```

A 1- or 3-character symbol silently corrupts both the hours and the kind on save. One value is hardcoded: `'LD'` (Leader), at `outreach.php:1573`, `:2164`, `:2367`.

#### The presentation catalog

- `outreachGalleryTheme` — **the presentation**, not a gallery. `ID`, `Name`, `CategoryID`, `SubjectID`, `Active`, `showPDF`, `Powerpoint`, `Script`, `PDFSlides`, `PDFText`. Bookable only when `CategoryID = 5 OR CategoryID = 6` — magic numbers hardcoded in four live places.
- `outreachObjects` — `ID`, `Name`, `Active`, `GIObjects`. **`GIObjects=1` marks objects owned by Gallery Interpreters**; Outreach may list them but not edit them (`outreach.php:370-378`).
- `outreachObjectPresentation` — a **proper join table**: `ID`, `ObjectID`, `PresentationID`. `PresentationID = 0` is a wildcard meaning "any presentation" (`outreach.php:573`).
- `outreachSubject`, `outreachCategory` — the topic tree.

Compared to Gallery Interpreters: GI groups objects by a **single FK** (`giArtefact.GalleryID`); Outreach uses a **many-to-many** join. But Outreach then stores the _booking's_ reserved objects as a comma-joined string in `outreachgroupTours.ObjectsTaken`, reproducing GI's worst pattern at a different level.

#### `outreachNotAvail` — member unavailability

`YYYYMM`, `Day`, `ORMemberID` (`outreach.php:1709`). A row means "this member declares they are NOT available on this day." No reason field, no time granularity.

**It filters nothing.** Only three references exist in the whole file — the read for the grid, the insert, and the delete. Every member picker queries only `WHERE outreach.Active=1` (`outreach.php:2159-2162`, `:2182-2184`, `:2361-2364`). It is a wall calendar the Scheduler is expected to read with their eyes.

It is also **new**: the entire client-side block is present in `outreach_data_26-06-12.js:17-38` and absent from `outreach_data_25-07-01.js` — those 21 lines are the _only_ difference between the two bundles.

No other Group has a member-declared unavailability table.

#### `outreachToTour` — dead

`TourID`, `ORMemberID`, `Status`, `LastVetDate` — referenced only inside the comment block at `outreach.php:30-71`. Structurally identical to the live `docentToTour`. Consequence: **in live Outreach any active member can be placed on any presentation.** The `Vetting` permission flag remains live and the JS still calls `updateORMemberVet` / `removeORMemberVet` — **server functions that do not exist**.

### 2.9 Groups beyond the eight

The ticket assumes eight scheduling Groups. The table-name sweep and the cross-Group aggregator show more:

| Symbol                      | Status                             | Note                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| --------------------------- | ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `walker`                    | **live and scheduling**            | Full stack: `walkerscheduledTours`, `walkerSchedules` (**two** container flavours, `status='walk'` and `status='group'`), `walkerSchtourToWalker` (per-walker join carrying its own `Visitors`), `walkerWalk`, `walkerTourType`, `walkerWalkVetting`, `walkerHistory`. Its own branch in the cross-Group panel (`servicesp.php:2868-2884`) and its own special case in the visitor report (`interactions.php:116-120`). Credits `MemberActivity` with a hardcoded `2*$total` (`public_html/res/walker/walker.php:2298`) |
| `romtravel`                 | **live and scheduling**            | `romtravelscheduledTours` (has `Count` _and_ `Visitors`), `romtravelSchedules`, `romtravelweeklySchedule`, `romtravelgroupTours`, `romtraveltour`, `romtravelHistory`. **No `_saveScheduledMemberActivity` call anywhere** — it reports but appears not to credit                                                                                                                                                                                                                                                       |
| `invitation`                | live, but **not shift scheduling** | `invitationSchedule` is per-member RSVP (`ID`, `EventID`, `MemberID`, `eventok`, `event1ok`, `event2ok`, `Active` — `public_html/res/invitation/invitation.php:189`), not a monthly container; there is no `YYYYMM`. The singular name is the only similarity. Still participates in the daily reminder cron (`daily.php:29`)                                                                                                                                                                                           |
| `owls`                      | **dead**                           | `owlsSchedules` / `owlsscheduledTours` exist only in the stale `resboot/` tree, and `owls` is commented out of the live committee roster (`servicesp.php:20`)                                                                                                                                                                                                                                                                                                                                                           |
| `hotspot` / `hot`           | **dead as a scheduling Group**     | `hotspot*` tables exist only in `demo/` and `resboot/`. The live `hot` symbol has only `{prefix}hot` and `{prefix}hotExec`, and renders `dmv_docentgroupTours` directly (`public_html/res/hot/hot.php:229`). But `Total_Hotspot` is summed into Docent and GDR tour totals (`docent.php:6074`, `gdr.php:5509`), so the column survives its Group                                                                                                                                                                        |
| `bw`, `joint`, `project`    | no scheduling tables               | `bw` (Bookworms) schedules **through Wayfinders** — it is one of the six Friends symbols in the credit sweep (§7.11)                                                                                                                                                                                                                                                                                                                                                                                                    |
| `fop fes ftc fcc fsa famis` | no own scheduling code             | Scheduled entirely through `specialEvents.committee` (§2.2). Their directories hold only a 5–8 KB stub and a data file                                                                                                                                                                                                                                                                                                                                                                                                  |

There is **no authoritative list of eight** in the code. The full committee roster is hardcoded at `public_html/res/services/servicesp.php:20`, and which of them schedule is a **data** question — `SELECT symbol FROM dmv_Committee WHERE Active=1 AND HasSchedule=1`.

A `*weeklySchedule` pattern layer exists for exactly **five** Groups: `vg`, `gi`, `docent`, `gdr`, `reception`. Wayfinders uses `specialDayPattern` instead; `walker`, `rombus`, `outreach`, `romtravel` have no pattern layer at all.

The reminder pipeline's own opt-in list is `public_html/res/services/daily.php:29`:

```php
$committees = array('docent','gdr','special','vg','reception','invitation');//,'gi','outreach','marbrk','walker');
```

So reminders are a **per-Group opt-in**, with `gi`, `outreach`, and `walker` commented out.

---

## 3. Synonym map

This is the section that tells us whether ADR-0015's fan-in is real. **It is — but three columns must be renamed on the way in, not carried forward.**

### 3.1 The dated thing, per Group

Derived from one authoritative call site that touches every Group in a single `if`/`else` chain — the "Scheduled Activities" panel, `public_html/res/services/servicesp.php:2783-2937`.

| Group                | Dated table               | Date col        | Time col                            | Member FK                                    | Shift-kind col                                      | Multi-member?         |
| -------------------- | ------------------------- | --------------- | ----------------------------------- | -------------------------------------------- | --------------------------------------------------- | --------------------- |
| Visitor Guides       | `vgscheduledTours`        | `Date`          | `Time`                              | `VgID` → `vg.ID` (+ denormalized `MemberID`) | `Type` (a **string**)                               | no — one row per seat |
| Visitor Wayfinders   | `specialSchedule`         | **`shiftDate`** | **`shiftStartTime`/`shiftEndTime`** | `MemberID` **direct**                        | `RoleID` → `specialRoles`                           | no — one row per seat |
| Docents              | `docentscheduledTours`    | `Date`          | `Time`                              | `DocentID` → `docent.ID`                     | `SignupTourID` + `TourID`                           | no                    |
| GDR                  | `gdrscheduledTours`       | `Date`          | `Time`                              | `GDRID` → `gdr.ID`                           | `SignupTourID` + `TourID`                           | no                    |
| Gallery Interpreters | `gischeduledTours`        | `Date`          | `Time`                              | `MemberID` **direct**                        | _(none)_                                            | no                    |
| Reception            | `receptionscheduledTours` | `Date`          | `Time`/`TimeEnd`                    | `commID` → `reception.ID`                    | _(none — the literal `"Desk"` is hardcoded in PHP)_ | no                    |
| ROMBus               | `rombusTrip`              | `Date`          | `Start_Time`/`End_Time`             | via `rombusToTrip.RombusID`                  | `TripTypeID`                                        | **yes**               |
| Outreach             | `outreachgroupTours`      | `Date`          | `Start_Time`/`End_Time`             | via `outreachgroupToORMember.ORMemberID`     | **`Role`** (a 2-char string, per member)            | **yes**               |
| Walker               | `walkerscheduledTours`    | `Date`          | `Start_Time`                        | via `walkerSchtourToWalker.WalkerID`         | `TourTypeID` + `WalkID`                             | **yes**               |

**The member-FK column name is the worst drift.** Nine Groups, seven spellings: `VgID`, `DocentID`, `GDRID`, `ORMemberID`, `WalkerID`, `RombusID`, `commID` (used by both Reception and ROMBus history), and two Groups (`gi`, `special`) that skip the surrogate and use `Members.MemberID` directly. Legacy itself keeps a lookup table of these — `public_html/res/services/memberhistoryactivity.php:9`:

```php
$commids = array("commID","DocentID","GDRID","MemberID","ORMemberID","VgID","WalkerID","commID","commID");
```

and resolves them generically through `__getMemberidFromUid($committee,$uid)` / `__getUidFromMemberid()` (`servicesp.php:8640-8656`), which assume `{prefix}{committee}` is the membership table with columns `ID` and `MemberID`.

### 3.2 `Count` — the same name, three meanings

**This is the single most dangerous column in the legacy schema.**

| Table                                                            | Meaning                                                                                                                                                                                             | Proof                                                                                                                                                                                                                                  |
| ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `vgscheduledTours.Count`                                         | **Hours** (decimal)                                                                                                                                                                                 | `60*$shifts` added to `Time` as minutes — `public_html/res/vg/vg.php:210-214`; `$hours=$row['Count']; $mins=$hours*60` — `:614-618`; `ADDTIME(Time, Count*10000)` — `:706`. Dialog label "Hours of shift"                              |
| `vgweeklySchedule.Count`                                         | Hours                                                                                                                                                                                               | `vg.php:1527`                                                                                                                                                                                                                          |
| `specialSchedule.Count`                                          | **Hours** — and this is the load-bearing cross-table case                                                                                                                                           | `Sum(Count)` written straight into `MemberActivity.Total_Scheduled` at `public_html/res/services/memberhistoryactivity.php:65-74`; summed as `sum(\`Count\`) as hours`at`special.php:2532`. Dialogs say "Shift Hours" / "Shift credit" |
| `specialDateTimes.Count`, `specialDayPattern.Count`              | Hours                                                                                                                                                                                               | `special.php:1451`, `:1828`                                                                                                                                                                                                            |
| `receptionscheduledTours.Count`, `receptionweeklySchedule.Count` | **Hours**                                                                                                                                                                                           | `strtotime("+ $hours hours")` — `reception.php:558`; header "Hours" — `:871`; default `3`                                                                                                                                              |
| `gischeduledTours.Count`                                         | **Hours** for conflict math — `($starthour + $shifts)` — `gi.php:3160-3161` — **and a tour count** for the history rollup — `Sum(Count) AS TotalTours` — `:4274`. One column, two incompatible uses |                                                                                                                                                                                                                                        |
| `docentscheduledTours.Count`                                     | **A tour count.** Never written; `SUM(Count)` renders under a column headed _Tours_                                                                                                                 | `docent.php:3550`, `:5829`                                                                                                                                                                                                             |
| `gdrscheduledTours.Count`                                        | **A tour count.** Never written; `Visitors / Tours` gives visitors-per-tour                                                                                                                         | `gdr.php:5298-5305`                                                                                                                                                                                                                    |
| `rombusToTrip.Count`                                             | Intended as hours (`Sum(b.Count) AS TotalHours`) but **never written**                                                                                                                              | `rombus.php:1511`                                                                                                                                                                                                                      |

**One correction to the ticket's framing: `Count` never means capacity.** The ticket describes "`Count` meaning capacity in one place and _hours_ in another." The hours sense is confirmed; the capacity sense is **not supported by any call site in the live tree**. Capacity is always a _different_ column — `specialRoles.Required` / `specialDayPattern.Required` (`special.php:1707`), `docentgroupTours.Required` / `NumberOfShifts`, `gdrgroupTours.Required`, `rombusTrip.Required`, `outreachgroupTours.PresentersNeeded` — or it is _N rows_ (§7.10). The genuine second sense of `Count` is **quantity of things done** (tours given), not capacity.

And the units are reconciled _outside_ the tables, by a hardcoded per-Group multiplier — `public_html/res/services/memberhistoryactivity.php:7-8`:

```php
$commsyms = array("owls","docent","gdr","gi","outreach","vg","walker","rombus","reception");
$hoursper = array(  1,      1,     1,    1,    1,        1,    2,        1,        1);
```

**`Count` is a name to retire.** Two distinct fields are hiding in it — _duration_ and _quantity-of-things-done_ — and a third table (`*History.Total_Scheduled`) stores the quantity while `MemberActivity.Total_Scheduled` stores the hours, under the same column name.

### 3.3 Shift kind — the same idea under five names

| Group                | Column                                                                     | Storage                                              | Scoped to |
| -------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------- | --------- |
| Visitor Guides       | `vgscheduledTours.Type`                                                    | **name string**, vocabulary in `vgShiftTypes.Shift`  | Group     |
| Visitor Wayfinders   | `specialSchedule.RoleID` → `specialRoles`                                  | FK, but `specialRoles` rows are **per event**        | one event |
| Docents / GDR        | `SignupTourID` → `*signupTour`, fanned to `TourID` via `*signupTourToTour` | FK, two levels                                       | Group     |
| Gallery Interpreters | _(none)_                                                                   | —                                                    | —         |
| Reception            | _(none)_                                                                   | the literal `"Desk"` in PHP (`servicesp.php:2847`)   | —         |
| ROMBus               | `rombusTrip.TripTypeID`                                                    | FK                                                   | Group     |
| Outreach             | `outreachgroupToORMember.Role`                                             | **2-char symbol string**, per _member_ not per shift | Group     |

Three genuinely different cardinalities: **per Group** (most), **per event** (Wayfinders), **per member-on-a-booking** (Outreach). Any Catalog design has to pick one and lose something.

`specialRoles`/`RoleID` and `outreachRole`/`Role` are **kinds of Shift**, not authority. Wayfinders' and Outreach's authority flags live on their membership tables (`special.Chair`, `outreach.Chair`, etc.).

### 3.4 Genuinely per-Group (no synonym elsewhere)

- `gdrscheduledTours.Visitorsfr` / `pq` / `to` / `roc` / `oth` — visitor provenance subtotals. GDR only.
- `gischeduledTours.ObjectID` / `giEvent.ObjectID` — object reservation. GI only (Outreach's `ObjectsTaken` is the same _idea_ but a different level and a different catalog shape).
- `giEvent.ObjectStart` / `ObjectEnd` — the object-lock buffer window. GI only.
- `outreachNotAvail` — member-declared unavailability. Outreach only.
- `outreachgroupTours.OrgType` / `PayType` / `Earned` / `ContactName` — the external-client booking fields. Outreach, plus partial analogues in `docentgroupTours` / `gdrgroupTours` / `rombusTrip`.
- `rombusGuests` — external non-member manifest. ROMBus only.
- `docentscheduledTours.IsVet` / `VetText`, `gdrscheduledTours.IsVet` / `VetText` — vetting-tour marker. Docents and GDR only (GDR's is force-disabled at `gdr.php:1997`).
- `vgscheduledTours.SPScheduleID` ↔ `specialSchedule.VGScheduleID` — the cross-Group link. Exactly one pair.
- `receptionweeklySchedule.Colour` / `vgweeklySchedule.Colour` — the fortnight discriminator. Two Groups, same 2012-07-01 epoch.

### 3.5 The same idea under different names — the short list

| Concept                   | Names in legacy                                                                                                                                                        |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Duration of a shift       | `Count` (vg, special, reception, gi), `Start_Time`+`End_Time` (rombus, outreach, walker), `shiftStartTime`+`shiftEndTime` (special)                                    |
| Quantity of work credited | `Count` (docent, gdr), `Shifts` (`docentgroupToDocent`, `gdrgroupToGDR`, `outreachgroupToORMember`), `Total_Scheduled` (`*History` = things; `MemberActivity` = hours) |
| Headcount needed          | `Required` (special, docent, gdr, rombus), `PresentersNeeded` (outreach), _N rows_ (special, vg)                                                                       |
| Headcount filled          | `PresentersSignedup` (outreach), `DocentShifts`/`GDRShifts` (docent, gdr — actually hours), _count of rows_ (everyone else)                                            |
| The date                  | `Date` (most), `shiftDate` (special)                                                                                                                                   |
| Visitor interactions      | `Visitors` — **uniform**, see §7.9                                                                                                                                     |
| Month container           | `*Schedules` keyed `YYYYMM` (all monthly Groups), `specialEvents` keyed by date range (Wayfinders)                                                                     |
| Shift kind                | see §3.3                                                                                                                                                               |
| Member FK on a dated row  | see §3.1                                                                                                                                                               |

---

## 4. Lifecycle and status values

### 4.1 The `*Schedules.status` ladder

Every monthly Group has a `status` column on its container. The **canonical ladder is `edit` → `signup` → `freeze` → `final`**, plus a `confirm`/`confirmed` terminal and an orthogonal `group` marker. No Group implements all of it.

| Value                 | Gates                                                                                                                   | Which Groups actually write it                                                                                                                                                                                                                                    |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| _(unset at creation)_ | Every Group's `INSERT` omits `status`, relying on a DB default the code cannot see. UI treats it as `edit`              | all                                                                                                                                                                                                                                                               |
| `edit`                | Scheduler-only view; editable grid; not visible to members                                                              | **written by**: docent (`docent.php:2922`), gdr, gi. **read-only in**: vg, reception                                                                                                                                                                              |
| `signup`              | Members may claim open slots                                                                                            | docent, gdr (`gdr.php:2515`), gi (`gi.php:3637`). **Never written by** vg or reception                                                                                                                                                                            |
| `freeze`              | Members may only fill unfilled slots / swap, not withdraw                                                               | docent, gdr, gi. **Never written by** vg or reception — vg accepts it in a filter (`vg.php:457`) but nothing sets it                                                                                                                                              |
| `final`               | Read-only / sign-in / statistics mode                                                                                   | docent, gdr, gi, **vg** (the "Post for Visitor Guide Signup" button writes `final`, skipping signup and freeze entirely — `vg.php:1181` → `:1649`), reception (`reception.php:1218`)                                                                              |
| `confirm`             | Statistics rolled up                                                                                                    | **vg** (`vg.php:2064`) and **gi** (`gi.php:4297`) only. docent/gdr/reception/outreach set a separate `confirmed=1` column instead                                                                                                                                 |
| **`group`**           | **Not a phase — a discriminator.** A _second_ container row for the same `YYYYMM` representing the group-booking ledger | **written by**: docent (`docent.php:4266`), gdr (`gdr.php:4674`), rombus (`rombus.php:1123`), **outreach** (`outreach.php:2534` — and it is the _only_ value outreach ever writes). **Filtered but never written by**: vg (six sites), gi (four sites), reception |
| `walk`                | Walker-only: marks the _walks_ container for the month, as opposed to its `group` container                             | `public_html/res/walker/walker.php:1524`                                                                                                                                                                                                                          |
| `telephoner`          | —                                                                                                                       | Appears only in the 2012 DDL comment. Not in code                                                                                                                                                                                                                 |

**The `status<>'group'` filter, explained.** In Docents and GDR each month gets a second `*Schedules` row with `status='group'` for the group-tours sub-schedule, so every regular-schedule query must exclude it. Visitor Guides, Gallery Interpreters and Reception were **copy-pasted from that code and kept the defensive guards without ever creating such a row** — in those three Groups the filters are no-ops against application-written data. (Whether hand-inserted legacy rows exist is a data question, not a code one — flagged in §8.)

Outreach inverts it: `'group'` is the _only_ status it writes, so Outreach has **no lifecycle at all**.

### 4.2 Per-row status

| Column                                                                                                                                 | Values                      | Gates                                                                                                                                                                      |
| -------------------------------------------------------------------------------------------------------------------------------------- | --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `*scheduledTours.Confirmed` / `specialSchedule.Confirmed`                                                                              | 0/1                         | Signed out / attendance verified. **Required for credit** — every rollup filters `Confirmed=1`. Note VG **resets it to 0** when a member removes themselves (`vg.php:935`) |
| `*Schedules.confirmed` (lowercase)                                                                                                     | 0/1                         | Month's statistics have been rolled into `*History` / `MemberActivity`. Prefixes the picker label with `C-`                                                                |
| `specialEvents.visible`                                                                                                                | 0/1                         | Publish. Gates the member picker, kiosk list and reminder emails — **not** the Scheduler's own list                                                                        |
| `specialEvents.Active`                                                                                                                 | 0/1                         | Soft-delete. **Never written by `special.php`**                                                                                                                            |
| `specialEvents.Confirmed`                                                                                                              | 0/1                         | Month closed. Seeded `0` for `special`, `1` for Friends Committees                                                                                                         |
| `rombusToTrip.Status`                                                                                                                  | `'Active'` / `'Deleted'`    | `'Active'` is **never written** — depends on a DB default; every read filters on it                                                                                        |
| `rombusTrip.Active`, `outreachGalleryTheme.Active`, `*tour.Active`, `*signupTour.Active`, `specialActivities.Active`, `giEvent.Active` | 0/1                         | Soft delete                                                                                                                                                                |
| `docentToTour.Active` / `gdrToTour.Active`                                                                                             | 0/1                         | Qualification in force                                                                                                                                                     |
| `gdrToTour.Status`                                                                                                                     | —                           | **Selected, never written.** Purpose unknown                                                                                                                               |
| `outreachgroupTours`                                                                                                                   | _(no status column at all)_ | Deletion is hard; no cancellation state                                                                                                                                    |

### 4.3 Membership status (gates who may sign up)

Per-Group membership tables (`vg`, `docent`, `gdr`, `gi`, `special`, `reception`, `rombus`, `outreach`) each carry `Active` (0/1), a string `Status`, and a numeric `StatusID` — **two parallel encodings that no code maps between**.

String values observed: `Full`, `Trainee`, `Emeritus`, `Transitional`, `Auxiliary`, `LOA`, `Resigned`, `Deceased`, `Admin`, `Temporary`, `FULL` (GDR uppercases it). Docents attaches real behaviour to several: `Transitional` may sign up only for the current or next month; `Auxiliary` only for the current month, or next month once past the 15th (`docent.php:1913-1914`). Case is inconsistent within Docents itself (`'auxiliary'` at `:693` vs `'Auxiliary'` at `:1913`).

Numeric mapping, from the Docent secretary functions (`docent.php:4663-4697`): `1=Full, 3=Emeritus, 4=Trainee, 6=Auxiliary, 10=Resigned, 99=Deceased`. Outreach's comment gives `2=LOA, 10=Resigned, 99=Deceased` (`servicesp.php:11549-11552`) — **the two Groups disagree on the numbering**.

GI and GDR instead resolve activeness through a shared lookup: `committeeToStatus (committee, statusID, activeFlag)` (`gi.php:2511`).

---

## 5. Denormalized caches and comma-joined id strings

Do not carry any of these forward. Each one names a relationship the schema is missing.

### 5.1 Comma-joined id strings

| Column                                | Format                                                                   | Missing relationship                                                                                                                                                                                                                                           | Cite                                                                                                     |
| ------------------------------------- | ------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| **`gischeduledTours.ObjectID`**       | `,188,201,` — comma-**wrapped** so `LIKE '%,188,%'` can find a single id | **`giScheduleObject` junction table.** A many-to-many between shifts and museum objects, encoded as a string. Forces unindexable `LIKE` scans and makes a DB uniqueness constraint impossible — so double-booking is enforced by three hand-written PHP passes | `public_html/res/gi/gi.php:96` (the comment explaining the wrapping), `:459`, `:2818-2825`, `:3133-3164` |
| **`giEvent.ObjectID`**                | same                                                                     | **A cache of a cache** — rebuilt by union-scanning the child shift rows. Names the missing `giEventObject` relationship _and_ the missing derived-view capability                                                                                              | `gi.php:2859-2881`, `:3195-3209`                                                                         |
| **`outreachgroupTours.ObjectsTaken`** | same                                                                     | `outreachBookingObject` junction. Conflict detection is a ±2-day substring scan across neighbouring bookings' strings                                                                                                                                          | `public_html/res/outreach/outreach.php:2514`, `:615`, `:558-578`                                         |

**Correction to the ticket's premise.** The ticket cites `specialEvents.ObjectID` as `,188,205,`. **`specialEvents` has no `ObjectID` column** — a case-insensitive grep for `objectid` across `public_html/res/special/` returns nothing. The `,188,` example is real but belongs to **Gallery Interpreters**; the literal comment `WHERE ObjectID LIKE '%,188,%'` is at `public_html/res/gi/gi.php:2821`. The Wayfinders analogue is not a comma-list at all — it is `specialSchedule.VGActivity`, a _string name_ used as a join key (§7.1).

Transient comma-strings that are **never persisted** (POST transport only, listed so nobody mistakes them for schema): `special.php`'s `skipdates` (`:1957-1975`), ROMBus's `$rombuss`/`$contacts` (`rombus.php:1089-1105`), Docents' `docents`/`shifts` (`docent.php:4255`), and Outreach's **three parallel index-aligned strings** `presenters`/`shifts`/`roles` (`outreach.php:2522-2526`).

### 5.2 Denormalized caches

**The `VGActivity` chain is the worst.** The value is copied **three tables deep** and only consumed at the far end:

```
specialActivities.VGActivity              (the master, hand-maintained)
   → specialRoles.VGActivity              (special.php:1706, :1710, :1988)
   → specialDayPattern.VGActivity         (special.php:1374, :1859, :1884)
      → specialSchedule.VGActivity        (special.php:1941, :1999)
         → consumed exactly once, at publish (special.php:1396)
```

Alongside it, `specialRoles.Name` and `specialDayPattern.Activity` both denormalize `specialActivities.Name`, and `specialDayPattern.Eligible` denormalizes a label computed from `EligibleID`. Renaming an activity does not update existing rows.

| Cache                                                                                              | Source of truth                                    | Sync mechanism                                                                                                                                                       |
| -------------------------------------------------------------------------------------------------- | -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `*History.Total_Scheduled` / `Total_Group` / `Total_Meeting` / `Total_Hotspot` / `Total_Museum`    | `SUM()` over the dated rows                        | Rebuilt wholesale at month-confirm. **`Total_Hotspot` is read by docent and gdr but written by neither**; `vgHistory.Total_Group` likewise                           |
| `MemberActivity.Total_Scheduled`                                                                   | the _same_ numbers, one level up                   | A **second, parallel** rollup (§7.11). Note Wayfinders skips `*History` entirely and writes straight to `MemberActivity` (`special.php:2424-2452`)                   |
| `docentgroupTours.DocentShifts` / `gdrgroupTours.GDRShifts` / `outreachgroupTours.PresenterShifts` | `SUM(join.Shifts)`                                 | Recomputed on full save, but **incrementally adjusted** on add/remove — drift is possible                                                                            |
| `docentgroupTours.Earned` / `gdrgroupTours.Earned`                                                 | `RatePerVisitor × Visitors + RatePerHour × Shifts` | Computed at save; the incremental add/remove paths change `Shifts` **without recomputing `Earned`**                                                                  |
| `outreachgroupTours.PresentersSignedup`                                                            | `COUNT(join rows)`                                 | Recomputed on full save only                                                                                                                                         |
| `outreachgroupTours.LeaderID`                                                                      | duplicates the join row with `Role='LD'`           | Two independent writes from one dropdown — **can diverge**                                                                                                           |
| `rombusTrip.CoordinatorID`                                                                         | duplicated into a `rombusToTrip` row               | Display re-derives by id comparison and skips the duplicate                                                                                                          |
| `rombusTrip.Visitors`                                                                              | `SUM(rombusGuests.Quantity)`                       | Overwritten by import, but also hand-editable                                                                                                                        |
| `*scheduledTours.YYYYMM`                                                                           | `Date`                                             | Written at insert, never recomputed. **`specialSchedule.YYYYMM` is a dead cache** — every report re-derives it with `substring(shiftDate,1,7)`                       |
| `*scheduledTours.Day`                                                                              | day-of-week of `Date`                              | Written at insert. GDR stores three different conventions in this one column                                                                                         |
| `vgscheduledTours.Colour` / `receptionscheduledTours.Colour`                                       | a pure function of `Date` vs the 2012-07-01 epoch  | Written at generation                                                                                                                                                |
| `vgscheduledTours.MemberID`                                                                        | `vg.MemberID` for the row's `VgID`                 | Resolved at write time; historical rows do not follow a re-link                                                                                                      |
| `*.FullName` (`vg`, `docent`, `gdr`, `gi`, `special`, `outreach`)                                  | `Members.First_Name`+`Last_Name`                   | `_updateAllFullNames` loops every Group table on rename (`servicesp.php:2631-2635`) — but `vg.FullName` has **no write site in `res/`** and is stale by construction |
| `docentscheduledTours.VetText` / `gdrscheduledTours.VetText`                                       | `IsVet`                                            | Cosmetic string duplicating a boolean                                                                                                                                |
| `gischeduledTours.ObjectText`                                                                      | `giArtefact.Name`                                  | Never refreshed when an object is renamed                                                                                                                            |
| Office flags on membership tables (`Chair`, `Scheduler`, `Statistician`, …)                        | `*Exec.Position` rows                              | Written together; the previous holder's flags are cleared by hand                                                                                                    |

---

## 6. Model-breakers, ranked

Ranked by how load-bearing each looks against **"one Schedule container over a date range, hand-entered Shifts, Sign-ups on Shifts."**

**1 — Outreach has no Shifts and no Sign-ups. The booking _is_ the shift; the join row _is_ both the shift-instance and the sign-up, fused.**
`outreachgroupToORMember` has no independent existence — it is created and destroyed only as part of a whole-booking save (`outreach.php:2519-2529`, `:2541`). **You cannot represent an empty shift awaiting a sign-up.** A booking with `PresentersNeeded=3` and one member has _one_ row, not three; the unfilled two are a subtraction (`PresentersNeeded - PresentersSignedup`), not records. Everything the core model wants to hang off a Shift — capacity, kind, a vacancy to advertise — has nowhere to live. _This is the one that actually breaks the model._

**2 — Capacity is expressed as N identical rows, not a number, in the two Groups the map profiled.**
Both Wayfinders (`special.php:1938`, `:1993`) and Visitor Guides (one dated row per weekly-pattern row) emit `Required` byte-identical rows and store the count nowhere on the dated row. The rows are indistinguishable seats. Consequences already visible in legacy: changing `Required` requires `DELETE FROM specialSchedule WHERE EventID=…` and regeneration, which **destroys every existing sign-up for the event** (`special.php:1928`, `:1960`). A model with a `capacity` integer on the Shift is _better_, but it is a genuine reshape, not a rename — and it changes what a "Shift" is from a seat to a slot.

**3 — Two Groups' "patterns" are recurring Sign-ups, not recurring Shifts.**
Reception's `receptionweeklySchedule.commID` (`reception.php:1117`) and Visitor Guides' `vgweeklySchedule.VgID` (`vg.php:1530`) both name a **permanent member**, and both generators copy that member onto the generated dated row — so generating a month **pre-fills the sign-ups**. Hand-entry-only for the first pass therefore does not merely cost these two Groups convenience; it removes the only mechanism by which their standing regulars are booked. This is the map's [#329](https://github.com/roytanaka/dmv-rom-v2/issues/329) question, and the code confirms it lives in _two_ Groups, not one.

**4 — `Count` means hours in four Groups and a quantity in three, and the hours sense is load-bearing in a cross-table join.**
See §3.2. `Sum(specialSchedule.Count)` is written directly into `MemberActivity.Total_Scheduled` as hours (`memberhistoryactivity.php:65-74`) while `Sum(docentscheduledTours.Count)` is written into `docentHistory.Total_Scheduled` as a tour count, reconciled only by a hardcoded per-Group multiplier array (`memberhistoryactivity.php:8`). Any fan-in that maps `Count → Count` silently corrupts every Group's hours. Two fields are hiding in one name.

**5 — Wayfinders' shift-kind catalog is scoped per _event_, not per Group.**
`specialRoles.EventID` (`special.php:1707`) means every event gets its own private set of shift kinds, re-created from `specialActivities` on each save, and hard-deleted with no cascade (`special.php:1715-1718`). ADR-0015's **Catalog** is "the small per-Group list of kinds of shift" — that is `specialActivities`, but what the dated row actually points at is the per-event copy. The Catalog question ([#326](https://github.com/roytanaka/dmv-rom-v2/issues/326)) has to decide which of the two survives, and Wayfinders is the Group where they are not the same thing.

**6 — Outreach's shift kind is per _member on a booking_, not per shift.**
`outreachgroupToORMember.Role` (`outreach.php:2527`) is the only place in eight Groups where the kind attaches to the assignment rather than to the dated thing. Two members on the same booking carry different kinds. A Catalog that hangs off the Shift cannot express this; a Catalog that hangs off the Sign-up mis-fits everyone else.

**7 — Outreach's per-member hours vary within one booking.**
`outreachgroupToORMember.Shifts` is an integer hour count with a floor of 1, adjustable per member with +/− buttons (`outreach.php:2286`, `:2457`). If a Sign-up's duration is derived from its Shift's start/end, these have nowhere to go.

**8 — Editing an Outreach booking deletes and re-creates it under a new primary key.**
`_ChangeGroupTour` calls `_AddGroupTour($schID)`, which inserts a fresh row and fresh join rows against the **new** id, then deletes the old ones (`outreach.php:2540-2541`, `:2549-2558`). **`outreachgroupTours.ID` is not stable.** No durable reference to a booking can exist — which affects migration keying, not just the app.

**9 — Wayfinders' container is shared across seven Groups.**
`specialEvents.committee` (`special.php:1350`) holds `special` _or_ one of six Friends Committees. So the date-range container is not "a Group publishes a Schedule" — it is "one Group's Chair publishes Schedules on behalf of seven." The map has already cut cross-Group authoring, but the _data_ is one table, and the Friends Committees have no scheduling tables of their own to migrate into.

**10 — The cross-Group link is a string match on four columns, and it never unwinds.**
`specialSchedule.VGActivity == vgscheduledTours.Type`, plus `Date`, `Time`, and `Count`-as-hours (`special.php:1406`). On publish it claims a free VG slot or **creates one**; on un-publish it does nothing (§7.1). Already out of scope, but it means the legacy data contains VG rows that exist only because a Wayfinders event was published once — and orphans from events since un-published or purged.

**11 — Object reservation has no DB constraint and, in GI, no junction table.**
`gischeduledTours.ObjectID` is a comma-string; double-booking is prevented by three read-then-write PHP passes with no transaction (`gi.php:3132-3181`). ADR-0015 calls object non-double-booking a **hard requirement** expressible as a uniqueness/overlap constraint — legacy has never had one, so migrating the data may surface existing conflicts that the new constraint would reject.

**12 — Outreach has no lifecycle and no confirmation state.**
No draft/tentative/confirmed/cancelled/completed on `outreachgroupTours`, no soft-delete, and the confirmation email persists nothing (`outreach.php:2571-2635`). Any state machine has no legacy field to migrate from. Its container only ever carries `status='group'`.

**13 — Reception has no shift kind at all**; the cross-Group panel hardcodes the string `"Desk"` in PHP (`servicesp.php:2847`). A required Catalog reference has nothing to point at.

**14 — The `Visitors` column is a _forecast_ in Outreach and an _actual_ everywhere else.**
Same name, opposite ends of the event (§7.9). High risk of a silent semantic mis-map.

**15 — GI's `giEvent` is a second, parallel container** with its own date range _and_ a wider object-lock window (`ObjectStart`/`ObjectEnd`, `gi.php:2190-2191`). If a Schedule is the only container, GI needs somewhere to put the buffer.

---

## 7. The ticket's specific targets, answered

### 7.1 The cross-Group linkage column — confirmed, with corrections

**The exact column name is `VGActivity`** (one word, no space). It exists on **four** tables, not one:

| Table                          | Role                                                                     |
| ------------------------------ | ------------------------------------------------------------------------ |
| `specialActivities.VGActivity` | the master value, hand-maintained on the _Update Activities List_ screen |
| `specialRoles.VGActivity`      | copy 1                                                                   |
| `specialDayPattern.VGActivity` | copy 1′ (the other authoring mode)                                       |
| `specialSchedule.VGActivity`   | copy 2 — **the only one that is consumed**                               |

**It is a VARCHAR holding the Visitor Guides shift-kind _name_, not an id.** It is compared directly against `vgscheduledTours.Type` (`special.php:1406`).

**The screen.** Scheduler menu button at `public_html/res/special/special.php:1202`, immediately preceded by the comment `// Support for VG link`:

```php
$res.="&nbsp;&nbsp;"._getButton('Update Activities List' , 'showTable("Activities");');
```

`showTable()` is the **generic** table editor (`res/js/dataroot.js`), which posts to `_showTable` in `public_html/res/services/servicesp.php:9112-9151`. That composes the physical name as `{prefix}` + `special` + `Activities` = `dmv_specialActivities`, and renders every column except `ID`/`Active`/`LastUpdateField`. Because it is schema-driven, **no PHP anywhere enumerates `specialActivities`' columns** — which is why the column list in §2.2 is marked incomplete.

**Every call site that reads the column.** A tree-wide grep for `VGActivity` returns **only `special.php` and its five stale snapshots**. No hit in `vg.php`, `servicesp.php`, or anywhere else.

| Line                                           | Reads from                                               | What it does                                                                                         |
| ---------------------------------------------- | -------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `special.php:1632`                             | `specialActivities`                                      | `ORDER BY VGActivity DESC, Name` — floats VG-linked activities to the top of the Add-Activity picker |
| `special.php:1703`                             | `specialActivities`                                      | copies into the new/updated `specialRoles` row                                                       |
| `special.php:1706`, `:1710`                    | → `specialRoles`                                         | writes the copy                                                                                      |
| `special.php:1780`                             | `specialActivities`                                      | same `ORDER BY` in the Day-Pattern picker                                                            |
| `special.php:1859`                             | `specialActivities`                                      | `mysql_select_field(…,"VGActivity")` — copies into `specialDayPattern`                               |
| `special.php:1371`, `:1374-1375`               | `specialDayPattern`                                      | copies again when cloning a pattern onto a new event                                                 |
| `special.php:1884-1885`, `:1889-1890`          | → `specialDayPattern`                                    | insert/update                                                                                        |
| `special.php:1925`, `:1935`, `:1941-1942`      | `specialRoles` → `specialSchedule`                       | copies a third time onto each generated seat                                                         |
| `special.php:1980`, `:1988-1989`, `:1999-2000` | `specialDayPattern` → `specialRoles` → `specialSchedule` | day-pattern generation                                                                               |
| **`special.php:1396`**                         | `specialSchedule`                                        | **the only consumer** — `WHERE EventID=$id AND VGActivity<>'' AND VGScheduleID=0`                    |
| `special.php:1404`, `:1406`, `:1419-1424`      | `specialSchedule` → `vgscheduledTours`                   | matched against `Type`, then written into a newly created VG row                                     |

**Is it exactly one populated row — "Plan Your Visit desk"? Cannot be confirmed from the repo, and the claim needs one query to settle.** Reasons: there is no SQL dump or seed file for `special*`; `special_data_24-03-14.js` and `special_data_signin.js` are **client-side function libraries**, not data; and the string `"Plan Your Visit"` appears nowhere in any `.php`/`.js`/`.htm` under `public_html/res/`.

Circumstantial support for a single row is strong:

- `special.php:2101` — the Wayfinders-side mirror view hardcodes `WHERE … Type='Desk'`, i.e. exactly one VG shift kind is reachable from this side.
- `special.php:2012` — `if($whereval<4) {_specialvgShowSchedule($whereval); return;}` — event ids 1–3 are pseudo-events routed straight to the VG desk view.
- `vg.php:2054-2056` (commented out) — the only auto-created `specialSchedule` row is annotated _"this was from PYV DESK ONLY"_, with hardcoded `EventID=1`, `RoleID=170`, `Count=2`. "PYV" = Plan Your Visit.
- `special.php:1632`, `:1780` — `ORDER BY VGActivity DESC` only makes sense as a "float the one special row to the top" hack.

**The query to run:** `SELECT ID, Name, VGActivity FROM dmv_specialActivities WHERE VGActivity <> ''`.

**How the link is kept in step — six sites, and it never unwinds.**

| #   | Site                                                                             | What it does                                                                                                        |
| --- | -------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| 1   | `_specialPublishEvent` — `special.php:1396`, `:1406`, `:1410-1411`, `:1419-1426` | claim a free VG slot **or create one**; set both pointers                                                           |
| 2   | `_specialaddsignUp` — `special.php:991-1004`                                     | member signs up: mirror `MemberID`/`VgID` into `vgscheduledTours`                                                   |
| 3   | `_specialdeletesignUp` — `special.php:1023-1030`                                 | cancel: clear both                                                                                                  |
| 4   | `__memberSignIn` / `_remoteSignIn` — `special.php:261-263`, `:290-292`           | kiosk sign-out: `UPDATE vgscheduledTours SET VgID=0, MemberID=$mid, Confirmed=1` — comment _"tell VGs it is taken"_ |
| 5   | `_specialSetScheduleMember` — `special.php:2202-2216`                            | Scheduler assigns by hand; same mirror                                                                              |
| 6   | `_specialdeleteshift` — `special.php:2225-2234`                                  | deletes the SP row, then **`DELETE FROM vgscheduledTours`**                                                         |

Plus four reciprocal writes from the VG side: `vg.php:294-295`, `:319-321`, `:915-917`, `:938-940`.

**Un-publish does not unwind.** The entire merge sits inside `if ($newvisible == 1)` (`special.php:1393`); when `$newvisible == 0` the function falls straight through to `UPDATE specialEvents SET visible=0`. Every VG row created by a publish survives as an orphan, still bookable on the VG side.

**The credit rule.** `public_html/res/vg/vg.php:915-918`:

```php
if($row['SPScheduleID'] > 0) { // if this shift has a link to Special Events, put the member name there to be visible on SP sign in page.
    $spID = $row['SPScheduleID']; // include the VgID number so SP knows to ignore it for Statistics purposes.
    mysql_command("UPDATE {$prefix}specialSchedule SET MemberID=$mid, VgID=$uid WHERE ID=$spID");
}
```

If the person filling a Wayfinders shift is themselves an active Visitor Guide, `VgID` is set non-zero and the shift is **excluded from all Wayfinders statistics** — `special.php:2314`, `:2431` (which physically **deletes** such rows at month-close), `:2533`, `:475`, `:175`, `special_daily.php:20`, `servicesp.php:10806`.

### 7.2 Capacity padding — extra stored rows, written by the Wayfinders side

**Definitive: padding is extra `vgscheduledTours` rows. Nothing is computed at render.**

The `else` branch of the publish scan, `public_html/res/special/special.php:1413-1427`:

```php
else {
    $result2 = mysql_select("SELECT * from {$prefix}vgscheduledTours WHERE `Date`='$date'");
    $row2 = @mysqli_fetch_array($result2);
    $cl=$row2['Colour']; $day=$row2['Day']; $yyyymm=$row2['YYYYMM'];
    $sqladd="INSERT INTO {$prefix}vgscheduledTours (
        `Day`,`Time`,`Date`,`YYYYMM`,`Colour`,`VgID`,`MemberID`,`Type`, SPScheduleID, Count
        ) VALUES ('$day','$time','$date','$yyyymm','$cl',0,0,'$activity',$spid,$hours)";
    mysql_command($sqladd);
    $vgschid = mysqli_insert_id($mysqli);
    mysql_command("UPDATE {$prefix}specialSchedule SET VGScheduleID=$vgschid WHERE ID = $spid");
}
```

For each Wayfinders seat that cannot find a free, unlinked VG slot matching `(Date, Time, Type, Count)`, a **brand-new vacant `vgscheduledTours` row** is inserted with `VgID=0, MemberID=0, SPScheduleID=$spid`. It borrows `Colour`, `Day`, `YYYYMM` from _any_ existing row on that date — with no `ORDER BY`, so it takes whatever MySQL returns first, and writes blanks if the date has no VG row at all.

That row is vacant, so the VG month view renders **an extra sign-up button in that one cell**, via the multi-row branch at `vg.php:827-873` (button at `:866`). **No capacity number is consulted anywhere in the render path** — the cell simply iterates whatever rows exist for `(YYYYMM, Date, Time, Count)` (`vg.php:787-788`).

So "the system generates a schedule for five altogether" = 2 rows from the VG weekly pattern + 3 rows minted by the Wayfinders publish.

**`$desksubNeeded` is not padding.** It decides whether removing yourself triggers a broadcast "sub needed" email (`vg.php:646-653`, `:849-856`): `-1` = not a Desk shift, skip the dialog; `0` = Desk but another row exists in the slot, no email; `1` = Desk, imminent, and the only row, so broadcast. Note it counts **all** rows in the slot regardless of `Type` — including `Shadow` and SP-padded rows — so a slot with one Desk plus one Shadow counts as 2 and suppresses the email. Flagged as likely unintended, not asserted.

### 7.3 Eligibility codes — verified, with one correction

**Confirmed**, and the correction is that subgroup ids are `100 + subCommittee.ID`, so the "any other value = committee id" band is precisely `2..98`.

| Value   | Meaning                                                                                               |
| ------- | ----------------------------------------------------------------------------------------------------- |
| `0`     | No one                                                                                                |
| `1`     | Everyone _(note: the dialog's default is `0`, not `1` — `special.php:1619`)_                          |
| `99`    | MIS only                                                                                              |
| `2..98` | `Committee.ID` — resolved to a table name via `Committee.symbol`                                      |
| `>100`  | `Eligible - 100` = `subCommittee.ID` — **and the picker offers only subgroups of Visitor Wayfinders** |

**Column names:** `specialRoles.Eligible` (numeric), and in Daily-Pattern authoring `specialDayPattern.EligibleID` (numeric) plus `specialDayPattern.Eligible` (a denormalized label). **`specialSchedule` has no eligibility column** — readers reach it by join.

**The picker** — two identical copies, `special.php:1641-1656` and `:1790-1807`:

```php
$eligibleselect.=__formatOption(0,"No one",$eligible);
$eligibleselect.=__formatOption(1,"Everyone",$eligible);
$eligibleselect.=__formatOption(99,"MIS only",$eligible);
$result = mysql_select("SELECT * FROM {$prefix}Committee where ID>1 AND Active=1 ORDER BY DisplayOrder");
while ($row = @mysqli_fetch_array($result)) { $id=$row['ID']; $eligibleselect.=__formatOption($id,$row['Name'],$eligible); }
//Now add in the list of subcommittees as a choice of members
$result = mysql_select("SELECT * FROM {$prefix}subCommittee where committee='special' AND Active=1 ORDER BY Name");
while ($row = @mysqli_fetch_array($result)) { $id=100+$row['ID']; $eligibleselect.=__formatOption($id,$row['Name'],$eligible); }
```

**The evaluation** — three copies, one per `DisplayStyle` (`special.php:713-742` Calendar, `:911-934` Across, `:1121-1146` Down). Quoting the Calendar copy:

```php
if($eligible == 0 || !_CovidProofOK($mid)) { $cell .= ""; }
else if($eligible == 1) { $cell .= _getButton($type,"specialAddSignUp($schid)")."<br>"; }
else if($eligible == 99) { if($isMIS) { … } }
else if($eligible > 100) { if(_isASubCommitteeMember($mid,"",$eligible - 100)) { … } }
else { // committee
    $result5=mysql_select("SELECT symbol FROM {$prefix}Committee WHERE ID=$eligible");
    $row5 = @mysqli_fetch_array($result5);
    $sql2="SELECT MemberID FROM {$prefix}".$row5['symbol']." where MemberID=$mid AND ACTIVE=1";
    if(mysql_select($sql2)) { … }
}
```

Note the committee branch builds a **table name from data** — `{prefix}` + `Committee.symbol`.

Note also that the `0` branch is **not purely "no one"** — it is `$eligible == 0 || !_CovidProofOK($mid)`, so a member failing the COVID check is treated as ineligible for _every_ code. That coupling is in all three copies. `_CovidProofOK()` is currently `return true;` (`servicesp.php:2417-2419`).

**Eligibility is a rendering decision only.** `_specialaddsignUp()` (`special.php:980-1015`) checks that the seat is free but performs **no eligibility check** — a crafted POST can claim any shift.

**Is the ladder written differently elsewhere? Yes — once, and it is broken.** A **fourth, truncated copy** lives in the shared services layer, in the generic Friends-Committee event grid — `public_html/res/services/servicesp.php:12090-12122`. It reads the same `specialRoles.Eligible` but implements **only two branches**:

```php
12107:  if($eligible == 1) { // EVERYONE add signup
12112:  else { SELECT symbol FROM Committee WHERE ID=$eligible; … }
```

There is **no `0` branch, no `99`/MIS branch, and no `>100` subgroup branch**. A shift kind with `Eligible=0` or `99` reaching this path falls into the `else`, runs `SELECT symbol FROM Committee WHERE ID=0` (or `99`), gets an empty symbol, and builds the malformed query `SELECT MemberID FROM dmv_ WHERE …`. So the six Friends Committees silently do not honour "No one", "MIS only", or subgroup restrictions.

Two further hazards in this code space:

- **`99` is overloaded.** It is also `StatusID` = deceased in an unrelated domain (`servicesp.php:11418`, `gdr.php:3215`, `docent.php:4692`). Different domains, same literal.
- **An unenforced invariant:** `Committee.ID` must stay `< 100` and `!= 99` or the ladder mis-routes. Nothing validates it.

**The MIS query itself does _not_ diverge.** The three copies in `special.php` are byte-identical in their SQL body (§7.3 above), the six symbols and their order are the same, and a tree-wide grep finds no other live copy. A **seventh copy once lived in `servicesp.php`** (function `__getCurrentEventsContent`) and survives only in the stale mirrors — it has been removed from the live tree.

Only Wayfinders has the per-shift eligibility mechanism. No other Group has a "who can sign up" column: Docents and GDR gate on a qualification matrix, GI on `committeeToStatus.activeFlag`, Reception and ROMBus not at all.

**Three different "is this member active" tests coexist** across the scheduling code, and they are not equivalent: `ACTIVE=1` (the MIS query and the eligibility ladder), `StatusID=1` (`public_html/res/vg/vg_daily.php:80`, which decides who receives the vacancy broadcast), and `committeeToStatus.activeFlag=1` (`gi.php:2511`, `servicesp.php:11607`).

### 7.4 Suspected-vestigial Visitor Guides shift types — the numbers

**The vocabulary lives in exactly one place: the `vgShiftTypes` table.** One SQL reference in the live tree, `public_html/res/vg/vg.php:1470`, inside `__getShiftSelect()` (`:1468-1484`):

```php
$result=mysql_select("SELECT * FROM `{$prefix}vgShiftTypes` where Active=1");
while ($row = @mysqli_fetch_array($result)) {
    $type=$row['Shift'];
    $shiftselect.="<option value='$type'".($shift=="$type"?" selected":"") .">$type</option>";
}
/*
$shiftselect.="<option value='Desk'…>Desk</option>";
$shiftselect.="<option value='Wayfinding'…>Wayfinding</option>";
$shiftselect.="<option value='Rotunda'…>Rotunda</option>";
$shiftselect.="<option value='Shadow'…>Shadow</option>";
$shiftselect.="<option value='Chen Court'…>Chen Court</option>";
*/
```

The commented-out block is the pre-migration hardcoded list. Called from four dialogs: `vg.php:1503`, `:1569`, `:1719`, `:1792`.

**Usage counts in `public_html/res/vg/vg.php`:**

| Literal      | Total occurrences       | Live code references                                                                                                     | Last-referenced call site                                                                           |
| ------------ | ----------------------- | ------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| `Desk`       | **21**                  | **8 live comparisons** — `:647`, `:652`, `:813`, `:847`, `:850`, `:855`, `:1669`, `:1687`; plus `vg_daily.php:54`, `:65` | live throughout                                                                                     |
| `Shadow`     | **18**                  | **6 live comparisons** — `:514`, `:661`, `:865`, `:1021`, `:1030`, `:1658`, `:1676`, `:1694`, `:1940`                    | live throughout                                                                                     |
| `Chen Court` | **3** — all on one line | **0**                                                                                                                    | `vg.php:1480`, **inside the `/* */` block**                                                         |
| `Rotunda`    | **3** — all on one line | **0**                                                                                                                    | `vg.php:1478`, **inside the `/* */` block**                                                         |
| `Wayfinding` | **6**                   | **0** — every one is in a `//` comment or the `/* */` block                                                              | `vg.php:1477` (block); comment at `:661`, `:865` — _"Old rule that must do Desk before Wayfinding"_ |

Neither `vg_data_25-05-03.js` nor `vg_data_signin_23-04-13.js` contains any shift-type list — the value is always taken from the server-rendered `<select id='shift'>`.

**What this supports putting to Visitor Guides, with numbers:**

> The shift-type dropdown is driven entirely by `vgShiftTypes WHERE Active=1`. The application code has **special handling for only two types**: `Desk` (8 live branches) and `Shadow` (6 live branches, the provisional-only lane). `Wayfinding`, `Rotunda`, and `Chen Court` appear **nowhere in live code** — only inside a commented-out fallback list disabled when the vocabulary moved into the table. Every non-Desk, non-Shadow type is treated as an opaque label: rendered, stored, matched against the Wayfinders link, and nothing else. Which of these are still in use?

Two consequences worth carrying into that conversation:

- Only `Desk` triggers the sub-broadcast (§7.2) and only `Desk` triggers the vacancy reminder email (`vg_daily.php:54`). **Any non-Desk shift that goes vacant is silently invisible to the reminder system.**
- What the code **cannot** tell us is whether `Chen Court` / `Rotunda` / `Wayfinding` rows still exist with `Active=1` in the table. That needs `SELECT ID, Shift, Active FROM dmv_vgShiftTypes`.

### 7.5 Suspected-dead authoring mode — **both paths are live**

The two modes are stored in `specialEvents.ScheduleUsing`, chosen at event creation (`special.php:1282-1285`) and **write-once** — `_specialUpdateEvent` (`:1383-1390`) omits it and the edit screen has no picker.

The branch, `special.php:1250-1260`:

```php
if($row['ScheduleUsing']==0) {
    … "Set Shifts"      → specialShowDateTime()   // writes specialDateTimes
    … "Set Activities"  → specialShowRoles()      // writes specialRoles
}
else {
    $res .= _specialShowDayPattern();             // writes specialDayPattern
}
```

Both converge on one "Generate Schedule" button (`:1265`), which forks at `:1912-1916` into `_specialGenerateSchedule` (Shift/Activity) or `_specialGenerateScheduleFromDayPattern` (Daily Pattern).

**Both are reachable and both have live writers.** Positive evidence that Daily Pattern is in real use: the Add-Event dialog's _"Copy Pattern from"_ list is populated by `SELECT * from specialEvents WHERE ScheduleUsing = 1 AND Active = 1 AND committee='special' order by startdate DESC LIMIT 0,25` (`special.php:1300`) — a picker that would be permanently empty if no Daily-Pattern Wayfinders events existed.

So the answer to _"I don't think she uses the other version anymore"_ is: **the code cannot retire either path.** Which is _actually_ used is a data question — `SELECT ScheduleUsing, COUNT(*) FROM dmv_specialEvents WHERE committee='special' GROUP BY ScheduleUsing`.

One asymmetry worth noting: `exportevent.php:24` drives its CSV off `specialDateTimes`, so **Daily-Pattern events export as an empty grid**. And `skipdates` (the "Optionally Skip these dates" box, `special.php:1263`) is only honoured by the Daily-Pattern branch — the Shift/Activity generator ignores it even though the same button posts it.

### 7.6 Reception's two-week pattern and its anchor

Answered in full at §2.6. The three facts:

1. The table is `receptionweeklySchedule`; the fortnight discriminator is **`Colour`** (`'red'`/`'blue'`), not a week number.
2. The phase is anchored on a **hardcoded epoch, `2012-07-01`**, as weeks-since-epoch mod 2, with a Saturday flip inside the month (`reception.php:1014-1020`, `:1042-1043`). The identical mechanism and the identical epoch appear in Visitor Guides (`vg.php:1342-1346`).
3. **The pattern rows carry a member** (`commID`, labelled "Regular Member"), and the generator copies it onto the dated row — so it is a **recurring Sign-up**, not a recurring Shift.

### 7.7 Docents' weekly pattern and the tour → qualified-member cross-link

**The pattern:** `docentweeklySchedule` (`ID`, `DayOfWeek`, `NameOfDayID`, `TimeOfDay`, `SignUpTourID`) — **exactly one unnamed global pattern**, no `Name`, no `Colour`. Generator at `docent.php:2733-2777`; duplicate-month guard at `:2741`. The advisory banner is `docent.php:2726` (quoted in §2.3). Unlike Reception and VG, **Docents' pattern carries no member** — it generates open slots only.

**The cross-link.** `docentToTour` (`TourID`, `DocentID`, `Active`, `LastVetDate`). A row means: this docent is qualified to give this tour kind; `Active=1` = in force; `LastVetDate` = last vetting. **The qualification attaches to the tour KIND, never to the dated shift.**

Member pickers filtered by it: `docent.php:144-146` (`__getDocentsForTour`), `:165-167` (`_getDocentSelectForTour`), `:776-778`, `:5290-5296`, `:600`.
Tour pickers filtered by it: `docent.php:205-217` (`_getTourForDocent`), `:232-235`, `:5467-5473`.

**The sign-up gate**, `docent.php:2013`:

```php
if(_CovidProofOK($mid) && $docentCanGiveTours && (($docentCanGiveGalleryThemeTours && ($signupID==1 || $signupID==8)) || ($signupID==8 && array_search(1,$validTourArray)!==false) || (array_search(__getTourID($signupID), $validtourarray)!==false)))
```

For a non-choice slot this resolves the slot to its tour via `__getTourID()` (which reads `docentsignupTourToTour`) and requires that tour to be in the member's qualified set. The three claim dialogs (`docent.php:2162-2165`, `:2215-2218`, `:2248-2251`) each run the same 4-table join, so the tour dropdown offered on Sign-up is exactly _tours reachable from this slot_ ∩ _tours this member is qualified for_.

Retire guard: `_DocentRetireTour` refuses while active qualifications exist (`docent.php:5197`).
Status-driven lifecycle: promotion to `Full` grants tour 1; Emeritus/Resigned/Deceased deactivates all; removal from the committee hard-deletes (`docent.php:4672-4702`).

Hardcoded constants that govern it (`docent.php:57-64`): `$allDocentToursArray = array(2,35)` — "tours that everyone can give"; `$docentMHNewDocentTourID = 59` (or `71`). Tour id `1` and signup ids `1`/`8` are hardcoded throughout.

GDR has the identical shape under `gdrToTour` / `gdrsignupTour` / `gdrsignupTourToTour`. **GI has none** — no qualification concept at all.

### 7.8 ROM for You / Outreach — the full shape

Answered at §2.8, with the ranked breakers at §6 (items 1, 6, 7, 8, 12, 14). The three claims in the ticket, checked:

- **"Organized around the organization presented to"** — correct, and worse than expected: **there is no organization table.** `Client_Name`, `Client_Address`, `ContactName`, `ContactPhone`, `ContactEmail` are re-entered on every booking and auto-filled by a `LIKE '%…%'` search of the most recent prior booking (`outreach.php:2474-2488`). The organization is emergent from booking history.
- **"And the presentation given (an extension of the GI object idea)"** — half correct. The presentation is `outreachGalleryTheme` (a table named for a gallery that holds presentations). The _object_ catalog is genuinely GI-derived and even cross-references GI's objects via `outreachObjects.GIObjects`, but the relationship shape differs: GI groups objects by a single FK, Outreach by a many-to-many join table — then stores the booking's reserved set as a comma-string anyway.
- **"Multiple members per booking each carrying a shift kind, rendered as letters after their names"** — **correct and unique to Outreach.** `outreachgroupToORMember.Role` holds `outreachRole.Symbol`, rendered as `Firstname Lastname-XX` at `outreach.php:1428`, `:1982`. The 2-character constraint is enforced only by browser string offsets (`outreach_data_26-06-12.js:205-206`).

### 7.9 The visitor-interaction count and its naming drift

**The column name does not drift. `Visitors` is uniform across every visitor-facing Group.** What drifts is _which table it hangs on_, _who enters it_, and _what it means_.

The report that proves the uniformity is `public_html/res/services/interactions.php`. It iterates the `Committee` table, reads `visitorTable`, concatenates it onto the symbol to form the table name, and hardcodes the column at `interactions.php:84`:

```php
$column='Visitors';
$commtable="$committee"."$table";
```

| Group                | Table carrying `Visitors`                                                                                      | Present?                             | Cite                                                                                                          |
| -------------------- | -------------------------------------------------------------------------------------------------------------- | ------------------------------------ | ------------------------------------------------------------------------------------------------------------- |
| Visitor Guides       | `vgscheduledTours`                                                                                             | yes                                  | write `public_html/res/vg/vg.php:315`                                                                         |
| Visitor Wayfinders   | `specialSchedule`                                                                                              | yes                                  | write `public_html/res/special/special.php:284`; read `:2311`                                                 |
| Docents              | `docentscheduledTours` (+ `docentgroupTours`)                                                                  | yes                                  | `public_html/res/docent/docent.php:517`, `:3363`                                                              |
| GDR                  | `gdrscheduledTours` (+ `gdrgroupTours`)                                                                        | yes, **plus 5 provenance subtotals** | `public_html/res/gdr/gdr.php:491-492`                                                                         |
| Gallery Interpreters | `gischeduledTours`                                                                                             | yes                                  | `public_html/res/gi/gi.php:365`, `:3029`                                                                      |
| **Reception**        | —                                                                                                              | **absent — confirmed**               | `Committee.visitorTable` is `NULL`; no such column in `reception.php`                                         |
| ROMBus               | `rombusTrip`                                                                                                   | yes, labelled "No of participants"   | `public_html/res/rombus/rombus.php:986`, `:1544-1552`                                                         |
| Outreach             | `outreachgroupTours`                                                                                           | yes — **but a forecast**             | `outreach.php:2437`, `:2513`                                                                                  |
| Walker               | **two columns** — `walkerscheduledTours.Visitors` _and_ `walkerSchtourToWalker.Visitors` (per member per walk) | yes                                  | join-table one written at `public_html/res/walker/walker.php:1277`; both summed at `interactions.php:116-120` |
| ROM Travel           | `romtravelscheduledTours`                                                                                      | yes                                  | `public_html/res/romtravel/romtravel.php:338`, `:368`                                                         |

**Three real divergences under the uniform name:**

1. **Where it hangs.** Most Groups put it on the dated row. Walker has it in **two places at once** — on the dated row _and_ on the member-assignment join (`walkerSchtourToWalker.Visitors`), so Walker records visitors _per member per shift_; only the join-table one is written at sign-in, and `interactions.php:116-120` sums **both**, with no guard against double-counting. Docents and GDR need a **second** sum from their `groupTours` table, hardcoded as a special case at `interactions.php:107-115`. So the same "total visitor interactions" figure is assembled by three different rules depending on the Group.
2. **What it means.** On the _dated shift_ row it is a **member-reported actual**, entered at sign-out. On the _booking_ tables it is a **pre-event number**: Outreach's `outreachgroupTours.Visitors` is a scheduler-entered forecast — the confirmation email reads _"We are expecting an audience of \$Visitors…"_ (`outreach.php:2610`) — and there is **no post-event entry path** (the `save_statistics_visitors` mechanism every other Group uses is commented out in Outreach's JS at `outreach_data_26-06-12.js:106-118`). `docentgroupTours.Visitors` and `gdrgroupTours.Visitors` are likewise the _booked_ group size, and `rombusTrip.Visitors` is the row count of an uploaded guest manifest (`aim_addguestfile.php:60`). All of them are nonetheless added into the same "Total Visitor Interactions" figure (`interactions.php:107-115`). Same name, opposite ends of the event.
3. **A second, unrelated column also exists.** `docentscheduledTours.Interactions` ("visitor interactions _excluding_ tour", `docent.php:518`) and `gdrscheduledTours.Interactions`. **Both are written and never read.** The name is reused at org level by `MemberActivity.Interactions`, which _is_ read — it is added on top of the per-Group `Visitors` sum for every Group (`interactions.php:122-123`), and is the **only** source for Groups with no `visitorTable` (`interactions.php:146-186`).

So the genuine naming hazards are: `Visitors` (actual vs forecast), `Interactions` (three columns, two dead), and `visitorTable` (a table name stored as data).

### 7.10 Is Wayfinders capacity N a column, or N rows? — **N rows**

**N rows.** The headcount lives on the _template_ (`specialRoles.Required` / `specialDayPattern.Required`) and is consumed by a loop that emits that many identical empty rows. Nothing on the dated row records the N.

Shift/Activity path, `public_html/res/special/special.php:1938-1943`:

```php
for($r=0;$r<$required;$r++) {
    $sql = "INSERT INTO {$prefix}specialSchedule
            (ID, EventID, ActivityID, shiftDate, YYYYMM, shiftStartTime, shiftEndTime, RoleID, Count, VGActivity, Confirmed)
            VALUES(NULL,$whereval,$actID,'$dt','$yyyymm','$stime','$etime',$roleID,$count,'$vgactivity',$confirmed)";
    mysql_command($sql);   // identical row, N times
}
```

Daily Pattern path: same shape, loop at `special.php:1993`, insert at `:1998-2001`.

So _"two people at each of these locations"_ ⇒ `Required = 2` ⇒ 2 rows per (date, start, end). The rows are byte-identical apart from `ID` — **indistinguishable seats**. Reader behaviour corroborates: `__getDisplayAcrossEventsContent` re-queries all rows for a (date, time, `RoleID`) triple and emits one name or one Sign-up button per row (`special.php:875-935`); `exportevent.php:36-63` emits one `____________` per unfilled row.

Visitor Guides works the same way (capacity per slot = number of matching weekly-pattern rows), which is why the padding mechanism has to _insert_ rows (§7.2).

### 7.11 The credit path (context for the map's "not yet specified" note)

Not a ticket target, but it is the join in which the `Count`-as-hours sense is load-bearing, so it belongs here.

There are **two parallel rollups**:

- **Per-Group:** `*History` (`ID`, `{Group}ID`, `YYYYMM`, `Total_Scheduled`, `Total_Group`, sometimes `Total_Meeting` / `Total_Hotspot` / `Total_Museum`). `Total_Scheduled` here counts **things done**.
- **Org-wide:** `MemberActivity` (`ID`, `committee`, `subCommitteeID`, `MeetingID`, `YYYYMM`, `MemberID`, `Total_Scheduled`, `Extra_Hours`, `Interactions`) — `servicesp.php:9302-9303`, `:9676`, `:9790`, `:9925`. `Total_Scheduled` here is **hours**.

Written by `_saveScheduledMemberActivity($committee,$uid,$yyyymm,$total,$memid)` (`servicesp.php:9296-9305`), called from `vg.php:2034`, `docent.php:3570` (shifts) and `:3603` (group tours), `gdr.php:3087` and `:3119`, `gi.php:4293`, `reception.php:1580`, `rombus.php:1529`, `outreach.php:1816`, `walker.php:2298`.

Four divergences in this one write path:

- **Wayfinders does not use the shared helper.** It writes `MemberActivity` inline (`special.php:2424-2452`), has no `specialHistory` table, and — uniquely — **accumulates**: `SET Total_Scheduled = Total_Scheduled + $total` after a bulk zeroing of the month (`special.php:2424`, `:2446`). Every other Group **replaces**: `SET Total_Scheduled = $total`.
- **Walker hardcodes its multiplier at the call site**: `_saveScheduledMemberActivity('walker',$walkerid,$whereval,2*$total); //2 hours per walk` (`public_html/res/walker/walker.php:2298`). It is the only Group that does, and it duplicates the `$hoursper` array's intent in a second place.
- **Confirming the Visitor Guides month writes credit for six _other_ Groups.** `_saveSpecialMemberActivity` (`public_html/res/vg/vg.php:2072-2085`, called from the VG confirm at `:2061`) sweeps `specialSchedule ⋈ specialEvents` for a hardcoded Friends list and credits each member under that Friends symbol:

    ```php
    $comms=array('fop','ftc','fcc','bw','fes','fsa'); //Add to this as more Committees have Events
    ```

    So the Friends Committees' hours depend on a Visitor Guides officer pressing Confirm.

- **`famis` is missing from that array** even though it holds events through `specialEvents.committee='famis'` (`special.php:1294`). No compensating write path was found. Flagged as a probable gap rather than an intentional exclusion (§8.2).

The unit reconciliation between `*History` (things) and `MemberActivity` (hours) is the hardcoded `$hoursper` array (`memberhistoryactivity.php:8`), where every Group is `1` except **Walker at `2`**.

Note also that the aliases lie: `docent.php:3550` selects `Sum(Count) AS TotalTours` and feeds it straight into `MemberActivity.Total_Scheduled`, which the reports render as **hours**.

---

## 8. Flagged unknowns

Purposes not derivable from call sites, and facts the code cannot settle. **A short list of queries would close most of the high-value ones.**

### 8.1 Answerable with one query each (high value)

1. **Is `specialActivities.VGActivity` exactly one populated row?** — `SELECT ID, Name, VGActivity FROM dmv_specialActivities WHERE VGActivity <> ''`. (§7.1)
2. **Which Visitor Guides shift types are still `Active`?** — `SELECT ID, Shift, Active FROM dmv_vgShiftTypes`. (§7.4)
3. **Are both Wayfinders authoring modes used?** — `SELECT ScheduleUsing, COUNT(*) FROM dmv_specialEvents WHERE committee='special' GROUP BY ScheduleUsing`. (§7.5)
4. **The DB default for every `*Schedules.status`** — no `INSERT` supplies it in any Group. The UI behaves as though it is `'edit'`, unverified.
5. **The DB default for `docentscheduledTours.Count` and `gdrscheduledTours.Count`** — never written by any code, yet every statistic depends on them.
6. **The DB default for `rombusToTrip.Status`** — never written; every read requires `'Active'`.
7. **Do `'group'`-status rows exist in `vgSchedules`, `giSchedules`, `receptionSchedules`?** Those Groups filter for them six/four/five times but never create them. (§4.1)
8. **Do `specialSchedule.Attended` and `vgscheduledTours.TourID` exist and hold data?** Both are referenced only by dead code.
9. **Which Groups actually schedule?** — `SELECT symbol, HasSchedule, visitorTable FROM dmv_Committee WHERE Active=1 ORDER BY DisplayOrder`. This single query settles both the Group count (§2.9) and the visitor-facing list (§7.9), neither of which the code fixes.

### 8.2 Columns whose purpose is not derivable

9. `gdrToTour.Status` — selected, never written.
10. `docentgroupTours.Posted` — read and formatted, never written by any code in the file.
11. `docentHistory.Total_Hotspot`, `gdrHistory.Total_Hotspot`, `vgHistory.Total_Group`, `vgHistory.Total_Museum`, `outreachHistory.Total_Scheduled` — read, never written.
12. `docentscheduledTours.Interactions` and `gdrscheduledTours.Interactions` — written, never read.
13. `outreachSchedules.notes` — selected at `outreach.php:1354`, never rendered.
14. `outreachCategory.isPrivate` — read at `outreach.php:3373` into a variable that is never used; never written.
15. `docentsection.StartDate` / `EndDate`, `gdrsection.StartDate` / `EndDate` — inserted as `NULL`, never read.
16. `vgSchedules.ExhibitionRevenue` — read by dead code only; the Docent analogue _is_ written.
17. `rombusTrip.End_Time` — read everywhere, written nowhere, display commented out.
18. `rombusGuests.MemberID` — always inserted as an empty string.
19. `outreachExec.DisplayOrder` — selected, never used for ordering.
20. `outreach` membership columns `WeekdayOK` / `FridayOK` / `WeekendOK` / `ShortNotice` — appear only in commented-out code; likely a retired availability mechanism predating `outreachNotAvail`.
21. `vg.StatusID` vs `vg.Status` vs `vg.Active` — `vg_daily.php:80` selects the vacancy-broadcast audience with `WHERE StatusID=1` while all of `vg.php` uses the string `Status` and the tinyint `Active`. The relationship is not derivable, and it decides who receives that email.
22. `outreachCategory.TourCategory` vs `.Name` — both are read as the category label, in dead and live code respectively. Whether both columns physically exist, and whether they agree, is unknown.
23. `outreachRole.ID = 1` — excluded by `ID>1` in both pickers with no comment. What the row is, is unknown.

### 8.3 Incomplete column lists (`SELECT *` hides them)

24. `specialActivities` — the maintenance screen is schema-driven, so no PHP enumerates its columns. Source proves only `ID`, `Name`, `VGActivity`, `Active`.
25. `vgShiftTypes` — source proves only `Shift` and `Active` (plus `ID` by convention).
26. `outreachgroupTours`, `outreachRole`, `outreachSchedules`, `outreachCategory`, `outreachObjects`, `outreachgroupTourType` — all read with `SELECT *` somewhere.
27. `vgweeklySchedule` — both `INSERT`s agree on eight columns plus `ID`, but `SELECT *` is used at `vg.php:1350` and `:1440`.
28. Whether `docentToTour`, `docentsignupTourToTour`, `outreachgroupToORMember` have surrogate `ID` columns — never referenced.
29. Whether `outreachgroupTourType` has an `Active` column — never filtered, so retired types would still appear in pickers.

### 8.4 Ambiguities the code contains but does not resolve

30. **`gischeduledTours.Count` is used as both duration and tour-count** (`gi.php:3160` vs `:4274`). One of the two is wrong; which is intended is unknown.
31. **GI uses three different shift lengths** — 45 minutes (`gi.php:313`, `:322`, `:2407`), 60 minutes (`gi.php:2547`, `:3161`). Which is authoritative is unknown.
32. **Docents' string `Status` and numeric `statusID` are two unmapped encodings**, and Docents' and Outreach's numeric mappings disagree (§4.3).
33. **`$validTourArray` vs `$validtourarray`** at `docent.php:2013` — the middle clause references an undefined variable (capital `T`), so the branch it guards never fires. Intent unknown.
34. **`__getTourSelectForDocent` omits `docentToTour.Active=1`** (`docent.php:232-235`) while every sibling query includes it. Deliberate (allow re-selecting a lapsed tour) or a bug — not determinable.
35. **Magic numbers with no lookup:** Outreach presentation bookability is `CategoryID = 5 OR CategoryID = 6`, hardcoded in four live places; GI's location exemption is `tourID in (1,36) or > 41`; Wayfinders' `ActivityID` `2`/`3`; Docents' tour `1`, signup ids `1`/`8`, and `array(2,35)`; `outreachObjects.ID=1` and `outreachObjectPresentation.PresentationID=0` as sentinels. A category-id reshuffle during migration breaks these silently.
36. **The `special_daily.php` branding.** The live file says _"ROM Events Resource reminder"_; the disabled `xspecial_daily.php` twin says _"DMV Visitor Wayfinder reminder"_. The live file has the **later** mtime but the **older** wording. Whether the current wording is intentional is unknown — and members are currently receiving reminders that call them Events Resource volunteers.
37. **Which Outreach JS bundle ships** — `outreach_data_25-07-01.js` and `outreach_data_26-06-12.js` differ only by the 21-line `outreachNotAvail` block; the include was not traced.
38. **`_outreachSigninReport` queries tables Outreach does not own** (`outreach.php:4009-4054`): the _unprefixed_ `{prefix}scheduledTours` and `{prefix}tour`, joined on `scheduledTours.ORMemberID`. Either Outreach once shared Docents' shift tables and this is a live-but-wrong leftover, or `scheduledTours` still carries an `ORMemberID` column. Unresolvable without the schema.
39. **`docent.php:549`, `gdr.php:526` read `$row['shiftDate']` / `$row['shiftStartTime']`** from their own `scheduledTours` rows — those are the _Wayfinders_ column names. The confirmation emails these build will contain empty dates. Copy-paste, or columns that exist unused? Not determinable from source.
40. **Environment leakage.** `special.php:2510`, `:2531`, `:2609-2611`, `eventactivityhistory.php:6`, `gi.php:4635`, `reception.php:1695`, `servicesp.php:9359`, `:9365`, `res/services/GIShiftHistory.php:9`, `res/hot/hot.php:229`, and all of `res/services/trackTours.php` hardcode the `dmv_` prefix inside otherwise-parameterized queries. This matters more than it looks: `daily.php:19` decides "am I production?" by comparing `$_SESSION['tblprefix']` to `"dmv_"`, so these paths always read production regardless of environment. Whether non-`dmv_` prefixes are still in use is unknown.
41. **Is `famis` meant to be in the Friends credit sweep?** It holds events via `specialEvents.committee='famis'` but is absent from the hardcoded array at `vg.php:2074`, and no compensating write path exists. Either a gap or a deliberate exclusion — not determinable from source.
42. **Table names are constructed from data in five places** — `special.php:734`, `:931`, `:1142` and `servicesp.php:12114` build `FROM {prefix}` + `Committee.symbol`; `interactions.php:85` builds `{prefix}` + `symbol` + `visitorTable`. Any migration must preserve the `symbol → table-name` identity or these fail silently (`mysql_select` errors are suppressed with `@`). Whether every active symbol still has a matching table is a data question.
43. **PHP version ceiling.** `while (list($key,$val) = each($_POST))` at `reception.php:1539` and the parallel statistics handlers in docent/gdr/rombus use `each()`, removed in PHP 8.0. The production runtime is therefore ≤ 7.x — relevant to any plan that touches the legacy app rather than only reading it.
44. **Unreferenced but web-servable copies.** `xservicesp.php` is a 548 KB near-complete duplicate of the whole services layer, `dailytest.php` a third copy of `daily.php`, plus `test.php`…`test8.php` and `testspecial.php` — all `.php` under `public_html`, none referenced. Out of scope here, but they are an attack surface and they inflate every grep.

---

## 9. Summary — is ADR-0015's fan-in real?

**Yes, for seven of the eight Groups, and the core's entity list survives contact.** Every monthly Group reduces cleanly to: a container keyed by month, dated rows carrying (date, time, kind, member, confirmed, visitors), a weekly template, and a per-Group vocabulary of kinds. Wayfinders' date-range container is a _second container shape_, not a second model.

**Three things must change name or shape on the way in**, and they are the whole content of the synonym map:

1. **`Count` must be split** into duration and quantity-credited. It currently means both, and the hours sense is load-bearing in the `MemberActivity` join.
2. **Capacity must become a number.** Legacy expresses it as N identical rows in the two Groups the map profiled; a `capacity` column on the Shift is a genuine reshape.
3. **The member FK must be normalized** to `Members.MemberID`. Nine Groups use seven different surrogate column names pointing at per-Group membership tables, and legacy already carries a hardcoded lookup table to cope.

**One Group does not fit.** Outreach has no Shifts and no Sign-ups as separate things, no self-service, no lifecycle, no stable primary key, and a shift kind that attaches per-member-per-booking. It is a booking ledger wearing a scheduling Group's clothes. It should be scoped explicitly — either as a strategy that reshapes the core, or as out of the first pass — rather than discovered late.
