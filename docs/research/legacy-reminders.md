# Legacy Reminder and empty-desk jobs

Research for [#463](https://github.com/roytanaka/dmv-rom-v2/issues/463), on the emailing map [#458](https://github.com/roytanaka/dmv-rom-v2/issues/458). Answers the six numbered questions in the ticket and proposes the shapes the spec needs: a bilingual chrome template, the per-Group settings, and the sent record.

**Sources.** Legacy is described by feature and module name only (public repo). Primary sources were the legacy nightly job and each committee's daily module in the live tree, the GDR schedule page's cancellation handler, and the private archaeology notes on the nightly cron. The v2 side is `CONTEXT.md` (Schedule, Shift, Shift kind, Sign-up, Audience), `app/Models/{Schedule,Shift,ShiftKind,SignUp}.php`, `app/Mail/SignUpCancelled.php` with its view and `lang/{en,fr}/scheduling.php`, [ADR-0015](../adr/0015-scheduling-model.md) and [ADR-0021](../adr/0021-scheduling-first-pass.md).

**Vocabulary.** Legacy "committee" is a v2 **Group**. Legacy "Events Resource" is the Group now called **Visitor Wayfinders**. Legacy "tour" (Docents, GDR) and "shift" (Visitor Guides, Reception, Wayfinders) are both a v2 **Shift**; a legacy "sign-up row with a member on it" is a v2 **Sign-up**.

## 0. How the nightly job works

- One cron hit per day, early morning server time, on a publicly reachable URL (defect 3 in #458; the rebuild uses a control-panel cron calling the Laravel scheduler, charting item 18).
- The job holds a **hardcoded list of committees**: Docents, GDR, Visitor Wayfinders, Visitor Guides, Reception, Invitations. Four more names — Gallery Interpreters, Outreach, "marbrk", ROMWalks — sit in the list **commented out**. For each active name it includes that committee's daily module and calls its one entry function. A module that is missing or throws mails an error to two administrator mailboxes and the loop moves on.
- Every send goes through one shared helper: skips an empty address or the **no-email sentinel** (an address containing a "noemail" domain — the legacy opt-out that #458 item 8 replaces with the no-email flag); wraps the body in `<html><body>`; declares **charset ISO-8859-1**; sets From to a fixed display name **"DMV Reminder"** on the administrator mailbox; no Reply-To; counts the send toward the daily tally (cap observed, not enforced — #458 "Not yet specified").
- Each module's reminder is a **date-equality match**: it selects rows whose date is exactly `today + lead days`. There is no window and no sent record, so a day the cron does not run is a day of reminders that are never sent (the archaeology notes record the same non-retry for the renewal reminders). A Sign-up made *after* its reminder day gets no reminder.
- All modules format the date as `Monday, September 7` (English day and month names) and the time as `g:i` — **no AM/PM**, so "at 1:30" is ambiguous. Nothing names the end time.
- The audit trail is four "Start / Connected / Complete / error" emails to an administrator per run.

The same job also runs three unrelated things (stale-document sweep, fees-not-paid notice, membership-renewal reminders). They are not Reminders in the #458 sense and are out of scope here; the renewal ones are owed with the renewal feature (#458 item 2).

## 1. Which Groups run Reminders, and with what lead

| Legacy committee | v2 Group | Runs today | Module exists | Lead days | What it reminds about |
| --- | --- | --- | --- | --- | --- |
| Docents | Docents | **on** | yes | 3 | scheduled tours; group tours (booking) |
| GDR | Guides du ROM | **on** | yes | 3 | scheduled tours; group tours (booking) — French |
| Events Resource | Visitor Wayfinders | **on** | yes | 3 | event shifts |
| Visitor Guides | Visitor Guides | **on** | yes | 3 | desk / shadow / other shifts, plus the **empty-desk alert** |
| Reception | Reception | **on** | yes | 3 | desk shifts |
| Invitations | (Invitations — not built) | **on** | yes | **4** | accepted invitations |
| Gallery Interpreters | Gallery Interpreters | off (commented out) | **no** — no daily module in the live tree | — | — |
| Outreach | Outreach | off (commented out) | **no** | — | — |
| "marbrk" | — | off (commented out) | **no** — no such committee directory exists | — | — |
| Walker | ROMWalks | off (commented out) | **no** | — | — |
| ROMBus, ROM Travel, others | — | not listed | no | — | — |

Findings:

- **Every running module uses 3 days except Invitations, which uses 4.** Confirmed: the Invitations module sets its lead to four days and matches events whose start date equals today + 4. Charting item 13 ("3 by default; Invitations will set 4") is right.
- **The "switched off but still exist" set is empty.** The four commented-out names have no daily module anywhere in the live tree; they are names in a list, nothing more. Nothing to port for them. If Gallery Interpreters, Outreach or ROMWalks want Reminders in v2, the per-Group switch gives it to them for free.
- Docents and GDR each send **two kinds** of reminder: one for a scheduled tour (a Shift) and one for a **group tour** (a booking with a named client). Group tours are the deferred booking capability ([ADR-0021](../adr/0021-scheduling-first-pass.md), #333). The group-tour reminder is **owed with that feature** (#458 item 2), not specced here.
- Invitations is an unbuilt feature; its reminder is likewise owed with the feature. Its rules are recorded in §2 so the ADR can list them.

## 2. Reminder content, module by module

Common to all: one email per person per row; salutation "Dear *First Last*" on its own line; body is a single sentence or a few `<br>`-separated lines; no end time; no sign-off unless noted; no link unless noted.

| Group | Subject | Names | Link | Language |
| --- | --- | --- | --- | --- |
| Docents (tour) | "ROM Docent Tour reminder" | tour name (kind), day-date, start time. "…you are giving a scheduled *{tour}* tour on *{date}* at *{time}*" | none | English |
| Docents (group tour) | "ROM Docent Group Tour reminder" | tour name, **client name**, day-date, start time | none | English |
| GDR (tour) | "Guides du ROM Tour reminder" | tour name, day-date, start time. "Ce message pour vous rappeler que vous donnez le tour *{tour}* le *{date}* a *{time}*" | none | **French body, English chrome** — see below |
| GDR (group tour) | "Guides du ROM Group Tour reminder" | tour name, client, day-date, start time. "…vous donnez le group tour *{tour}* pour *{client}* le…" | none | French body, English chrome |
| Visitor Wayfinders | "ROM Events Resource reminder" | day-date, start time; then "The event is: *{event name}: {event comments}*"; "Your activity is: *{role name}: {role description}*"; an underlined line "Please remember to sign-in on the Events Resource Register on the day of your shift."; signed "Thank you, DMV Events Resource Coordinator" | none | English |
| Visitor Guides | "ROM VG *{type}* shift reminder" (or "ROM Plan your Visit Desk reminder", see §3) | shift type (kind), day-date, start time. "…you have booked a Visitor Guide shift: *{type}* on *{date}* at *{time}*"; then "In the unfortunate case that you have to cancel your shift please **click here** and you will be signed into the DMV website where you can simply remove yourself or specify a replacement" | **auto-login link** to the VG schedule page, carrying the member's address and the last four digits of their museum ID in the URL, plus the row id | English |
| Reception | "ROM DMV Reception shift reminder" | day-date, start time. "…you are staffing the Reception Desk on *{date}* at *{time}*" | none | English |
| Invitations | "Invitation reminder for *{day-date}*" | day-date, start time; event name in bold; the sub-events the guest accepted ("with *{event1}* and *{event2}*"); the event description; "If you have an emergency and cannot come, please contact *{creator name}* at *{creator email}*" | none (creator's address in the body) | English |

Where the French module mixes English into French:

- **Subjects are English** ("Guides du ROM Tour reminder").
- **Salutation is English** ("Dear …").
- The **date is rendered with English day and month names** ("Monday, September 7") inside a French sentence.
- "à" is written as bare **"a"**; "group tour" stays English inside the French sentence.
- The GDR module UTF-8-encodes names and tour names while the shared sender declares ISO-8859-1, so an accented tour name is liable to arrive **mojibaked**. The bodies avoid accents, probably for this reason.
- The From display name "DMV Reminder" and the wrapper are English for everyone.

Other observations:

- **Docents and GDR name the kind; Reception names nothing but the date** (its Shifts carry no kind in v2 either). Wayfinders names the Schedule (the event) *and* the kind (the role). Visitor Guides names the kind.
- Only Visitor Guides gives the recipient a **way to act** (cancel / replace) from the mail. Only Invitations names a **person to contact**. Nobody names a partner, a location, or a duration.
- The Wayfinders "sign in on the Register" line and the Visitor Guides cancel instruction are the only Group-specific prose. Everything else is the same sentence with different nouns.

### Proposed single bilingual chrome template

One Mailable, one view, chrome rendered in the recipient's saved locale (`Member::preferredLocale()`, the pattern `SignUpCancelled` already uses). All Group-specific nouns are **as-authored content** rendered as-is (ADR-0004): Group name, ShiftKind name, Schedule name, Member name.

Fields it needs:

| Field | From | Notes |
| --- | --- | --- |
| `first_name`, `last_name` | Member | salutation |
| `group.name` | Group (via Schedule) | subject and intro |
| `schedule.name` | Schedule | the "event" line Wayfinders had; harmless for a monthly Group ("September 2026") |
| `starts_at`, `ends_at` | Shift | converted to `app.org_timezone`; `translatedFormat` gives French day and month names for free; **times carry AM/PM** and the end time is named — both fixes of legacy ambiguity, not deviations of substance |
| `kind.name` | ShiftKind (nullable) | omitted when null (Reception) |
| schedule URL | Schedule route | plain link, login required, no token (charting item 17, ADR-0001); the French route for a French recipient (ADR-0008) |

English strings (placeholders in Laravel `:name` form):

```
reminder_email.subject   Reminder: your :group shift on :date
reminder_email.heading   Shift reminder
reminder_email.intro     Hello :first_name, this is a reminder that you are signed up for a :group shift:
                         [panel: **:date**  /  :start – :end  /  :kind  /  :schedule]
reminder_email.action    Open the schedule
reminder_email.footer    If you can no longer make it, please remove your sign-up on the schedule page so the seat can be offered to someone else.
```

French strings:

```
reminder_email.subject   Rappel : votre quart de :group le :date
reminder_email.heading   Rappel de quart
reminder_email.intro     Bonjour :first_name, ce message vous rappelle que vous êtes inscrit(e) à un quart de :group :
                         [panel: **:date**  /  :start – :end  /  :kind  /  :schedule]
reminder_email.action    Ouvrir l'horaire
reminder_email.footer    Si vous ne pouvez plus vous y présenter, veuillez retirer votre inscription sur la page de l'horaire afin que la place puisse être offerte à quelqu'un d'autre.
```

("quart" and "inscription" match the existing `lang/fr/scheduling.php` cancellation strings.)

What this drops, and why it is acceptable:

- The Wayfinders "sign in on the Register" line and signature. The register is the sign-in kiosk; the line is a habit reminder, not information. If the Chair wants it back, the cheapest faithful hook is an optional per-Group **reminder note** (officer-authored content, one column, rendered as-is under the panel). Listed as an assumption below rather than proposed outright, since charting item 13 fixed the settings at on/off and lead days.
- The Visitor Guides "specify a replacement" clause: the rebuild has no replace flow (#458 out of scope). "Remove your sign-up" is what remains.
- Group-tour and Invitations content (client name, sub-events, creator contact) — owed with those features.

## 3. Skip rules

Legacy has one deliberate cross-Group suppression and a handful of incidental ones.

**The Visitor Guides / Visitor Wayfinders link.** When a Wayfinders event is built, its Chair can mirror an event slot onto the Visitor Guides desk schedule: the Wayfinders row records the VG row's id and the VG row records the Wayfinders row's id. Whoever fills the slot from either side is written to both rows. The two rows then disagree about who "owns" the person, and the daily modules resolve it by a flag on the VG row that says whether the holder came in as a Visitor Guide:

- **Visitor Guides skips** a filled VG row that is linked to a Wayfinders row *and* whose holder is not a Visitor Guide — a Wayfinder took it through the Wayfinders schedule, so Wayfinders will remind them.
- **Wayfinders skips** a filled Wayfinders row whose holder *is* a Visitor Guide — they took it through the VG schedule, so Visitor Guides will remind them.

Net effect: one reminder per person per mirrored slot, worded by the Group whose schedule they signed up on. A side effect of the same flag: a VG row held by someone with no Visitor Guide id and no Wayfinders link (a Scheduler placement of a non-member, in practice rare) gets the odd subject "ROM Plan your Visit Desk reminder" instead of "ROM VG Desk shift reminder".

**Other suppressions:**

- Wayfinders skips shifts on an event that is **not visible** — the v2 analogue is a `draft` Schedule.
- Docents, GDR, Reception and Wayfinders skip **unfilled** rows (no member). Not a rule in v2: a Reminder is addressed to a Sign-up, and an empty Shift has none.
- Invitations reminds only guests who **accepted** the invitation (accepted flag on their row) and only for events that are active and visible.
- The **no-email sentinel** is checked twice, once in each module and once in the shared sender. In v2 this is the no-email flag (#458 item 8), checked once by the mailer.

**Does the rebuild still need the cross-Group rule? No.** A v2 Shift belongs to exactly one Schedule and one Group; cross-Group participation is the `open` **Audience** on that one Shift, evaluated as a read filter (`CONTEXT.md` § Audience, ADR-0021 §6) — "there is no second row and nothing to keep in sync". A Wayfinder who takes an open Visitor Guides desk Shift holds one Sign-up on one Shift owned by Visitor Guides, so exactly one Group's Reminder applies, and its wording ("a Visitor Guides shift") is correct. A Member with two Sign-ups on two overlapping Shifts in two Groups gets two Reminders, which is right: they are two commitments.

What survives as rules in v2:

1. A Reminder goes only to a Sign-up on a Shift whose Schedule is **`published`**.
2. A Reminder goes only where the Shift's **Group has Reminders on**.
3. The recipient's **no-email flag** is off.
4. No Reminder for a Shift that has already **started** (the catch-up rule in §6 must not send a stale one).

## 4. The empty-desk alert

Runs inside the Visitor Guides daily module, after that Group's Reminders. It exists nowhere else.

| Aspect | Legacy behaviour |
| --- | --- |
| **Cadence** | Runs only when the **day of the month is a multiple of 3** (3, 6, 9 … 30). The module also excludes the 1st explicitly, which is redundant. Never on the 31st, 1st or 2nd; the 30th-to-3rd gap is four days in a 31-day month. The comment in the module reads "no repeat from 30th to 1st". Charting item 14 ports this faithfully and flags it odd. |
| **Window** | Shifts dated from **today through today + 3 days inclusive** (four calendar days, today included). The window reuses the Reminder lead constant. |
| **Kind watched** | Only shifts of type **"Desk"**. Shadow, Wayfinding, Rotunda, Chen Court and any other type in the VG shift-type table are ignored. |
| **Definition of empty** | A Desk row with no holder, *and* **no other Desk row for the same date and time that has a holder**. Legacy models capacity as N identical rows, so "empty" means the whole slot is vacant, not merely a seat in it. One line per date-and-time (duplicates collapsed). In v2 terms: a Shift of a watched kind with **zero Sign-ups**. A Shift with one Sign-up and capacity two is *not* alerted, faithfully. |
| **Recipients** | Every row of the Visitor Guides roster with the **"Full" status** (status id 1, the roster default). Not LOA, not trainees, not any other standing. Each addressed "Dear *Full Name*". The no-email sentinel is honoured by the shared sender. |
| **Subject** | "Vacant VG Desk shifts" |
| **Body** | "The following Desk shifts are unfilled in the next few days." then one line per slot: "*Monday, September 7* @ *1:30*" with **today's date in bold**; then "Please **Click here** if you can fill any of them. Thank you". |
| **Link** | Auto-login link to the VG schedule page (address and last four digits of museum ID in the URL). |
| **Silence** | If no slot is empty, nothing is sent. |
| **Sent record** | None. A double run would send twice. |

### Proposed per-Group fields (empty-desk alert)

| Field | Type | Default | Seeded for Visitor Guides |
| --- | --- | --- | --- |
| `empty_shift_alert_enabled` | boolean on `groups` | `false` | `true` |
| `empty_shift_alert_days_ahead` | unsigned small int on `groups` | `3` | `3` |
| `alert_when_empty` | boolean on `shift_kinds` | `false` | `true` on "Desk" |

Notes on the choice:

- "Shift kinds to watch" (charting item 14) is a property of the kind, so the cleanest home is a flag on `shift_kinds` rather than a list column on `groups`. Reception (no kinds) cannot use the alert, which matches legacy. A Group with the alert on but no watched kind sends nothing.
- The cadence is **not** a field. It is the fixed day-of-month-divisible-by-3 rule, ported as-is and named odd in the ADR. Making it configurable would be a new feature.
- **Recipients** in v2: the Group's roster in the standings that `MembershipStatus::canSignUp()` permits (Full, Trainee, Transitional, Auxiliary, Projects, Emeritus, Donor), with the no-email flag off. Legacy's "Full only" is narrower; the residual fork is whether to keep it narrow. Recorded as an assumption below with the wider set as the default, since a vacancy alert to someone who cannot sign up is noise and to someone who can is the point.
- **Content** ports as one bilingual chrome mail, plain link to the Group's Schedule list (the Shifts may span several Schedules), one line per empty Shift with date, start–end time and kind, today's Shifts marked.

English:

```
empty_shift_email.subject   Open :group shifts in the next few days
empty_shift_email.heading   Shifts still open
empty_shift_email.intro     Hello :first_name, nobody has signed up for these :group shifts yet:
                            [list: **:date** · :start – :end · :kind   (today's lines marked "today")]
empty_shift_email.action    Open the schedule
empty_shift_email.footer    If you can fill any of them, please sign up on the schedule page. Thank you.
```

French:

```
empty_shift_email.subject   Quarts de :group à pourvoir dans les prochains jours
empty_shift_email.heading   Quarts encore libres
empty_shift_email.intro     Bonjour :first_name, personne ne s'est encore inscrit à ces quarts de :group :
                            [list: **:date** · :start – :end · :kind   (« aujourd'hui »)]
empty_shift_email.action    Ouvrir l'horaire
empty_shift_email.footer    Si vous pouvez en combler un, veuillez vous inscrire sur la page de l'horaire. Merci.
```

## 5. Cancellation-window mail

**Legacy sends a cancellation mail from exactly one Group's schedule page: GDR.** When a GDR member deletes their tour sign-up on the schedule page, and only if the tour is in the **current calendar month**, the page emails **one person** — the last-listed holder of the Scheduler position — with subject "Cancel a tour" and the body "*First Last* has cancelled the shift he/she was scheduled to gdrve on *Mon dd, yyyy*" (typo in the original). English only, no link. A cancellation for next month sends nothing.

The other schedule pages send nothing on delete:

- **Docents**: clears the row, no mail.
- **Visitor Guides**: clears the row (and the mirrored Wayfinders row), no mail.
- **Visitor Wayfinders**: clears the row (and the mirrored VG row), no mail. This is the module ADR-0021 refers to, where a "+2 days" guard is still computed but commented out — the guard gated *whether the member may cancel*, not a mail.
- **Reception**: clears the row, no mail.

(The "Taking your … shift" mails in Docents, Visitor Guides, Wayfinders and Reception are a different thing: a member asking a named colleague to take their shift. That is the replace flow, out of scope in #458.)

**Does v2 already cover it? Yes, and more.** `SignUpCancelled` fires on every Member self-cancellation, for every scheduling Group, with no month or proximity window, to **all** of the Group's Schedulers and Chairs (`Group::schedulers()`), in each recipient's locale, naming date, times and kind. ADR-0021 §4 records this as deliberate ("unconditional"). Nothing from the GDR current-month rule needs porting: the v2 mail is a superset. The only residual difference is that legacy sends a small subset of cancellations and v2 sends all of them; that is the accepted trade, and #458's faithful-port preference is satisfied because every mail legacy sent is still sent.

## 6. Proposed sent-record shape

Two small tables, one per job. Both exist so the daily run is **idempotent** (a second run the same day sends nothing new) and so a **missed day catches up** (charting item 15).

### Reminders — `reminder_sends`

| Column | Notes |
| --- | --- |
| `id` | |
| `shift_id` | FK, cascade on delete |
| `member_id` | FK, cascade on delete |
| `sent_at` | UTC instant |
| `locale` | the locale the copy was rendered in — cheap, and answers "what did they get?" |
| unique (`shift_id`, `member_id`) | one Reminder per person per Shift, ever |

Keyed on the (Shift, Member) pair rather than on `sign_up_id` on purpose: a Sign-up that is deleted and re-taken by the same person is still one commitment, and this keeps a drop-and-retake from re-sending. A Sign-up deleted before its reminder simply never matches.

Daily predicate (one query, no per-Group loop needed beyond the lead-days join):

> Sign-ups whose Shift starts **after now** and **before the end of `today + group.reminder_lead_days`** on the org wall clock, whose Schedule is `published`, whose Group has `reminders_enabled`, whose Member has the no-email flag off, and for which **no `reminder_sends` row exists**.

That predicate is what makes catch-up free: a day the cron did not run leaves rows inside the window with no record, and the next run sends them; a Sign-up created inside the window (legacy would miss it) also gets one. "Starts after now" stops the catch-up from reminding anyone about a Shift already begun.

### Empty-desk alerts — `empty_shift_alert_runs`

| Column | Notes |
| --- | --- |
| `id` | |
| `group_id` | FK, cascade on delete |
| `run_on` | the org-wall-clock **date** the alert is for |
| `sent_at` | UTC instant |
| `shift_count` | how many empty Shifts were listed (0 when the run found none and sent nothing — still recorded, so the day is marked done) |
| `recipient_count` | |
| unique (`group_id`, `run_on`) | one run per Group per day |

Daily predicate:

> For each Group with `empty_shift_alert_enabled`, when today's day-of-month is a multiple of 3 and no row exists for (`group`, today): collect Shifts of a watched kind, on a `published` Schedule, starting from the start of today through the end of `today + empty_shift_alert_days_ahead`, with zero Sign-ups; write the row; if any Shifts were found, send to the eligible roster.

A missed cadence day is **not** caught up (faithful: the alert is about the next few days and the next cadence day is at most three days off; the Reminder catch-up covers the individual commitments). Recording a row even on a silent day is what keeps a second run the same day silent too.

### Per-Group fields — Reminders (for completeness)

| Field | Type | Default | Seeded on |
| --- | --- | --- | --- |
| `reminders_enabled` | boolean on `groups` | `false` (ADR-0015: "a per-Group opt-in — a flag on the Group") | Docents, GDR, Visitor Wayfinders, Visitor Guides, Reception |
| `reminder_lead_days` | unsigned small int on `groups` | `3` | 3 everywhere; Invitations sets 4 when built |

Both editable by a Chair or Scheduler (charting item 13). Meaningful only where `has_scheduling` is on.

## Assumptions

1. **Default off, seeded on for the five.** `reminders_enabled` defaults to `false` and the seed turns it on for the five Groups that run today. A new scheduling Group opts in, as ADR-0015 says.
2. **The Reminder is a window, not a day.** Legacy matches one exact day; the rebuild sends for any Sign-up inside the lead window with no record (charting item 15). The consequence, accepted: a Sign-up taken two days before its Shift gets a Reminder the next morning, which legacy would not have sent.
3. **Empty-desk recipients are everyone who could fill the gap** — roster standings where `MembershipStatus::canSignUp()` is true — rather than legacy's "Full" status alone. Residual fork for a human if the Visitor Guides Chair wants it narrower.
4. **Empty means zero Sign-ups**, not below capacity. Faithful to legacy's "no other row for the slot has a holder".
5. **Watched kinds live on `shift_kinds`** (`alert_when_empty`), not as a list column on `groups`. Groups without kinds cannot use the alert; Reception did not have it in legacy.
6. **The cadence stays a constant**, not a setting: day-of-month divisible by 3. Named odd in the ADR, not fixed.
7. **No per-Group reminder note.** The Wayfinders "sign in on the Register" line and signature are dropped from the chrome template. If the Chair wants them, the hook is one optional as-authored `reminder_note` column on `groups`; not proposed here because charting fixed the settings at on/off and lead days.
8. **The cancellation mail needs no month window.** v2's unconditional `SignUpCancelled` to all Schedulers and Chairs supersedes GDR's current-month mail to one person.
9. **Group-tour and Invitations reminders are owed with their features** (booking, Invitations), with the rules recorded above: group tours name the client; Invitations use 4 days, only accepted guests, and name the organiser as contact.
10. **AM/PM and end times are shown.** Not a deviation from anything legacy decided, only from what it omitted; the same format the cancellation mail already uses.
11. **Reminders and alerts have no Reply-To** (charting item 9, automatic mail) and go out under the app's one From address with the Group name as display name, replacing legacy's fixed "DMV Reminder".
12. **The disabled modules are not owed.** Gallery Interpreters, Outreach and ROMWalks have no legacy reminder code to port; they get the per-Group switch and nothing else.
