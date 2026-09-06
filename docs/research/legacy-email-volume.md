---
issue: 460
date: 2026-09-06
snapshot: legacy production database, 2026-06-29
---

# Legacy email volume and use

Research for [#460](https://github.com/roytanaka/dmv-rom-v2/issues/460) (_What does the legacy send log say about email volume and use?_). Input to the delivery-mechanics decision: one mail per recipient versus BCC blocks, under a provider cap of **4,000 recipients/day** and **500/hour**.

Sources: the legacy production database snapshot of 2026-06-29 (send log, daily counter, Member roster, committee rosters, schedules) and the legacy PHP source that writes the log. Every number below was computed from that snapshot. Window for "last two years" is **2024-06-30 → 2026-06-29** (730 days) unless stated.

## Answer

| Question                                              | Number                                                                                                                  |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Recipients per day, median / p95 / max (last 2 years) | **184 / 876 / 2,099**                                                                                                   |
| Days over 1,000 recipients                            | 20 of 730 (2.7%)                                                                                                        |
| Days over 2,000 recipients                            | 1                                                                                                                       |
| Days over 4,000 recipients                            | **0** — the provider's daily cap was never approached (all-time max day is 3,026, in 2022)                              |
| Recipients in the busiest clock hour                  | **888**                                                                                                                 |
| Clock hours over 500 recipients                       | **23** in two years (2 in 2024 H2, 13 in 2025, 8 in 2026 H1)                                                            |
| Clock hours over 250 recipients                       | 195                                                                                                                     |
| Largest single batch                                  | **447** recipients (a whole-department Broadcast; there were 457 current Members on snapshot day)                       |
| Batches over 90 recipients (one BCC block)            | 479 of 6,447 composer batches (7.4%); max 5 blocks                                                                      |
| Batches over 500 recipients                           | **0**, ever                                                                                                             |
| Composer batches, last 2 years                        | 6,447 (~9/day); 185,431 recipient deliveries                                                                            |
| Same traffic as BCC blocks of 90                      | 7,467 `mail()` calls — 25x fewer messages                                                                               |
| Cron reminders (already one mail per recipient)       | ~34 recipients/day average, max 188/day                                                                                 |
| Members with the no-email sentinel                    | 22 total; **4** current (2 Active, 1 Honourary, 1 Sustaining); none holds an officer role or a Sign-up since April 2025 |
| Members sharing an address with another Member        | 76 Members across 37 addresses; **28 current** Members; only **2 pairs** are current-with-current                       |

**What this means for the choice.** Per-recipient sends fit comfortably under the 4,000/day cap — the worst day in two years was 2,099 and the p99 was 1,256. The **500/hour** limit is the binding constraint: a single whole-department Broadcast (≈445 recipients) nearly fills an hour on its own, officers routinely send two or three of them back-to-back (23 hours over 500 in two years, 195 over 250), and the peak day stacked three department-wide sends plus four 100+ committee sends in one afternoon. Per-recipient delivery therefore needs a queue that throttles to the hourly rate and tolerates a Broadcast landing 30–60 minutes after it was pressed, and the officer-facing UX should say so. BCC blocks of 90 sidestep the hourly limit entirely (peak hour ≈ 10–15 messages) but keep the legacy defects: no per-recipient failure signal, unsubscribe links only on 90+ batches, and no personalisation. Shared addresses are a non-issue either way (two current households).

## 1. Daily volume

Source: the daily counter table (one row per calendar day, incremented by every send path). The send log's per-day sum agrees within 1% (difference 1,719 recipients over two years, from a notification path that counts but does not log).

### Distribution, last two years (730 days)

| Statistic    | Recipients/day |
| ------------ | -------------- |
| Mean         | 282            |
| Median (p50) | 184            |
| p90          | 635            |
| p95          | 876            |
| p99          | 1,256          |
| Max          | 2,099          |

| Bucket      | Days |
| ----------- | ---- |
| 0           | 2    |
| 1–99        | 189  |
| 100–499     | 418  |
| 500–999     | 101  |
| 1,000–1,999 | 19   |
| 2,000–3,999 | 1    |
| 4,000+      | 0    |

Weekday shape: Wednesday/Thursday average 360–375, Monday/Tuesday/Friday 285–320, Saturday/Sunday 160–175.

### By calendar year (whole log)

| Year                  | Days logged | Recipient deliveries | Max day | Composer batches |
| --------------------- | ----------- | -------------------- | ------- | ---------------- |
| 2021 (from late Sept) | 97          | 43,347               | 1,780   | 1,205            |
| 2022                  | 365         | 177,950              | 3,026   | 3,931            |
| 2023                  | 365         | 159,819              | 2,277   | 3,575            |
| 2024                  | 366         | 112,784              | 1,568   | 3,187            |
| 2025                  | 365         | 103,015              | 1,602   | 3,380            |
| 2026 (to 29 June)     | 180         | 53,902               | 2,099   | 1,765            |

Volume has fallen roughly 40% since 2022 (177,950 → ~106,000 annualised). The all-time max day (3,026, in 2022) is still under 4,000.

### The peak days and what caused them

Every top day is the same pattern: **several whole-department Broadcasts on one day, plus committee-wide sends, plus a correction or resend.**

| Day              | Recipients | What happened                                                                                                                                                                                                         |
| ---------------- | ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-02-09 (Mon) | 2,099      | Three department-wide Broadcasts (447, 375, 375) in one afternoon/evening; three committee-wide sends of 118 to one committee; one of 174 and one of 105 to two others; a dozen smaller sends                         |
| 2026-01-09 (Fri) | 1,732      | Four department-wide Broadcasts (377, 438, 377, 377) spread 06:00–23:00                                                                                                                                               |
| 2025-03-05 (Wed) | 1,602      | One announcement sent separately to five committees (41+22+164+32+42), then a correction to all five the same evening (same sizes again), then a department-wide Broadcast of 370 sent **twice** within three minutes |
| 2025-11-13 (Thu) | 1,523      | Three department-wide Broadcasts (380, 442, 380 — the third a link correction) plus 84, 108, 40, 32, 33 to committees                                                                                                 |

Resends are common: 83 same-day duplicates (same committee, subject and audience size, 20+ recipients) in two years, 149 extra sends, 8,288 extra recipient deliveries — about 4.5% of Broadcast traffic.

### Hourly concentration

Timestamps are per batch, so an hour's total is the sum of batches whose send started in that clock hour.

| Statistic (last 2 years)   | Value                   |
| -------------------------- | ----------------------- |
| Clock hours with any send  | 4,046                   |
| Max recipients in one hour | 888                     |
| Hours over 500             | 23                      |
| Hours over 250             | 195                     |
| Top five hours             | 888, 870, 862, 858, 774 |

Officer sends cluster 09:00–17:00 local (peaks 10:00–12:00 and 15:00–17:00); a second, smaller wave runs 19:00–23:00 local. The cron runs at 06:00 local.

## 2. Who sends

Committee here is the committee context the composer was opened in. "DMV-wide" is the department-wide context (Broadcasts to all Members); "member home page" sends are automatic notifications, not composer use.

### Last two years, by committee

| Committee (by page name)           | Composer batches | Recipient deliveries | Batches >90 | Distinct senders |
| ---------------------------------- | ---------------- | -------------------- | ----------- | ---------------- |
| Docents                            | 1,285            | 34,175               | 0           | 102              |
| Member home page (system notices)  | 1,225            | 1,286                | 0           | 3                |
| DMV-wide                           | 1,141            | 74,420               | 180         | 66               |
| Visitor Wayfinders                 | 443              | 15,025               | 90          | 51               |
| ROMForYou                          | 373              | 6,843                | 0           | 55               |
| Visitor Guides                     | 364              | 10,741               | 96          | 24               |
| ROMWalks                           | 360              | 4,733                | 0           | 48               |
| Guides du ROM                      | 280              | 3,743                | 0           | 16               |
| Gallery Interpreters               | 210              | 17,490               | 97          | 15               |
| Reception                          | 171              | 2,279                | 0           | 15               |
| ROMBus                             | 137              | 1,372                | 0           | 11               |
| Friends of Earth & Space           | 96               | 2,508                | 0           | 8                |
| Friends of Palaeo                  | 94               | 2,359                | 0           | 9                |
| Invitations                        | 68               | 6,315                | 16          | 14               |
| Friends of TC                      | 50               | 567                  | 0           | 10               |
| ROMTravel                          | 45               | 435                  | 0           | 9                |
| Bishop White                       | 45               | 987                  | 0           | 9                |
| Hands-on Tours                     | 15               | 27                   | 0           | 3                |
| Friends of Global South Asia       | 12               | 33                   | 0           | 4                |
| Special Projects                   | 12               | 28                   | 0           | 9                |
| Friends of CC (inactive committee) | 10               | 19                   | 0           | 4                |
| Les Amis Francophiles              | 9                | 44                   | 0           | 4                |
| (no committee recorded)            | 2                | 2                    | 0           | 1                |

Only five contexts ever exceed 90 recipients: DMV-wide, Visitor Wayfinders, Visitor Guides, Gallery Interpreters, Invitations. Every other committee's largest audience fits in one BCC block.

**Committees that never use the composer:** none among the 20 active committees in the last two years. The lightest users are Les Amis Francophiles (9 batches), Special Projects (12), Friends of Global South Asia (12) and Hands-on Tours (15). The two placeholder committees (home/root) and the retired Biodiversity committee have no composer use.

Note that "Docents" is inflated by the group-tour request feature (415 of its 1,285 batches, see §4); its hand-written Broadcast volume is closer to 700 over two years.

### Batches per committee per month

Typical monthly composer batches (last two years, excluding the home page and cron): Docents 20–120 (peaks January–March), DMV-wide 26–114 (peaks October and November), Visitor Wayfinders 5–47, Visitor Guides 5–40, ROMForYou 5–26, ROMWalks 2–33, Guides du ROM 4–24, Gallery Interpreters 1–23, Reception 3–12, ROMBus 1–11, Friends groups 0–11 each. Department-wide monthly totals: 216–650 batches, 4,400–14,100 recipient deliveries; November 2025 was the heaviest month for deliveries (14,121), April 2026 for batches (650, driven by renewal-season system notices).

### Distinct senders

| Measure                                                              | Count                                         |
| -------------------------------------------------------------------- | --------------------------------------------- |
| Distinct sender addresses, last two years (excluding system notices) | 295                                           |
| … all time since 2021                                                | 665                                           |
| Senders with exactly one batch in two years                          | 129                                           |
| Senders with 2–5                                                     | 62                                            |
| Senders with 6–20                                                    | 55                                            |
| Senders with 21–100                                                  | 38                                            |
| Senders with 100+                                                    | 11                                            |
| Share of batches from the top 10 senders                             | 45% (2,328 of 5,222)                          |
| Share from the top 30                                                | 71%                                           |
| Senders active in more than one committee context                    | 76 of 295 (5 senders in 5+ contexts, 2 in 18) |

The long tail of single-batch senders is largely non-officer use: any signed-in Member can send from a tour-request form or sub-call form, and those sends log the Member's own address as originator. The genuinely regular senders number 40–50.

## 3. Audience size

Composer batch recipients = the log's count minus one (the log adds one for the To: address, which is the sender). Cron rows are excluded here; they are per-committee daily totals, not batches.

### Distribution, last two years

| Scope                                     | Batches | p50 | p90 | p95 | p99 | Max | Mean |
| ----------------------------------------- | ------- | --- | --- | --- | --- | --- | ---- |
| All composer batches                      | 6,447   | 7   | 65  | 114 | 376 | 447 | 28.8 |
| Excluding home-page system notices        | 5,222   | 10  | 77  | 165 | 377 | 447 | 35.3 |
| Hand-written Broadcasts only (§4 class 6) | 3,757   | —   | —   | —   | —   | 447 | 45.5 |

| Recipients | Batches | Recipient deliveries | BCC blocks of 90 |
| ---------- | ------- | -------------------- | ---------------- |
| 0–1        | 1,710   | 1,710                | 1,710            |
| 2–5        | 1,396   | 3,300                | 1,396            |
| 6–20       | 1,645   | 18,993               | 1,645            |
| 21–50      | 1,016   | 37,411               | 1,016            |
| 51–90      | 201     | 14,407               | 201              |
| 91–180     | 284     | 36,369               | 568              |
| 181–500    | 195     | 73,241               | 931              |
| 501+       | **0**   | 0                    | 0                |
| Total      | 6,447   | 185,431              | 7,467            |

- **7.4%** of batches (479) exceed one BCC block of 90; they carry **59%** of recipient deliveries.
- **0%** exceed 500. The ceiling is the current-Member count (457 on snapshot day; 447 is the largest audience ever reached in the window).
- 60% of batches go to 20 or fewer people; 27% go to one or two.
- Subcommittee-targeted sends: 726 of 6,447 (11%).

## 4. Automatic versus hand-written

The log records the originator and subject, which lets the send paths be told apart. Cron rows carry a fixed originator; system notices from the member home page carry one of three system addresses and templated subjects; roster and sign-up notices have fixed subjects and one or two recipients; two form-driven features (group-tour requests, reception sub calls) have fixed subjects and a committee-sized audience.

| Class                                                                                                            | Batches | % batches | Recipient deliveries | % deliveries | Already per-recipient?        |
| ---------------------------------------------------------------------------------------------------------------- | ------- | --------- | -------------------- | ------------ | ----------------------------- |
| 1. Cron reminders (06:00 daily)                                                                                  | 1,466   | 18.5%     | 12,344               | 6.2%         | Yes — one `mail()` per Member |
| 2. System notice: profile / renewal / expiry change (home page)                                                  | 1,225   | 15.5%     | 1,286                | 0.7%         | Effectively (1–2 recipients)  |
| 3. System notice: roster or sign-up change (joined/resigned/LOA, tour or shift confirmation, tour change/cancel) | 856     | 10.8%     | 1,190                | 0.6%         | Effectively (1–2 recipients)  |
| 4. Form-driven group request (docent / GdR group-tour request, reception sub call)                               | 556     | 7.0%      | 11,844               | 6.0%         | No — BCC blocks               |
| 5. Test sends                                                                                                    | 53      | 0.7%      | 241                  | 0.1%         | —                             |
| 6. Hand-written Broadcasts                                                                                       | 3,757   | 47.5%     | 170,870              | 86.4%        | No — BCC blocks               |

So **about 45% of batches are automatic but only 7.5% of deliveries**; hand-written Broadcasts are half the batches and 86% of the deliveries. The delivery-mechanics choice is really about class 6 (and 4).

Cron detail (logged May 2025 → June 2026; the cron did not write to the send log between late 2021 and May 2025, so it is invisible in the older per-batch data but included in the daily counter):

| Committee          | Days with reminders | Avg recipients/day | Max/day |
| ------------------ | ------------------- | ------------------ | ------- |
| Visitor Guides     | 356                 | 22.0               | 95      |
| Docents            | 329                 | 5.8                | 15      |
| Guides du ROM      | 320                 | 1.1                | 3       |
| Visitor Wayfinders | 243                 | 5.4                | 34      |
| Reception          | 205                 | 1.6                | 2       |
| Invitations        | 19                  | 32.8               | 160     |

Whole cron: mean 33.7 recipients/day, max 188/day.

Home-page notices split: renewal 903, expiry-date update 263, contact-details change 59. They spike in April (277 and 287 batches in April 2025 and 2026 — renewal season) and are the only reason batch counts, not deliveries, peak in April.

## 5. The no-email sentinel

The roster's email column holds a placeholder domain containing the sentinel word for Members who have no address. The cron skips them; the composer's address builders skip them; several profile pages check for them.

| Measure                                                                                            | Count                                                                                   |
| -------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Members in the roster (all categories, including resigned, deceased, donors, and an archive block) | 1,939                                                                                   |
| Carrying the sentinel                                                                              | **22**                                                                                  |
| Blank address (no sentinel)                                                                        | 895 — 879 of them in the archive block (category group X), 13 donors, 3 current Members |

### Sentinel Members by category

| Category group | Category              | Count |
| -------------- | --------------------- | ----- |
| Current (C)    | Active                | 2     |
| Current (C)    | Honourary             | 1     |
| Current (C)    | Sustaining            | 1     |
| Deceased (D)   | Deceased              | 7     |
| Non-member (N) | Donor                 | 2     |
| Resigned (R)   | Retiring, age related | 5     |
| Resigned (R)   | Health                | 2     |
| Resigned (R)   | Reason not given      | 1     |
| Resigned (R)   | Other                 | 1     |

Current Members with no usable address at all (sentinel or blank): **7 of 457** (1.5%).

### Do any hold a role or a Sign-up?

| Check                                                                                     | Sentinel Members matching                                          |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Department executive table                                                                | 0                                                                  |
| Committee executive tables (any committee)                                                | 0                                                                  |
| Officer flag on a committee roster (chair, secretary, scheduler, statistician, treasurer) | 0                                                                  |
| Officer flags on the Member record                                                        | 0                                                                  |
| Active row on a committee roster                                                          | 1 (an Active Member)                                               |
| Active subcommittee membership                                                            | 1 (the same Active Member; end date is the open-ended placeholder) |
| Historical board-positions table                                                          | 7 Members, 17 rows — all for fiscal years 1965–2013, none current  |
| Any Sign-up ever (all scheduling committees)                                              | 10 Members, ~1,160 Sign-ups all-time                               |
| Sign-up since 2025-07-01                                                                  | **0** — most recent Sign-up by any sentinel Member is April 2025   |
| Future Sign-up                                                                            | 0                                                                  |

Three of the four current sentinel Members have historical Sign-ups; one still sits on a committee roster and a subcommittee. None holds an officer role. Nobody with the sentinel is currently scheduled, so a rebuild that refuses to schedule a Member without an address would strand no one today.

## 6. Shared addresses

Case-insensitive, trimmed match on the address column, excluding blank and sentinel values.

| Measure                                              | Count                       |
| ---------------------------------------------------- | --------------------------- |
| Addresses used by more than one Member               | 37                          |
| Members sharing                                      | 76 (35 pairs, 2 triples)    |
| … of whom current (C)                                | 28                          |
| … donors / non-members (N)                           | 38                          |
| … resigned (R)                                       | 8                           |
| … deceased (D)                                       | 1                           |
| Shared addresses with at least one current Member    | 26 (53 Members, 28 current) |
| Shared addresses where **two current Members** share | **2** (4 Members)           |

Most sharing is a current Member paired with a donor record or a resigned/deceased household member — records that would not be in a Broadcast audience anyway. Only two households would receive two copies of a department-wide per-recipient send today (four Members). Legacy's BCC path deduplicates addresses before blocking, so it already collapses those to one copy; per-recipient delivery would add at most two duplicate copies per department-wide Broadcast unless the rebuild deduplicates on address.

## Notes on the log itself

- The send log has 18,515 rows from 2021-09-29 to 2026-06-29; the daily counter has 1,738 days from 2021-09-25.
- The count per batch is 1 + the number of addresses in the CC/BCC header (the extra one is the sender's own To: copy). Addresses are deduplicated before blocking but the logged count is taken before deduplication, so it can slightly overstate distinct recipients.
- The daily counter carries a daily-limit column (2,000 for the first 47 days of 2021, 10,000 since). **The limit is not enforced** — the pre-send check was commented out and always returns OK. No send has ever been blocked by it.
- A batch is logged only if every 90-block `mail()` call returned true; partial failures leave no record.
- Attachments are not logged (size, count or presence).
- The log records the committee page the composer was opened in, not the audience selector (all / full / provisional / subcommittee) except when a subcommittee is chosen.
- Timestamps in the snapshot are UTC; local-time statements above are shifted by the Toronto offset (the 06:00 cron appears at 10:00/11:00 UTC).

## Needs data

Things the log cannot answer and which the rebuild will have to measure itself or estimate:

1. **Attachment sizes and frequency.** The composer supports multiple attachments; nothing about them is logged. No basis in legacy data for an attachment limit.
2. **Per-recipient delivery outcomes** (bounces, rejections, spam-folder rates). Legacy sends through the host's `mail()` and records nothing per recipient.
3. **Cron volume before May 2025** as separate rows — it is inside the daily counter but not the per-batch log for 2022–early 2025.
4. **Distinct recipients per day.** The log counts deliveries, so a Member who receives three Broadcasts on one day counts three times. The provider's cap is presumably on messages, so this matches, but distinct-reach cannot be derived.
5. **Audience selector used** (all / full / provisional / auxiliary) — inferable only from the auto-prefixed subject line, not logged as a field.
