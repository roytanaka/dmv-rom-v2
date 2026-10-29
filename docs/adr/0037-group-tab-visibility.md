---
status: accepted
date: 2026-10-29
accepted: 2026-10-29
---

# Group tab visibility: one site-wide rule

From a grilling session on 2026-10-29, prompted by three Tester feedback items on staging (61, 62, 70). It settles who sees each **Group** section tab, adds a **How to join** section to the Overview, and takes **past Schedules** off the Scheduling list. It **amends [ADR-0021 §6](0021-scheduling-first-pass.md)** ("then past"). See `GLOSSARY.md` for **How to join** and **Past Schedule**.

## Context

The three feedback items asked for per-Group rules from the Legacy app:

- **Item 61:** hide Scheduling from non-members for Gallery Interpreters, ROMForYou, DMV Hands-on Tours and Visitor Wayfinders.
- **Item 62:** for Reception, Docents, Guides du ROM and Visitor Guides, show non-members at most two months of Scheduling.
- **Item 70:** show past periods only to the Group's Chair or Statistician.

The Tester agreed by email (2026-10-25) to one consistent policy. The legacy rules grew one Group at a time as Groups were added. A need that one Group has becomes a Group setting, not a hard-coded branch.

Facts that shaped the decision:

- Read access is already mostly org-wide. ADR-0021 §6 made Scheduling org-open. ADR-0019 makes Public and Group visibility a matter of navigation only. Private returns 404 to a non-member.
- Meetings and Tours are members-only. Today the tab is hidden from a non-member and the route returns 403.
- Documents already shows its tab to a non-member, with only the `group` Folders in it.
- ADR-0021 dropped the two-month cap in #306.
- ADR-0021 §6 lists past Schedules below the current ones. Nothing is hidden by date.
- Seat numbers (visitor counts) go to Scheduler/Chair, the seat-holder and co-volunteers on the same Shift (ADR-0023 §5, #652).
- No Group page tells a non-member how to join. The About text is free prose.

## Decision

### 1. One matrix for every Group

**Every Group follows the same tab rules.** A Group cannot hide a tab from non-members. Item 61 is declined.

Readers are signed-in Members. Guests reach no Group page.

| Tab        | Non-member                                    | Member              | Officer extras                             |
| ---------- | --------------------------------------------- | ------------------- | ------------------------------------------ |
| Overview   | yes                                           | yes                 | Secretary/Chair edit About and How to join |
| Members    | names only                                    | yes                 | roster edits, past members                 |
| Meetings   | join prompt (§2)                              | published Meetings  | unpublished Meetings, manage               |
| Documents  | `group` Folders                               | all Folders         | Librarian manages                          |
| Scheduling | published Schedules; sign up on `open` Shifts | also `group` Shifts | Scheduler/Chair: drafts, all seat numbers  |
| Tours      | join prompt (§2)                              | yes                 | Vetting manages                            |
| Hours      | own records                                   | own records         | Chair/Statistician reports                 |
| Settings   | hidden                                        | hidden              | per configuration right (ADR-0027)         |

**A Private Group still returns 404 to a non-member**, whatever the matrix says (ADR-0019). A Container still returns 404 to everyone.

**Sign-up names stay visible to every reader of a Schedule** (ADR-0017 §6, ADR-0021 §7). **Seat numbers keep the #652 audience**: Scheduler/Chair, the seat-holder and co-volunteers on the same Shift. The Tester's email proposed officers and the Member concerned only. We keep #652 because co-volunteers saw the same visitors.

### 2. Members-only tabs show a join prompt

**A non-member sees the Meetings and Tours tabs.** The body shows only a short notice, "For members of this Group", with a "How to join" link to the Overview's How to join section (§3). Neither tab shows any part of its content to a non-member: no dates, no titles.

The route still refuses the content. Only the tab and the notice are new. This matches Documents, where a non-member sees the tab and only what is open to them.

The Meetings section URL returns 404 when the Group has Meetings off, super-tier included. This matches Tours, Documents and Scheduling.

### 3. How to join, a section on the Overview

**The Overview sidebar gets a How to join card, below At a glance.**

- It holds as-authored text, one language, like About Us. It is never translated.
- Secretary and Chair edit it inline, with the same right as About Us (`GroupPolicy::update`).
- Everyone who can read the Overview sees it, members included.
- When it is empty, the card shows a fallback line: to join, contact this Group's Chair. So the link from §2 never lands on an empty card.
- The card has a fixed anchor, so the §2 link lands on it.

### 4. Scheduling: no date cap, past Schedules off the list

**No two-month cap.** A non-member sees every upcoming published Schedule. Item 62 is declined.

**The list shows current and upcoming Schedules only**, for everyone. A **Past Schedule** is one where `ends_on < today` (the inverse of `Schedule::isCurrent`). This amends ADR-0021 §6, which listed past Schedules below the current ones.

**A "Past schedules" link shows past Schedules**, newest first, at `?past=1` on the Scheduling tab. Only the Group's **Chair, Statistician and Scheduler** see the link. This follows the Roster's officer-only `?past=1` view. Item 70 named Chair and Statistician. Scheduler is added because the Scheduler owns the Schedules.

**A Past Schedule still opens by permalink** for any Member who can read it. Hiding it from the list keeps the list short. It does not keep anything secret. This is the same reasoning ADR-0019 applies to Group visibility. Old email links and a seat-holder's own history keep working.

### 5. No per-Group visibility settings yet

**We build no Group setting for visibility now.** No Group has asked for one under the single rule. When a Group does, the setting goes on Group Settings (ADR-0027), and this ADR is amended.

## Considered alternatives

- **Hide Meetings and Tours from non-members (today's behaviour).** Rejected. A hidden tab does not tell a Member that the Group meets, or how to take part.
- **Show part of the members-only content (meeting dates without minutes).** Rejected. Each field would need its own rule.
- **Put join steps in About Us.** Rejected. A fixed section can carry an anchor and a fallback line. Free prose cannot.
- **Keep the two-month cap for some Groups.** Rejected. It brings back per-Group branches, and #306 already dropped it.
- **Make past Schedules secret, refusing the permalink to non-officers.** Rejected. It breaks old links and a seat-holder's history, and it protects nothing the Directory does not already show.
- **Limit seat numbers to officers and the seat-holder.** Rejected. It reverses #652 without a new reason.

## Consequences

- Non-members see two more tabs on Groups that use Meetings or Tours. Each holds only a notice.
- Most Groups launch with an empty How to join card. The fallback line covers that until each Chair writes the steps.
- An officer who is not Chair, Statistician or Scheduler (for example the Secretary) cannot reach the past list from the link. They can still open a Past Schedule by permalink.
- The Scheduling list changes for everyone: past Schedules leave it. Help articles that show the "past" heading need new screenshots.
