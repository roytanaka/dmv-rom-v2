---
status: accepted
date: 2026-09-07
accepted: 2026-09-07
---

# Emailing: Broadcasts, Direct messages, Reminders, and Notices

From the wayfinder map [#458](https://github.com/roytanaka/dmv-rom-v2/issues/458) (_emailing: Broadcasts, Reminders, Notices, and member-to-member mail_). Twelve tickets over 2026-09-06 and 2026-09-07 settled the model; this ADR is where their answers land. It records every email the app sends, and the shared ground every one of them stands on: transport, sender identity, the recipient's locale, the no-email flag, the queue, the cron, and the sent record.

It **pays the debt [ADR-0011](0011-authorization-model.md) and [ADR-0017](0017-authorization-enforcement.md) named**: the Directory hides contact details, so the app owes an in-app, email-native way for one Member to reach another. It **is the mail-provider ADR [ADR-0002](0002-stay-on-stormweb-shared-hosting.md) promised**, and it **amends ADR-0002** in one line: email no longer sends synchronously inside a request. It **refines [ADR-0021](0021-scheduling-first-pass.md)**: the Sign-up cancellation mail moves onto the queue, and the "daily reminder pipeline" that ADR deferred is decided here. Amendment notes are folded back onto those ADRs.

**No product code ships from this ADR.** The spec does.

## Context

The app sends one email today: a Member cancels a Sign-up and the Group's Schedulers hear about it, through the `log` mailer. Nothing else is wired. No cron, no scheduler, no opt-out, no composer.

Legacy sends a great deal more, through one shared composer reached from every list page, one nightly job, and a handful of member-administration flows. Over the last two years it delivered a median of 184 mails a day, hand-written officer Broadcasts making up 86 percent of them. Every active committee used the composer. The rebuild ports all of it, because a volunteer department runs on these mails.

Four findings shaped everything below.

**The host counts the cap per recipient, and the hourly limit is the one that binds.** The hosting account may send 4,000 mails in a rolling 24 hours and 500 in a rolling hour, and a message to ninety addresses costs ninety. Legacy never came near the daily cap (max 2,099) but exceeded the hourly limit 23 times in two years, always from mid-size sends stacked in one hour. One department-wide Broadcast is most of an hour's budget on its own. This decided per-recipient sends, the queue, and the throttle.

**Every legacy Audience is a list-page filter that the browser posts back.** The server never re-derives the recipient list. Whoever can reach a page with a Send button can mail whatever that page lists, and the list, the From alias, and the no-email exclusion are all trusted from the request. This decided that the server resolves every Audience at send time, and fixed who may pick which.

**Legacy's opt-out never worked, and the law does not require one for this mail.** The unsubscribe link was built from the sender's address with no token and no record; the only working opt-out is editing a Member's address to a sentinel. Under Canadian anti-spam law, Reminders, Notices, member-to-member mail, and committee-business Broadcasts are not commercial messages, so no unsubscribe mechanism is owed. The one edge, a Broadcast promoting a paid trip or a ticketed event, is handled by policy.

**Nothing reads the mailbox.** Bounces land in the sending mailbox and nobody parses them. Inbound mail is out of scope, so a bad address that bounces after the host accepts it is invisible to the record. Accepted; named where it matters.

## Decision

### 1. Vocabulary

Five nouns for mail, three for the machinery. All go into `CONTEXT.md`.

- **Broadcast**: a message an officer writes and sends to an Audience.
- **Direct message**: a message any Member writes and sends to one other Member, from the Directory or a profile. A peer of Broadcast: same composer, same record, same queue, same no-email rule. Differs only in who may send it and that its Audience is one Member.
- **Audience** (of a Broadcast): a named set of recipients, resolved on the server at send time. Distinct from a Shift's `audience`, which decides who may take a Shift.
- **Reminder**: an automatic mail about the recipient's own upcoming Sign-up.
- **Notice**: an automatic mail triggered by an event, such as a cancellation or a resignation.
- **No-email flag**: a Records-set switch that silences all mail to a Member.
- **Delivery**: one queued mail to one recipient. The unit the throttle counts and the sent record stores. States: pending, sent, failed, expired.
- **Drain**: the every-minute scheduled task that sends pending Deliveries. The only code path that talks SMTP.

**"Notification" is not a domain word.** It named too many things (a Reminder, a Notice, a browser toast, a GitHub approval mail). Retired; earlier ADR prose that says "notification email" means a Notice.

### 2. Transport: the host's own SMTP

**Laravel's `smtp` mailer against the hosting account's mail server, over STARTTLS on port 587, authenticated as a dedicated app mailbox.** No external provider, no package. Decided after research and confirmed end to end from the web box: TLS 1.3, a clean certificate, login accepted.

Why the host and not a service: volume is small (a median 184 a day against a 4,000 cap), the DMV would not pay for a service, and Canadian residency is better kept. The host's policy binds a third-party service the same way and requires affirmative consent from recipients; a membership application is that consent, stated here once beside the anti-spam finding in §6.

Why `smtp` and not `sendmail`: at the cap, SMTP refuses the login synchronously and the app knows; `sendmail` accepts and fails later as a bounce nobody reads. SMTP also aligns the envelope sender with the domain, which is what SPF and DMARC check.

**Facts the spec relies on.** Both caps are rolling windows, counted per recipient: 4,000 a day, 500 an hour, per hosting account, shared by staging and production. The server advertises a 512 MB message limit; the app sets its own ceiling at 50 MB. SPF, DKIM, and DMARC exist for the domain; DMARC reports flow to the app mailbox and the policy stays `p=none` until the app is the only sender. The app mailbox is read by Roy until Records takes it over; its password lives in the server `.env` and nowhere in the repo.

### 3. Sender identity and locale

**From is one app address.** Its display name is the Group's name for a Group Broadcast, a Reminder, or a Notice; the app's name for an org-wide Broadcast; the sender's name for a Direct message.

**Reply-To is the person who wrote the mail.** A Broadcast's Reply-To is the sending officer; a Direct message's is the sender. So a reply discloses the recipient's own address to the sender, by the recipient's choice, and the app never shows an address to anyone. **Automatic mail has no Reply-To.** Replies to a Reminder or a Notice land in the app mailbox, which is skimmed, not a no-reply box.

**The sender gets a copy** of every Broadcast and Direct message, sent last (§4).

**Locale.** A Broadcast or Direct message goes out as written, in one language: it is content ([ADR-0004](0004-chrome-only-translation.md)). Automatic mail is chrome, rendered in each recipient's saved locale, with every link built for that locale ([ADR-0008](0008-bilingual-url-routing.md) §10). Group names, Schedule names, shift kinds, and Member names pass through as authored.

**Links in mail are plain links to the page.** Login is required. No auto-login tokens ([ADR-0001](0001-authentication-and-identity.md)); legacy's Reminder links carried the Member's address and part of their museum ID in the URL.

### 4. Delivery: one throttled queue, drained every minute

**Per recipient, never BCC blocks.** The host counts the cap per recipient, so a ninety-address block costs ninety and buys nothing. Each mail has one To, and can carry the recipient's name and locale. CC mode, which legacy used for audiences of ten or fewer and which exposed addresses, is gone.

**Nothing sends inside a web request.** A send writes one **Delivery** row per recipient. The **Drain** runs every minute, sends the next slice, and marks each row. Broadcasts, Direct messages, Reminders, and Notices all ride this one queue, so the Sign-up cancellation mail that ADR-0021 sends inside the request moves onto it. One code path, one throttle, one sent record.

**The throttle: 450 in any rolling hour, at most 8 per pass.** That leaves 50 of the host's 500 for the account's human mailboxes. A department-wide Broadcast of about 450 finishes in roughly an hour; anything under 8 recipients lands within a minute; a day's Reminders (max 188 in legacy) fit inside the same budget. The daily cap is **not enforced by the app**: legacy never passed 2,099 against 4,000, and if the host refuses at the cap, that is a connection-level failure handled below. The server's per-connection limits (100 messages, 750 recipients) never bind at 8 a pass.

**Failure, in two classes.**

1. **Connection-level** (cannot connect, cannot log in, which is also how the daily cap presents over SMTP): the pass stops, no row is changed, the next minute tries again. **No give-up**: the cap clears over a rolling day and outages end. The queue records **last successful send** and **last connection error** with its message; §10 says who reads them, and the error is reported, not swallowed.
2. **Row-level**, classified by the SMTP reply code Symfony puts on the exception. A temporary failure (4xx, or the connection dropped mid-message) gets a next-attempt time and **three tries**: next pass, then 10 minutes later, then 1 hour later, then **failed**. A permanent failure (5xx, or an address that fails validation when the row is written) is **failed on the first try**.

**The sender's copy is the done signal, and it names who was not reached.** It is sent last. When any Delivery failed it carries a footer naming the Members not reached, up to 20 then "and N more"; a Direct message's reads "Could not deliver to <name>". Nothing mails a Records mailbox; the sender chose those people and is the only one who will chase them. Bounces that arrive after the host accepted the mail are not seen (Context); the record undercounts them.

**Expiry.** A Reminder's Delivery **expires at its Shift's start**: a revived queue must not remind people about Shifts that already happened. Broadcasts, Notices, and Direct messages never expire; late is still wanted.

**Not Laravel's queue driver.** Delivery rows are our own table, needed anyway as the sent record and the progress count; a `jobs` table beside them would be a second store of truth. The spec may revisit if the rows turn out to duplicate framework work.

**Attachments: two per Broadcast, 10 MB total**, attached at send time and never stored. Per-recipient sends multiply bytes, so the composer's rule of thumb is a link to a Document, not a file.

### 5. Audiences, and who may send to whom

Every Audience is **resolved on the server at send time** from the Group model. The browser names the Audience; it never posts a recipient list, a From alias, or an exclusion. This is the fix for the largest legacy defect and it is not optional.

"Present standing" means `MembershipStatus::canSignUp()` is true: Full, Trainee, Transitional, Auxiliary, Projects, Emeritus, Donor. "Records" means member-administration authority, a membership in the Group that stewards `member_admin`; super-tier inherits everything. "The Group's officers" means memberships holding any `Role` in that Group, not a fixed subset: legacy's executive lists are free-text positions with no status filter.

_(Amended 2026-09-07, from the legacy maintainer's note on the DMV members list.)_ **"Org-wide senders"** are the Members who may mail the whole department: a membership in any Group that stewards a new `org_mail` stewardship function (seeded on DMV Executive, Records, and Awards), or a `Role::Chair` in any active Group at any depth. This is legacy's rule for the Send option on the DMV list, ported faithfully; the three stewarding Groups are the ones legacy names, and a stewardship is how the Group model already says "this Group runs an org-wide function" ([ADR-0011](0011-authorization-model.md)). Legacy also shows the Board and Chair sublists to anyone who can open the list, so those three Audiences are open to any Member.

| Audience | Who may pick it | Definition |
| --- | --- | --- |
| All Members | org-wide senders | `Category` with an access tier, minus `Loa`: Active, PreActive, Provisional, Sustaining, Honourary. **Not** the Directory set, which keeps LOA and drops Provisional and PreActive. |
| All Members and on leave | org-wide senders | all six current Categories |
| Active and Provisional | org-wide senders | `Category` in {Active, Provisional} |
| One Category | org-wide senders | the chosen Category |
| Board of Directors | any Member | the DMV Executive Group's roster in present standing |
| Committee Chairs | any Member | `Role::Chair` holders in every active Group that was a legacy top-level committee |
| All Chairs | any Member | `Role::Chair` holders in every active Group, any depth |
| Whole Group | any member of the Group | the Group's memberships in present standing (drops Inactive and LOA, keeps Donor) |
| Whole Group and on leave | any member of the Group | present standing plus `Loa` |
| One status | any member of the Group | memberships with that `MembershipStatus` |
| Active | any member of the Group | memberships with status `Full` |
| The Group's officers | the Group's officers | memberships holding at least one `Role` |
| Sign-ups on a Schedule | the Group's officers | distinct Members with a Sign-up on any Shift of the Schedule ending now or later |
| Sign-ups on one Shift | the Group's officers | distinct Members with a Sign-up on that Shift. **New**; legacy addressed only the whole event. |
| A child Group's roster | the parent Group's officers | the child's memberships in present standing |
| One Member | any Member | that Member, from the Directory or a profile (a Direct message) |
| Hand-picked | any member of the Group; org-wide senders on the Directory | the ticked rows, drawn from the page's roster |

**Exclusions.** Only the no-email flag survives as a rule (§9). Legacy's blank-address, shared sign-in account, and non-member account-type exclusions are dead. Recipients are de-duplicated by Member; the two current shared-address pairs each receive two copies, which is correct.

**Two deviations from legacy, named.** First, the **Scheduler role gets the Sign-up Audiences** alongside the Chair; legacy gave them to the Chair bit only, and `Group::schedulers()` already treats the two alike. Second, **All Chairs picks up child-Group Chairs** at any depth, a harmless superset.

_(Amended 2026-09-07.)_ **The org-wide picker rule is no longer a deviation.** The accepted text gave every org-wide Audience to Records alone, as the map's residual authorization fork, because research had only found "any Chair may mail All Members". The legacy maintainer then supplied the full rule for the DMV list: DMV Executive, Records, Awards, and any Chair may send org-wide, and anyone may mail the Board and the Chairs. That is the "org-wide senders" definition above, ported as is. The hourly budget is protected by the throttle (§4), not by narrowing who may send. Tightening later is one line in the picker rule and one seeded stewardship fewer.

**Hand-pick surfaces that are not Audiences.** Legacy's Trainers, Section Admins, Coordinators, per-tour and per-walk lists, and Day-availability lists are roster filters owed with scoped roles and the content catalog. They are not ported here.

### 6. Broadcast and Direct message: the composer

**Where compose lives.** One control, "Email ▾", wherever an Audience is reachable. Its menu **is** the Audience list: each item names an Audience and its resolved count, and a last item, "Pick people…", opens a hand-pick from the page's roster. Prototyped on `prototype/compose-picker`; the winner was the Audience menu with a stepped side sheet, its Who step reshaped into a To field of name chips.

1. **Group page**: in the sticky section-tab strip, on every section. Org-wide Audiences never appear here.
2. **Roster tab**: the same control moves into the roster toolbar, beside the officer controls.
3. **Schedule**: in the opened Schedule's card header. Its menu leads with "Sign-ups on <Schedule>", then the Group's Audiences.
4. **Shift**: in the Shift card's action row, only when a seat is taken. One Audience: "Sign-ups on this Shift".
5. **Directory**: in the page header, for every Member who can open the Directory. Any Member's menu lists Board of Directors, Committee Chairs, and All Chairs; an org-wide sender's menu adds the remaining org-wide Audiences and "Pick people…" _(amended 2026-09-07, §5)_.
6. **Member profile**: a "Message <first name>" button in the profile header, hidden on the viewer's own profile. Opens the same sheet with one fixed recipient.

**The sheet, stepped Who → Message → Sent.** A right-hand sheet; the host page stays visible behind it.

- **Who.** Heading "Who · N Members", live. A line naming the Audience as the record will store it. A **To field of name chips**, one per resolved recipient, each with an × that removes them; removing sets the record's edited flag and the Audience name stays ("Whole Group, 2 removed"). An "Add people" control opens a panel under the field: name search, tick-all, the page's roster as tick rows. "Pick people…" opens the sheet with that panel open and the To field empty. The Audience is the select-all; there is no other. **Unreachable Members are not marked here.** They are reported after send.
- **Message.** A read-only To summary, then "From <Group name> · replies come to you · you get a copy". Subject, rich-text body, attachments (names and sizes shown). Send is disabled until subject, body, and at least one recipient are present.
- **Sent.** "Queued for N Members. Delivery takes up to an hour; you get a copy when it is done." For an officer, "M could not be reached (email switched off): <names>". For a Direct message to a flagged Member, the refusal "cannot be reached" replaces the whole flow.

**Direct message.** Same sheet. Who shows the one recipient (avatar, name, no address) and the note that a reply comes to the sender's own address and so shows it, by the recipient's choice. Next goes straight to Message.

**Rich text.** The body is rich text; a tiptap editor is added, **flagged for package review** under the hard rule. Mail headers are built by the framework from validated fields, never from raw request strings.

**The sent record.** One row per composer send: sender, Group (null for org-wide and Direct messages), the Audience's name plus the edited flag, resolved recipient count, subject, body as sent, attachment names and sizes, queued time, sent and failed counts. The recipient list is not a separate field: it is the Delivery rows, one per recipient, **kept forever**. Readable in the **Records-only** field-visibility tier plus the sender for their own sends. Pruning to counts is a small job later if it ever matters. **No sent-items screen this pass**, and **no drafts**: a reload loses the message, as in legacy.

**No abuse guard for Direct messages** beyond authorization and the record. Two years of the send log show a long tail of single sends and no abuse; Reply-To makes a Direct message traceable by its recipient. Laravel's rate limiter is a one-line add if that changes.

**The anti-spam rule, as policy.** Reminders, Notices, Direct messages, and Broadcasts on committee business are not commercial messages under CASL, so no unsubscribe is built. A Broadcast that promotes a paid trip or a ticketed event **is** a commercial message, and then needs sender identification and an opt-out. **Rule: paid things go out through the newsletter**, which runs outside this app with its own unsubscribe. The composer does not enforce it; the officer guidance says it. Membership is the affirmative consent the host's policy requires for every mail below.

### 7. Reminders and the empty-desk alert

**Reminder settings are per-Group fields a Chair or Scheduler edits**: on or off, and lead days. Off by default ([ADR-0015](0015-scheduling-model.md): opt-in); seeded on with 3 days for the five Groups that run Reminders today (Docents, Guides du ROM, Visitor Wayfinders, Visitor Guides, Reception). Invitations will set 4 when built. Meaningful only where scheduling is on.

**A Reminder is a window with a sent record, not a date match.** Legacy selects Shifts dated exactly today plus lead days, with no record: a day the cron does not run is a day of Reminders never sent, and a Sign-up taken inside the window gets none. Here, the daily pass sends for every Sign-up whose Shift starts after now and before the end of today plus the Group's lead days, on a published Schedule, in a Group with Reminders on, to a Member without the no-email flag, **for which no Reminder Delivery exists yet**. That predicate is idempotent and catches up a missed day for free. The Delivery is keyed on the (Shift, Member) pair, so a Sign-up dropped and re-taken by the same person is one commitment and one Reminder.

**One bilingual chrome template covers every Group.** Legacy has one sentence per module with different nouns, a French module under English chrome with English date names, times with no AM/PM and no end time, and a display name "DMV Reminder". Here: subject "Reminder: your <Group> shift on <date>", the Member's name, the Group, the Schedule name, start and end with AM/PM, the shift kind when there is one, and a plain link to the Schedule. Wording from the research doc, in both locales. Two Group-specific lines are dropped: Visitor Wayfinders' "sign in on the Register" (the kiosk is closed, [ADR-0023](0023-scheduling-second-pass.md)) and Visitor Guides' "specify a replacement" (no replace flow). If a Chair wants a Group note back, the hook is one optional as-authored `reminder_note` on the Group; not built now.

**The cross-Group skip rule dies.** Legacy mirrors a slot onto two Groups' schedules and each module skips the other's holder so one person gets one Reminder. A v2 Shift belongs to one Group; cross-Group participation is the `open` audience on that one Shift ([ADR-0021](0021-scheduling-first-pass.md)), so exactly one Group's Reminder applies. A Member with two Sign-ups on two overlapping Shifts gets two Reminders, correctly.

**The empty-desk alert** is a per-Group setting, on for Visitor Guides by default: shift kinds to watch (a flag on the shift kind, so a Group without kinds cannot use it, as in legacy) and days ahead (3). **It runs only on days of the month divisible by 3.** That cadence ports faithfully and is odd: never on the 31st, 1st, or 2nd, so a 31-day month has a four-day gap. It is a constant, not a setting. On a run day, for each watched kind on a published Schedule from today through today plus days-ahead, a Shift with **zero Sign-ups** (not below capacity) is listed; if any, one mail goes to the Group's roster in present standing with the flag off, naming each open Shift with date, times, and kind, today's marked, with a plain link to the Group's schedules. A run row per (Group, date) is written even on a silent day, so a second run the same day is silent too; a missed cadence day is not caught up.

**Recipients are wider than legacy's.** Legacy mails Visitor Guides' `Full` status only. Here it is every standing that can sign up, because a vacancy alert to someone who cannot fill it is noise and to someone who can is the point. The one residual fork on this map: if the Visitor Guides Chair wants it narrow, it is a one-line change to the recipient predicate.

### 8. Notices

**Sign-up cancellation** ([ADR-0021](0021-scheduling-first-pass.md) §4) stays as decided: unconditional, to the Group's Schedulers and Chairs, in each recipient's locale. It now writes Delivery rows instead of sending inside the request. Legacy's one cancellation mail (Guides du ROM, current month only, one Scheduler) is a subset of it; nothing more to port.

**Standing change: Resigned and Deceased mail the Chairs.** When a Member's `Category` moves into Resigned or Deceased through member administration, every `Role::Chair` holder of every Group where that Member's Membership is not already departed gets one mail: for Resigned, any status but `resigned` or `deceased`; for Deceased, any status but `deceased`, so a Chair whose roster already shows the Member resigned is still told of a death. Co-chairs each get one. The mail names the Member, the Group, the new standing, and the effective date, and carries a plain link to the Group's roster. The Chair updates their own roster; the Notice changes nothing but the Category.

**The trigger is a new standing-change action.** v2 has no write path for `Category` today; the member-edit request whitelists it out on purpose. The Notice hooks into the action that must be built anyway, guarded by "category changed and the new value is a departure": no observer, no state machine, no sent record for the guard. A repeated save sends nothing; Resigned then Deceased sends twice, correctly. That action's own shape (a standing field on the profile or a separate action, a category history or not, what happens to Memberships) is member-administration scope and is specced there, not here. The ADR names the hook and its recipient rule so the two specs meet.

**Silences, all faithful.** Leave of absence, reinstatement, and Withdrawn send nothing. Officer assign and remove send nothing (ADR-0021). No takeover notice: the rebuild has no replace flow. **Deviations from legacy, named**: "reply to confirm" is dropped for the roster link, because automatic mail has no Reply-To and the Chair can act in-app; the bcc to an administrator mailbox is dropped, it was a debugging copy the sent record replaces; the sub-committee variant (auto-remove and announce) is collapsed into the one rule, because a sub-committee is a child Group here and legacy's variant never sent in production.

### 9. The no-email flag

**One Records-set switch on the Member that silences everything**: Broadcasts, Direct messages, Reminders, Notices. A member-administration field in the Records-only visibility tier; super-tier inherits it. It replaces legacy's opt-out by editing the address to a sentinel; four current Members carry that sentinel today and none holds a role.

**Checked once, when Delivery rows are written.** A flagged Member gets no row. **A sender learns a Member is unreachable only by trying to reach them**: a Direct message to a flagged Member is refused with "cannot be reached"; a hand-pick reports which ticked names were skipped; a named Audience reports the skipped count and names to the officer. Nobody is shown a list of flagged Members they did not address, and the picker never marks them.

**There is no other opt-out.** The legacy unsubscribe never worked; the newsletter has its own; the law needs none for this mail (§6). Migrating the sentinel to the flag belongs to the legacy migration plan.

### 10. The daily run: cron, Drain, heartbeat, status

**One crontab line per environment folder, every minute**, in the control panel's user cron, with the full PHP 8.4 path (the host's default `php` is 7.4):

```
* * * * * cd <app folder> && /usr/local/php84/bin/php artisan schedule:run >> /dev/null 2>&1
```

Everything else is scheduled in code. **Staging runs the same line and keeps `MAIL_MAILER=log`**, so it exercises the scheduler without spending the shared daily cap. The publicly reachable cron URL legacy used is gone.

**Two scheduled tasks**, in `routes/console.php`:

1. **The Drain**, every minute: sends the next slice of pending Deliveries (§4). The only code path that talks SMTP.
2. **The daily pass**, 06:00 in the org timezone: the Reminder and empty-desk logic of §7. It **only writes Delivery rows**; the Drain sends them within the hour.

**Double runs** are prevented by `withoutOverlapping(10)` on both, locking on the existing `cache_locks` table of the database cache store. The 10-minute expiry means a crashed pass cannot jam the queue for the default 24 hours.

**The heartbeat is Sentry.** Package `sentry/sentry-laravel`, **flagged for package review**; this ADR proposes it, the spec installs it. A cron monitor on the Drain emails the developer when the every-minute check-in stops; the check lives outside the host, so it works when the cron is dead, which is what the legacy start-and-finish mails could never do. The Drain **reports** the connection-level exception instead of swallowing it, so "cron runs but cannot send" alerts once, grouped, not every minute. Every plan includes one monitor; the daily pass is not monitored, because a dead scheduler trips the Drain's monitor and a throwing daily pass reports as an error. **Privacy**: `send_default_pii` off, a `before_send` scrubber for email addresses, Member data stays out of error payloads. Sentry has no Canadian region; named here beside the residency point in §2. Everything else in the app gets crash reports too, the wider win for a sole engineer.

**A "Mail status" page, super-tier only**, from the user menu. Shows scheduler last ran, mail last sent, last connection error with message and time, pending Delivery count. Two warnings: **Dead**, scheduler last ran is older than 10 minutes; **Cannot send**, the last connection error is newer than the last successful send. "Mail last sent" is shown, not judged. "Mail last sent" is derived from the newest sent Delivery; "scheduler last ran" and "last connection error" are two cache keys the Drain writes, wiped by a deploy's cache clear and rewritten within a minute. Promote to a table if the spec finds a reason. The Dashboard stays the launcher.

**Legacy's start-and-finish mails are dropped.** A dead cron cannot send them.

### 11. Owed: mails whose host feature is not built

Every mail legacy sends is ported, but these belong to features the rebuild does not have yet. Each is listed with its timing rule so the feature's spec can pick it up without re-reading legacy. Each rides the same queue, From, locale, and no-email rules as everything above.

- **Group-tour Reminders** (Docents, Guides du ROM): 3 days before, names the tour and the **client**. With the booking capability.
- **Booking-driven mails**: the tour-request and substitute-call forms that log the requesting Member as sender (about 7 percent of legacy batches), and group-tour and bus-booking confirmations. With the booking capability; rules to be read when it is specced.
- **Invitations Reminder**: **4 days** before, to guests who **accepted**, on events that are active and visible; names the event, the sub-events accepted, the event description, and the organiser as the person to contact. With Invitations.
- **Intake Notices**, the mentor-and-Records pattern. Four fire from an applicant's status change and are the mentor telling Records, Reply-To the mentor: **New Provisional** (accepted or placed, no member record yet; Records, cc mentor); **Applicant Placement** (placed, record exists; Records, cc mentor); **Yellow Card completion** (applicant marked active; Records, cc a Treasurer role holder and the mentor); **Applicant Withdrawn** (marked incomplete with a withdrawn reason; Records, cc mentor; legacy also cc'd a ROM staff mailbox outside the app). The fifth, **Member Now Active** (Category Provisional → Active), is Records telling the mentor and hooks into §8's standing-change action once a mentor exists to address; legacy's cc to Records is self-notification and can go. All five with intake, mentor, and placement.
- **Membership renewal Reminders and the fees-not-paid Notice**: from the nightly job, non-retrying in legacy. With membership renewal.
- **Zoom notices and query results**: named in charting; with their features, rules to be read then.

Not owed: the Gallery Interpreters, Outreach, and ROMWalks Reminders (names in a list, no module exists), and the "taking your shift" mails (the replace flow, out of scope).

### 12. Legacy defects this port does not inherit

Fixed, not ported. Named per the map's faithful-port rule.

1. **No authorization on the send endpoint.** Every send now passes a policy: the Audience table in §5 is the rule.
2. **Recipient list and From alias trusted from the browser.** The server resolves both.
3. **An unsubscribe link with no token and no record.** Removed; no opt-out but the flag (§9).
4. **Opt-out by editing the address to a sentinel.** Replaced by the flag.
5. **CC mode exposing addresses to small audiences.** Gone; per-recipient sends.
6. **Unsanitized mail headers.** Framework-built from validated fields.
7. **A publicly reachable cron URL.** Control-panel cron only.
8. **A daily cap shown but not enforced.** Replaced by a real hourly throttle and a status page; the daily cap is left to the host.
9. **Auto-login links carrying the Member's address and museum ID.** Plain links, login required.
10. **Reminders with no sent record and no window.** A Delivery per (Shift, Member), catch-up for free.
11. **A French Reminder under English chrome**, with English date names and a likely mojibake on accented names. One bilingual template.
12. **Start-and-finish audit mails** that a dead cron cannot send. Sentry and the status page.

## Considered alternatives

- **An external mail service** (a transactional provider). Rejected: cost the DMV would not carry, data leaves Canada, and the host's policy binds a service the same way. Revisit on the ADR-0002 vendor-failure trigger.
- **BCC blocks of ninety inside the request**, the ticket's original default. Rejected once the host confirmed per-recipient counting: blocks save connections, not budget, and lose the name and locale.
- **Small Broadcasts in the request, large ones queued.** Rejected: legacy's over-limit hours came from bursts of mid-size sends, and a throttle blind to in-request sends cannot protect the limit. A second send path is a second place the limit leaks.
- **Laravel's queue driver with a `jobs` table.** Rejected for now: the Delivery rows are the record and the progress count, and a second table is a second truth.
- **Org-wide Audiences for Records only**, the accepted text's residual fork. Superseded 2026-09-07 once the legacy maintainer supplied the full DMV-list rule (§5): ported instead, with the throttle guarding the budget.
- **Pruning Delivery rows to counts** once a send finishes. Rejected: retry and any future sent-items screen need the rows, failed rows must be kept anyway, and the cost is a who-got-what log in the Records-only tier.
- **A "Message" or "Mail" umbrella noun, or a kind field on Broadcast**, to cover member-to-member mail. Rejected for two familiar nouns, Broadcast and Direct message, sharing one record.
- **Marking unreachable Members in the picker.** Rejected: it tells an officer about flags on people they did not address.
- **A header button with a two-pane dialog**, and **select-first with a full-page composer**. Prototyped and rejected; the To field of chips from the second was kept.
- **healthchecks.io ping** for the heartbeat. Rejected: no package, but a second vendor and no error reporting.
- **A stuck-Broadcast alert.** Not this pass; the sender's copy is the done signal and the heartbeat covers a dead or unable cron.
- **A Broadcast footer with a Broadcast-only opt-out** for the commercial edge case. Rejected for the policy rule; the newsletter already has the machinery.
- **Making the empty-desk cadence a setting.** A new feature; the constant ports.

## Consequences

- **Every mail the app sends waits for a cron pass.** Nothing is instant; the cancellation Notice that today goes out in the request now lands within a minute. A dead cron means no mail at all, not just late Reminders, which is why the heartbeat is not optional.
- **Two packages are proposed and flagged for review**: tiptap for the composer body, `sentry/sentry-laravel` for the heartbeat and crash reporting. The spec installs them or finds substitutes.
- **Staging and production share the daily cap.** Staging keeps the log mailer; a staging test of real sending spends production's budget.
- **The sender's copy is the only failure signal**, and it cannot see bounces that arrive after acceptance. The record undercounts bad addresses until inbound mail is in scope.
- **Records gains three fields and one screen**: the no-email flag, the standing-change action (its own spec), and read access to the sent record. Super-tier gains the Mail status page.
- **Groups gain four settings** (Reminders on, lead days, empty-desk alert on, days ahead) and shift kinds gain one (watch when empty). A Chair or Scheduler edits them.
- **Org-wide mail stays with legacy's senders.** DMV Executive, Records, Awards, and every Chair may mail the department; anyone may mail the Board and the Chairs. A new `org_mail` stewardship names the three Groups. Tightening is one rule and one seed _(amended 2026-09-07, §5)_.
- **Some Members get a Reminder legacy would not have sent**: one taken inside the lead window, and one for a Shift whose exact reminder day the cron missed. Both are the point.
- **The Sentry payloads leave Canada.** Scrubbed of addresses, PII off; the residency story is the mail, not the crash reports.
- **The prototype branch is a reference, not a base.** `prototype/compose-picker` fakes the flag and the send and pushes the roster into every Group section; the real build needs an endpoint that resolves an Audience by name, count and rows.
- **Code deltas:** a `deliveries` table and model with the four states, a `broadcasts` (sent record) table, an empty-desk run table; the Drain and the daily pass as scheduled commands with overlap locks; the `smtp` mailer config and the app mailbox; Mailables for Reminder, empty-desk alert, standing-change Notice, Broadcast, Direct message, and the sender's copy; the Audience resolver and its policy; an `org_mail` stewardship function seeded on three Groups; the compose sheet and its six entry points; the no-email flag on `members`; four Group settings and one shift-kind flag; the Mail status page; two packages.

## Deliberately out

- **The standing-change action itself.** Member-administration scope; §8 names only the hook and recipient rule.
- **A sent-items screen.** The record exists; the screen is new scope.
- **Self-service unsubscribe** and any opt-out beyond the flag.
- **Inbound mail** of any kind: no bounce parsing, no reply relay.
- **The newsletter.** A separate system with its own list and unsubscribe.
- **Takeover and leave notices.** No replace flow; legacy sends no leave notice.
- **The owed mails of §11**, specced with their features.
- **Migrating the no-email sentinel** and the legacy send log. The migration plan owns both; this ADR takes facts from research, never data.
- **Legacy's hand-pick roster filters** (Trainers, Section Admins, Coordinators, per-tour lists). With scoped roles and the content catalog.

## References

- Wayfinder map [#458](https://github.com/roytanaka/dmv-rom-v2/issues/458) and its tickets: [#459](https://github.com/roytanaka/dmv-rom-v2/issues/459) (Stormweb mail), [#460](https://github.com/roytanaka/dmv-rom-v2/issues/460) (legacy volume), [#461](https://github.com/roytanaka/dmv-rom-v2/issues/461) (anti-spam law), [#462](https://github.com/roytanaka/dmv-rom-v2/issues/462) (Audiences), [#463](https://github.com/roytanaka/dmv-rom-v2/issues/463) (Reminders and the empty-desk alert), [#464](https://github.com/roytanaka/dmv-rom-v2/issues/464) (standing-change Notices), [#465](https://github.com/roytanaka/dmv-rom-v2/issues/465) (delivery), [#466](https://github.com/roytanaka/dmv-rom-v2/issues/466) (the record and the Direct message), [#467](https://github.com/roytanaka/dmv-rom-v2/issues/467) (compose placement and the picker), [#470](https://github.com/roytanaka/dmv-rom-v2/issues/470) (host confirmation and the mailbox), [#476](https://github.com/roytanaka/dmv-rom-v2/issues/476) (failure), [#477](https://github.com/roytanaka/dmv-rom-v2/issues/477) (cron and heartbeat), [#468](https://github.com/roytanaka/dmv-rom-v2/issues/468) (this ADR)
- Research, on their `research/` branches: `docs/research/stormweb-mail.md`, `legacy-email-volume.md`, `casl-unsubscribe.md`, `legacy-email-audiences.md`, `legacy-reminders.md`, `legacy-standing-notices.md`
- `prototype/compose-picker`: the composer prototype, three variants and the winner, with README
- [ADR-0002](0002-stay-on-stormweb-shared-hosting.md): hosting; amended here (mail is queued, not synchronous)
- [ADR-0011](0011-authorization-model.md) and [ADR-0017](0017-authorization-enforcement.md): the messaging debt, paid here
- [ADR-0021](0021-scheduling-first-pass.md): the cancellation Notice, moved to the queue; the reminder pipeline, decided here
- [ADR-0004](0004-chrome-only-translation.md) and [ADR-0008](0008-bilingual-url-routing.md): locale of automatic mail and its links
- [ADR-0001](0001-authentication-and-identity.md): no auto-login tokens
- [ADR-0010](0010-group-model.md): the Group model every Audience is resolved from
