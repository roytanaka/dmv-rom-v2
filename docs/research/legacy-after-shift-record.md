# Legacy: what is recorded after a shift has happened

Research findings for [#399](https://github.com/roytanaka/dmv-rom-v2/issues/399), a child of the after-shift Wayfinder map [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398).

**Scope is fact, not design.** Every claim below carries a file and line. Where the code does not settle a question it says **not answerable from the code — needs a person**, rather than inferring.

**Not in scope:** proposing a model, planning the migration, reading production data. Where a production-dump number is quoted it comes from ADR-0022 ([#406](https://github.com/roytanaka/dmv-rom-v2/issues/406), PR #396) and is attributed as such, not re-derived here.

---

## 1. Method, sources, and caveats

### Source

Read-only archaeology against `/Users/roytanaka/Documents/ROM/DMV/dmv-rom-legacy`. All citations are paths relative to that repo root, e.g. `public_html/res/vg/vg.php:2034`. Supporting context was also read in the archaeology notes (private repo), which are never quoted or path-cited here.

### Live tree only

`public_html/res/` is the live tree. `res4test/`, `resboot/`, `resphone/`, `resdocumentation/`, `demo/`, `new/`, `res3/`, `res4/` are stale near-complete copies. Every grep below was run repo-wide and then filtered to `res/`; where a copy contributes something the live tree has lost, it is named.

The `x` prefix is the disable convention (`xgi.php`, `xservicesp.php`, `xvg_daily.php` …). Those files are excluded.

### Two more disable conventions matter here, and both bite

1. **`/* … */` around live-looking code.** Two of this ticket's targets sit *inside* block comments — the `hoursper` array (§4.3) and the whole of ROM Travel's reporting (§6). A grep that reports a line number without reading the surrounding twenty lines gets both wrong.
2. **A subsequent unconditional assignment.** `$ipok = $memid==1246 || …;` followed on the next line by `$ipok = TRUE;` (§5.4). The first line looks like the rule; the second is the behaviour.

### `mysql_select()` returns `FALSE` on zero rows

`public_html/res/services/functions.php:32-41`. Every `if(mysql_select(…))` existence test discussed below therefore works as written.

### Privacy

No member names, emails or IDs are reproduced. Note for anyone repeating this work: `docs/` under each Group in the legacy repo holds real member data. Stay out of them — except the procedure PDFs cited in §5.6, which are published member documentation.

---

## 2. Summary of findings

| # | Question | Answer |
|---|----------|--------|
| 1 | `Confirmed` | Live in **6 of 10** scheduling Groups (VG, Wayfinders, Docents, GDR, GI, Reception). **Absent entirely** from ROMBus, Outreach, Walker, ROM Travel — zero references. Means *signed out*, not *booked*. |
| 1 | `Attended` | **Not a live column.** One reference in the whole live tree, inside a `/* */` block (`vg.php:2055`). Every other hit is a table header on a *meeting* screen. |
| 1 | `Visitors` (dated rows) | Live in **9 of 10** Groups (all but Reception). Written at sign-out and by Statistician screens; read only by two org-level reports and the Groups' own statistics screens. **Never feeds credit.** |
| 2 | `_saveScheduledMemberActivity` | Fires on a **month-close button** a Chair or Statistician presses, never on sign-up and never on sign-out. It **replaces**, so re-running does not double-credit — but Docents and GDR have *two* buttons writing one row, and no Group except Wayfinders ever zeroes a member back out. |
| 3 | `hoursper` | **Not a live mechanism.** It is a PHP array inside a `/* */` comment in a one-off backfill script. The only live multiplier is the literal `2*` at `walker.php:2298`. |
| 3 | `Count` | What lands in `Total_Scheduled` is **hours for 4 Groups, tours for 2, shifts for 3, walks×2 for 1**, and a never-written column for 1. Per-Group table in §4.4. |
| 4 | The `*signin` screens | Built as a shared museum-network device (an iPad, per a comment in the code) at a Group's desk: member-list dropdown, 4-digit badge PIN, 3–5 minute idle reset. The network gate is now disabled in all six Groups and the hub says **"no longer in use"**. Sign-out now happens on the member's own device via the main site. |
| 5 | ROM Travel | **Confirmed, and it goes further.** No `_saveScheduledMemberActivity` call anywhere, no `Confirmed`, no sign-in surface, no Schedule menu, no Scheduler menu — and its entire reporting file is commented out. |
| 6 | Everything else | Substitution (overwrites, no trace), Statistician bulk edits of `Count` / `Visitors`, Walker's `Comments` + `Earned` + per-member `Shifts`, `Interactions` (written and never read, twice over), self-entered Extra Hours. **No no-show marker anywhere.** |

---

## 3. The three columns

### 3.1 `Confirmed` — six Groups live, four Groups absent

**It does not mean "booked". It means "signed out after the shift".** The reader that proves it is `vg.php:222-232`: `Confirmed==1` renders *"Signed in"* while the shift window is open and *"Completed"* afterwards; `Confirmed==0` on a past shift renders the **Sign Out** button (`vg.php:530-545`). And the outstanding-work query is `Date>=$backten AND Confirmed=0` — 28 days back, commented *"include any past tours that are outstanding"* (`vg.php:511-514`).

| Group | Column | Writes | Reads | Live? |
|---|---|---|---|---|
| Visitor Guides | `vgscheduledTours.Confirmed` | sign-out `vg.php:314`; sign-in-as-me `:290`; Scheduler per-row Confirm `:1983`; **reset to 0** on withdrawal `:935` | credit filter `:2016`; My Schedule `:530`; kiosk list `:222` | **yes** |
| Visitor Wayfinders | `specialSchedule.Confirmed` | sign-out `special.php:283`; sign-in `:257`; Statistician per-row Confirm `:2410`; propagated from the VG side `vg.php:295`, `:321` | credit filter `special.php:2439`; reports `:2533`, `:2613` | **yes** |
| Docents | `docentscheduledTours.Confirmed` | sign-out dialog `docent.php:536`; remote sign-out `:556`; Statistician row edit `:2372`; per-row Confirm `:3390` | credit filter `:3552`; My Schedule `:1800` | **yes** |
| GDR | `gdrscheduledTours.Confirmed` | sign-out `gdr.php:493`, `:496`; remote `:516`; Statistician row edit `:3035`; per-row Confirm `:2889` | tour reports `:5088`, `:5301`, `:5515`; My Schedule `:1668`. **Not the credit query** — §4.4 | **yes, but not for credit** |
| Gallery Interpreters | `gischeduledTours.Confirmed` | sign-out `gi.php:499`; Statistician row edit `:4256`; insert-as-confirmed `:3942` | My Schedule `:2404`. **Not the credit query** — §4.4 | **yes, but not for credit** |
| Reception | `receptionscheduledTours.Confirmed` | sign-out `reception.php:290`; sign-in `:271`; per-row Confirm `:1531` | credit filter `:1564`; My Schedule `:486` | **yes** |
| **ROMBus** | — | — | — | **absent — 0 occurrences in `rombus.php`** |
| **Outreach** | — | — | — | **absent — 0 occurrences** |
| **Walker** | — | — | — | **absent — 0 occurrences** |
| **ROM Travel** | — | — | — | **absent — 0 occurrences** |

Attendance is recorded in exactly the six Groups that have a sign-out surface. The four that do not have no attendance concept at all: in ROMBus, Outreach and Walker, holding the Sign-up **is** the record, and nothing distinguishes "was scheduled" from "turned up".

Two Group-level flags share the name and are different things:

- **`*Schedules.confirmed`** (lowercase) — the month has been rolled up. Written by every month-close: `docent.php:3573`, `gdr.php:3090`, `reception.php:1583`, `rombus.php:1532`, `outreach.php:1819`, `walker.php:2301`, `gi.php:4297`. VG and GI additionally set `status='confirm'` (`vg.php:2064`, `gi.php:4297`).
- **`specialEvents.Confirmed`** — the *event* has been rolled up (`special.php:2455`). It drives the Statistician's "Confirm an Event" picker, which lists only `Confirmed=0` events whose `enddate` is past (`special.php:2265-2266`).

### 3.2 `Attended` — not a live column

Exhaustive grep of the live tree for `Attended` returns **three** hits:

- `public_html/res/vg/vg.php:2055` — an `INSERT INTO … specialSchedule (…, Attended, Count) VALUES(…, 1, 2)` sitting inside a block comment opened at `vg.php:2036` (`/* REMOVE UP TO`) and closed at `:2060` (`// HERE AFTER NOVEMBER CONFIRM */`).
- `public_html/res/services/aim_changemeeting.php:195` and `public_html/res/famis/aim_changepresentation.php:110` — a `<th>Attended</th>` table header on the **meeting** attendance screens. Different feature, different table.

The 2012 mysqldump (`public_html/resboot/res/vg/vgSQL.txt`) does not contain it either; the only other occurrence anywhere is the identical commented block in `resboot/res/vg/vg.php:1913`.

**`Attended` is a column on `specialSchedule` that no live code writes and no live code reads.** Whether it physically exists in the database, and whether it holds data from before the block was commented out, is **not answerable from the code — needs one query** (§9 item 1). It carries the same flag in the prior schema research (`legacy-scheduling-schema.md` §8.1 item 8).

### 3.3 `Visitors` on the dated rows — nine Groups, three different write moments

The name does not drift; what drifts is which table carries it, who types it, and whether it is an *actual* or a *forecast*. The org-level reader hardcodes the column name and builds the table name from data — `interactions.php:84-85`:

```php
$column='Visitors';
$commtable="$committee"."$table";   // Committee.symbol + Committee.visitorTable
```

| Group | Table | Written by | When |
|---|---|---|---|
| Visitor Guides | `vgscheduledTours` | `_remoteSignIn` `vg.php:315` | member, at sign-out |
| Visitor Wayfinders | `specialSchedule` | `_remoteSignIn` `special.php:284`; Statistician Confirm dialog `special.php:2394-2402` | member at sign-out, or Statistician after |
| Docents | `docentscheduledTours` | sign-out dialog `docent.php:536`; remote `:558`; Statistician row edit `:2372`; **bulk grid** `:3526` | member at sign-out, Statistician after |
| GDR | `gdrscheduledTours` (+ 5 provenance subtotals) | sign-out `gdr.php:491-492`; Statistician row edit `:3030-3031`; bulk grid `:3049` (**commented out**, `:3040-3059`) | member at sign-out, Statistician after |
| Gallery Interpreters | `gischeduledTours` | `_remoteSignIn` `gi.php:500`; Statistician row edit `:4256` | member at sign-out, Statistician after |
| **Reception** | — | — | **absent — `Committee.visitorTable` is `NULL`; no such column in `reception.php`** |
| ROMBus | `rombusTrip` | Statistician bulk grid `rombus.php:1498`; guest-manifest upload `aim_addguestfile.php:66` (row count of the uploaded file) | Statistician after, or file upload before |
| Outreach | `outreachgroupTours` | booking form `outreach.php:2514` | **Scheduler, before the event — a forecast.** The confirmation email reads *"We are expecting an audience of \$Visitors"* (`outreach.php:2610`). No post-event path: `save_statistics_visitors` is commented out in the Outreach bundle (`outreach_data_26-06-12.js:107`) |
| Walker | **two columns** — `walkerscheduledTours.Visitors` and `walkerSchtourToWalker.Visitors` | coordinator/member dialog `walker.php:1277-1279` writes **only the join-table one**, per member per walk | after the walk |
| ROM Travel | `romtravelscheduledTours` | **nothing writes it.** Read only inside the commented block, `romtravel.php:338`, `:368` | — |

**Read by exactly two org-level reports, plus each Group's own screens.** `public_html/res/services/interactions.php` (Summary Visitor Interactions) and `public_html/res/services/detailedactivity.php` (Detailed Committee Statistics), both linked from the DMV statistics menu at `public_html/res/Members/Members.php:3514-3516`. The `a_interactions.php` / `a_detailedactivity.php` siblings are older (Feb–Mar 2024 vs Feb 2025) and linked from nothing.

Three assembly rules inside the one report (`interactions.php:100-120`):

- most Groups: one sum over the dated table;
- Docents and GDR: **plus** a second sum over `{symbol}groupTours.Visitors` (`:107-115`) — the *booked* group size, a pre-event number;
- Walker: `walkerscheduledTours.Visitors` **plus** `walkerSchtourToWalker.Visitors` (`:116-120`), with no guard against counting the same walk twice.

**`Visitors` never contributes to credit.** No credit query in any Group references it (§4.4).

---

## 4. The credit path

### 4.1 The helper

```php
function _saveScheduledMemberActivity($committee,$uid,$yyyymm,$total,$memid=null) {
    if($memid==null) { $memid = __getMemberidFromUid($committee, $uid); if($memid==null) return; }
    if($id = mysql_select_field("Select ID from …MemberActivity WHERE committee='$committee'
              AND subCommitteeID=0 AND YYYYMM='$yyyymm' AND MemberID=$memid AND MeetingID=0","ID")) {
        mysql_command("Update …MemberActivity SET Total_Scheduled=$total WHERE ID=$id");
    } else {
        mysql_command("INSERT INTO …MemberActivity (ID,committee,YYYYMM,MemberID,Total_Scheduled)
                       VALUES(NULL,'$committee','$yyyymm',$memid,$total)");
    }
}
```

`public_html/res/services/servicesp.php:9296-9305`.

Three facts fall straight out:

- **It replaces, never accumulates.** `SET Total_Scheduled=$total`.
- **`subCommitteeID` is always 0** — hardcoded in the `WHERE`, omitted from the `INSERT` so it takes the column default of 0. Confirms ADR-0022 finding 1.
- **It writes only `Total_Scheduled`.** Not `Extra_Hours`; not `Total_Hours` (a database trigger, per ADR-0022 finding 2 — no PHP anywhere writes it); not `Visitors`; not `Interactions`.

A **second, divergent definition** exists at `public_html/res/services/memberhistoryactivity.php:47-56` — same body, minus the `if($memid==null) return;` guard, and `VALUES('', …)` instead of `VALUES(NULL, …)`. It is local to that one-off script (§4.3).

### 4.2 Every call site, exhaustively

Repo-wide grep, live tree only, `x`-prefixed files excluded:

| # | Call site | Group credited | Live? |
|---|---|---|---|
| 1 | `public_html/res/docent/docent.php:3570` | Docents — shifts | yes |
| 2 | `public_html/res/docent/docent.php:3603` | Docents — group tours | yes |
| 3 | `public_html/res/gdr/gdr.php:3087` | GDR — shifts | yes |
| 4 | `public_html/res/gdr/gdr.php:3119` | GDR — group tours | yes |
| 5 | `public_html/res/gi/gi.php:4293` | Gallery Interpreters | yes |
| 6 | `public_html/res/vg/vg.php:2034` | Visitor Guides | yes |
| 7 | `public_html/res/vg/vg.php:2082` | **`fop`, `ftc`, `fcc`, `bw`, `fes`, `fsa`** — six Friends Committees, from inside the VG month-close (array at `vg.php:2073`) | yes |
| 8 | `public_html/res/reception/reception.php:1580` | Reception | yes |
| 9 | `public_html/res/rombus/rombus.php:1529` | ROMBus | yes |
| 10 | `public_html/res/outreach/outreach.php:1816` | Outreach / ROM for You | yes |
| 11 | `public_html/res/walker/walker.php:2298` | Walker / ROMWalks, `2*$total` | yes |
| 12 | `public_html/res/services/memberhistoryactivity.php:74` | `special` — a capped historical backfill, `if($yyyymm>'201510') break;` | live, but historical only |
| — | `public_html/res/special/special.php:2452` | Visitor Wayfinders | **commented out** — Wayfinders writes `MemberActivity` inline instead, `special.php:2445-2451` |
| — | `public_html/res/services/memberhistoryactivity.php:37` | all nine, one-off backfill | **inside `/* */`**, delimiters at `:6` and `:46` |
| — | `public_html/res/gi/xgi.php:4277` | — | disabled by the `x` convention |

**Eleven live application call sites in nine files, plus one historical backfill.** They do **not** map one-to-one onto ten scheduling Groups:

- Docents and GDR each have **two**, not one.
- One call site (`vg.php:2082`) credits **six Friends Committees**, none of which is a scheduling Group and none of which has a screen of its own. Their hours depend on a Visitor Guides officer pressing Confirm.
- **Visitor Wayfinders has no call site.** Its line is commented out; it writes `MemberActivity` by hand and, uniquely, additively.
- **ROM Travel has no call site anywhere, in any tree** (§6).

### 4.3 `hoursper` — it is not a live mechanism

`hoursper` appears in exactly **two lines of the live tree**, both in `public_html/res/services/memberhistoryactivity.php`:

```php
 6  /*
 7      $commsyms = array("owls","docent","gdr","gi","outreach","vg","walker","rombus","reception");
 8          $hoursper = array(  1,      1,     1,    1,    1,        1,    2,        1,        1);
 9          $commids  = array("commID","DocentID","GDRID","MemberID","ORMemberID","VgID","WalkerID","commID","commID");
…
35              $total = $hoursper[$c] * ($row['Total_Scheduled']+$row['Total_Group']);
…
46  */
```

**Lines 6 and 46 are the block-comment delimiters.** The array, the loop that applies it, and its `_saveScheduledMemberActivity` call at `:37` are all inside. The file is itself a one-off backfill script — no function wrapper, `$_POST=$_GET` at line 2, `echo` progress output — that walks every `{symbol}History` table and back-fills `MemberActivity`. Only its tail (`:59-76`, a Wayfinders sweep capped at `201510`) is live top-level code.

`grep -rn "hoursper\|hours_per\|HoursPer\|multiplier"` over the whole legacy tree returns those two lines and nothing else in application code. It is **not** a database column, **not** a config key, and **not** referenced by any Group's month-close.

**The only live per-Group multiplier in legacy is a PHP literal**, `public_html/res/walker/walker.php:2298`:

```php
_saveScheduledMemberActivity('walker',$walkerid,$whereval,2*$total); //2 hours per walk
```

ADR-0022 finding 5 is correct; the ticket's framing of `hoursper` is not. See §7.3.

### 4.4 What actually ends up in `Total_Scheduled`, per Group

| Group | Summed expression | Source column's real meaning | Multiplier | Filters `Confirmed=1`? | Cite |
|---|---|---|---|---|---|
| Visitor Guides | `Sum(vgscheduledTours.Count)` aliased `TotalTours` | **hours** — dialog label "Hours of shift"; `ADDTIME(Time, Count*10000)` at `vg.php:706` | 1 | **yes** | `vg.php:2014-2034` |
| Friends ×6 (`fop` `ftc` `fcc` `bw` `fes` `fsa`) | `sum(specialSchedule.Count)` aliased `TotalTours` | **hours** | 1 | **no** — the sweep has no `Confirmed` clause | `vg.php:2075-2082` |
| Visitor Wayfinders | `Sum(specialSchedule.Count)` aliased `TotalTours` | **hours** — dialogs say "Shift Hours" / "Shift credit" | 1 | **yes** | `special.php:2437-2451` (inline; **accumulates**) |
| Docents — shifts | `Sum(docentscheduledTours.Count)`, then `+= Total_Group` | **tour count.** Never written by any code; the report column is headed *Tours* (`docent.php:5829`) | 1 | **yes** | `docent.php:3550-3570` |
| Docents — groups | `Sum(docentgroupToDocent.Shifts)`, then `+= Total_Scheduled` | **shifts** | 1 | n/a | `docent.php:3583-3603` |
| GDR — shifts | `Sum(gdrscheduledTours.Count)`, then `+= Total_Group` | **tour count.** Never written | 1 | **no** | `gdr.php:3067-3087` |
| GDR — groups | `Sum(gdrgroupToGDR.Shifts)`, then `+= Total_Scheduled` | **shifts** | 1 | n/a | `gdr.php:3099-3119` |
| Gallery Interpreters | `Sum(gischeduledTours.Count)` aliased `TotalTours` | **ambiguous** — used as hours for conflict math (`gi.php:3160`) and as a tour count for the rollup | 1 | **no** | `gi.php:4274-4293` |
| Reception | `Sum(receptionscheduledTours.Count)` aliased `TotalTours` | **hours** — `strtotime("+ $hours hours")` at `reception.php:558`; header "Hours" | 1 | **yes** | `reception.php:1561-1580` |
| ROMBus | `Sum(rombusToTrip.Count)` aliased `TotalHours` | intended as hours; **never written by any code** — depends on an invisible DB default | 1 | n/a (filters `Status='Active'`) | `rombus.php:1510-1529` |
| Outreach | `Sum(outreachgroupToORMember.Shifts)` | **shifts** | 1 | n/a | `outreach.php:1797-1816` |
| Walker | `Sum(walkerSchtourToWalker.Shifts)` — `Shifts` is 0 or 1 per walker per walk (`walker.php:1277-1279`) | **walks** | **× 2** | n/a | `walker.php:2279-2298` |
| ROM Travel | — | — | — | — | **no call site** |

**So `MemberActivity.Total_Scheduled` is a mixed unit.** Hours for VG, Wayfinders, Reception and the six Friends Committees; a tour count for Docents-shifts and GDR-shifts; shifts for Docents-groups, GDR-groups and Outreach; walks×2 for Walker; an unwritten column for ROMBus; ambiguous for GI. Every report renders the field as *hours*.

The aliases actively mislead — `docent.php:3550` selects `Sum(Count) AS TotalTours` and feeds it into a field reported as hours.

Nothing reconciles the units. The array written to do so never ran.

### 4.5 When it fires, and whether it can double-credit

**It fires only on a month-close button**, never on sign-up and never on sign-out. Taking VG, which is representative:

1. The Statistician or Chair opens *Statistics* and picks a month from **"Confirm Shifts for a Month"** (`vg.php:1865-1877`). The picker lists the last **15** months with `status IN ('final','confirm')` and `YYYYMM <= currentmonth`; already-confirmed months are labelled `C-` and **stay selectable**.
2. `get_statsSchedule` renders the month's rows and, **only if the month is not the current one** (`vg.php:1912`), a button captioned *"All the shift entries are Correct and the Month can be confirmed"*.
3. Pressing it calls `_confirm_statistics_scheduled` (`vg.php:2006-2068`): delete empty rows, sum `Count` over `Confirmed=1` rows, write `vgHistory`, call `_saveScheduledMemberActivity`, sweep six Friends Committees, set `vgSchedules.status='confirm'`.

Everything ADR-0022 says about the trigger holds. Adding to it:

- **There is no fiscal-year bound.** Fifteen months spans two fiscal years and every entry is selectable. Nothing in `get_statsSchedule` or `_confirm_statistics_scheduled` compares `$whereval` to a fiscal boundary.
- **Authorization is client-asserted.** The menu gate is `if($_POST['isChair']>0 || $_POST['isStatistician'] >0)` (`vg.php:1861`) — a request-body value. And the dispatcher's session check is commented out at `public_html/res/services/servicesp.php:628-637`, so the endpoint itself gates nothing. Compounding the irony, the sign-in page sets the exact session marker that check would have read: `$_SESSION['vid']=md5('dmv')`, `vg.php:149`.

**Can it fire twice for one shift?** Four distinct answers, and only one of them is "no harm":

1. **Re-running the same button on the same month is safe.** The helper replaces. Same inputs, same output.
2. **Wayfinders accumulates, but zeroes first.** `special.php:2446` is `SET Total_Scheduled = Total_Scheduled + $total`, which alone would double-credit — but `:2424` zeroes the whole month for `committee='special'` before the event loop, and `:2426` re-walks *every* event ending that month, commented `// INCLUDING PREVIOUSLY CONFIRMED SO WE CAPTURE TOTAL FOR MONTH`. Net: idempotent, deliberately.
3. **Docents and GDR write one row from two buttons, and the order decides the number.** Both `_confirm_statistics_scheduled` and `_confirm_statistics_groups` call the helper with the same `(committee, member, YYYYMM)` key. Each tries to compensate by adding the other half read back out of `*History` — `docent.php:3564` `$total += $row2['Total_Group']`, `:3597` `$total += $row2['Total_Scheduled']`. But that `+=` only happens **when a `*History` row already exists** (`:3560`, `:3593`). On the first close of a brand-new month, whichever button runs first writes its half alone; if the other then runs, it **clobbers** rather than sums. A lost-credit hazard, not a double-credit one, and invisible.
4. **Credit is never taken away.** Every month-close zeroes `*History` for the month (`vg.php:2020`, `docent.php:3555`, `reception.php:1566`, `rombus.php:1515`, `walker.php:2284`; GI's is commented out at `gi.php:4279`) — but **none of them zeroes `MemberActivity`**. The helper only touches rows returned by the `GROUP BY`. A member whose shifts were deleted, or whose `Confirmed` was reset, drops out of the result set and keeps their stale `Total_Scheduled` forever. Only Wayfinders escapes, via the explicit sweep at `special.php:2424`.

Two further destructive side effects of re-running a close:

- **Rows are deleted.** `vg.php:2012` deletes every row with `VgID=0 AND MemberID=0`; `reception.php:1559` deletes `commID=0`; `gi.php:4272` deletes `MemberID=0`; `special.php:2431` deletes `MemberID=0 OR VgID>0`. Re-running on a month whose Schedule has since been re-opened destroys the newly created empty slots.
- Re-running is nonetheless an **intended** workflow: `special.php:2430` records that the "delete unconfirmed rows" behaviour was withdrawn on purpose — *"No Longer do this — we just ignore when tallying up totals — This allows to REDO if Members complain."*

---

## 5. The sign-in surfaces

### 5.1 What exists

Seven directories at the web root: `signin/` (a hub), plus `vgsignin/`, `specialsignin/`, `docentsignin/`, `gdrsignin/`, `gisignin/`, `receptionsignin/`. Each per-Group one is a single `index.html` that loads `res/js/datasignin.js` plus a Group-specific `*_data_signin*.js` and calls `startSigninPage("<symbol>")` on load.

### 5.2 The hub is retired, in writing

`public_html/signin/index.html` — `<title>DMV - SIGN OUT</title>`, mtime 2023-06-01 — renders exactly one sentence:

> **This page is no longer in use. To sign out, please use your committee's schedule page in the main [DMV website](https://www.dmv-rom.ca)**

The six-icon committee picker below it is wrapped in an HTML comment (`<!--` at line 25, `-->` at line 79).

This matters beyond the hub, because **every per-Group kiosk page returns to it on idle**: `memberEndSigninPage()` is `window.location.href="../signin/"` (`res/vg/vg_data_signin_23-04-13.js:76-80`, and identically in the special, reception, gi and docent bundles). Any kiosk left alone for a few minutes lands on "no longer in use".

### 5.3 The design is unmistakably a shared device at a desk

From `res/vg/vg_data_signin_23-04-13.js` and the six `index.html` files:

- **No account.** "Log in Name" is a `<select>` of every active member of the Group, fetched by `getMemberSelect` (`vg.php:89-95` → `_getActiveVgSelect`). The password is `<input size=6 pattern="[0-9]*">`, and `_memberLogin` accepts it if it equals **the last four digits of the member's `Museum_ID`** (`vg.php:119-121`). A badge number, against a public dropdown.
- **Aggressive idle reset.** A `setInterval` fires `memberEndSigninPage()` after **3 minutes** (GI `gi_data_signin_18_12_27.js:5`; Reception `reception_data_signin.js:13`; Wayfinders `special_data_signin.js:50`) or **5 minutes** (VG `vg_data_signin_23-04-13.js:49`; Docents `docent_data_signin_23-05-10.js:13`) at the picker, and **10 minutes** once a member is logged in — *"if no action after 10 minutes by signed in member, Reset Page"* (`vg_data_signin_23-04-13.js:119`).
- **A "Reset Page" button in the banner**, wired to `memberEndSigninPage()` rather than to a logout.
- **iOS home-screen web-app metadata on all seven pages**: `<link rel="apple-touch-icon">`, `<meta name="apple-mobile-web-app-capable" content="yes">`, `<meta name="viewport" … user-scalable=no>`.
- **The list is everyone's, not yours.** `_showSignInSchedule` (`vg.php:176-272`) lists **all** of today's shifts for the whole Group, with member names, and renders a *Sign Up* button on empty slots and a *Replace* button on someone else's (`:237`, `:242`). Only the row matching the logged-in member gets the visitor box and *Sign Out* (`:245-255`).
- **The heading is a bench instruction**: *"Sign-in, Sign-up for an Empty shift or Replace another member"* (`vg_data_signin_23-04-13.js:116`).

### 5.4 It was IP-locked to the museum, and that lock is disabled

Six Group files carry the same block. Taking VG (`vg.php:150-165`):

```php
150   $ipok = $memid==1246 || $memid==58 || $memid==2418 || $memid=2461; // me,gary,patty Y,john G
151 $ipok = TRUE;
152   if($ipok == FALSE) { … look up dmv_giNetworks WHERE ipRoot='$ipstart' AND Active=1 … }
165   $resp['ipok'] = $ipok;
```

and the client refuses the page on `ipok==false` with *"You may only use this page while in the DMV. ip=…"* (`vg_data_signin_23-04-13.js:102-105`).

**Line 151 makes the gate unreachable**, and the same unconditional `$ipok = TRUE;` appears in `gi.php:250`, `special.php:116`, `reception.php:150`, `docent.php:387`, `gdr.php:316`. Line 150 also contains a real bug — `$memid=2461` is an assignment, so the expression is always truthy anyway.

Three layers of evidence that this began as an on-premises kiosk: the network gate, the commented-out `207.164.192` / `207.75.204` / `207.211.94` ranges beside it (`vg.php:163`), and the `dmv_giNetworks(ipRoot, Active)` table it consults (`vg.php:160`, `gi.php:260`, `special.php:126`, `reception.php:160`, `docent.php:397`, `gdr.php:326`).

### 5.5 The strongest single sentence in the codebase

`public_html/res/vg/vg.php:2070`, immediately above `_saveSpecialMemberActivity`:

```php
//***  WITH SIGN IN IPAD, EVENTS RESOURCE does its own confirmation but we still do Friends Events as they are assumed correct ***//
```

An **iPad**, used by Visitor Wayfinders ("Events Resource" is their former name), which changed who confirmed what.

### 5.6 Where sign-out happens now

The same `remoteSignIn` call is wired to a second, non-kiosk surface: the member's own **My Schedule** on the main site (`vg.php:530-545`, `special.php:473-495`, `gi.php:2386-2410`, `docent.php:1783-1805`, `gdr.php:1651-1670`, `reception.php:469-490`). For a shift belonging to the logged-in member that has already started, it renders:

```php
$visitors = "<input id='visitorNumber$id' … placeholder='Enter # Visitors' />";
$onclick  = "remoteSignIn($id)";
$confButton = $visitors."  "._getButton("Sign Out",$onclick,null,"visitorSignin$id",null,null,true);
```

— the Sign Out button **disabled until a visitor number is typed** (`checkVisitorNumber`, `vg_data_signin_23-04-13.js:192-200`), and offered from `55 * Count` minutes after the start time, i.e. five minutes before the nominal end (`vg.php:533`). Unsigned-out shifts stay on the list for **28 days** (`vg.php:511`).

The current member-facing procedure documents agree. `public_html/res/gi/docs/infopkg-255-GI DMV Website Signing In and Out 2024 10 20.pdf` — the most recent of five revisions in the tree — tells volunteers to go to `http://www.dmv-rom.ca/`, from *"at home, at the DMV office, at a library, at an internet café, on a laptop, a tablet or a smartphone"*, and to sign in with **email and first name**. It describes no kiosk and no numeric PIN.

### 5.7 What the code answers, and what it does not

**Answered.** The surfaces were built as a shared, museum-network device (almost certainly an iPad) at a Group's desk, with a member-list dropdown and a 4-digit badge PIN, auto-resetting in 3–5 minutes. The network gate has since been disabled in all six Groups. The hub has been retired in writing and points members at the main site. The identical write path is live on the member's own device via the main site's My Schedule, and the 2024 procedure documents describe only that.

**Not answered — needs a person.** Whether any physical device is still in service today, at which desks, and whether volunteers reach the surviving `*signin` pages by bookmark. The code cannot distinguish "retired" from "retired but still bookmarked".

One corroborating signal, weak on its own: `public_html/gdrsignin/index.html` references `../res/gdr/gdr_data_signin_23-05-10.js`, and the only file present is `res/gdr/gdr_data_signin_19-01-18.js`. In this snapshot `startSigninPage` is undefined and the GDR kiosk page throws on load. Whether the file exists on the production host is a data question, not a code one.

The app does record the device: `addUserLog` (`servicesp.php:1167-1183`) writes a `dmv_log` row per login with `UserAgent`, `BrowserName`, `PlatformFamily`, `isMobile` and `ipAddress`. **One query would settle this mechanically** — see §9 item 5.

---

## 6. ROM Travel — confirmed, and it goes further than the ticket says

**Confirmed: there is no `_saveScheduledMemberActivity` call for ROM Travel anywhere in the legacy repo**, in any tree, live or stale. A repo-wide grep of `public_html/**/*.php` returns 100+ hits across `res/`, `res4test/`, `resboot/`, `resphone/`, `resdocumentation/` and `new/`; none is in a `romtravel` file and none passes `'romtravel'` as the committee. [#351](https://github.com/roytanaka/dmv-rom-v2/issues/351) §B1 is correct.

The absence is broader than credit:

| Capability | ROM Travel | Cite |
|---|---|---|
| `Confirmed` column | **none** — 0 occurrences in `romtravel.php` | — |
| Sign-in / sign-out surface | **none** — no `romtravelsignin/` directory, no `romtravel_data_signin*.js` | `find public_html -iname "*signin*"` |
| Schedule menu item | **none** — `romtravelLogin` emits Publications, Meetings, Secretary, Statistics/Extra Hours only | `servicesp.php:1727-1734`, against `rombusLogin` at `:1760`, `:1766` |
| Schedule / Scheduler functions | **the sections are empty** — `/* START Schedule */` at `:212` is followed immediately by `/* END Schedule */` at `:214` and `/* START Scheduler */` at `:216`, with nothing between | `romtravel.php:212-216` |
| Row in the cross-Group "Scheduled Activities" panel | **no branch** — the `if`/`elseif` chain covers docent, gdr, gi, reception, vg, walker, special, rombus, outreach | `servicesp.php:2787-2937` |
| Statistician menu | Extra Hours + Meeting Hours reports only | `romtravel.php:264-275` |

**And the reports the ticket assumes exist are also commented out.** `romtravel.php` is 558 lines; **lines 292 to 557 are a single `/* … */` block** containing `__romtravelPrintTourSummary`, `__romtravelPrintStatistics` and their header helpers. A second, smaller block at `:276-282` comments out the `_romtravelPrintTourSummary` entry point.

So the tables those functions read — `romtravelscheduledTours` (`.Count`, `.Visitors`, `.TourID`, `.YYYYMM`), `romtravelgroupTours` (`.RomtravelShifts`, `.Visitors`, `.Earned`), `romtravelHistory` (`.Total_Scheduled`, `.Total_Group`, `.Total_Meeting`), `romtravelCategory`, `romtravelsection`, `romtraveltour` — are **named only inside dead code**.

**What ROM Travel reports from instead.** The two live buttons on its Statistician screen (`romtravel.php:267-268`) are `PrintExtraHoursReport` and `PrintMeetingsReport`, both org-shared helpers in `servicesp.php` that read `dmv_MemberActivity` — `Extra_Hours`, and `Extra_Hours` on rows carrying a non-zero `MeetingID`. ROM Travel's members therefore have hours in the system — self-entered extras and meeting attendance — and **no scheduled hours at all**. That is consistent with ADR-0022's dump reading, which counts 1,558 sub-committee rows under ROM Travel carrying only `Extra_Hours`.

`Committee.HasSchedule` for `romtravel` is **not answerable from the code — needs one query** (§9 item 3). If it is `1`, the cross-Group panel at `servicesp.php:2784` enters the `if` and then falls through every branch, silently contributing nothing.

**The migration consequence, stated plainly.** Any importer that assumes "ten scheduling Groups ⇒ ten sets of credit history" finds a hole at ROM Travel, and a second, differently shaped hole at Visitor Wayfinders, whose credit is written by hand rather than by the helper. Nine Groups ever write `Total_Scheduled` through the helper; six of those writes are for Friends Committees that are not scheduling Groups at all.

---

## 7. Where ADR-0022 and this research disagree

ADR-0022 ([#406](https://github.com/roytanaka/dmv-rom-v2/issues/406); PR #396, **open** at the time of writing — it does not yet live on `main`) reads the same legacy code plus a 2026-06-29 production dump. Three places need correcting or sharpening.

A fourth item — *when recalculation fires* — is **not** a disagreement. ADR-0022 is right that it is a manual per-Group action, always at `subCommitteeID=0`, and that legacy does not bound it to a fiscal year. §4.5 above verifies all three and adds the four ways it can still go wrong.

### 7.1 The call-site count does not equal the Group count

**ADR-0022 finding 1** says `Total_Scheduled` is written *"by each Group's own recalculate this month action on its scheduling screen (`docent.php:3570`, `vg.php:2034`, `gdr.php:3087`, and seven more)"*.

The three named lines are correct. "And seven more" is close on arithmetic — there are eleven live call sites, so eight more — but the implied one-Group-per-call-site mapping is wrong in four ways, enumerated in §4.2:

- Docents and GDR each have **two** call sites, not one.
- `vg.php:2082` credits **six Friends Committees**, none of which is a scheduling Group and none of which has a screen of its own.
- **Visitor Wayfinders has no call site.** Its line is commented out (`special.php:2452`); it writes `MemberActivity` inline and, uniquely, additively.
- **ROM Travel has no call site anywhere**, in any tree.

Not a disagreement about the mechanism — a disagreement about coverage. The importer needs the coverage.

### 7.2 Three different "visitor" columns, and ADR-0022 ruled out the wrong one for #398's purposes

This is the item with the most consequence, so it is set out in full.

| | `dmv_MemberActivity.Visitors` | `dmv_MemberActivity.Interactions` | `Visitors` on the scheduling dated rows |
|---|---|---|---|
| **Table** | the hours table — one row per (member, Group, month) | the same row | a different table per Group: `vgscheduledTours`, `specialSchedule`, `docentscheduledTours`, `gdrscheduledTours`, `gischeduledTours`, `rombusTrip`, `outreachgroupTours`, `walkerscheduledTours` + `walkerSchtourToWalker`, `romtravelscheduledTours` |
| **Declared as** | `int(11) NOT NULL DEFAULT 0 COMMENT 'Click Count'` | `int(11) NOT NULL DEFAULT 0` | one column per Group; the table named by `Committee.visitorTable` |
| **Written by** | **nothing.** Zero references in any live PHP | the *Interactions* dialog, `servicesp.php:9690-9800` — member self-entry, current and previous month | sign-out (`vg.php:315`, `gi.php:500`, `special.php:284`, `docent.php:536`/`:558`, `gdr.php:491`); Statistician bulk grids (`docent.php:3526`, `rombus.php:1498`); Walker's post-walk dialog (`walker.php:1279`); Outreach's booking form as a **forecast** (`outreach.php:2514`) |
| **Read by** | **nothing** | `interactions.php:122-123` (added on top of every Group's `Visitors` sum) and `:154`, `:166` (the **only** source for a Group with no `visitorTable`); `detailedactivity.php` | `interactions.php:87-120`, `detailedactivity.php:179-213`, and each Group's own statistics screen |
| **Populated?** | unknown; nothing could have populated it | ADR-0022: **zero on all 74,248 rows** | **live daily practice** — confirmed 2026-08-07 per #351 §B1 |

Two conclusions.

**(a) `MemberActivity.Visitors` is a fourth kind of dead — dead by never having been wired at all.** A grep for lines mentioning both `MemberActivity` and `Visitors` across the live tree returns **nothing**. Every `Visitors` reference under `res/services/` is against a scheduling table or the Walker join table. ADR-0022 quotes the column in its DDL block without remark; it should be named as never-written, never-read, and its `COMMENT 'Click Count'` suggests it was for something else entirely. **Do not import it.**

**(b) ADR-0022 finding 4 is correct about `MemberActivity.Interactions`, and does not speak to the per-shift `Visitors` column at all.** These are different tables. Ruling out the first does not rule out the second, and #398's *"per-shift visitor-interaction count … a field volunteers fill in today on every visitor-facing Group"* is the second.

Worth recording alongside (b), because it explains the all-zero column rather than leaving it a mystery: **the `Interactions` write path is broken**, and in a way that would defeat even a determined user. `_MemberInteractionsUpdate` builds its `$where` at `servicesp.php:9769` as

```php
$where = "committee='…' AND subCommitteeID=… AND MemberID=… AND MeetingID=0 and Interactions>0";
```

with `and Interactions>0` **inside the row-matching clause**. Consequences:

- the UPDATE branch (`:9784`) can only ever fire against a row that already has `Interactions>0`, which nothing else in the codebase creates;
- so the `elseif` INSERT (`:9790`) always fires — and it inserts a **new** `MemberActivity` row rather than updating the member's existing month row, duplicating the (committee, subCommitteeID, YYYYMM, MemberID) grain;
- `__getInteractionsLine` (`:9751`) carries the same `Interactions>0` filter, so the "Interactions as of" cell renders `-` even after a successful save — the dialog looks broken to the person using it;
- and `:9793` reads last month's running total from **`Extra_Hours`** instead of `Interactions`, a copy-paste from the sibling Extra Hours handler.

A form that always reports back "nothing on file" is one nobody uses twice. The all-zero column is consistent with that. ADR-0022's decision to leave it out stands — on better evidence than a row count.

### 7.3 `hoursper` is not a mechanism, and the ticket's framing should be retired with it

**ADR-0022 finding 5** — Walker's ×2 is a PHP literal at `walker.php:2298`, every other Group multiplies by one — is **correct and complete**.

The ticket (#399 Q3, #398, #351 §B1) describes `hoursper` as *"a hardcoded per-Group multiplier"* reconciling `Count`'s two meanings. §4.3 shows it is a PHP array **inside a `/* */` block** in a one-off backfill script, with the loop that applies it commented out alongside. It is not a column, not a config key, and not reachable from any Group's month-close. Nothing in production has ever applied it, and the unit reconciliation it was written to perform **does not happen anywhere** (§4.4).

The nine-element array is still useful as *documentation* — it is the only place anyone wrote down that Walker's unit differs and the others do not — but it must not be described as a live mechanism, and an importer must not look for it. ADR-0022's `hours_multiplier` column (ADR §7) is the right shape; note only that the number it must reproduce is the literal at `walker.php:2298`, and that ADR-0022's migration note is right that **the ×2 is already baked into the stored legacy values**.

---

## 8. Everything else recorded after a shift

Ordered by how much a rebuild would miss it.

### 8.1 Walker's post-walk record — the richest, and split between two people

`_walkerTourStatistics` (`walker.php:1271-1287`) writes three things from one dialog:

```php
UPDATE walkerSchtourToWalker  SET Visitors=$visitors, Shifts=1  WHERE ScheduledTourID=… AND WalkerID=…
UPDATE walkerscheduledTours   SET Comments='$comments', Earned=$earned  WHERE ID=…
```

- **`Shifts` is the credit unit**, 0 or 1 per walker per walk, and it is set to **0** when the visitor field is blank or `N/A` (`:1277`). That is the closest thing legacy has to a did-not-happen marker, and it is a side effect of leaving a box empty.
- **`Comments`** — a free-text `<textarea>` (`walker.php:1245`); the only free-text after-shift note in any Group.
- **`Earned`** — "Monies Collected", shown only for `TourTypeID` 2 or 4 (Plus / Group) (`walker.php:1249-1251`).

`Comments` and `Earned` are gated on `$coordinator==1` (`walker.php:1261`); `Visitors` is not. So a walker records their own head count, and the coordinator records the note and the money.

### 8.2 Substitution — an email, and no record

Every Group with a sign-in surface has a *Replace* path: `_memberReplaceShift` (`vg.php:302-310`, `special.php:269-278`, `reception.php:277-286`) and `_memberReplaceTour` (`docent.php:542-552`, `gdr.php:502-512`). Each one:

1. reads the row to find the member being replaced;
2. emails them — *"This is to confirm that I have filled in for you on … at …"*, cc'd to the sender;
3. **overwrites `MemberID` / `VgID` / `commID` on the row** with the substitute, and sets `Confirmed=0` (`vg.php:309` passes `$confirm=0`).

**Nothing records that a substitution happened.** The original member is gone from the row; the only artefact is an email neither party's record retains. A shift covered by a substitute is indistinguishable from one the substitute always held. Withdrawal has the same shape — `vg.php:935` sets `VgID`, `MemberID` and `Confirmed=0` in one statement.

This is the second constituency for [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334). `dmv_MemberActivity` has a `lastUpdate` timestamp (read at `servicesp.php:9639`) and no author column; the dated scheduling rows have neither.

### 8.3 The Statistician's bulk edit screens — after the fact, by someone else

Three shapes, all `POST`-key-scanning loops over inputs named `schid{ID}`:

| Group | Edits | Cite |
|---|---|---|
| VG, Reception | **`Count`** — the credited hours themselves | `vg.php:1989-2004`, `reception.php:1537-1554` |
| Docents, ROMBus | **`Visitors`** | `docent.php:3518-3535`, `rombus.php:1490-1504` |
| GDR | `Visitors` — **commented out** (`gdr.php:3040-3059`), though the client function `gdrsave_statistics_visitors` still ships (`gdr_data_26-01-19.js:525`) | — |

Plus per-row dialogs that rewrite a completed shift wholesale: `_gichangestatsschedule` (`gi.php:4245-4262` — sets member, tour, object, `Count`, `Visitors` and `Confirmed=1` in one statement), `docentChangeStatsSchedule` (`docent.php:2372`), `gdr.php:3030-3035`.

So a Group officer can restate a member's hours and visitor count for a past month, with no trace and no authorship. The VG grid renders `Count` as an editable text box on **every** row of the month (`vg.php:1958`).

### 8.4 `Interactions` on the dated rows — written, never read

Distinct both from `MemberActivity.Interactions` (§7.2) and from `Visitors`. Two Groups have it on the shift row:

- `docentscheduledTours.Interactions` — the sign-out dialog labels it *"Visitor interactions excluding tour"* (`docent.php:518`); written at `:536` and `:559`;
- `gdrscheduledTours.Interactions` — written at `gdr.php:492`, `:3033`.

**Neither is read by anything.** Two Groups' volunteers have been typing a second number at sign-out, for years, into a column no report touches.

### 8.5 GDR's visitor provenance

`gdrscheduledTours.Visitorsfr / Visitorspq / Visitorsto / Visitorsroc / Visitorsoth` — five subtotals written alongside `Visitors` at sign-out (`gdr.php:492`) and at the Statistician row edit (`:3031`). GDR only. Read at `gdr.php:5298-5305` for the visitors-per-tour report.

### 8.6 Self-entered Extra Hours and meeting attendance

Not per-shift, but they land in the same `MemberActivity` row and are recorded after the fact:

- **Extra Hours** — `_MemberActivityUpdate` (`servicesp.php:9653-9687`): additive (`$hours1 = $hours1 + $hourstodate`), floored at zero (`max($hours1,0)`), **current and previous month only**, whole numbers only, and a blank entry writes nothing (*"DO NOT CREATE AN EMPTY record, too many people just visit and hit OK"*, `:9642`).
- **A Chair's bulk screen for other members' extra hours** — `get_EnterMembersActivity` / `_SaveMembersActivity` (`servicesp.php:9836-9937`), which **replaces** rather than adds.
- **Meeting attendance** — `_SaveMeetingActivity` (`:9938-9962`) writes the meeting length into `Extra_Hours` on rows carrying a non-zero `MeetingID`. The `<th>Attended</th>` headers at `aim_changemeeting.php:195` and `aim_changepresentation.php:110` belong to this, not to shifts.

### 8.7 What is conspicuously absent

- **No no-show marker.** `grep -riE "no.?show|absent|didnotattend|did not attend"` over the live tree returns nothing. `Confirmed=0` on a past shift conflates *forgot to sign out*, *withdrew*, and *did not turn up*. The 28-day outstanding window (`vg.php:511`) then quietly drops it.
- **No cancellation state on Outreach** — `outreachgroupTours` has no status column at all; deletion is hard.
- **No free-text note on any shift except Walker's.** `*Schedules.notes` exists and is Scheduler-authored for the whole month (`vgUpdateScheduleNote`, `vg_data_signin_23-04-13.js:278-281`); `outreachSchedules.notes` is selected at `outreach.php:1354` and never rendered.
- **No authorship on anything.** One `lastUpdate` timestamp on `MemberActivity`; nothing on the dated rows.
- **`dmv_log`** (`servicesp.php:1167-1183`) records logins per committee with browser, platform, `isMobile` and IP — the closest thing to an audit trail, and it records reaching a page, not changing a row.

One read-only surface worth knowing about: `public_html/today/index.html` calls `showToday` with **no login at all** and renders today's activities across the department. It records nothing.

---

## 9. Flagged unknowns

Not answerable from the code. Each of the first five is one query.

1. **Does `specialSchedule.Attended` physically exist, and does it hold data?** `SELECT COUNT(*), SUM(Attended) FROM dmv_specialSchedule`. Referenced only by commented-out code (§3.2).
2. **Is `MemberActivity.Visitors` non-zero anywhere?** `SELECT COUNT(*) FROM dmv_MemberActivity WHERE Visitors <> 0`. Nothing in the codebase could have written it (§7.2a).
3. **`Committee.HasSchedule` and `visitorTable` for every active symbol.** `SELECT symbol, HasSchedule, visitorTable FROM dmv_Committee WHERE Active=1 ORDER BY DisplayOrder`. Settles whether ROM Travel is flagged as scheduling despite having no scheduling code (§6), and fixes the visitor-facing list (§3.3).
4. **The DB defaults for `docentscheduledTours.Count`, `gdrscheduledTours.Count` and `rombusToTrip.Count`** — never written by any code, yet every statistic for those Groups depends on them (§4.4).
5. **Which devices reach the sign-in surfaces.** `SELECT PlatformFamily, isMobile, ipAddress, COUNT(*) FROM dmv_log WHERE committee IN ('vg','special','docent','gdr','gi','reception') AND ID > … GROUP BY 1,2,3`. Settles §5.7 mechanically.
6. **Whether any `*signin` page is still opened at a desk, and by whom.** **Not answerable from the code — needs a person.** The hub says retired, the network gate is disabled, and the 2024 procedure document describes the main website only; none of that proves a bookmarked iPad is not in daily use.
7. **Whether `famis` is meant to be in the Friends credit sweep.** It holds events via `specialEvents.committee='famis'` (`special.php:1294`) but is absent from the hardcoded array at `vg.php:2073`, and no compensating write path exists. Gap or deliberate exclusion — **not answerable from the code**.
8. **Why VG and Reception let the Statistician edit `Count` after the fact while Docents, GDR and ROMBus let them edit `Visitors`.** The split is consistent within each Group and unexplained. **Needs a person.**
9. **Whether `gdrsignin` is broken in production**, or only in this snapshot (§5.7).

---

## 10. Cross-references

- [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398) — the after-shift map this answers to
- [#351](https://github.com/roytanaka/dmv-rom-v2/issues/351) §B1 — the three credit facts carried out of the scheduling map; §7 confirms one, sharpens one, and retires one
- [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) / ADR-0022 (PR #396, open) — the hours model; §7 records where this research disagrees
- [#334](https://github.com/roytanaka/dmv-rom-v2/issues/334) — authorship; §8.2 is its second constituency
- `docs/research/legacy-scheduling-schema.md` on branch `research/legacy-scheduling-schema` — the shape inventory this builds on. Its §7.11 sketched the credit path; §4 above supersedes it on `hoursper` and on the call-site enumeration.
