# What the DMV reports from scheduling data, and who consumes it

Research findings for [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400), a child of the scheduling second-pass Wayfinder map [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398).

**Scope is what the code does, and who the code says can reach it.** Where the reason a number matters lives in a person's head rather than in a call site, it is marked **not answerable from the code — needs a person** rather than guessed at.

**Not in scope:** designing the replacement, reading production data, planning the migration.

---

## 1. Method, sources, and caveats

### Sources

- **Legacy app**, read-only, at `/Users/roytanaka/Documents/ROM/DMV/dmv-rom-legacy`. All `public_html/…` citations below are relative to that repo root.
- **[ADR-0022](https://github.com/roytanaka/dmv-rom-v2/blob/worktree-hours-model-decisions/docs/adr/0022-hours-and-statistics-model.md)** — _Hours and statistics: a faithful port of the legacy monthly bucket_, accepted 2026-08-23. **It lives on branch `worktree-hours-model-decisions` (PR [#396](https://github.com/roytanaka/dmv-rom-v2/pull/396)), not on `main`** — the link in spec [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) points at `main` and is wrong at the time of writing.
- **`docs/research/legacy-scheduling-schema.md`** on branch `research/legacy-scheduling-schema` — the schema inventory this builds on. Cited as _schema research §N_.
- **Archaeology notes (private repo)** — the 2026-05 feature inventory and the 2026-08-03 programs/sub-group menu study. Cited by description only; that repo is private and is never linked from here.

### The same caveats as the schema research still apply

There is **no DDL for the scheduling tables** in the legacy repo, so column purposes are derived from call sites. `mysql_select()` returns `FALSE` on zero rows (`public_html/res/services/functions.php:32-41`), so `if(mysql_select(…))` existence tests work correctly. `public_html/res/` is the live tree; `res4test/`, `resboot/`, `resphone/`, `resdocumentation/`, `demo/`, `new/` are stale copies and are excluded. An `x` prefix on a filename is the disable convention.

One DDL exception is new since the schema research: **ADR-0022 quotes the real `dmv_MemberActivity` DDL** from the 2026-06-29 production dump, including two triggers. Where this document needs that table's shape, ADR-0022 is the source, not inference.

### Privacy

No member names, addresses, phone numbers, or personal email addresses appear below. Where a hardcoded recipient matters, it is described by role and domain. Note for anyone repeating this work: the `docs/` subdirectory under each Group in the legacy repo contains **real member data**. Stay out of them.

---

## 2. The reports that exist

Two rollups feed everything (schema research §7.11): **`*History`** (per Group, per member, per month — counts _things done_) and **`MemberActivity`** (org-wide, per committee, per member, per month — counts _hours_). Every report below reads one or the other, or reads the dated scheduling rows directly.

### 2.1 DMV-wide reports — the `Members`/DMV committee

Reached from the DMV committee's Statistics menu → _Committee Reports_ → _For Fiscal year ending March 31, YYYY_ (`public_html/res/Members/Members.php:3227-3235`), which routes the `R`-prefixed value to `get_statsReport()` (`:3254`, body at `:3505`).

| Report | What it shows | Reads | Cite |
| --- | --- | --- | --- |
| **Summary Committee Statistics** | Fiscal-year grid: rows = each `Active`, `HasSchedule=1` committee; columns = Apr→Mar + Total. Body = scheduled hours per Group. Footer rows: Scheduled Hours Total, Meeting Hours Total, Extra Hours Total, Total Hours. Current month is excluded — data stops at "first day of last month" | `Committee` (`symbol`, `Active`, `HasSchedule`, `DisplayOrder`), `MemberActivity.Total_Scheduled` / `.Extra_Hours` split by `MeetingID=0` vs `<>0` | `public_html/res/services/activity.php:12`, `:32-39`, `:48-76`, `:86`, `:94-95`, `:106-107` |
| **Detailed Committee Statistics** | Same grid, but each Group broken into four rows: _shifts_, _Meetings_, _Extra Hours_, _Visitor Interactions_. Five footer totals | as above, plus `{symbol}{visitorTable}.Visitors`, `docentgroupTours`/`gdrgroupTours.Visitors`, `walkerSchtourToWalker.Visitors`, `MemberActivity.Interactions` | `public_html/res/services/detailedactivity.php:44`, `:52-70`, `:79-119`, `:137-150`, `:182-183`, `:202-213`, `:216-217` |
| **Summary Visitor Interactions** | Fiscal-year grid of visitor-interaction totals by Group | `Committee.visitorTable`; `{symbol}{visitorTable}.Visitors`; the docent/GDR `groupTours` and Walker join-table special cases; `MemberActivity.Interactions` added on top for every Group and as the **only** source where `visitorTable` is `NULL` | `public_html/res/services/interactions.php:32-51`, `:75-123`, `:154-155`, `:166-167` |
| **Active Members Ranked Hours** | Every `Active`/`Prov` member ranked by fiscal-year total hours, with a "no activity this fiscal year" section appended | `MemberActivity.Total_Hours` | `public_html/res/Members/Members.php:3258-3325` |
| **Members with Zero Hours / Zero Shift Hours / Zero Extra Hours** | Three variants of the same list — `Total_Hours`, `Total_Scheduled`, `Extra_Hours` respectively — each with member email and phone, checkboxes, and a **Send email** broadcast button | `MemberActivity` + `Members` | `public_html/res/Members/Members.php:3329-3380` |
| **Member Extra Hours summary** | Member × Apr→Mar PDF with a YTD column. On the DMV committee with no sub-committee selected it covers **all activity org-wide** | `MemberActivity.Extra_Hours` | `public_html/res/services/servicesp.php:10974`, `:11014-11021` |

`activity.php`, `detailedactivity.php` and `interactions.php` are standalone `?year=YYYY` pages opened in a new tab (`_getButtonAsHref`, `public_html/res/services/htmlUtilities.php:143-150`). `detailedactivity.php` and `interactions.php` each carry an **Export to Excel** button that POSTs the rendered HTML table to `htmltableexport.php` and gets a CSV back (`detailedactivity.php:123`, `interactions.php:70`; converter at `public_html/res/services/htmltableexport.php:29-66`).

### 2.2 Per-Group reports

Every scheduling Group has the same three-part Statistician menu: an _Extra Hours_ / _Interactions_ entry button, a _Confirm a Month_ picker, and a _Choose a Report Year_ picker offering `Fiscal YYYY` back to 2013 (e.g. `public_html/res/vg/vg.php:1859-1888`).

| Report | Groups | What it shows | Reads |
| --- | --- | --- | --- |
| **`_<group>PrintStatistics`** — the shift-hours PDF | vg, gi, docent, gdr, reception, rombus, walker, outreach, special, invitation | Member × twelve months + YTD, landscape PDF | **`<group>History.Total_Scheduled` (+ `Total_Group`, + `Total_Meeting` / `Total_Hotspot` where present)** — *not* `MemberActivity` |
| **`__EventsPrintStatistics`** — the generic events PDF | `special` and the six Friends committees | Member × Apr→Mar + YTD, optionally filtered by event name | `specialSchedule.Count` joined to `specialEvents`, `WHERE b.Confirmed=1 AND a.Confirmed=1 AND a.MemberID>0` |
| **`_EventsReport`** — _Total Hours by Event_ | same | Event × Apr→Mar + Total, on screen with a Print link | `specialSchedule.Count`, `WHERE Confirmed=1 AND MemberID>0 AND VgID=0` |
| **_Volunteers Activity by Event_** | `special` only | One row per shift: Member × Event × Activity for the fiscal year | `specialSchedule` ⋈ `Members`, `shiftDate` range |
| **_Members with Zero Hours_ / _Zero Shift Hours_** | per Group | Same shape as the DMV-wide version, scoped to the Group's roster | `MemberActivity` |
| **_Member History_** | per Group, officer-only | One member's Fiscal-YTD and rolling-12-month hours, split scheduled vs extra, per committee | `MemberActivity` ⋈ `Committee` ⋈ `subCommittee` |

Citations: PDF report functions at `public_html/res/vg/vg.php:2292`, `gi/gi.php:4718`, `docent/docent.php:5980`, `gdr/gdr.php:5415`, `reception/reception.php:1660`, `rombus/rombus.php:1746`, `walker/walker.php:3599`, `outreach/outreach.php:3884`, `romtravel/romtravel.php:467`, `special/special.php:2585`, `invitation/invitation.php:884`; generic events PDF at `public_html/res/services/servicesp.php:10866`, its SQL at `:10891-10900`; `_EventsReport` at `:10764`, its SQL at `:10805-10806`; _Volunteers Activity by Event_ linked at `public_html/res/special/special.php:2480`, file `public_html/res/special/eventactivityhistory.php:19-21`; per-Group zero-hours at `public_html/res/services/servicesp.php:9319`; _Member History_ at `public_html/res/services/servicesp.php:3636` → `_get_statisticsFor()` at `:3675`.

**ROM Travel has no live statistics report.** `__romtravelPrintStatistics` and `__romtravelPrintTourSummary` are both inside `/* … */` blocks (`public_html/res/romtravel/romtravel.php:465-557` and `:290-453`), so `function_exists()` is false for both and `get_statsReport()` (`:264-276`) offers only the shared Extra Hours and Meeting Hours PDFs. This is the reporting half of [#351](https://github.com/roytanaka/dmv-rom-v2/issues/351) §B1's finding that ROM Travel never credits: it neither writes nor reports scheduled hours.

**Invitations is the mirror image.** `__invitationPrintStatistics` (`public_html/res/invitation/invitation.php:884-943`) is live and reachable, but reads `invitationHistory` — a table **nothing in the live tree writes** — and its columns are **`Jan…Dec`, a calendar year**, not Apr–Mar (`:925`, `:889`). Its Group's `get_statistician()` routes only the `E…` (extra hours) branch (`:871-878`), so there is no menu path to it; it is reachable via `fn=report&report=invitationPrintStatistics` and from `invitation_data_21-01-19.js:265`.

### 2.2b The reports that count money, visitors, and objects — not hours

These are built on scheduling rows and are **not in ADR-0022 §8's list at any level**. Several are the only place a dollar figure appears.

| Report | Group | Columns | Reads | Cite |
| --- | --- | --- | --- | --- |
| **Tour Summary and Treasurer Report** | docent | Tours Given / Tours / Visitors / **$** by category and section, TOTAL row | `docentSchedules.ExhibitionRevenue` summed over the fiscal year, `docentCategory`, `docentsection`, `docentscheduledTours` | `public_html/res/docent/docent.php:5789-5966`, header `:5967` |
| **Tour Detail Report** | docent, gdr | Per section and tour: Tours, Visitors, **$** | `docentscheduledTours` / `gdrscheduledTours` `sum(Count) AS Tours, sum(Visitors) AS Visitors` | `public_html/res/docent/docent.php:6209-6403`; `gdr/gdr.php:5645-5840` |
| **Tour Summary** | gdr | Tours / Visitors / **$** | `gdrSchedules`, `gdrscheduledTours` | `public_html/res/gdr/gdr.php:5037-5224` |
| **Tour Provenance** | gdr only | Two fiscal years × **Mois, Visites, Visiteurs, Moyen, France/Eur Fr, Québec, Toronto, Reste du Canada, Autre Pays** — where the visitors came from | `gdrscheduledTours` plus its provenance columns | `public_html/res/gdr/gdr.php:5237-5395`, header `:5396` |
| **Object Usage Statistics** | gi | Object Name, Location, YTD, Apr…Mar — how often each object was on a cart | `gischeduledTours` `sum(Count)` grouped by object | `public_html/res/gi/gi.php:4585-4705` |
| **Presentation Summary Statistics** | outreach | **Presentations, Member Hours, Attendees, $** by presentation type | `outreachgroupTourType`, `outreachgroupTours` (`PresenterShifts`, `Visitors`, `Earned`) | `public_html/res/outreach/outreach.php:3622-3870`, header `:3871` |
| **Walk Summary Statistics** | walker | **Walks, No., Visitors, Walkers, $** per walk type, by season / inside / month | `walkerTourType`, `walkerscheduledTours`, `walkerSchtourToWalker` | `public_html/res/walker/walker.php:3454-3585`, header `:3586` |
| **Sign-in / sign-up sheets** | vg, docent, gdr, gi, reception, walker, outreach | Printable rosters of who is on which shift — the paper the shift runs on | the Group's dated rows | `vg.php:2552`, `:2709`; `docent.php:6142`; `gdr.php:5579`; `gi.php:4844`; `reception.php:1792`, `:1919`; `walker.php:3725`; `outreach.php:4009` |

The Outreach YTD variant has a behaviour worth naming: it walks the fiscal months and **stops at the first unconfirmed month** (`public_html/res/outreach/outreach.php:3781`). It is the only report in the system that treats "confirmed" as a boundary rather than a label.

**`__vgPrintStatistics` is representative of the whole family**: it iterates the Group's own roster table, and for each month reads `vgHistory.Total_Scheduled + Total_Group` (`public_html/res/vg/vg.php:2325-2371`). It never touches `MemberActivity`. This is the split that makes §6 possible.

### 2.3 Member-facing

**My Statistics**, on the home page. Every volunteer sees their own hours for the current fiscal year to date and a rolling twelve-month total, one row per committee, split into a _Scheduled_ line and an _Extra_ line, only for committees where `(Total_Scheduled+Extra_Hours)>0` and `Committee.Active=1` — `public_html/res/services/servicesp.php:3662` (`_get_menumystatistics`), shared renderer `_get_statisticsFor()` at `:3675-3707`. The header text is explicit about the period: _"My hours for this Fiscal year(April 1 to March 31) and Total Hours for the last 12 months"_ (`:3664`).

This is the most-read report in the system by number of readers, and it reads `MemberActivity.Total_Scheduled` directly.

### 2.4 Public feeds built on scheduling data

Two XML endpoints publish scheduling rows to the open internet with no session and no gate, by design:

- **`today_activity.php`** — `<dmvactivity date="…">` with `<docenttours>`, `<gdrtours>`, `<gi>` sections; each entry carries date, time, tour name, type, category, description, and for GI the object and gallery location. Reads `gischeduledTours`, `{comm}scheduledTours` ⋈ `{comm}tour`/`section`/`Category`, and `allTourDescription`. `Content-type: application/xml` at `public_html/res/services/today_activity.php:7`; GI end times are computed as 45 minutes × `Count` at `:39-41`. Volunteer first names are fetched but the `<volunteer>` element is commented out (`:47`), so no member names leak. Fetched over HTTPS by `public_html/today/fetch_today.php:7`.
- **`alltour_activity.php`** — `<dmvtours>`: upcoming **public** docent and GDR tours from today forward, restricted to months whose `{comm}Schedules.status` is `final` or `freeze` (`public_html/res/services/alltour_activity.php:20-22`, `:30-32`, `:47-51`). No in-repo consumer.

**Neither is in ADR-0022's report list**, and neither is an hours report — they publish the *schedule itself*. See §8.

### 2.5 Exports

- **`res/special/exportevent.php`** — CSV of one Wayfinders event's schedule: rows are date/time slots, columns are the event's `specialRoles`, cells are the assigned member names with `____________` for unfilled seats. Linked from `public_html/res/special/special.php:1265` and `:2081`. **The file itself has no session or role check** — it includes `globals.php`/`functions.php` and reads `$_REQUEST['whereval']` straight into SQL (`public_html/res/special/exportevent.php:3-6`).
- **`htmltableexport.php`** — the generic HTML-table→CSV converter behind every _Export to Excel_ button (`public_html/res/services/servicesp.php:10101-10112`).
- **`exporttable.php`** — the member-query CSV export. Assembles `SELECT $select FROM $tables WHERE $where` from request parameters (`public_html/res/services/exporttable.php:22-34`), no auth. Not a scheduling report, listed because it is the other CSV path a Statistician might be using.
- **`printtable.php`** — the universal printable view, used by ~30 sites. It sets `$_POST['print']=1`, which makes `servicesp.php` **skip `session_start()`** (`public_html/res/services/servicesp.php:9-10`) and makes report builders echo raw HTML instead of the JSON envelope.

### 2.6 Dead, frozen, or unreachable — do not port, but know they exist

| File | Status |
| --- | --- |
| `a_detailedactivity.php`, `a_interactions.php` | Older copies of their namesakes, no caller anywhere; `a_interactions.php` hard-filters `visitorTable IS NOT NULL` and so silently drops Groups that report only `Interactions` |
| `no_activity.php` | Fees-and-hours crossover list, URL-only, no gate, dumps member emails and home phones. Already ruled out by ADR-0022 |
| `memberhistoryactivity.php` | One-shot 2015 backfill that **writes** `MemberActivity` on GET with no gate; hard-stops at `if($yyyymm>'201510') break;` (`:64`). Its per-Group `*History` migration block is entirely commented out (`:6-46`) |
| `giHistory.php` | One-shot GI import; takes a table name from `$_GET['file']` and writes `giHistory.Total_Group` |
| `GIShiftHistory.php` | GI shift-distribution analysis frozen to 2015-08-01 → 2016-01-31 (`:4-6`) |
| `services/eventactivityhistory.php` | Superseded by `res/special/eventactivityhistory.php`; the services copy still filters by calendar year (`shiftDate LIKE '$yr%'`) instead of fiscal |
| `todayactivities.php` | Dead three ways over — function never called, no includes, GI query built but never executed |
| `trackTours.php` / `trackToursPeek.php` | Docent sign-up fill-rate tracker (Museum Highlights and Group Tours percentage taken, next two months) writing `docentTrackTours`. Its only invocation is **commented out** at `public_html/res/services/daily.php:389-390`. `trackToursPeek.php` still emails a hardcoded personal Gmail address on every run (`:30`, `:71`) |
| `__vgPrintTourSummary` | Has no caller; the only thing that reads `vgscheduledTours.TourID` (schema research §2.1) |

---

## 3. Who reads them

### What the code proves

| Surface | Gate | Cite |
| --- | --- | --- |
| **My Statistics** | none beyond being logged in — it reads `$_POST['mid']` | `public_html/res/services/servicesp.php:3663` |
| **Extra Hours / Interactions entry**, every Group | none — the button is emitted on every branch | `public_html/res/services/servicesp.php:9520-9526` |
| **Member History picker**, per Group | `dmvChair==1`, else `SELECT MemberID FROM {committee} WHERE MemberID=$mid AND (Chair>0 OR Statistician=1)`; on the `Members` committee `OR Records>0` is added | `public_html/res/services/servicesp.php:9531-9546` |
| **Per-Group report year picker + Confirm a Month** | `$_POST['isChair']>0 \|\| $_POST['isStatistician']>0` | `public_html/res/vg/vg.php:1861`; identically at `rombus.php:1436`, `romtravel.php:228`, `bw.php:103`, `fop.php:194`, `ftc.php:103`, and siblings |
| **DMV-wide report hub** | `$exec = $_POST['isChair']>0 \|\| $_POST['isStatistician']>0 \|\| $_POST['isRecords']>0 \|\| $_POST['isSecretary']>0` | `public_html/res/Members/Members.php:3507`, menu at `:3210` |
| **Summary Visitor Interactions** | **deliberately offered to non-exec members too** — it is the sole item in the `else` branch | `public_html/res/Members/Members.php:3524-3526` |
| **Sub-committee statistics** | sub-committee membership or `dmvChair`, then `isChair==1 \|\| Secretary>0` for the report half | `public_html/res/services/servicesp.php:9456-9481` |
| **`activity.php`, `detailedactivity.php`, `interactions.php`, `no_activity.php`, `exportevent.php`, the XML feeds** | **none in the file** | each file's includes; `servicesp.php`'s own session check is commented out at `:630-637` |

**And none of it gates the reports themselves.** `remap()` dispatches `fn=report` before any check — `else if ($mee=="report") { _exec_report(); }` (`public_html/res/services/servicesp.php:627`) — and `_exec_report()` is three lines: `$fname = $_REQUEST['report']; if (function_exists('_'.$fname)) call_user_func('_'.$fname);` (`:2266-2286`). The Group whose code is loaded comes from `$_REQUEST["committee"]` (`:592-596`). So **any request carrying `fn=report&report=<name>&committee=<group>` renders that Group's statistics PDF**, and every `get_statistician()` dispatcher (e.g. `public_html/res/vg/vg.php:1898`) routes `S`/`R`/`G`/`E` with no role test inside. The `Statistician` flag decides only whether the *button* is drawn.

Two role details the model should carry forward. **Docent and GDR Section Heads get the report list** — `if($_POST['isChair']>0 || $_POST['isStatistician'] >0 || $result)` where `$result` is a row in `docentsection` with `SectionHeadID=$_POST['uid']` (`public_html/res/docent/docent.php:3263`, `gdr/gdr.php:2774`, menu at `public_html/res/services/servicesp.php:1236-1239`); the tour picker is narrowed to their own section. This is the section-scoped Statistician that ADR-0010 models. And **Walker's sign-in stats menu excludes the Chair**: `if($_POST['isStatistician'] > 0)` with no `isChair` term (`public_html/res/walker/walker.php:1073`) — the only Group where that is true.

Three structural notes. First, **every one of those role tests reads `$_POST`, not `$_SESSION`** — the browser asserts its own authority. ADR-0022 records this as the security defect it deviates from legacy to fix. Second, the org-wide `Members.Statistician` bit is **loaded into the session and never read by any gate** (archaeology notes, 2026-08-03 menu study; the assignment is `public_html/res/services/servicesp.php:1087`) — the Statistician role that actually gates anything is the per-committee column on the Group's membership table. Third, the Hours menu itself is emitted on every branch of the ladder for every user; only the *label* and *payload* change with role.

### The consumers the code can name

- **Every volunteer** — their own hours, via My Statistics, and their own renewal decision rides on it (spec [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) states this explicitly).
- **A Group's Chair and Statistician** — the shift-hours PDF, the money-and-visitor reports, the month review, Member History, the zero-hours lists.
- **A Docent or GDR Section Head** — the same report list, narrowed to their own section's tours.
- **A sub-committee's Chair (and, inconsistently, its Secretary)** — the extra-hours and meeting-hours PDFs.
- **The DMV Chair / Secretary / Statistician / Records** — the DMV-wide report hub and everything on it.
- **Anyone at all** — Summary Visitor Interactions, deliberately; and every standalone report page, accidentally.
- **The open internet** — today's tours and the upcoming public-tour list, via the two XML feeds.

### The consumers the code cannot name

**The ROM itself.** No report is emailed anywhere. The only scheduling-adjacent hardcoded recipients in the live tree are a ROM staff mailbox bcc'd on **group-booking confirmations** (`public_html/res/rombus/rombus.php:1180`, `:1237`; `gdr/gdr.php:4830`, `:4897`; `docent/docent.php:4444`, `:4511`; `outreach/outreach.php:2632`) and the Records role mailbox on the ROM domain, cc'd on **fees and membership** mail (`public_html/res/services/daily.php:130`, `:201`, `:229`). Neither carries a statistic.

The claim that the ROM receives fiscal-year statistics appears twice in this repo — spec [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) ("The DMV Executive cannot produce the fiscal-year statistics the ROM asks for. This is the single report the whole feature exists to make") and [ADR-0022](https://github.com/roytanaka/dmv-rom-v2/blob/worktree-hours-model-decisions/docs/adr/0022-hours-and-statistics-model.md) §5 ("which is the number the ROM is given") — but both are statements from the committee, not findings from the code. **The delivery path is outside the application**: somebody opens a report, exports or prints it, and hands it on.

> **Not answerable from the code — needs a person.** Which of these reports actually leaves the DMV; who at the ROM receives it and in what form; whether there is an annual report, a board pack, or a departmental submission that quotes these numbers; and which of the two XML feeds is still consumed by an in-museum display or the ROM website. `alltour_activity.php` has no consumer anywhere in the repo.

---

## 4. Service awards

**No code anywhere computes a service award from hours.** Legacy's service-award mechanism is entirely **years of service**, hand-maintained by Records.

The mechanism, in full:

- `Members.Service_Awards` is a **comma-separated string** of award codes, appended to by a dropdown. `Members.Last_Award` is the year of the most recent one. `Members.Next_Svc_Award` is a year. `Members.Other_Awards` is free text for government/external awards.
- The award vocabulary is data, not code: `SELECT * FROM {prefix}MembersServiceAwards` populates the picker (`public_html/res/Members/Members.php:1186-1193`). Per the archaeology table inventory it is a **12-row, 4-column table**. The thresholds are therefore not in the source at all — only the cadence is.
- **The cadence is five years.** Typing a `Last_Award` year recomputes the next one client-side: `document.getElementById("Next_Svc_Award").value = 5+(1*newstr);` (`public_html/res/Members/Members_data_24-11-08.js:379-384`).
- **The clock starts at entry, not at Active.** When a member moves `Prov`/`PreActive` → `Active`, the server sets `Next_Svc_Award = 5 + Entry_Year` (`public_html/res/Members/Members.php:1652-1656`). The one-off transfer script says so in a comment: `// No award yet - under 5 years - measured from Entry Date, NOT Active date` (`public_html/res/services/dbtransfer.php:139-141`), and the same file computes `$nextaward = 5 + (1*$lastaward)` for members who already hold one (`:81`).
- Adding an award is `MembersNewAward()` appending to the string (`Members_data_24-11-08.js:367-371`); it is a **manual Records action in the volunteer-record dialog**, with `Last_Award` and `Service_Awards` rendered as free text inputs (`public_html/res/Members/Members.php:1245-1250`).
- Display is one line on the member record: `"Last Service Award … <code> in <year>"` (`public_html/res/services/servicesp.php:2486-2492`).
- Photos carry a note that they are "always visible to DMV Records and DMV Awards Committees" (`public_html/res/services/servicesp.php:2658`), and the DMV has a standing **Awards** body of 8 members (archaeology feature inventory, 2026-05-23) — so an Awards committee exists and reviews something. What it reviews is not in the code.

**There is no join between `MemberActivity` and any award column.** Grepping `Service_Awards|Last_Award|Next_Svc_Award|Other_Awards` across the live tree returns exactly the sites above; none of them is in a file that also reads `Total_Scheduled`, `Total_Hours`, or `Extra_Hours`.

This contradicts, on its face, two statements in this repo: ADR-0022 §4 ("per-Member hours feed service awards, and legacy has never shown them to a peer") and spec [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) ("the conversation that drives renewal, standing, and service awards"). Both are plausible as *practice* — a Chair looks at the hours report before nominating — and both are false as *code*.

> **Not answerable from the code — needs a person.** Whether hours are a criterion for a service award at all, and if so whether the criterion is a threshold, a minimum, or a judgement call; what the twelve rows of `MembersServiceAwards` are and what each requires; who decides — the Awards committee, the Chair, or Records; and whether the 5-year cadence is the whole rule or just the reminder. The answer decides whether the rebuild's hours feature has an award consumer at all, or whether awards are a pure Records/tenure feature that never touches scheduling.

---

## 5. The annual cycle

**There is a monthly cycle. There is no annual one.**

### The monthly close is a button, not a job

Each scheduling Group's Statistician menu offers a _Confirm a Month_ picker and a confirm button — `public_html/res/vg/vg.php:1865` and `:1913`, `gi/gi.php:4019`, `docent/docent.php:3319`, `gdr/gdr.php:2826`, `rombus/rombus.php:631`, `outreach/outreach.php:1931`, `reception/reception.php:1462`. Every invocation is a browser `onclick`; **no cron calls any confirm handler**.

Confirming a month does four things (`public_html/res/vg/vg.php:2006-2068` is the reference implementation):

1. **Deletes** the month's empty rows — `DELETE FROM vgscheduledTours WHERE YYYYMM='$whereval' AND (VgID=0 AND MemberID=0)` (`:2012`). GI deletes `MemberID=0` (`gi/gi.php:4272`). **Wayfinders deletes more**: `DELETE FROM specialSchedule WHERE EventID='$eventID' AND (MemberID=0 OR VgID>0)` (`public_html/res/special/special.php:2431`) — that is, it destroys the rows for shifts filled by a Visitor Guide.
2. **Zeroes the Group's `*History` rows for the month**, then rebuilds them from the dated rows.
3. **Upserts `MemberActivity.Total_Scheduled`** via `_saveScheduledMemberActivity()` (`public_html/res/services/servicesp.php:9296-9305`), which **replaces**: `SET Total_Scheduled=$total`.
4. **Flips a status flag** — `vgSchedules.status='confirm'` (`:2064`), `docentSchedules.confirmed=1` (`docent/docent.php:3573`), `specialEvents.Confirmed=1` (`special/special.php:2454`).

**Re-running is unrestricted and expected.** The picker keeps listing already-confirmed months, prefixed `C-` (`public_html/res/vg/vg.php:1874`; same at `rombus.php:1447`, `walker.php:2218`, `gi.php:3975`, `reception.php:1379`). Wayfinders says so in a comment: `** This allows to REDO if Members complain **` (`public_html/res/special/special.php:2430`). The only bound is a `LIMIT` on how far back the picker looks — 15 months for VG and ROMBus, 13 for GI, 12 for Reception and Walker.

**Nothing freezes.** The `confirmed`/`status='confirm'` flag is only ever read to label a dropdown or to filter which months a report offers. No write path anywhere checks it before overwriting a number.

### The fiscal year is a report window, nothing more

**1 April – 31 March, named for the year it ends in.** The same six lines appear in nine files (`public_html/res/vg/vg.php:1881-1884`, `gi/gi.php:3983-3986`, `reception/reception.php:1386-1390`, `gdr/gdr.php:2778`, `docent/docent.php:3267`, `outreach/outreach.php:1754`, `project/project.php:407`, `joint/joint.php:147`, `romtravel/romtravel.php:233`), and the server-side bounds are `$fystart=($year-1)."04"; $fyend=$year."03";` (`public_html/res/services/servicesp.php:9319-9320`). ADR-0022 §8 fixes the same window for the rebuild.

Every fiscal-year report is a pure `SELECT` over mutable rows. **There is no publish step, no snapshot, no signed-off number, and no archive table** — past periods just stay in place keyed by `YYYYMM`. Grepping the live tree for table names containing `archive`/`backup`/`_old`/`_prev`/`_bak` or a year suffix returns nothing. The `*History` tables are the live rollup, not an archive.

The one genuinely annual process in the app is **membership renewal**, and it is driven by a hand-edited control row (`SELECT * FROM {prefix}MembersRenewalControl WHERE Active=1`, `public_html/res/services/servicesp.php:4536`), writes `Members.Renewal_Year`, and **touches no scheduling number**.

### Correction after the fact

Three unbounded paths:

1. **Re-confirm the month** (above) — the primary one.
2. **A Chair or Statistician overwrites any member's `Extra_Hours` for any of the last 12 months.** The picker is `SELECT DISTINCT YYYYMM FROM MemberActivity ORDER BY YYYYMM DESC LIMIT 12` (`public_html/res/services/servicesp.php:9804-9807`); the save is a direct set, not a delta: `UPDATE MemberActivity SET Extra_Hours=$val WHERE … AND MemberID=$mid` (`:9919`). It never checks whether the month is confirmed. On the DMV committee a Statistician's picker reaches back to **2012** while a plain committee Chair's reaches back to 2023 (`public_html/res/Members/Members.php:3222-3231`).
3. **A member self-enters `Extra_Hours`** for the current and previous month only, additively, floored at zero (`public_html/res/services/servicesp.php:9607-9608`, `:9671-9672`).

**No audit trail.** `MemberActivity.lastUpdate` is the only trace, read once (`:9639`). Editing a two-year-old month leaves no record of the prior value. ADR-0022 §6 fixes this with an adjustment log.

### The annual moment the code does not contain

> **Not answerable from the code — needs a person.** Whether the DMV in practice has a year-end at which the Statistician declares the numbers final; who chases the ten Groups to press Confirm; whether there is a deadline, and what happens to a Group that never confirms a month. The code makes an unconfirmed month simply absent from every report, silently — no Group is listed as missing. Also: whether the ROM's fiscal year and the DMV's Apr–Mar window are the same year, since only the DMV's is in the code.

---

## 6. Per Group vs per Member, and where they disagree

### What is reported at each grain

| Grain | Where it lives | What it counts |
| --- | --- | --- |
| **Per Member, per Group, per month** | `MemberActivity` (`committee`, `subCommitteeID`, `MemberID`, `YYYYMM`, `MeetingID`) | **hours** — `Total_Scheduled`, `Extra_Hours`, and the trigger-derived `Total_Hours` |
| **Per Member, per Group, per month** | `<group>History` (`{Group}ID`, `YYYYMM`) | **things done** — tours, walks, shifts |
| **Per Group, per month** | derived at read time by `SUM()` over `MemberActivity` grouped by `committee` | hours |
| **Per Event, per month** | derived at read time from `specialSchedule.Count` grouped by `EventID` | hours |

There is no stored per-Group total anywhere. Every Group figure is a sum of member figures computed live — which is what makes the two rollups' divergence visible.

### Where they are expected to agree

A Group's total in **Summary Committee Statistics** should equal the sum of its members' rows in that Group's **Member History**, and both should equal what each member sees in **My Statistics** for that Group. All three read `MemberActivity`, so they do agree by construction. The sub-committee rollup is likewise free and accidental: a sub-committee's row stores the **parent's** symbol in `committee`, so every report that groups by `committee` already includes its sub-committees, with no rollup code anywhere (ADR-0022, Context §3).

### Where they can legitimately disagree

1. **The per-Group PDF and the DMV-wide grid read different tables.** `__vgPrintStatistics` (`public_html/res/vg/vg.php:2292`) sums `vgHistory.Total_Scheduled + Total_Group` (`public_html/res/vg/vg.php:2367-2370`); `activity.php` sums `MemberActivity.Total_Scheduled` (`public_html/res/services/activity.php:94`). One counts things, the other counts hours, and they are reconciled only by the hardcoded `$hoursper` array (`public_html/res/services/memberhistoryactivity.php:8`) — every Group `1`, **Walker `2`**. For Walker the two reports differ by a factor of two **by design**, and the difference is invisible on either page.
2. **GI's `*History` is never zeroed on re-confirm.** The zeroing line is commented out — `//mysql_command("UPDATE giHistory SET Total_Scheduled=0 WHERE YYYYMM = …")` (`public_html/res/gi/gi.php:4279`) — while the `MemberActivity` upsert is still a replace. A GI whose shifts were removed keeps a stale `giHistory` row, so GI's per-Group PDF and the DMV-wide grid drift apart permanently.
3. **`MemberActivity` rows are never zeroed on re-confirm either** (except by Wayfinders). Each Group's confirm zeroes `*History` for the month wholesale, but only rewrites the `MemberActivity` rows returned by the `GROUP BY`. A member whose confirmed shifts fall to zero after a correction keeps their previous `Total_Scheduled` forever. Wayfinders is the exception: it explicitly zeroes the whole month first (`public_html/res/special/special.php:2423`).
4. **Wayfinders accumulates where everyone else replaces.** Within a month it does `SET Total_Scheduled = Total_Scheduled + $total` per event (`public_html/res/special/special.php:2446`), after the month-wide zero.
5. **The two Wayfinders event reports disagree about guests.** _Total Hours by Event_ filters `VgID=0` (`public_html/res/services/servicesp.php:10806`); _Member Hours for All Events_ does not (`:10894`). After the month is confirmed the point is moot, because confirm deletes the `VgID>0` rows outright — but before confirm, the per-event total and the per-member total for the same month will not add up.
6. **Docents credit twice from two buttons.** `_confirm_statistics_scheduled` and `_confirm_statistics_groups` each call `_saveScheduledMemberActivity` with their own half plus whatever the other half already holds (`public_html/res/docent/docent.php:3564-3570`, `:3597-3603`). A docent with only group tours is credited only if the group button is pressed.
7. **Friends hours depend on a Visitor Guides officer.** Confirming the VG month sweeps `specialSchedule` ⋈ `specialEvents` for a hardcoded list `array('fop','ftc','fcc','bw','fes','fsa')` (`:2073`) and credits each member under that Friends symbol (`public_html/res/vg/vg.php:2071-2085`). **`famis` is missing from that array** despite holding events (schema research §8.4 item 41) — and the reason is now visible: `famis`'s own Extra Hours button is commented out with `//removed as Amis hours must go to GdR` (`public_html/res/famis/famis.php:98-99`). Les Amis Francophiles' hours are **deliberately** credited to Guides du ROM, not to `famis`. That closes flagged unknown §8.4 item 41 as intentional. Separately, and unlike the VG sweep of its own rows, this sweep does **not** filter `Confirmed=1` — Friends members are credited for shifts nobody signed out of.
8. **Invitations reports from a table nothing writes.** `__invitationPrintStatistics` reads `invitationHistory` (`public_html/res/invitation/invitation.php:903`, `:923`), which no code in the live tree writes. ROM Travel's equivalent is dead code (§2.2), so it reports nothing at all — which extends [#351](https://github.com/roytanaka/dmv-rom-v2/issues/351) §B1's "ROM Travel has no `_saveScheduledMemberActivity` call anywhere" to: it has no live statistics report either.
9. **Invitations' report is a calendar year.** Its column headers are `Jan…Dec` (`public_html/res/invitation/invitation.php:889`) while every other live Group PDF is `Apr…Mar`. ROM Travel's dead copy had the same shape (`romtravel.php:475`) while its picker said `Fiscal $y` (`:239`).
10. **`vgHistory.Total_Group` is read and never written** (schema research §8.2 item 11), so VG's per-Group PDF adds a permanent zero.
11. **Three `*History` columns are dropped by most reports.** `Total_Museum` is referenced **nowhere** in the live tree; `Total_Hotspot` appears twice (`public_html/res/docent/docent.php:6074`, `gdr/gdr.php:5509`); `Total_Meeting` four times (`reception/reception.php:1713`, `:1743`; `invitation/invitation.php:925`; and ROM Travel's dead copy). Every other Group's PDF sums only `Total_Scheduled + Total_Group`, so anything parked in those columns is silently absent from that Group's statistics.
12. **Walker's zero-hours report is broken.** `public_html/res/walker/walker.php:2344` calls `CommitteeMembersZeroHoursReport($year)` with one argument, but the JS signature is `(yyyy, type)` (`public_html/res/js/data_26-06-05.js:2987`), so `$_POST['type']` is undefined and neither SQL branch in `_CommitteeMembersZeroHoursReport` fires. The report renders empty and always has.
13. **Docents' and GDR's confirm screens edit `Visitors`, VG's and Reception's edit `Count`.** `_statistics_visitors()` (`public_html/res/docent/docent.php:3518`, `gdr/gdr.php:3041`) writes the visitor count; `_statistics_shifts()` (`vg/vg.php:1989`, `reception/reception.php:1537`) writes the hours. The same screen position means different things in different Groups.

---

## 7. Which numbers someone would notice changing

[#351](https://github.com/roytanaka/dmv-rom-v2/issues/351) §A2 already flags two: **membership counts on the seven Friends Committees go up by one each**, and **cross-Group hours will be attributed differently**. Both stand. What follows is what else is hiding in this data.

### Numbers that will move

1. **Walker's hours, by a factor of two, or not at all.** Legacy multiplies at the call site (`_saveScheduledMemberActivity('walker',…,2*$total)`, `public_html/res/walker/walker.php:2298`) and *also* carries the same intent in `$hoursper` (`memberhistoryactivity.php:8`). ADR-0022 §7 moves it to an `hours_multiplier` column. If both the column and a derived-from-duration recalculation apply, Walker doubles; if neither, it halves. This is the single most likely silent regression in the set.
2. **Docents' and GDR's "hours" are tour counts wearing an hours label.** `public_html/res/docent/docent.php:3550` selects `Sum(Count) AS TotalTours` and feeds it straight into `MemberActivity.Total_Scheduled`, which every report renders as hours (schema research §7.11). Deriving duration from `starts_at`/`ends_at` per ADR-0021 will change every docent's number, in both directions, and the fiscal-year comparison against last year will look broken.
3. **Two Groups' per-Group reports will start producing numbers.** ROM Travel and Invitations currently render empty or stale (§6 item 8). Anything that writes hours for them is new data, not a restated number.
4. **Friends hours will change if the sweep is not reproduced exactly** — unconfirmed Friends shifts are credited today (§6 item 7) and a stricter rule drops them, and `famis` must keep routing to Guides du ROM rather than to itself, which is a deliberate rule encoded only as a commented-out button.
5. **Stale carry-forward disappears.** Members whose corrected months left a stale `Total_Scheduled` behind (§6 item 3) will see historical months drop. This looks exactly like data loss.
6. **Recalculation gets a fiscal-year bound it never had.** ADR-0022 §2 permits recalculation for the current fiscal year only. Legacy has no such bound — pickers reach back 12–15 months and the Statistician's extra-hours picker reaches back to 2012. Anyone whose routine is "fix last March in June" will find the button refuses.
7. **Members can no longer have hours entered on their behalf.** ADR-0022 §4 makes entry self-only pending a verification workflow. Legacy has a chair bulk-entry screen (`get_EnterMembersActivity`, `public_html/res/services/servicesp.php:9836`); a Chair who currently types in a member's Saturday will find the screen gone.
8. **Every dollar figure the schedule produces has no home in the rebuild.** Docents' Tour Summary and Treasurer Report, GDR's Tour Summary and Detail, Outreach's Presentation Summary and Walker's Walk Summary all report **$** alongside tours and visitors, from `docentSchedules.ExhibitionRevenue`, `outreachgroupTours.Earned` and `walkerscheduledTours.Earned` (§2.2b). ADR-0021 defers the honorarium/`Earned` fields with group booking, and ADR-0022 is about hours — so the Treasurer-facing half of scheduling reporting is currently owned by nobody.
9. **GDR's Tour Provenance disappears.** A two-year table of where visitors came from (`public_html/res/gdr/gdr.php:5237-5395`), built on GDR's own scheduling rows, in French, for one Group only. Nothing in the rebuild plan mentions it.
10. **The Outreach YTD summary stops at the first unconfirmed month** (`public_html/res/outreach/outreach.php:3781`). Anything that reports the whole year regardless will show Outreach a bigger number than it is used to seeing.

### The one to flag loudly: Summary Visitor Interactions

ADR-0022 does not ship **Summary Visitor Interactions**, on the ground that `dmv_MemberActivity.Interactions` is non-zero on **zero** of 74,248 rows. That evidence is about the wrong column.

`interactions.php` builds its total in three parts (`public_html/res/services/interactions.php:75-125`):

- the **main input**: `SELECT sum(Visitors) FROM {symbol}{visitorTable}` — that is, the `Visitors` column on the Group's own **scheduling rows** (`vgscheduledTours`, `specialSchedule`, `gischeduledTours`, `walkerscheduledTours`, …), the number a volunteer types at sign-out;
- **plus** `docentgroupTours`/`gdrgroupTours.Visitors` and `walkerSchtourToWalker.Visitors` as hardcoded special cases (`:107-120`);
- **plus** `MemberActivity.Interactions` on top (`:122-123`) — the all-zero column — which is the **only** source for Groups whose `visitorTable` is `NULL` (`:146-186`).

So `Interactions` being empty means the *fallback* is empty. It says nothing about the report's main input, and [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398) records the per-shift visitor count as **live daily practice on every visitor-facing Group, confirmed 2026-08-07**. `Detailed Committee Statistics` carries the same figure as a per-Group row and a footer total (`public_html/res/services/detailedactivity.php:63-70`, `:114-119`), and it is the one report **deliberately shown to ordinary members** (`public_html/res/Members/Members.php:3524-3526`).

Separately, `dmv_MemberActivity` has a **`Visitors` column commented `'Click Count'`** in ADR-0022's quoted DDL. Grepping the live tree finds **no reader and no writer** for it. It is not a reported number; the visitor figures in the reports come from the scheduling tables.

Three more things about that report worth knowing before it is dropped or rebuilt (schema research §7.9):

- The same "total visitor interactions" figure is assembled by **three different rules** depending on the Group.
- On a dated shift row it is a **member-reported actual**; on a booking row (`outreachgroupTours.Visitors`, `docentgroupTours.Visitors`, `rombusTrip.Visitors`) it is a **pre-event forecast** — the Outreach confirmation email reads _"We are expecting an audience of $Visitors…"_. All of them are added into one number.
- **Walker is counted twice** — `interactions.php:116-120` sums both `walkerscheduledTours.Visitors` and `walkerSchtourToWalker.Visitors` with no guard.

### Two more that are quiet

- **`Total_Hours` is written by a database trigger, not by PHP** (ADR-0022, Context §2). `Active Members Ranked Hours` and `Members with Zero Hours` read it and nothing else. ADR-0022 moves the derivation into the model — same identity, but any row where the trigger's identity does not hold today (ADR-0022 confirms it on all 44,913 rows dated 2018-04 or later, leaving ~29,000 older rows unstated) will restate on import.
- **The current month is deliberately missing from Summary Committee Statistics** (`activity.php:86` cuts at "first day of last month") but **present in the visitor figures** (`interactions.php:80`, `detailedactivity.php:175` vs `:131`). Two reports on one page disagree about how much of this year they cover. Reproducing this faithfully is odd; not reproducing it changes both numbers.

---

## 8. Where ADR-0022 and this research disagree

ADR-0022 §8 enumerates the report set and the access gate, and this research confirms it for everything that reads `MemberActivity`. Five additions and one correction:

0. **A whole class of report is absent: the money-and-visitor reports** (§2.2b) — Docents' Tour Summary and Treasurer Report, Docents' and GDR's Tour Detail, GDR's Tour Summary and **Tour Provenance**, GI's Object Usage Statistics, Outreach's Presentation Summary, Walker's Walk Summary, and every Group's sign-in / sign-up roster PDF. They read the dated scheduling rows rather than `MemberActivity`, so an hours ADR is the wrong place for them — but nothing else in the rebuild claims them either, and two of them are the department's only revenue reporting.
1. **Two public XML feeds are built on scheduling data and are not in the list** — `today_activity.php` (today's docent/GDR/GI shifts, fetched over HTTPS by `public_html/today/fetch_today.php:7`) and `alltour_activity.php` (upcoming public tours from `final`/`freeze` months). They are not hours reports, so ADR-0022 is not wrong to omit them from an hours ADR — but they are scheduling data leaving the building, they are the only reports with a named audience outside the DMV, and no v2 ticket covers them.
2. **Wayfinders' _Volunteers Activity by Event_** (`public_html/res/special/eventactivityhistory.php`, linked at `special.php:2480`) is a live fiscal-year per-member × event × role listing not named in ADR-0022 §8's Group-level list.
3. **_Total Hours by Event_ and _Member Hours for Events_** (`public_html/res/services/servicesp.php:10764`, `:10866`) are generic, shipped to `special` and all six Friends committees, and read `specialSchedule.Count` directly rather than `MemberActivity`. ADR-0022's Group-level list does not name them.
4. **`exportevent.php`** is a live CSV export of a Wayfinders event's staffing, reachable with no gate. ADR-0021 defers "print and export of a Schedule" to the document capability; it is worth knowing that the legacy version exports member names and is currently unauthenticated.
5. **The Summary Visitor Interactions evidence is about the wrong column** — see §7. This is the one place where following ADR-0022's reasoning would drop a number people are still producing every day.

6. **The reporting gate is weaker than ADR-0022 records.** ADR-0022 correctly names `get_statsReport()` reading `isChair`/`isStatistician`/`isRecords`/`isSecretary` from `$_POST`. The PDF path is worse: `fn=report` is dispatched before any check at all (`public_html/res/services/servicesp.php:627`, `:2266-2286`), and the Group is chosen by request parameter. Every per-Group statistics PDF, every sign-in sheet, and every revenue report is a plain GET away. Worth stating, because ADR-0022's "no aggregate view for an ordinary Member" is a change from what legacy *actually* permits, not just from what it *intends*.

Two places where ADR-0022 is more accurate than earlier research: `Total_Hours` is a **trigger**, not an unwritten column (the schema research and the annual-cycle sweep both concluded only "never written by PHP"), and the sub-committee rollup is **free**, not absent.

---

## 9. Summary — the six questions

1. **The reports that exist.** Three standalone DMV-wide grids (`activity.php`, `detailedactivity.php`, `interactions.php`) plus four Members-level lists and PDFs; ten live per-Group shift-hours PDFs; a generic events pair for Wayfinders and the Friends; **a money-and-visitor family** (Docents' Treasurer report, GDR's Tour Provenance, GI's Object Usage, Outreach's and Walker's summaries) plus sign-in rosters; a per-member history; a member-facing My Statistics; two public XML feeds; and one CSV schedule export. Gates are per-committee `Chair`/`Statistician` bits, plus `Records`/`Secretary` at DMV level — all read from `$_POST`; standalone report pages have **no gate of their own**, and `fn=report` renders any Group's PDF with no check at all.
2. **Who reads them.** Every volunteer (own hours), Group Chairs and Statisticians, Docent and GDR Section Heads for their own section, sub-committee Chairs, and the DMV Chair/Secretary/Statistician/Records. Summary Visitor Interactions is deliberately open to all members; the PDF reports are open to anyone by accident. **Whether and how any of this reaches the ROM is not in the code — needs a person.**
3. **Service awards.** Not computed from hours. A hand-maintained years-of-service mechanism on the Member record, five-year cadence, clock starting at `Entry_Year`, vocabulary in a 12-row table. **Whether hours are a criterion in practice is not answerable from the code — needs a person.**
4. **The annual cycle.** A per-Group monthly Confirm button, re-runnable forever, with nothing that freezes, archives, snapshots or publishes. The fiscal year (1 Apr – 31 Mar) exists only as a report window. **Whether the department has a year-end moment around it is not answerable from the code — needs a person.**
5. **Per Group vs per Member.** Everything is per Member; every Group figure is a live sum. Two parallel rollups (`*History` counts things, `MemberActivity` counts hours) are reconciled by a hardcoded multiplier, and thirteen named cases where they legitimately disagree are in §6.
6. **What would be noticed changing.** Walker's factor of two, Docents' and GDR's tour-counts-as-hours, ROM Travel and Invitations gaining numbers, Friends hours moving twice, stale carry-forward disappearing, the new fiscal-year bound on recalculation, on-behalf-of entry going away — and, loudest, **Summary Visitor Interactions**, whose main input is the per-shift `Visitors` column on scheduling rows, not the all-zero `MemberActivity.Interactions` that ADR-0022 tests.

---

## 10. What needs a person

| # | Question | Why the code cannot answer it |
| --- | --- | --- |
| 1 | Does any of this reach the ROM, and in what form? | No report is emailed; the only ROM-domain recipients get booking confirmations and fees mail |
| 2 | Is there an annual report, board pack, or departmental submission quoting these numbers? | Nothing in the app produces or transmits one |
| 3 | Who consumes `today_activity.php` and `alltour_activity.php`? | `alltour_activity.php` has no in-repo caller; `today_activity.php`'s only in-repo caller is a debug harness |
| 4 | Are hours a criterion for a service award — threshold, minimum, or judgement? | No code joins hours to any award column |
| 5 | What are the twelve rows of `MembersServiceAwards`, and what does each require? | The vocabulary is data; only the 5-year cadence is in code |
| 6 | Who decides awards — the Awards committee, the Chair, or Records? | Only a photo-visibility note names the Awards committee |
| 7 | Is there a year-end moment when the Statistician declares the numbers final? | Nothing freezes; confirm is re-runnable forever |
| 8 | Who chases the Groups to press Confirm, and what happens if one never does? | An unconfirmed month is silently absent from every report |
| 9 | Is the ROM's fiscal year the same as the DMV's Apr–Mar window? | Only the DMV's is in the code |
| 10 | Is ROM Travel's calendar-year report a bug or intentional? | Its picker says "Fiscal", its columns say Jan–Dec |
| 11 | Is the per-shift visitor count still being entered, on which Groups, and who reads the total? | [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398) records it as live practice as of 2026-08-07; the report that consumes it is proposed for retirement |
| 12 | Who reads the money reports — Docents' Treasurer report, Outreach's and Walker's `$` columns? | They exist and are ungated; no v2 ticket claims them |
| 13 | Is GDR's Tour Provenance still produced, and by whom for whom? | One Group, one report, in French, no other trace |
| 14 | Are Invitations' hours real? | Its report reads a table nothing writes; either hours are entered another way or the report has been empty for years |
| 15 | Does Walker's "Members with Zero Hours" ever produce output for anyone? | The call passes one argument to a two-argument function; the report cannot fire |

---

## References

- [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400) — this research ticket
- [#398](https://github.com/roytanaka/dmv-rom-v2/issues/398) — the scheduling second-pass map
- [#351](https://github.com/roytanaka/dmv-rom-v2/issues/351) §A2, §B1 — the two flagged changes, and the credit-path facts
- [#406](https://github.com/roytanaka/dmv-rom-v2/issues/406) — the Hours first-pass spec
- [ADR-0022](https://github.com/roytanaka/dmv-rom-v2/blob/worktree-hours-model-decisions/docs/adr/0022-hours-and-statistics-model.md) — hours and statistics (PR [#396](https://github.com/roytanaka/dmv-rom-v2/pull/396), **branch, not `main`**)
- [ADR-0021](https://github.com/roytanaka/dmv-rom-v2/blob/staging/docs/adr/0021-scheduling-first-pass.md) — scheduling first pass
- [ADR-0015](https://github.com/roytanaka/dmv-rom-v2/blob/staging/docs/adr/0015-scheduling-model.md) — scheduling model
- `docs/research/legacy-scheduling-schema.md` on branch `research/legacy-scheduling-schema`
