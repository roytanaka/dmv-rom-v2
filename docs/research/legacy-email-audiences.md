# Legacy email Audiences, mapped onto the Group model

Research for [#462](https://github.com/roytanaka/dmv-rom-v2/issues/462), on the emailing map [#458](https://github.com/roytanaka/dmv-rom-v2/issues/458). Charting fixed *who may send to whom* (map item 6). This note fixes *what each named Audience contains*, by reading the queries behind every legacy list that carries a "Send email" button and mapping each onto `Group`, `Category`, `MembershipStatus`, `Role`, and stewardship.

**Sources.** Legacy is described by page and feature name only (public repo). Primary sources were the DMV member list ("Who's Who") and its selector, the shared committee roster page and the four committees that override it (Gallery Interpreters, Docents, Guides du ROM, ROMWalks), the sub-committee roster page, the Visitor Wayfinders schedule views, the Invitations page, the shared composer dialog and its send endpoint, the meeting-notice helpers, the shared address formatter, and the private archaeology vocabulary notes. The v2 side is `CONTEXT.md` (Officer, Admin → member administration, Directory, Schedule, Shift, Sign-up), `app/Enums/{Category,MembershipStatus,Role,StewardshipFunction}.php`, `app/Models/{Group,Member}.php`, and [ADR-0010](../adr/0010-group-model.md).

**Vocabulary.** Legacy "committee" is a v2 **Group**; a legacy "sub-committee" is a **child Group**. The legacy DMV as a whole is modelled as a pseudo-committee whose sub-committees are the org-level bodies (Records, Awards, Communications, Nominations, Governance, Executive, and others). Legacy "Events Resource" is **Visitor Wayfinders**.

## How legacy builds a recipient list

Every legacy list page with a "Send email" button works the same way:

1. The page query returns rows, and the server builds a parallel list of addresses, one slot per row — **blank where the row has no address or the address carries the no-email sentinel** (an address whose domain part begins with a "noemail" marker).
2. The list is returned to the browser with the table. On roster pages the button opens the composer with the **checked rows only**; a header checkbox checks every row ("send to all"). On the Wayfinders schedule pages and the Invitations page there are no checkboxes: the button takes the whole list.
3. The browser posts the addresses back to the composer as a `Bcc:` string. The composer shows a CC/BCC choice only for a sub-committee list or fewer than 20 recipients, and **defaults to CC for a sub-committee list of ten or fewer** (the CC-exposure defect charting fixed). The send endpoint appends the sender's own address (the sender's copy), sets Reply-To to the sender, de-duplicates, drops anything blank, containing the sentinel, or failing address validation, and mails in blocks of 90. Past 89 recipients it appends the unsubscribe footer that never worked. Nothing on the server re-derives the audience — the "recipient list trusted from the browser" defect.

So a legacy Audience is really *the standing filter of the list page the sender happened to be on*. The rest of this note records those filters.

## Legacy standing vocabulary used below

**DMV-wide category.** One column on the member table, joined to a category lookup. The lookup's *current-member* group holds Active, Provisional, PreActive, Sustaining, Honourary, and Leave of Absence. The remaining codes are exit reasons (Resigned-*, Withdrawn-*, Deceased) and non-member account types (Donor, Volunteer, Guest, Security, Admin). v2 `Category` carries the six current codes plus Withdrawn, Resigned, and Deceased; the non-member account types do not exist in v2.

**Per-committee status.** One row per member on each committee's roster table, with a numeric status id and a text label. The id scale is shared across committees, and each committee enables its own subset through a per-committee status lookup: 0 Admin (a placeholder row), 1 Full, 2 LOA, 3 Emeritus, 4 Trainee, 5 Provisional, 6 Auxiliary, 7 Projects, 8 Inactive, 9 Donor (Friends groups) or **Temporary** (Visitor Wayfinders, for a member of another committee who takes a shift), 10 Resigned, 99 Deceased. Docents also carry Transitional, addressed by label. Every roster row also carries an **active flag**, copied from the status lookup's per-status "active" value when the row is added or changed — so "active" is a property of the *status*, set per committee as data. The code fixes only three points of it: Projects and Inactive are created inactive, a Wayfinders Temporary row is created active, and a Resigned row is set inactive. v2 `MembershipStatus` carries Full, Loa, Trainee, Transitional, Auxiliary, Projects, Emeritus, Inactive, Resigned, Deceased, Donor. Legacy per-committee **Provisional**, **Admin**, and **Temporary** have no v2 status (see Assumptions).

**Executive positions.** Every committee has a positions table (Chair, Vice-Chair, Secretary, Treasurer, Scheduler, Past Chair, and so on — free text) joined to the roster row, plus a `Chair` bit on the roster row itself. The DMV as a whole has a separate **board positions** table (President, Vice-Presidents, Secretary, Treasurer, section heads) keyed directly on the member.

**DMV role bits.** The member table carries five booleans — Chair (the President), Secretary, Treasurer, Statistician, and Records — read as org-wide flags. In v2, "Records" is membership in the Group that stewards `member_admin` (`Member::hasMemberAdminAuthority()`); the President is super-tier; the other three are Roles in the DMV Executive Group.

## 1. Org-wide sets — the DMV member list

The DMV "Who's Who" page is available to every logged-in member whose category grants full access (Limited and None categories collapse the menu to "About Us").

**The selector** offers, in order:

| Option | Shown to |
| --- | --- |
| All Members *(default)* | everyone |
| All Members + LOA | the extended set below, **and** the requester is the DMV Chair or holds the Records bit |
| Active and Provisional Members | the "extended" set: any of the five DMV role bits, or Chair of a DMV sub-committee, or Chair of any committee |
| one option per current-member category (Active, Provisional, Honourary, Sustaining, Leave of Absence, PreActive) | the extended set |
| DMV Board of Directors | everyone |
| Committee Chairs | everyone |
| Committee and DMV SubCommittee Chairs | everyone |
| Emergency information for All Members | only the Security account type and the two lowest-numbered accounts |

**The "Send email" button** on the resulting table appears when the requester holds any of the five DMV role bits, *or* chairs a DMV sub-committee, *or* is an active member of the Awards sub-committee, *or* chairs any committee, *or* — whoever they are — picked Board, Committee Chairs, or All Chairs. So any full-access member can broadcast to the Board and to the Chairs; only role-holders can broadcast to the membership.

**Standing filters:**

| Option | Legacy filter | v2 definition |
| --- | --- | --- |
| All Members | category in the current-member group **and not LOA** | `Category` in {Active, PreActive, Provisional, Sustaining, Honourary} — every Category whose `accessTier()` is not None, minus Loa |
| All Members + LOA | category in the current-member group | `Category` with `accessTier()` not None (the six current standings) |
| Active and Provisional | category is exactly Active or Provisional | `Category` in {Active, Provisional} (PreActive, Sustaining, Honourary are *not* included) |
| per-category | category equals the picked code | `Category` equals the case (Active, Provisional, PreActive, Sustaining, Honourary, Loa) |
| DMV Board of Directors | every row of the board positions table joined to the member; **no category filter** | the roster of the DMV Executive Group in present standing (see § 2 for "present") |
| Committee Chairs | for every active committee except the DMV pseudo-committee (first in display order): roster rows with the Chair bit set and status not Admin, whose first executive position title contains "chair" or "president" | `Role::Chair` holders in every active Group that was a legacy top-level committee |
| Committee and DMV SubCommittee Chairs | the above, plus the chair-flagged, active members of every active DMV sub-committee | `Role::Chair` holders in every active Group, any depth |
| Emergency information | current-member group, ordered by surname, showing phones and emergency contact; **no email column, no send button** | not an email Audience — a Records report; out of scope here |

Notes. "All Members" is **not** the v2 Directory: `Category::grantsDirectoryListing()` lists Active, Honourary, Sustaining, and LOA, whereas this Audience drops LOA and adds Provisional and PreActive. The spec should not reuse the Directory scope for it. The Board list has no standing filter at all, so a board row for a member who has since resigned still receives mail until the row is deleted. The two Chairs lists count only the DMV's own sub-committees, never a committee's internal sub-committees, and (unlike the "section chair" helper used elsewhere, which skips Special Projects) they include every active committee.

## 2. Per-Group sets — a committee's member list

Every committee has a "Who's Who" page. Its menu item is shown to members whose roster row is not Resigned, Donor, or Deceased, and to the DMV Chair; a non-member can still reach a Friends group's page, where they see Full members only (a Donor viewer sees everyone). **The "Send email" button is unconditional on every committee roster view** — any viewer who can open the list can hand-pick from it, select-all included. This matches charting item 6.

### The shared roster page (most committees)

Selector: All Members *(default)*, All + LOA, Active, one option per status the committee has enabled (ids 1–9), and Executive ("Bureau" on the French-language Friends group).

| Option | Legacy filter | v2 definition |
| --- | --- | --- |
| All Members ("whole committee") | status id between 1 and 9, **excluding 2 (LOA)** — so Full, Emeritus, Trainee, Provisional, Auxiliary, Projects, **Inactive**, and **Donor/Temporary** are all in | **present standings**: `MembershipStatus::canSignUp()` true — Full, Trainee, Transitional, Auxiliary, Projects, Emeritus, Donor. Inactive is dropped (see Assumptions) |
| All + LOA | status id between 1 and 9 | present standings plus `Loa` |
| Active | the roster row's active flag is set (on the Joint and Wayfinders committees: status id 1 exactly) | `MembershipStatus::Full` — the flag is per-committee data with no v2 counterpart, and Full is the one status every committee treats as active; a Group wanting the wider set uses "whole Group" |
| one status | status id equals the picked id | `MembershipStatus` equals the case |
| Executive | every row of the committee's positions table, joined through the roster row; **no status filter** | every membership in the Group holding at least one `Role` — the glossary's **Officer**. Not a fixed subset of roles |

### Committees with their own roster page

Four committees override the shared page. Their differences:

- **Gallery Interpreters** — everyone sees All Members, Executive, and **Trainers** (roster rows with the trainer bit). "All Members" is Full, Emeritus, Auxiliary, Projects only (no Trainees, no Inactive). All + LOA, All + Inactive, All + Inactive + LOA, and the per-status options are shown to the Chair and Secretary only.
- **Docents** — "All" and "All + LOA" as the shared page. Adds Full + Transitional + Auxiliary, Full + Auxiliary, per-status by label, **Section Admins** (the head of every active section in the first three tour categories), and one list per tour (docents assigned to that tour who are Full, Emeritus, Transitional, or Trainee, with an active assignment). "Active" is the label Full exactly.
- **Guides du ROM** — the shared shape in French; "All" and "All + LOA" as above, per-status from the lookup, Bureau.
- **ROMWalks** — "All" and "All + LOA" as the shared page; "Active" is the active flag. Adds Leave of Absence, Trainees, Emeritus, **Coordinators** (the coordinator of every active walk), **Day availability** (rows with the short-notice bit, any status), and — for members only — one list per walk (walkers assigned to it with the active flag set).

Trainers, Section Admins, Coordinators, tour and walk lists, and Day availability are **hand-pick surfaces**, not named Audiences: the sender still ticks names. Their v2 home is a scoped role or a content-catalog attribute (ADR-0010 § Scoped roles), and none of those exist yet — see Assumptions.

### Send-time audiences used by automatic mail

The meeting-notice feature (the Zoom-meeting mail) is the one place legacy resolves an audience on the server from a stored kind, and its helpers are *stricter* than the roster pages:

| Meeting kind | Legacy filter | v2 definition |
| --- | --- | --- |
| whole committee | status id 1, 3, 4, 5, 6, 7 — "all except LOA", which also drops Inactive and Donor/Temporary; for the DMV pseudo-committee: category in the current-member group | present standings (matches the Broadcast definition above once Inactive is dropped) |
| committee executive | positions table, no status filter | every Role holder |
| sub-committee | sub-committee rows with the **active flag** set | the child Group's present-standing roster |
| ROMWalks coordinators | coordinator of every active walk | deferred with the walks catalog |

## 3. Signed-up sets — a Schedule's Sign-ups

Only Visitor Wayfinders has a "Send email … to signed-up members" button, on each of its three schedule views (calendar grid, across-events, upcoming). It is gated on the **committee Chair bit only** — a member holding the Scheduler position but not the Chair bit sees the schedule without the button. There are no checkboxes: the list is every member placed on any shift the view shows, de-duplicated only at send time.

What the view shows decides the set:

- **calendar grid** — every shift of the event that falls in the **calendar month the event starts in**, past days of that month included;
- **across-events** and **upcoming** — every shift of the event dated today or later.

So legacy addresses **an event (or a month of it), never one shift and never one day**, and two of three views trim to upcoming shifts. Wayfinders shifts may be filled by members of other committees (the shift's eligibility points at another committee, and some shifts mirror a Visitor Guides desk slot and read the member from there), so the set can include people who are not on the Wayfinders roster.

| Audience | v2 definition |
| --- | --- |
| Sign-ups across a Schedule | distinct Members holding a Sign-up on any Shift of the Schedule whose `ends_at` is now or later |
| Sign-ups on one Shift | distinct Members holding a Sign-up on that Shift — new in v2; legacy has no shift-level send |

The Invitations page has a "Send an email … to the Guests who have Accepted" button (every invitee row marked accepted, sentinel skipped, shown to anyone on the page). Invitations are unbuilt in v2; this is owed with that feature, not an Audience here.

## 4. A child Group's roster, addressed from the parent

The sub-committee roster page is reached two ways: from the parent committee's Who's Who via the **SubCommittees** button (shown to the committee Chair; on the DMV page, to the DMV Chair or Records), and through the sub-committee switcher, which lists a sub-committee to its own members (status below Resigned), the parent committee's Chair, and the DMV Chair. Once on the page the Send email button is unconditional.

The roster query takes sub-committee rows with **status id between 1 and 9 and ignores the active flag** (the active-flag clause is commented out). A member set to LOA on a sub-committee keeps status 2 with the active flag cleared, so **LOA and Inactive rows appear on the roster**; Resigned (10) and Deceased (99) rows, and hard-deleted rows, do not. The send-time helper for the same sub-committee (§ 2) uses the active flag instead, which excludes LOA and Inactive.

| Audience | v2 definition |
| --- | --- |
| a child Group's roster, from the parent | memberships of the child Group in present standing (`canSignUp()` true). LOA is excluded, matching the send-time helper rather than the roster page |

Who may pick it: the parent Group's officers (charting item 6; legacy's SubCommittees button was Chair-only), and the child's own members hand-pick from the same roster.

## 5. Exclusions applied everywhere

| Rule | Where legacy applies it | v2 |
| --- | --- | --- |
| **no-email sentinel** — an address whose domain begins with a "noemail" marker | list-building on every page, the meeting-notice helpers, the shared address formatter, and the send endpoint | survives as the **no-email flag** (map item 8): a Records-set switch that silences every mail to the Member, and tells the sender the Member cannot be reached. The legacy sentinel becomes the flag in the migration plan |
| blank address | every list builder and the send endpoint | dead — a Member has exactly one identity with a unique email (ADR-0001). Keep a defensive null-skip, no rule |
| the shared past-president sign-in account | one place only: the sub-committee roster page blanks its address | dead — no shared accounts in v2 |
| the Security account type | sees only the emergency list, never a send button | dead — non-member account types do not exist in v2 |
| status Admin rows (id 0) | excluded from the Chairs lists and from every whole-committee query | dead — no Admin membership status in v2 |
| address validation and de-duplication | the send endpoint validates each address and de-duplicates the block | de-duplicate by Member, since one Member can reach an Audience through several memberships; validity is guaranteed at identity |
| the daily cap | a browser-side check that always passes | fixed by charting, not an audience rule |

## Audience name → who may pick it → v2 definition

"Present standing" means `MembershipStatus::canSignUp()` true: Full, Trainee, Transitional, Auxiliary, Projects, Emeritus, Donor. "Records" means member-administration authority — a membership in `Group::stewardOf(StewardshipFunction::MemberAdmin)`; super-tier inherits everything. "The Group's officers" means memberships holding any `Role` in that Group.

| Audience | Who may pick it | v2 definition |
| --- | --- | --- |
| All Members | Records | `Category` with `accessTier()` not None, minus Loa: Active, PreActive, Provisional, Sustaining, Honourary |
| All Members + LOA | Records | `Category` with `accessTier()` not None (all six) |
| Active and Provisional | Records | `Category` in {Active, Provisional} |
| One Category | Records | `Category` equals the chosen case (six current standings) |
| Board of Directors | Records | roster of the DMV Executive Group in present standing |
| Committee Chairs | Records | `Role::Chair` holders in every active Group that was a legacy top-level committee |
| All Chairs | Records | `Role::Chair` holders in every active Group, any depth |
| Whole Group (roster select-all) | any member of the Group | memberships of the Group in present standing |
| Whole Group + LOA | any member of the Group | present standing plus `Loa` |
| One status | any member of the Group | memberships with that `MembershipStatus` |
| The Group's officers | the Group's officers | memberships of the Group holding at least one `Role`, any role |
| Sign-ups across a Schedule | the Group's officers | distinct Members with a Sign-up on any Shift of the Schedule ending now or later |
| Sign-ups on one Shift | the Group's officers | distinct Members with a Sign-up on that Shift |
| A child Group's roster | the parent Group's officers | memberships of the child in present standing |
| One Member | any Member | that Member, from the Directory or a profile |
| Hand-picked from a roster | any member of the Group | the ticked memberships, drawn from any list the Group's roster page offers |

## Assumptions

Where legacy was ambiguous or contradicted itself, the safe default chosen and why:

1. **Whole Group drops Inactive.** The roster page includes Inactive (id 8) in "All Members"; the meeting-notice helper for the same committee excludes it, and Gallery Interpreters' own page excludes it too. v2 reuses the existing per-Group floor (`canSignUp()`), which excludes Inactive, rather than inventing a third status partition. A Group wanting Inactive members picks the "One status" list.
2. **Whole Group keeps Donor.** The roster page includes Donor; the meeting-notice helper excludes it. Kept, because `canSignUp()` keeps it and a Friends Group's roster *is* donors — excluding them would make a Friends Broadcast reach nobody.
3. **"Active" maps to Full.** Legacy's active flag is copied from a per-committee lookup when a row is written, so which statuses count as active is data that differs by committee (two committees define it as Full exactly). No v2 concept matches the flag; Full is the one status every committee agrees on.
4. **Officers means every Role.** Legacy's executive lists are free-text position rows with no status filter, so a Vice-Chair or Past Chair is in, and a Scheduler is in only if someone typed a position row. v2 has a closed Role catalog and no positions table; the Audience is every Role holder. Positions with no v2 Role (Vice-Chair, Past Chair, member-at-large) are a roster-migration question, not an Audience one.
5. **Board of Directors is the DMV Executive Group's roster.** Legacy keeps the board in a separate positions table with no standing filter, and *also* has a DMV sub-committee named Executive. v2 has one DMV Executive Group; the board is its present-standing roster. A board row for a departed member no longer lingers.
6. **Committee Chairs versus All Chairs.** The distinction is whether Chairs of the org-level bodies (legacy DMV sub-committees) count. Both are kept because both are one query, but v2 "All Chairs" also picks up Chairs of a committee's internal child Groups, which legacy did not — a superset judged harmless.
7. **Org-wide Audiences require Records.** In legacy any full-access member could broadcast to the Board and to the Chairs, and any committee Chair could broadcast to All Members. Charting item 6 gives the org-wide Audiences to member-administration authority only. Recorded here as the one residual fork for the human: does a committee Chair keep the ability to mail All Members, or does that go through Records?
8. **Schedule Sign-ups are upcoming only.** Two of three Wayfinders views trim to today-or-later; the calendar grid shows the event's starting month whole, past days included. Upcoming is the default because the mail's purpose (a change to the event) concerns people still due to work it. A Schedule that has fully passed resolves to nobody.
9. **A child Group's roster excludes LOA.** The roster page includes LOA rows; the meeting-notice helper excludes them. The helper is the closer analogue of a server-resolved Audience, and it keeps the child definition identical to Whole Group.
10. **Legacy per-committee Provisional, Admin, and Temporary have no v2 status.** Provisional falls into `Category::Provisional` on the Member and `Trainee` on the membership at migration; Admin rows are placeholders and are not migrated; Wayfinders Temporary rows are the legacy way of letting another committee's member hold a Wayfinders shift, which v2 does with an `open` Shift audience and no membership. None changes an Audience: Provisional was in every whole-committee set, Admin in none, and a Temporary holder is reached through the Sign-up Audiences rather than Whole Group.
11. **Trainers, Section Admins, Coordinators, per-tour and per-walk lists, Day availability** are not named Audiences. They are hand-pick surfaces on three Groups' roster pages, and depend on scoped roles and a content catalog that v2 does not yet have. Owed with those features; until then the Group hand-picks from its roster.
12. **The Scheduler role also gets the Sign-up Audiences.** Legacy gates the signed-up send on the Chair bit alone. Charting says "the Group's officers", and `Group::schedulers()` already treats Chair and Scheduler alike (ADR-0011), so the widening is deliberate, not an oversight.
