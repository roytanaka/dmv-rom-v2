# DMV-ROM

Volunteer management for the Department of Museum Volunteers at the Royal Ontario Museum. The app supports ~500 bilingual (EN/FR) members who staff tours, programs, and committees. This document is the canonical glossary for domain terms — when language in the codebase, ADRs, or issues drifts, this file is the tiebreaker.

## Language

**Member**:
A person who belongs to the Department of Museum Volunteers and uses the app — the canonical identity of the system (the `members` table, model `Member`). A Member _has memberships_ in Groups; "Member" is the person, never the join-row (see **Membership**).
_Avoid_: Volunteer (the department's name contains "Volunteers," but the person/identity term is **Member**), user (too generic; survives only as the framework concept — `Auth::user()` returns a `Member`).

**Membership**:
A Member's join-row in a **Group** — carrying that Member's status within the Group and the **role(s)** they hold there. A Member may hold many memberships; each is scoped to exactly one Group.
_Avoid_: reusing **Member** for this (the person is the Member; the relationship is the Membership). "Group member" is fine prose for a person in a Group, but is never the schema name for the identity.

**Category** (of a Member):
A Member's standing in DMV _as a whole_ — Active, Sustaining, Honourary, Provisional, LOA, Resigned, etc. Lives once on the `members` record and sets the base access tier (Full / Limited / None). **Independent of the per-Group status a Membership carries** — a Member can be DMV-wide Active yet on leave from one Group.
_Avoid_: conflating with a **Membership**'s within-Group status; they are different facts set by different officers.

**Officer**:
A Member who holds at least one authority-bearing **role** (Chair, Secretary, Scheduler, Statistician, Vetting, Librarian, Content-maintainer, Treasurer) in a given **Group**. Always **per-Group** — a Member can be an officer of one Group and an ordinary member of another; there is no global officer status. See [ADR-0011](docs/adr/0011-authorization-model.md).
_Avoid_: using "officer" as an org-wide rank or as a synonym for the all-DMV grant (that is the **super-tier** — see **Admin**).

**DMV Executive**:
A specific **Group** (Kind: standing committee) — DMV's top governance body, one committee under the DMV root. Its leadership offices (President, VP1, VP2) are the Members seeded into the **super-tier**, but "Executive" names the _Group_, not the grant.
_Avoid_: equating "DMV Executive" with all-DMV access (that is the super-tier), or treating it as the org root (the root Group is DMV itself).

**Admin** (disambiguated — never use the bare word):
"Admin" means three different things; use the precise one:

- **Super-tier** — the single org-wide "see and do everything" grant, seeded with President / VP1 / VP2 (a flag on the Member; [ADR-0011](docs/adr/0011-authorization-model.md)).
- **Member administration** — managing member records and all-DMV reports; authority comes from holding a **role in the Records Group** (a stewardship), not a flag.
- **Support administration** — a maintainer administering data or impersonating for support; the explicit `initiate-support-session` permission ([ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md)), deliberately _not_ super-tier.
  _Avoid_: "admin" unqualified; admin-ness is a role or grant, never a membership standing.

**Directory**:
The org-wide, read-only roster of **Members** — every Member whose DMV-wide **Category** grants a listing (Active, Honourary, Sustaining, LOA), shown to any logged-in Member at `/directory`. Each row carries avatar, name, **Group** memberships, and standing — but never contact details (email/phone are a profile-only concern, gated by `viewContact`). The departed (Resigned/Withdrawn/Deceased) and the not-yet-activated (PreActive/Provisional) are excluded.
_Avoid_: treating the Directory as a member-administration or editing surface — it is read-only; managing member records is a separate authority (see **Admin** → member administration).

**Field-visibility tier**:
Which viewers a **Member** field reaches, enforced once in `MemberResource` ([ADR-0017 §6](docs/adr/0017-authorization-enforcement.md)) as an allowlist. Three tiers: **always-public** (name, photo, standing, Groups/roles — any logged-in Member); **peer-visible** (email + the three phones, gated by `viewContact`); and **Records-only** (the Member themselves, Records/member-administration, and super-tier — never a peer, even one who passes `viewContact`). The **home address** and a Member's **skills** are Records-only: the address is gated behind `viewAddress`, while skills are simply never added to any peer-visible payload (absent from `MemberResource` entirely) and surface only on the owner's own Skills settings page (#232, #247).
_Avoid_: calling Records-only fields "private" as if self-only — the Member's own record, Records, and super-tier all read them; only _peers_ are excluded. And a blocklist framing — the tier is an allowlist, so a new field is non-public until deliberately exposed.

**Login**:
The act of authenticating into the app with email + password. The only authentication flow in the rebuild.

**Impersonation**:
Acting as another **Member** via `Auth::login`. Two variants, distinguished by _depth_, not just environment: **become** — full login-as, the operator _is_ the other Member (the non-production dev **Role-switcher**); and **view-as** — read-mostly, consented, audited (the future production **support administration** tool, [ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md)). "Impersonation" is the umbrella; become / view-as is the safety axis.
_Avoid_: using "impersonation" to mean the production tool only — it covers both; name the variant when the depth matters.

**Persona**:
A curated, seeded, catalogued identity that exists only to be impersonated for dev/QA — realistic name and email, but fictional. Personas cover every authorization gate and membership standing so the whole authorization surface can be walked from the **Role-switcher**. They live in a code **persona catalogue** (the single source for seeding them, listing them in the switcher, and the impersonation allowlist), not behind a database flag. See [ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md).
_Avoid_: calling bulk-roster filler Personas — those are ordinary seeded Members; a Persona is specifically a catalogued impersonation target.

**Role-switcher**:
The non-production dev toolbar (`local`/`staging` only) that lets a **Support-operator** **become** a **Persona** to exercise role-gated behaviour and UX fast. Presentation over a server-driven prop; the environment boundary — not the toolbar's visibility — is the security control ([ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md)).
_Avoid_: "user switcher" (ADR-0009's older name) and conflating it with the production **view-as** support tool; the Role-switcher does full **become** on **Persona** data only. Note the operator is a **Support-operator**, _not_ super-tier (the two were split — see below).

**Support-operator**:
The maintainer capability to _operate_ the **Role-switcher** — a `support_operator` marker on **Member** (`isSupportOperator()`), deliberately **separate from super-tier**: running the switcher is a maintainer power, not a President's org authority, so a super-tier executive is _not_ an operator unless independently marked one. Read as a **direct predicate, never a Laravel gate** — [ADR-0017 §1](docs/adr/0017-authorization-enforcement.md)'s `Gate::before` grants super-tier every ability, so a gate-backed check would hand impersonation straight back to super-tier ([ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md) amendment; [ADR-0017 §5](docs/adr/0017-authorization-enforcement.md)). Distinct too from the production tool's `initiate-support-session` permission — two tools, two gates.
_Avoid_: equating it with super-tier or bare "admin"; it confers no org authority, only operating the dev switcher.

**Return to impersonator**:
The Role-switcher's escape hatch back to the original operator, from any impersonated Persona. Keyed on the operator id stored in the session at the start of impersonation, so it works regardless of the impersonated Persona's tier.
_Avoid_: "return to super" — the mechanism keys on the stored impersonator id, not on tier; and the operator is the **Support-operator** who began the session, who is deliberately _not_ super-tier (that split is the point — [ADR-0009](docs/adr/0009-user-switching-and-support-impersonation.md)).

**Group**:
The single organizing entity in DMV. Every committee, subcommittee, program, working group, project, and event cohort is a **Group** — a named set of people, each holding **role(s) in that Group**, with one parent, a lifecycle state, and a set of capabilities it switches on (meetings, documents, scheduling, content, vetting, announcements). Two capabilities are **always on** and carry no flag: **roster** and **hours** ([ADR-0022](docs/adr/0022-hours-and-statistics-model.md)). "Subcommittee" is not a separate noun: it is a **Group whose parent is another Group**. Authorization and document visibility are decided from a Member's Group memberships and the roles they carry there. See [ADR-0010](docs/adr/0010-group-model.md) for the Kind / Scope / Lifecycle axes and the capability set, and [ADR-0011](docs/adr/0011-authorization-model.md) for how authorization reads from it.

**Committee**:
A **Kind** of **Group**: a standing, org-scoped group that meets and holds documents but runs no shift scheduling (governance, operations, social). One Kind among several — not the central entity.
_Avoid_: treating "committee" as the organizing entity, or "subcommittee" as a separate noun. Both are Groups (see **Group**).

**Program**:
A **Kind** of **Group**: the member-facing operating units that run scheduling, content, and stats (docents, gallery guides, GDR, reception, special events). Distinguished from a **Committee** mainly by having the scheduling + stats capabilities turned on.

**Schedule**:
The container a **Group** publishes its **Shifts** in — a **named date range** owned by exactly one Group, in one of two states: `draft` (seen only by the Group's Scheduler/Chair and super-tier) or `published` (seen by the Group's `listing_visibility` audience). A calendar month is the common case, not a separate kind: "August 2026" and a Visitor Wayfinders occasion are the same entity with different ranges and names. Schedules may overlap, are kept indefinitely, and can be un-published or deleted only while they hold zero **Sign-ups**. Its `name` and `description` are as-authored **content** — never translated. See [ADR-0021](docs/adr/0021-scheduling-first-pass.md).
_Avoid_: "the month" or "the event" for this (see **Event** under Legacy vocabulary); and calling the _authoring screen_ a Schedule — that is a surface, this is the row.

**Shift**:
A dated thing a **Member** signs up to staff — a tour, a desk slot, an event role. The unit of scheduling. Belongs to exactly one **Schedule** (mandatory — there are no orphan Shifts) and carries a start and end time, an integer **capacity**, an optional **shift kind**, and an **audience**. Programs name it differently — docents say "tour," Visitor Guides say "shift" — but **Shift** is the canonical umbrella term.
_Avoid_: reading a Shift as one person's seat — it is a **slot** that holds up to `capacity` **Sign-ups**. Per-seat facts (one volunteer's hours, one volunteer's visitor count) belong on the Sign-up. And note what a Shift does **not** carry: no location (kind is the only descriptive axis), no state of its own (it inherits the Schedule's), no qualification flag (a requirement hangs off the **shift kind**), and no repeat rule — recurring Shifts are **bulk-written**, never spawned from a stored pattern.

**Shift kind**:
An entry in a **Group**'s small list of the kinds of shift it runs — "Desk", "Shadow", "Level 2 greeter". Per Group, nullable on a **Shift** (Reception's Shifts carry none). Model `ShiftKind`, table `shift_kinds`. This is [ADR-0015](docs/adr/0015-scheduling-model.md)'s **Catalog**, renamed. A **qualification** requirement hangs off the kind, never off the dated Shift — decided in shape only; nothing is built.
_Avoid_: "Catalog" (already three other things in this repo: the content catalog capability, the role catalog, the persona catalogue), and legacy's `Activity` / `Role` / `Type` (see Legacy vocabulary).

**Sign-up**:
The record that a **Member** has taken (or been assigned) a **Shift** — one Member, one Shift. Created by the Member themselves or by the Group's **Scheduler**; one entity, two actors. Carries **no state**: cancelling is deleting it, allowed for as long as the Member could have taken it (until the Shift starts — no deadline). A Member may hold Sign-ups on overlapping Shifts, but never two on the same Shift.
_Avoid_: confusing with **Login** (authentication) — a Sign-up is _staffing a Shift_, not authenticating. And "cancel state", "swap", "assistant" — swap and assistants are not built, and cancel is a deletion, not a state. And "attended" — see **Attendance**.

**Attendance**:
**Not recorded.** A **Sign-up** says a **Member** took a **Shift**; nothing says they turned up. This is a decision, not an omission ([#401](https://github.com/roytanaka/dmv-rom-v2/issues/401)). **Scheduled hours** are already written from the Sign-up alone, so attendance makes no reported number more correct; no Group runs a no-show workflow the app could serve; and the correction already exists — a **Scheduler** may remove a Sign-up at any time, including after the Shift has passed. A no-show is fixed by removing the Sign-up, which drops the credit with it.
_Avoid_: legacy's `Confirmed`, which means _signed out at the end of a shift_ rather than _was present_, is live in only six of ten Groups, and gates credit in only four of those. Legacy's `Attended` is a column no live code reads or writes. And do not read a Sign-up on a past Shift as proof anyone was there — it is proof of a booking nobody corrected.

**Audience** (of a **Shift**):
Who may take it: `group` (the default — Members of the owning Group, in a per-Group standing that permits sign-up) or `open` (any Member who can read the **Schedule**). This is what makes cross-Group participation a **read filter** rather than a relationship — there is no second row and nothing to keep in sync. A **Scheduler** placing a named volunteer is not bound by it.
_Avoid_: reading audience as an eligibility gate. Two separate floors decide whether a person may work at all (`Category::canSignUp()` DMV-wide, `MembershipStatus::canSignUp()` per Group); audience only decides who is shown a Sign-up button.

**Audience** (of a **Broadcast**):
A named set of recipients, resolved **on the server at send time** from the Group model: "Whole Group", "The Group's officers", "Sign-ups on this Shift", "All Members", "Board of Directors", and the rest of the table in [ADR-0024 §5](docs/adr/0024-emailing-model.md). Who may pick an Audience is part of its definition: any member of a Group gets its roster sets, the Group's officers get the officer and Sign-up sets, any Member gets the Board and Chair sets, and the remaining org-wide sets need an **org-wide sender**: a membership in a Group stewarding `org_mail` (DMV Executive, Records, Awards) or a Chair role anywhere. An officer may remove names from a resolved Audience; the record then keeps the Audience's name with an edited flag.
_Avoid_: confusing it with a **Shift**'s `audience` (`group` / `open`), which decides who may take a Shift, not who gets mail. And never let the browser post a recipient list: the server resolves the Audience, the browser only names it.

**Broadcast**:
A message an **Officer** writes and sends to an **Audience**, from the "Email ▾" control on a Group page, roster, Schedule, Shift, or (for Records) the Directory. Rich text, up to two attachments, one language as written (it is **content**). Sent by the app from one address with the Group's name as display name; Reply-To is the officer, who also gets a copy, sent last, naming anyone not reached. Stored as a sent record with its **Delivery** rows ([ADR-0024](docs/adr/0024-emailing-model.md)).
_Avoid_: "email blast", "mass mail", "newsletter" (the newsletter is a separate system); and using Broadcast for the one-to-one case, which is a **Direct message**.

**Direct message**:
A message any **Member** writes and sends to **one** other Member from the Directory or a profile. A peer of **Broadcast** sharing its composer, sent record, queue, and no-email rule; it differs only in who may send it and that its Audience is one Member. The sender never sees the recipient's address; Reply-To is the sender, so a reply discloses the recipient's address by their own choice. No abuse guard beyond authorization and the record.
_Avoid_: "member-to-member messaging" (the older ADR-0011 / ADR-0017 phrase), "DM", "chat", and any reading that implies an inbox: nothing is in-app, the app sends email.

**Reminder**:
An automatic mail about the recipient's **own upcoming Sign-up**, written by the daily pass for every Sign-up inside the Group's lead window (3 days by default) that has no Reminder **Delivery** yet, and sent by the **Drain**. Per-Group settings: on or off, lead days. Chrome, rendered in the recipient's **locale**; no Reply-To. A Reminder's Delivery expires at the Shift's start. The **empty-desk alert** is its sibling, a Group setting (watched shift kinds and days ahead, on for Visitor Guides): every third day of the month the roster is told which watched Shifts still have no Sign-up.
_Avoid_: "notification"; and reading a Reminder as a date match. It is a window with a sent record, so a missed day catches up and a late Sign-up still gets one.

**Notice**:
An automatic mail triggered by an **event**: a Member cancelling a Sign-up (to the Group's Schedulers and Chairs, [ADR-0021](docs/adr/0021-scheduling-first-pass.md)), or a Member's **Category** moving to Resigned or Deceased (to the Chairs of every Group where their Membership is not already departed). Chrome in the recipient's locale, no Reply-To, plain links, never expires. Officer assign and remove, leave of absence, reinstatement, and Withdrawn send nothing, on purpose.
_Avoid_: "notification" (retired, see Flagged ambiguities); and building a Notice on a model observer. The hook is the action that makes the change.

**No-email flag**:
A **Records**-set switch on a **Member** that silences **all** mail to them: Broadcasts, Direct messages, Reminders, Notices. Records-only field-visibility tier; super-tier inherits it. Checked once, when Delivery rows are written. A sender learns a Member is unreachable only by trying to reach them: a Direct message is refused with "cannot be reached", and an officer is told the names skipped from their Audience after sending. The only opt-out there is; it replaces legacy's address sentinel (see **noemail** under Legacy vocabulary).
_Avoid_: "unsubscribe" (nothing self-service exists, and none is legally owed for this mail), "opt-out preferences", per-kind switches. One flag, everything.

**Delivery**:
One queued mail to one recipient: the unit the throttle counts and the sent record stores. A Broadcast to 450 Members is 450 Deliveries. Four states: **pending**, **sent**, **failed**, **expired**. Written by a send (or by the daily pass), drained by the **Drain** at most 8 a minute and 450 a rolling hour, retried on a temporary error (three tries) and failed at once on a permanent one. Kept forever, readable in the Records-only tier and by the sender for their own sends.
_Avoid_: Laravel's `jobs` table, or the word "job", for it. A Delivery is our own row and the record of truth. And "sent" for a pending row: the sender's flash says "queued".

**Drain**:
The every-minute scheduled task that sends pending **Deliveries**. **The only code path that talks SMTP.** Runs under an overlap lock; stops the pass on a connection-level error (which is also how the host's daily cap presents) and retries next minute without giving up; writes "scheduler last ran" and "last connection error" for the super-tier Mail status page; checks in with the Sentry cron monitor. A second task, the **daily pass** at 06:00, only writes Delivery rows for Reminders and the empty-desk alert.
_Avoid_: "worker" or "queue worker" ([ADR-0002](docs/adr/0002-stay-on-stormweb-shared-hosting.md): none exist; this is a cron pass), and sending mail from anywhere else.

**Visitor count**:
The number of visitors one **Member** served on one **Shift**, recorded after the shift on their **Sign-up** as a nullable integer (`visitor_count`). Null means _not recorded_; zero means _recorded as zero_. The Member enters it at sign-out; a Group **Officer** may set or correct it at any time, with no deadline. Nine of the ten scheduling Groups collect it, and whether a Group collects it is a **Group** setting rather than a **shift kind** one. It never feeds **hours** ([#404](https://github.com/roytanaka/dmv-rom-v2/issues/404)).
_Avoid_: reading it as a headcount of distinct people. Several volunteers on one Shift each record their own number, so a Group total counts _interactions_, not visitors. And do not confuse it with a **Booking**'s expected audience, which is a forecast typed before the event.

**Extra interaction count**:
Visitors a Member served on a **Shift** outside the tour they led, as a second nullable integer (`extra_interaction_count`) on the same **Sign-up**. Only tour-leading Groups show the field; for a desk or gallery Group the **visitor count** already is an interaction count. Kept because Docents and GDR have maintained the split for years, on 81% and 54% of their signed-out shifts, even though no legacy report ever read it.
_Avoid_: folding it into **visitor count** at entry. The two are stored apart and added only where a report asks for total interactions. "Extra" carries the same sense it has in **extra hours**: _not already counted_.

**Visitor provenance**:
Where the visitors on one **Shift** came from, as five nullable integers on the same **Sign-up** beside the **visitor count** — `visitors_france_europe`, `visitors_quebec`, `visitors_toronto`, `visitors_rest_of_canada`, `visitors_other_countries`. **GDR only**, switched on per **Group** exactly as the visitor count is. The five **must sum to the visitor count**, validated on the server. Ported because GDR has filled them in on every counted tour since 2020 and reports on them by fiscal year ([#426](https://github.com/roytanaka/dmv-rom-v2/issues/426)).
_Avoid_: building general per-Group breakdown machinery for it. One Group in fifteen years is not a pattern; five columns on `sign_ups` is the whole design, and a second Group asking is when it gets generalised.

**Hours record**:
One Member's hours in one **Group** for one **calendar month** — the unit the department has reported in since 2013, ported unchanged from legacy's `MemberActivity` ([ADR-0022](docs/adr/0022-hours-and-statistics-model.md)). Model `HoursRecord`, table `hours_records`; the grain is (Member, Group, month, optional **Meeting**). It carries **scheduled hours** and **extra hours** as whole integers, and a derived `total_hours` that is their sum. Every Group has hours; there is no capability flag to switch off.
_Avoid_: "activity" and legacy's `MemberActivity` (see **Activity** under Legacy vocabulary — the word is already taken); and reading the record as a timesheet entry — it is a **monthly bucket**, not a dated row, so it has no start time, no duration, and no description.

**Extra hours**:
The half of an **Hours record** a Member **enters for themselves**: work done outside a **Shift** and outside a meeting. Any logged-in Member may enter them on any Group whose page they can open, for the current month or the previous one, because anyone is free to help any Group. Entry is **additive** — the number typed is added to what is on file, negatives correct a mistake, and the result floors at zero.
_Avoid_: reading "extra" as _overtime_ or _bonus_. It means _not already counted_ — the entry form says so out loud, telling Members to exclude scheduled shifts and meetings because those arrive by other routes.

**Scheduled hours**:
The half of an **Hours record** derived from what a Member was scheduled to work, **stored rather than computed on read**. A Group officer runs _recalculate this month_ and the month's total is overwritten from that Group's **Sign-ups**. Storing is deliberate: deriving at report time would let a **Shift** edited years later silently restate a closed **fiscal year**. Recalculation is permitted for the current fiscal year only.
_Avoid_: assuming the number tracks Sign-ups live. It tracks them **as of the last recalculation**.

**Meeting hours**:
Hours credited for attending a **Group**'s meeting. Stored on an **Hours record** that names the Meeting, and counted inside **extra hours** rather than as a third column — legacy's shape, kept. **No entry path is built** in the first pass: recording them needs a meeting attendance roster, which is a Meetings feature, not an Hours one. Historical rows import and their reports run.
_Avoid_: treating them as a separate stored total; the only thing distinguishing them is the Meeting on the record.

**Hours adjustment**:
One append-only row recording a single change to an **Hours record**: the delta, and who made it. Model `HoursAdjustment`, table `hours_adjustments`. It exists because the monthly bucket is overwritten in place, so without it a mistyped number is unattributable and fixable only by another delta. Behind an unchanged entry screen; nobody sees it but the people who have to answer "who put 200 hours on this Member."
_Avoid_: calling it an audit _log_ in the sense of a system-wide trail — it covers hours writes and nothing else.

**Fiscal year**:
DMV's reporting year: **1 April to 31 March**, named for the year it ends in. "Fiscal 2026" runs 2025-04 through 2026-03. Every hours report is a twelve-month matrix over one fiscal year with a year-to-date column.
_Avoid_: the calendar year, and "FY26"-style shorthand; the reports say _Fiscal year ending March 31, 2026_.

**Chrome**:
The application's persistent **frame** — the top bar, side rail, breadcrumb strip, and footer that wrap every screen and stay put while the page content changes. A UI term (after [GUI chrome](https://www.nngroup.com/articles/browser-and-gui-chrome/)), unrelated to the web browser. The Part 3 app shell _is_ the chrome; product screens render inside it.
_Avoid_: confusing with the Google Chrome browser. Synonyms "shell" / "frame" are fine.

**Locale**:
The technical identifier for a language + regional convention pair. The app supports two locales: `en` (English, default) and `fr` (Canadian French, `fr-CA`). A **Member**'s `locale` column captures their saved preference. Laravel's `app()->setLocale()` consumes it.
_Avoid_: Language (the user-facing label is "Language" or "Langue," but in code and ADRs, use **Locale**).

**Chrome / content translation boundary**:
The line that decides what gets translated. **Chrome** (the frame's own words — UI labels, navigation, system emails) is translated from `lang/{en,fr}` files. **Content** (anything a **Member** authors into a DB row — **Group** names, news, document titles) is single-column and rendered **as-authored**, identical in both locales — never translated, no `_en`/`_fr` columns. See [ADR-0004](docs/adr/0004-chrome-only-translation.md).
_Avoid_: "bilingual content," "translatable field" — content is as-authored, not bilingual.

**Default locale**:
English (`en`). It is the canonical, unprefixed locale — English URLs live at the root, French URLs live under `/fr/`. See [ADR-0008](docs/adr/0008-bilingual-url-routing.md).

**PRD** (product requirements document):
A scoped chunk of product work — large enough to need its own document, small enough to be implementable. PRDs are drafted as GitHub issues labeled `prd`, then broken into implementation tickets.
_Avoid_: epic, spec, brief, initiative — all refer to the same artifact in other vocabularies; in this project, call it a PRD.

## Relationships

- A **Member** has exactly one identity (one unique email, one password)
- A **Member** may belong to zero or more **Groups**; each **Membership** carries the **role(s)** that Member holds in that Group
- A **Group** has one parent (one tree, DMV at the root); **Committee** and **Program** are Kinds of Group
- A **Group** with the scheduling capability publishes **Schedules**; a **Schedule** holds **Shifts**; a **Member** takes a **Shift** via a **Sign-up**, and one Shift holds up to `capacity` Sign-ups
- Every **Group** holds **Hours records**, one per **Member** per month; a Group's report totals its own and every descendant's, to any depth
- **Login** to the app grants the **Member** their session and their authorization scope
- An **Officer** sends a **Broadcast** to an **Audience**; any **Member** sends a **Direct message** to one Member; both become one sent record and one **Delivery** per recipient
- The daily pass writes a **Reminder** Delivery per (Shift, Member) inside the lead window; a **Notice** fires from the action that changes something; the **Drain** sends every Delivery; the **No-email flag** stops a row being written at all

## Example dialogue

> **Dev:** "When a **Member** logs into the app, what determines what they can do?"
> **Domain:** "Their **Group** memberships and the roles they carry in each. Authorization isn't a global role flag — it's per-Group. The one exception is DMV's top leadership, who hold an explicit org-wide grant."
>
> **Dev:** "And households where two **Members** share an inbox?"
> **Domain:** "Not supported. Each **Member** has a unique email."

## Flagged ambiguities

- _"Member" vs "Membership"_ — **Member** is the person (the identity record); a **Membership** is that Member's join-row in a Group. Keep them distinct in schema names: the identity table is `members`; the Group join-table is `group_member`, never `members` again. "Group member" is acceptable prose for a person in a Group.
- _"Volunteer"_ — superseded as the person term by **Member**. Still correct only inside the proper noun "Department of Museum Volunteers." Earlier ADR prose may still say "Volunteer"; treat **Member** as canonical wherever they conflict.
- _"Audience"_ — two senses. A **Shift**'s `audience` (`group` / `open`) says who may take it; a **Broadcast**'s **Audience** says who receives mail. Both stay, because both are the natural word; qualify when a sentence could read either way.
- _"Notification"_ — **retired as a domain word** ([ADR-0024](docs/adr/0024-emailing-model.md)). Say **Reminder** (about your own Sign-up), **Notice** (fired by an event), or **Broadcast** (written by an officer). Earlier ADR prose that says "notification email" means a Notice.

## Legacy vocabulary

Words you will meet in the legacy app, in its database, in meeting transcripts, and in Adrian's speech — several of which collide with terms defined above. This section is **descriptive**: it records what a word means _over there_ and what we call it _here_. It commits to nothing that the glossary above has not already decided; where a decision is still open, it says so and points at the ticket.

Read this before legacy archaeology or migration work. Every one of these has already caused a misunderstanding at least once.

**Scheduler** — three senses, only one of which is ours:

- _our_ **Scheduler** — the per-Group authority-bearing **role** (see **Officer**). This is the only correct use.
- _legacy_ "Scheduler" — a **screen**, the authoring surface a Group's officers open to build a schedule. Never call our screen this.
- _loose speech_ "the scheduler" — the **person** who does the work. Say who they are and what role they hold: at Visitor Wayfinders that person is the **Chair**, not a Scheduler.

_Avoid_: "the scheduler" with no article of precision. Name the role, the screen, or the Member.

**Role** (legacy `specialRoles` / `RoleID`, and `outreachRole` / `outreachgroupToORMember.Role`):
In legacy scheduling, a _kind of position within an event_ — "Level 2 greeter", "Plan Your Visit desk", Outreach's "Leader" vs "Presenter". It is a **shift kind**, and has nothing to do with authority. Two Groups do this, not one: Wayfinders hangs it off the dated row, Outreach hangs it off the _assignment_ (the only place in ten Groups where it does), which turned out to be one booking row doing two jobs — two members with different kinds is two **Shifts** in one **Schedule**. Outreach's 2-character `Symbol` limit is a browser string-offset bug, not a domain rule; nothing carries it forward.
_Avoid_: reading legacy `RoleID` as one of our **roles**. Ours (Chair, Secretary, Scheduler, …) confer authority; legacy's describe work. Two unrelated concepts sharing a column name.

**Booking** (legacy `*groupTours`, `rombusTrip`, `walkerSchedules status='group'`):
An outside organization engaging a **Group** for a dated engagement — a school books a tour, a community group books a presentation — which the Group then staffs. **Not** a **Sign-up**, a **Schedule**, or a **Shift**: the separable half is a _customer record with a date on it_ (client name and address, contact details, venue type, honorarium, billing class, order number, guest manifest). Five Groups have it — Outreach, ROMBus, Walker, **and Docents and GDR**, who also run ordinary shifts — so it is a **capability orthogonal to scheduling**, not a Group shape. Deferred out of the scheduling first pass ([ADR-0021](docs/adr/0021-scheduling-first-pass.md)); the _staffing_ half of a booking is expressible today as a Schedule with the Shifts it needs.
_Avoid_: treating a booking as a kind of Shift, and treating legacy as a design to port — **there is no organization table at all**; the client is re-entered every time and auto-filled by a `LIKE` search of prior bookings.

**Event** — three senses:

- _legacy_ `specialEvents` — the **date-range schedule container** for Visitor Wayfinders. **We call this a `Schedule`** ([ADR-0021](docs/adr/0021-scheduling-first-pass.md)) — the same entity a monthly Group uses, with a different range and name.
- _plain English_ — a real-world happening at the ROM (an open house, an exhibit opening). Not a system concept — and deliberately unmodelled, so two Groups both staffing Doors Open produce two Schedules of the same name with nothing joining them.
- _our_ **Group** definition — "event cohort" appears as one of the things that is a Group.

_Avoid_: bare "event". Say **Schedule**, _the occasion_, or **Group**.

**Program** — three senses:

- _our_ **Program** — a Kind of **Group** (see above). The only correct use.
- _Adrian's_ "special programs" — time-boxed exhibits or initiatives. In our model these are Groups too, but the phrase is not a Kind.
- _"training program"_ — the thing a Member completes to earn a qualification. Unbuilt; deliberately out of the scheduling first pass ([ADR-0021](docs/adr/0021-scheduling-first-pass.md) § _Deliberately out of the first pass_), which decides only that a requirement hangs off a **shift kind** and points at a named **Qualification**.

**Activity** (legacy `specialActivities`, `ActivityID`):
An entry in Visitor Wayfinders' hand-maintained list of shift kinds, chosen from a picker when authoring. In practice many entries name a **place** ("Level 1 Oslo gate", "Level 2 dinosaurs") rather than a kind of work, so activity and location are conflated at source and will not split cleanly on migration.
Separately, "member activity" in reporting contexts means **hours and statistics rows** — an unrelated use, and the one that named the table (see **MemberActivity** below).
We **do** keep a per-Group list, and we call it a **shift kind** (`ShiftKind`), not ADR-0015's _Catalog_ — see the glossary above.

**Pattern** (legacy "daily pattern", "weekly pattern", "two-week pattern", "copy pattern from"):
A stored template a legacy generator fans out into dated rows. It conceals **two** different ideas:

- a repeating **Shift** template — the shifts that exist each week; and
- a repeating **Sign-up** — a Member who holds the same slot every week or every second week.

Reception's two-week pattern is the second wearing the clothes of the first: its shifts are weekly, and the fortnight exists only because some Members attend on alternate weeks. Confirmed by the schema, not only by testimony — `Colour` appears in exactly the two Groups that name a person on a pattern row (`receptionweeklySchedule.commID`, `vgweeklySchedule.VgID`) and nowhere else.
**We build neither** ([ADR-0021](docs/adr/0021-scheduling-first-pass.md)). This is pure legacy vocabulary: nothing recurring is stored, and a Scheduler **bulk-writes** N ordinary rows instead — bulk-create Shifts, bulk-place a Member weekly or biweekly, each with a symmetric bulk undo. Note also that legacy's "copy pattern from" copies the previous _event's day-pattern template_, never a Schedule or its Shifts — there is no schedule-duplication feature over there to port.
_Avoid_: "pattern" unqualified. Say _shift template_ or _recurring Sign-up_ — and expect to be describing legacy when you do.

**Count** (legacy column):
Two meanings, neither of them capacity: **duration in hours** (Visitor Guides, Wayfinders, Reception, Gallery Interpreters) and **quantity of tours given** (Docents, GDR, and Gallery Interpreters again — the same column, both ways). The two are reconciled outside the tables by a hardcoded per-Group multiplier (`hoursper`: every Group `1`, **Walker `2`**), so any migration that maps `Count → Count` silently corrupts a Group's hours. The multiplier survives, as data rather than code: [ADR-0022](docs/adr/0022-hours-and-statistics-model.md) puts an `hours_multiplier` on the Group (default `1`, ROMWalks `2`), replacing the `2*$total` hardcoded in `walker.php`. Capacity is always a _different_ column (`Required`, `PresentersNeeded`) or it is N identical rows. A name to retire, never to carry forward — our **Shift** derives duration from its start and end times.

**`Visitors`** / **`Interactions`** (legacy columns — four of them, and one is live):

The two words name four different columns across two kinds of table, and both words are spoiled. Separated by research ([#399](https://github.com/roytanaka/dmv-rom-v2/issues/399), [#400](https://github.com/roytanaka/dmv-rom-v2/issues/400)) after ADR-0022 ruled one out and the map read it as another.

- **`Visitors` on the dated scheduling rows** — the live one, in nine of ten Groups, and **current**: every collecting Group's most recent value is in the present month. Three write moments: the Member at sign-out (six Groups), a Statistician after the fact (five), and — in Outreach alone — the **Scheduler before the event, as a forecast**. It **never feeds credit**: no Group's credit query reads it. Read by Summary Visitor Interactions and Detailed Committee Statistics. **Reception's rows do carry the column** — nothing writes it, and `Committee.visitorTable` is null so no report reads it, which is what makes Reception the tenth Group rather than a Group missing a column. **ROM Travel has no scheduling table in the production database at all.**
- **`Interactions` on the dated scheduling rows** — Docents and GDR only, labelled _"Visitor interactions excluding tour"_ at sign-out. **Nothing reads it, and volunteers fill it anyway**: 2,533 Docent rows and 364 GDR rows in 24 months, 81% and 54% of their signed-out shifts. Two Groups have typed a second number for years into a column no report touches, so the department under-reports its own interactions by roughly 2,900 records. **We call this an extra interaction count.**
- **`MemberActivity.Interactions`** — zero on all 74,248 rows, and that is a **bug, not disuse**: `and Interactions>0` sits inside the row-matching `$where` (`servicesp.php:9769`), so the UPDATE can never fire.
- **`MemberActivity.Visitors`** (carrying the comment `Click Count`) — genuinely dead. No reader, no writer, no reference anywhere in live PHP, and **zero on all 12,521 rows** of the last two years. Do not import it.

Two more distortions live in the org-level report: it adds the _booked_ group size from a second table for Docents and GDR, and it sums Walker's two `Visitors` columns with no guard against counting one walk twice.

- **`Visitorsfr` / `Visitorspq` / `Visitorsto` / `Visitorsroc` / `Visitorsoth`** on `gdrscheduledTours` — **GDR alone, and live.** Visitor origin, labelled in French at sign-out: _France + Europe Fr_, _Prov Quebec_, _Toronto_, _Reste du Canada_, _Autres Pays_. Written at sign-out (`gdr.php:492`) and by the Statistician's row edit (`:3031`); read by a fiscal-year Tour Provenance PDF (`:5298`). **They sum to `Visitors` on 712 of 712 rows since 2022** — legacy enforces it, in the browser only. Filled on every counted tour since 2020, and no other table in the database has breakdown columns. **We call this visitor provenance** ([#426](https://github.com/roytanaka/dmv-rom-v2/issues/426)).

**We call the live ones a visitor count and an extra interaction count** (see the glossary above). Both are nullable integers on the **Sign-up**, per volunteer, and no new entity ([#402](https://github.com/roytanaka/dmv-rom-v2/issues/402), [#404](https://github.com/roytanaka/dmv-rom-v2/issues/404)) — names chosen precisely because both legacy words are already spent. There are two rather than one because `Visitors` does not mean the same thing across Groups: on a Visitor Guide's row it is _people I talked to at the desk_, on a Docent's row it is _people on my tour_, with the talked-to number in `Interactions` beside it.

One legacy design decision is worth porting deliberately. Visitor Guides, Wayfinders and Gallery Interpreters **disable the Sign Out button until a number is typed**, and carry a count on 96-98% of signed-out shifts. GDR and Walker have no such gate and manage 54-59%. The forcing function is what makes the data usable, so the field is nullable in storage and **required at the entry surface** — validated on the server, since legacy's version is browser-only.
_Avoid_: the bare words "visitors" and "interactions" — each names a live column and a dead one. Say _visitor count_, and name the table when you mean a legacy column.

**Special** (legacy table prefix and menu symbol):
Means **Visitor Wayfinders**, the Group. Legacy names its tables `specialEvents`, `specialSchedule`, `specialActivities`, `specialRoles`.
_Avoid_: reading "special" as an adjective. It is that one Group's short name.

**Wayfinder** — two unrelated things:

- **Visitor Wayfinders**, a DMV **Group** (legacy symbol `special`).
- _Wayfinder_, the planning method used for large efforts, which produces a map issue labelled `wayfinder:map` and its decision tickets.

No relationship whatsoever. Both appear in this repo's issues.

**MemberActivity** (legacy table `dmv_MemberActivity`):
The whole legacy hours feature in one table — 74,248 rows, current through 2026-05. **We call this an Hours record** (see the glossary above), and unlike the scheduling tables it is ported almost unchanged ([ADR-0022](docs/adr/0022-hours-and-statistics-model.md)). Four things to know before touching it:

- `committee` is a **symbol string**, not a foreign key, and a **sub-committee's row stores its parent's symbol**. That is why legacy's rollup needs no code, and why our port needs some.
- `Total_Hours` is not written by any PHP. Two **database triggers** maintain it as `Total_Scheduled + Extra_Hours`. We keep the identity and drop the triggers.
- **Meeting hours hide inside `Extra_Hours`**, distinguished only by a non-zero `MeetingID`.
- `Interactions` and `Visitors` are both zero, for **different reasons** — `Interactions` because of a bug in its own update query, `Visitors` because nothing has ever touched it. See the `Visitors` / `Interactions` entry above, which separates all four columns wearing these two names.

_Avoid_: the name **Activity** for our model (already three things — see above), and mapping `subCommitteeID=0` as a real value; it is a sentinel meaning _the committee itself_.

**MIS** (legacy eligibility value `99`, labelled "MIS only"):
A coarse cross-Group audience for a legacy shift. **Not** a designation stored on a Member — it is evaluated on the spot as active membership in any of six Groups: Gallery Interpreters, Docents, GDR, Visitor Guides, Outreach, Visitor Wayfinders. A hardcoded union that nobody maintains.
**Not ported** ([ADR-0021](docs/adr/0021-scheduling-first-pass.md)). Our **audience** enum has two values, `group` and `open`; `MIS` and legacy's three other values (nobody, a committee id, a subcommittee id) are dropped. A proper Group-list audience covers this case when a Group needs it, and the two-value enum widens to one without reshaping **Sign-up**.
_Avoid_: treating it as a qualification or a standing.

**noemail** (the legacy opt-out sentinel):
Legacy has no opt-out flag. To silence a Member, an officer edits their email address to one containing a "noemail" domain, and every list page and the nightly job skip addresses that match. Four current Members carry it today, none with a role. **We call this the No-email flag** (see the glossary above): a Records-set switch on the Member, the address left intact. Moving the sentinel onto the flag is the migration plan's job, not a v2 ticket.
_Avoid_: reading a sentinel address as a bad address, and porting the string match.

**Send email** (legacy button) and **DMV Reminder** (legacy From name):
Every legacy list page carries a Send email button that posts the page's visible addresses to one shared composer. "Send email" is therefore a _surface_, not a kind of mail, and _which page it sat on_ is what we now call the **Audience** (of a **Broadcast**). "DMV Reminder" is the fixed From display name of the nightly job; ours is the Group's name on the app's one address, and the mail is a **Reminder**.
_Avoid_: "the emailer" as a feature name. Name the Broadcast, the Audience, or the Reminder.
