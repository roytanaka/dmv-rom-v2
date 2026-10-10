---
status: accepted
date: 2026-10-10
accepted: 2026-10-10
---

# Docents and GDR Bookings: a client record with one Shift

From a grilling session on 2026-10-10, started by Tester reports on staging ([Feedback item 73](https://staging.dmv-rom.ca/feedback/73) to [76](https://staging.dmv-rom.ca/feedback/76)). Docents and Guides du ROM (GDR) run two kinds of tour. **Scheduled tours** are the daily public tours, one Docent each, already served by Schedules and Shifts ([ADR-0021](0021-scheduling-first-pass.md)). **Group tours** are tours a client books for a date: a school, a tour company, an internal ROM event. [ADR-0021](0021-scheduling-first-pass.md) deferred these as the **Booking** capability, so today a group tour is only a Shift with no client, visitors, type or money. This ADR ports the Booking for Docents and GDR. The rule is a faithful port of the old app. We deviate only for security, privacy, a data-loss bug, or a cheap UX improvement, and each deviation is named below. See `GLOSSARY.md` for **Booking**, **Booking type** and **Earned**.

## Decision

1. **A Booking is its own record, with exactly one Shift.** The Booking holds the client half: the **Tour** given ([ADR-0033](0033-tours-and-tour-qualifications.md)), client, expected visitors, booking type, group leader, ROM order number and date, comments, and Earned. The Shift holds the staffing: start and end, the Group's group-tour shift kind, and the docents needed as its capacity. Docents sign up to the Shift with an ordinary Sign-up. One Shift per Booking is enough: the old app's multi-shift group tours have not been used since 2019. Putting the fields on the Shift was rejected, because every daily tour would then carry empty client and money columns.

2. **Docents and GDR only.** Each turns it on with a Group setting, like the other capabilities. Outreach, ROMBus and Walker each run a different variant and are out of scope.

3. **A new per-Group role, Booker.** A Booker adds, changes and deletes Bookings, assigns docents, and sends the Booking mails. The Chair implies it, as with every role ([ADR-0011](0011-authorization-model.md)). The Statistician may also change and delete Bookings. This ports the old app's per-docent "Group Tour" flag, which is a separate power from building the daily schedule, so it is not folded into Scheduler.

4. **Each month's Bookings live in their own Schedule.** The first Booking in a month creates a published Schedule named for that month's group tours, apart from the daily-tour Schedule. This is the old app's separate group-tour month. It also means a draft daily Schedule never hides a Booking made months ahead.

5. **Who sees what.** Members of the Group see the client, visitors, type, leader and comments. Only Bookers, the Statistician and the Chair see the order number, order date and Earned. Anyone else who can read the Schedule sees only the Shift: time, tour and seats. *Deviation, privacy:* Schedules are readable across the org ([ADR-0021](0021-scheduling-first-pass.md) §6), which is wider than the old app's docent-only list, so client and money data are held back from non-members.

6. **Booking types are a per-Group list.** Each type has a name, a rate per visitor and a rate per docent-hour. Docents and GDR are seeded with their five: Tour Paid, Tour Free, Tour Internal, Spot Paid, Spot Free (GDR names them in French). They are kept in Group Settings, like shift kinds.

7. **Earned is one figure, computed, and the Statistician's correction stands.** The app computes it as rate per visitor × visitors plus rate per docent-hour × docent-hours. The Statistician may correct it. A later edit to the Booking does not overwrite a correction. *Deviation, data-loss bug:* the old app recomputes Earned on every ordinary edit and silently discards the Statistician's figure.

8. **Signing up and substituting.** A Member may take a Booking's Shift when a seat is open and they hold an active qualification for its Tour, or the Tour is open to all ([ADR-0033](0033-tours-and-tour-qualifications.md)). A substitute must meet the same test. A Member who holds the seat cannot drop it. They choose a qualified **substitute** instead, who takes the seat. This is the old app's rule: a client tour must not lose its docent without someone noticing. A Booker or Scheduler may still remove any Sign-up.

9. **Four mails, as in the old app.** *Request* goes to every Member qualified for the Tour (the whole roster for an open-to-all Tour), asking them to sign up. *Confirmation* goes to the assigned Members. On creation the app sends Request if seats are open, or Confirmation if the tour is full, and the Booker can send either again from the Booking. The existing *Reminder* covers the old app's three-day reminder. A *substitution notice* goes to the old and new Member and to the Bookers. *Deviation, cheap:* the old app hard-codes a ROM staff address as a copy. Here it is an optional Group setting.

10. **Client is free text.** The form suggests clients from this Group's past Bookings. There is no client record, as in the old app.

11. **No monthly confirm step.** The old app's "Confirm All Correct" only writes each docent's group-tour credit. Here the existing *recalculate this month* ([ADR-0022](0022-hours-and-statistics-model.md)) already counts the Sign-ups on Booking Shifts as scheduled hours. *Deviation:* the step has nothing left to do, so it is not ported.

12. **Two reports.** *Tour Summary* and *Tour Detail*, each for a month and for the fiscal year to date, as print pages with CSV, behind the Chair and Statistician report gate ([ADR-0022](0022-hours-and-statistics-model.md)). Tour Summary shows tours, visitors and Earned per booking type, then a grand total with the scheduled tours and the month's exhibition revenue. Tour Detail splits the same figures by type and tour. "Tours" counts docent-tours, as the old report does. Exhibition revenue is a monthly number the Statistician enters.

## Consequences

- Scheduled hours come from the Shift's length, so a 45-minute group tour credits 0.75 hours. The old app credited one tour. Group tours almost always run a whole number of hours.
- Moving historical group tours across is the legacy-data migration's job, not this one's.
- A public form for ROM Group Sales to submit Bookings directly is not part of this decision.
