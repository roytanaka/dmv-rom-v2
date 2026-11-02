---
status: accepted
date: 2026-11-03
accepted: 2026-11-03
---

# Records office tools: member records, applicants and renewal

From a grilling session that ran from 2026-10-25 to 2026-11-03. It settles the data model and the behaviour of the **Records office** tools: the **Member record** and the **Member lists**, **Saved queries**, **Applicants**, the annual **Renewal**, **Service awards**, **Remove personal data** and the **Sign-in log**. It is a faithful port of the Legacy app's Records menu, ranked first on the build list. The legacy data migration spec waits on §13, the list of fields the import may overwrite. See `GLOSSARY.md` for every bold term.

The rule for this session was: **the Legacy app is the source of truth.** v2 deviates only for a privacy or security defect, or where a hard rule of this repo forbids the legacy mechanism. Every deviation is listed in §14.

## Context

In the Legacy app, the Records menu (`Members/Members.php`) is one 4,000-line file: six Member lists, Applicants, saved queries with email, print, CSV and labels, two reports, twelve reference tables, and a dozen dialogs. Who may use it is a `Records` bit on the Member row, set to 1 for 19 Members and to 2 ("assistant", same power) for 6. No function checks the bit on the server: any signed-in Member who knows a function name can call it. Query SQL is built in the browser and run as sent. The emailed query link runs the stored SQL with no login.

In v2 today, member administration is a **Stewardship** held by the Records Group ([ADR-0011](0011-authorization-model.md), [ADR-0036 §7](0036-group-administration.md)). A member admin may change only a Member's name and email. Officer Tools → Members, Reports and DMV Settings, and the `renew` route, render a Coming Soon page. The `members` table lacks about twenty legacy columns, among them the ROM membership number that Beta's first sign-in needs ([ADR-0035 §7](0035-beta-and-cutover.md)). The nightly import must know which Member fields it may overwrite ([ADR-0035 §4](0035-beta-and-cutover.md)).

Two reports from that menu are already ported: the total-hours roster and the zero-activity report ([ADR-0022](0022-hours-and-statistics-model.md)). Broadcasts to an Audience exist ([ADR-0024](0024-emailing-model.md)). Authorship is settled as columns ([ADR-0036 §8](0036-group-administration.md)).

## Decision

### 1. Three PRDs under this ADR

- **(a) Member records**: the Member record and its new fields, Category history, Member lists, reinstate, delete, Remove personal data, Service awards, Saved queries, the Sign-in log.
- **(b) Applicants**: the Applicant record, the applicant code and public form, the Applicants list, accept.
- **(c) Renewal**: the renewal steps, the code of conduct, fees and payment, the Renewal settings, the renewal reminders and the ROM-expiry notice.

Each ships on its own. (c) waits on the Records Chair for the reminder dates, the message texts, the external addresses and the Akaraisin link.

### 2. Who

- **Member administration** (the `member_admin` stewardship, Records today) covers all of (a), and the Renewal settings in (c). The super-tier inherits it.
- **Applicants** get a new stewardship, **`applicants`**, held by the Membership Group. This is the Legacy app's rule: the Membership subcommittee ran intake, not Records. The holder's Chair edits every Applicant. A member of the holder edits the Applicants they mentor and reads the rest. A Chair of any active Group reads. A member admin reads, through the Applicants Member list. The stewardship is assigned on DMV Settings like the other two ([ADR-0036 §7](0036-group-administration.md)).
- **A Member** edits their own contact fields, address, phones, ROM membership expiry, photo, skills and the renewal form. Nothing else on their own record.
- **Every action checks a policy on the server.** A member admin may edit every field of a Member record except Category, which moves only through a Category history entry (§4).

### 3. The Member record

The record gains these Legacy-sourced fields: ROM membership number, ROM membership expiry (a date, or "Life"), amount paid, **paid-through year**, fee category, entry year, the Service awards list, last award year, next award year, other awards, emergency contact and number, newsletter opt-in, status date, code of conduct year, renewal year, renewal status, the four renewal placement texts, the photo-public flag, mentor (text), volunteer experience, past experience and education (text), and a **notes** log.

- **Notes** are one text field. Each save prepends a dated line with the author's name, as the Legacy app does. Earlier lines are never edited.
- **Languages** are structured: one row per Member and language, with speaks, reads and writes flags. ASL and LSQ are languages with the speaks flag only. The Legacy app flattens them into two text columns; the import parses them. Applicants use the same shape.
- **Authorship**: `created_by` and `updated_by` on `members`, per [ADR-0036 §8](0036-group-administration.md). The record shows "Last updated by X on date", which replaces the Legacy app's initials column.
- **Email becomes nullable.** Remove personal data (§6) blanks it. A Member without an email cannot sign in, and every Member it touches already has no access.

### 4. Category and Category history

The `Category` enum gains **Donor**, **Guest**, **Volunteer**, **Admin** and **Security**, all with access tier None, outside the Directory, reachable by an Audience. The Admin rows are ROM staff mailboxes; whether they survive Cutover is a migration-spec question. **Resigned** and **Withdrawn** gain a **departure reason**: family, job, health, moved, none given, age, schedule, stopped, other, involuntary. This replaces the Legacy app's eighteen reason-coded categories with two categories and one reason.

A **Category group** is derived from the Category: Current (Active, Provisional, Honourary, Sustaining, LOA, Pre-active), Resigned (Resigned, Withdrawn), Deceased, Admin, Non-member (Donor, Guest, Volunteer), Security. **Past** is a Resigned or Deceased Member whose personal data was removed (§6). It replaces the Legacy app's "expired retired" group, which only Remove personal data ever set.

**Category changes only through a Category history entry** of (Member, category, date). The newest entry sets the Category and the status date, as the Legacy app recomputes them. A member admin adds an entry; the record shows the list. Three entries have side effects: Provisional or Pre-active to Active sets the next award year to entry year + 5 and sends a **Notice** to the mentor; a move to Resigned or Deceased sends the existing Notice to the Chairs and departs the Member's Memberships; **Reinstate** writes two entries, Reinstate and Active. Service awards do not read the history.

### 5. Member lists

Seven lists under Officer Tools → Members: **Current, Resigned, Past, Deceased, Admin, Donors, Applicants**. The first six filter on the Category group; Donors filters on the Donor Category. Row actions follow the Legacy app: Current gets Edit and Photo; Resigned gets Edit and Reinstate; Past, Deceased and Admin get Edit and Delete; Donors gets Edit. Current and Resigned get Send email, a Broadcast with the list as its Audience. The Directory stays read-only.

### 6. Delete and Remove personal data

**Delete** exists only on Past, Deceased and Admin. It removes the Member and every row that is theirs: Memberships, Category history, board positions, languages, Sign-ups, Deliveries. **It refuses when the Member has Hours records**, because those are in the department's reported totals; Remove personal data is the path for them. The Legacy app deletes the row and leaves the rest orphaned. A typed confirmation is required.

**Remove personal data** is a bulk action. The member admin picks a year, default seven years ago. For every Resigned or Deceased Member whose status date is before 1 January of that year, it blanks the address, phones, email, membership number, expiry, paid-through year, experience and education, skills, languages, next award year, emergency contact, photo and notes; sets the photo-public flag off and amount paid to null; and marks the personal data as removed, which puts the Member in the Past list. It also deletes every Applicant whose status date is before that year. It keeps the name, the Category history, board positions and Hours records. A typed confirmation names the count. A log line records the count, the actor and the time. The Legacy app has neither.

### 7. Saved queries

A **Saved query** is a named filter over Member data, stored as structure, not SQL: up to ten clauses, each a field, an operator (is, is not, less than, greater than, contains, does not contain) and a value, joined by AND or OR; one sort field, ascending. Fields come from the Member record, Memberships (Group and years), board positions (position and years) and Category history (category and date). When no clause names a Category group, the query runs on the Current group, as the Legacy app prepends. The Legacy app's Projects table is checked at PRD time; if v2 has no equivalent it is not a source.

A run shows the count, the clause text, and a table with a checkbox per row. Four actions: **Send email** (a Broadcast to the checked Members), **CSV** (every output column, through the existing export), **Labels** (a print-styled page in the Avery 5160 grid, three by ten, name and address, country shown only outside Canada) and **Email results** (a message to chosen Members with a link that opens the query behind login and member administration).

The membership number, the password, and any other secret are never selectable output fields.

### 8. Applicants

An **Applicant** is its own record, not a Member. Fields: names, address, phones, email, ROM membership number and expiry, emergency contact and number, mentor (a Member), placement (a Group), **Applicant status**, status date, departure reason when incomplete, applied-on date, entry year, notes log, languages, and the Member it became. The seven statuses are New, Assigned for interview, Accepted, Placed, Yellow card completed, Ready to go active, Incomplete. The status date moves on every status change.

**The applicant code** is one shared code with a publish flag and an expiry date, set by the Chair of the `applicants` holder on the Applicants page. When published, the sign-in page shows "enter the code you were given". The public form takes names, address, phones, email, membership number and expiry, emergency contact, and languages. The same form, opened by a holder member, also takes the mentor and records who entered it.

**Accept** runs when the status becomes Accepted or Placed and no Member is linked: it creates a Member with Category Provisional, copies the matching fields, writes the Category history entry and sets the entry year. When the email and name match an existing Member (a Donor, or someone who resigned), the fields are copied onto that Member and the Category does not change. The Applicant row stays, linked. Three more status changes send a **Notice**: Placed to the mentor; Yellow card completed to the Treasurer of the DMV Executive; Incomplete with a linked Member to the mentor, with the reason. Accept itself sends a Notice to the Records Audience and the mentor.

**Delete** is allowed while no Member is linked. **Print record** is a print-styled page. The Applicants list filters by status, searches by last name, and has Send email and Print.

### 9. Renewal

**Renewal settings** is a page under Officer Tools → Members for the member admin: show-renewal flag, renewal year, the Active send date and its two messages, the Sustaining send date and its two messages, and the Sustaining attachment. It replaces the Legacy app's one-row control table, which Records edits once a year.

**The renewal link** appears for a Member whose Category is Active, Pre-active or Sustaining when the show flag is on and the paid-through year is before the renewal year. With the flag off and no paid-through year, it reads Provisional to Active. A Member with renewal status LOA sees it too. A Member opens only their own.

**Four steps**: the code of conduct (skipped when already acknowledged for the year; the save stores the year only), the form, skills, the payment page. The form carries: membership number with a confirm checkbox, expiry (date or "Life"), names, address, phones, a placements checklist built from the Member's Memberships and roles plus two "other placement" selects and two free texts, emergency contact and number with a verify checkbox, and the Category choice from the fee table. Save writes the renewal status, the renewal year, the placements, and, except for a Provisional Member, the amount paid and the fee category. It sends a Notice to the Records Audience with the changed fields. A choice of Resign, LOA, or Active to Sustaining ends the flow with a message that Records will be in touch.

**Fees** are a seeded table: Active, Active senior, Active student, Sustaining, Sustaining senior, LOA, Honorary, with amount and active flag. **The payment page** shows the amount, a link to the ROM's Akaraisin page that carries only the amount, and cheque instructions. Nothing comes back from Akaraisin. The member admin types the paid-through year, with a +1 helper. Paid-through before the renewal year means renewed, not yet paid.

**Renewal reminders** join the daily mail pass ([ADR-0024](0024-emailing-model.md)). The first goes to every Active Member whose paid-through year is before the renewal year, once, on or after the Active send date plus six weeks; the second at ten weeks. Sustaining Members get the same from their date, with the attachment. Each is a Delivery with a sent record, so a missed day catches up; the Legacy app fires only on an exact day match. A summary goes to the Records Audience. **The ROM-expiry notice** goes on the first of June, August, October, December and February to Active and Sustaining Members whose ROM membership expiry is past. The Legacy app calls this "fees not paid"; it is not about DMV dues. Both are off during Beta ([ADR-0035 §6](0035-beta-and-cutover.md)).

### 10. Service awards

Manual, as the Legacy app. The member admin adds an award from the seeded list (5 to 60 years, in steps of 5) to the Member's list and sets the last award year; the next award year becomes last + 5. The move from Provisional to Active sets next = entry year + 5 (§4). Nothing is computed from hours or from the Category history.

### 11. Reference data stays seeded

Fee categories, service awards, applicant statuses, departure reasons and skills categories are seeded tables or enums with no editor. They change once a year at most, by a seeder change. Renewal settings (§9) is the one Records-editable table. Age range is dropped: the Legacy app stopped capturing it. The Skills catalogue editor stays deferred.

### 12. Sign-in log

v2 records every sign-in: Member, time, IP address, user agent. After one year a nightly job blanks the IP address and user agent; the row stays. The Member record shows the last sign-in; the member admin sees a sign-ins count. The legacy log imports as Member and time only. The Legacy app keeps IP and browser for every sign-in since 2011 and nothing reads them.

### 13. Legacy-sourced and v2-only fields

The import overwrites Legacy-sourced fields on every run and never touches v2-only fields ([ADR-0035 §4](0035-beta-and-cutover.md)).

| Legacy-sourced (import overwrites) | v2-only (import leaves alone) |
|---|---|
| first name, last name, email, phones (3), address (5) | password, remember token, email verified at |
| Category, departure reason, status date, Category history | first-sign-in hash (import writes it once, never restores it) |
| membership number, ROM membership expiry | `support_operator`, `no_email`, `super_tier` |
| amount paid, paid-through year, fee category | locale |
| entry year, service awards, last and next award year, other awards | personal data removed at |
| emergency contact and number, newsletter opt-in | `created_by`, `updated_by` |
| code of conduct year, renewal year, renewal status, placements (4) | Sign-in log rows written by v2 |
| photo, photo-public flag, mentor, experience and education, notes | |
| languages (parsed), skills | |
| board positions, Applicants and their fields, Saved queries, Renewal settings, fee table | |

The Sign-in log is Legacy-sourced for rows before Cutover and v2-only after. The departure reason comes from the eighteen legacy reason codes. The Past list comes from the legacy "expired retired" group.

### 14. Deviations from the Legacy app

Each is a privacy or security defect, or a hard rule of this repo.

1. Every action checks a policy. The Legacy app checks no role on any Records function.
2. Query filters are stored as structure and the server builds the query with Eloquent. The Legacy app builds SQL in the browser and runs it as sent.
3. Email results goes to Members only, and the link opens behind login and member administration. The Legacy app emails a link to any typed address that runs the stored SQL with no login.
4. The membership number, the password and other secrets are never query output. The Legacy app lets a query select and export the password column and the membership number, which is its password.
5. Notices go to the Records Audience and the mentor in-app. The Legacy app emails an applicant's home address and renewal diffs to hard-coded ROM mailboxes and a developer's personal Gmail. External addresses become a Renewal setting, empty until the Records Chair confirms them.
6. The applicant code is checked before any row is created, attempts are throttled, and the expiry date is honoured. The Legacy app inserts a blank row first, compares loosely, and ignores its own expiry column.
7. Remove personal data and Delete require a typed confirmation and write a log line. Delete refuses a Member with Hours records. The Legacy app confirms nothing and leaves orphans.
8. Labels and the applicant print record are print-styled pages, not PDFs. No PDF package is added.
9. Renewal reminders are windows with a sent record, not exact day matches, so a missed day catches up.
10. The Sign-in log blanks IP and user agent after a year.

### 15. Dead, not ported

The password column (the Legacy app compares the membership number instead), `Mode`, the second newsletter column, the Zoom host id, the Covid proof fields, `Interests` (no writer), the role bits (v2 roles), `Full_Name`, the Assistants button (a Records bit of 2 with the same power), the `MORE` and `EXEC` pseudo-skills and the proficiency digit, age range, the applicant interview date, comfort, reasons and times blocks, the applicant skills dialog (no caller, wrong key), the Covid Member list, the DMV Renewal attachment on Active reminders.

## Interaction with Beta and the import

- The import derives the first-sign-in hash from the last four characters of the membership number on the first run only ([ADR-0035 §7](0035-beta-and-cutover.md)).
- The import maps the eighteen reason-coded legacy categories to Resigned or Withdrawn plus a departure reason, and the "expired retired" group to the personal-data-removed marker.
- The import parses the two legacy language columns into language rows, and the applicant status table's duplicate New rows into one.
- Every Notice, renewal reminder and the ROM-expiry notice is off during Beta.
- Member lists, Saved queries and the record are usable on Beta with real data; the Legacy app stays the system of record, so a member admin's edits on Beta are overwritten the next night. The Members page says so during Beta.

## Considered alternatives

- **An Applicant as a Member row with a pre-active Category.** Rejected: the Legacy app keeps a separate table, and a separate table keeps unaccepted people out of the Members table, the Directory, Audiences and sign-in.
- **A PDF package for labels and the applicant record.** Rejected: a print-styled page does the job and adds no package. Reopen if the Records office reports that the grid does not print true.
- **A generic reference-table editor.** Rejected: twelve tables that change once a year do not justify it. Renewal settings, the one table Records edits yearly, gets its own page.
- **Dropping the Sign-in log.** Rejected on the user's word: it is used for troubleshooting and statistics. Retention is the compromise.
- **Keeping queries as SQL text.** Rejected by the raw-SQL hard rule and the injection it carries.

## Consequences

- **Officer Tools → Members** stops being a stub: seven lists, the record, Saved queries, Applicants, Renewal settings.
- **The `members` table gains about twenty columns**, nullable email, `created_by` and `updated_by`. New tables: category history, languages, board positions, applicants, saved queries, renewal settings, sign-in log, fee categories, service awards.
- **The `Category` enum gains five values** and a departure reason beside it. Every `match` on the enum is touched.
- **A third Stewardship, `applicants`**, appears on DMV Settings.
- **The daily mail pass gains** renewal reminders and the ROM-expiry notice; the Notice table gains the applicant and activation events.
- **The migration spec** takes §13 as its field list.
- **Help articles** ([ADR-0025](0025-help-centre.md)): Member records, Saved queries, Applicants, Renewal, Renewal settings.
- **Open**: the Records Chair confirms the reminder dates, messages, external addresses and Akaraisin link before (c) is built. The Projects query source is checked at PRD time.

## References

- [ADR-0001](0001-authentication-and-identity.md) — one unique email per Member; amended here to allow null after Remove personal data
- [ADR-0011](0011-authorization-model.md) — member administration as a stewardship
- [ADR-0017](0017-authorization-enforcement.md) — field-visibility tiers
- [ADR-0018 §4](0018-server-driven-grouping-rail.md) — Officer Tools → Members
- [ADR-0022](0022-hours-and-statistics-model.md) — the two reports already ported
- [ADR-0024](0024-emailing-model.md) — Audiences, the daily mail pass, Notices
- [ADR-0035](0035-beta-and-cutover.md) — the import rule and first sign-in
- [ADR-0036](0036-group-administration.md) — stewardships page, authorship columns
