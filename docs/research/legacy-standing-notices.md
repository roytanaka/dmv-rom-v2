# Legacy standing-change notices

Research for #464 (map #458, "Fixed by charting" items 2 and 16). What the legacy app emails when a Member's DMV-wide standing changes, and where each of those mails would live in v2.

Legacy is described by page and feature only. Mailboxes are named by role. Everything read comes from the live legacy tree's **Members program** (the member-administration pages) and its shared mail helper; v2 facts come from the code on `staging` as of 2026-09-06.

## Summary

- Legacy sends **two** standing-change notices from member administration proper: **Resigned** and **Deceased**, one mail per committee the Member was still in, to that committee's Chair. Nothing is sent for **Leave of absence**, for a **Reinstate**, or for any other category change.
- The **Records-mailbox notices** (new provisional, applicant placement, yellow-card completion, applicant withdrawn, member now active) all hang off the **Applicants** feature — an applicant's status change, signed by a mentor — except *member now active*, which fires from the member-record Save after a Provisional → Active category entry and is addressed to the mentor.
- v2 today has **no write path for `Category` at all**: the member-edit Form Request deliberately whitelists `category` out ("set only through their own gated actions"), and no such action exists. The Notice's home is therefore a **new standing-change action**, not a hook on the existing edit. Every other ingredient — the `Category` enum, Chairs as `Role::Chair` rows, per-Group `MembershipStatus` — is built.
- The five Records-mailbox notices have **no host** in v2: four need the intake feature (applicant, mentor, placement), and the fifth needs a mentor to address. They go to the ADR's owed list with intake.

## 1. Resignation and death notice

### Legacy

**Trigger.** On the member record (Members program → a member's record dialog), the **Category History** panel has a "new category" action: pick a category, enter a date, add. The app appends a history row, then recomputes the record's current Category from the *latest* history row (also stamping the status date; for a first move from Provisional/PreActive to Active it also sets the next service-award year). If — and only if — the new category belongs to the **Resigned** family or the **Deceased** family, the "resign from DMV" routine runs. A **Withdrawn** entry, like LOA or any other family, sends nothing (see Assumptions on the family letter). Editing the record's other fields and saving does not touch Category and sends nothing; the **Reinstate** action on the departed-members list (which writes a Reinstate row plus an Active row and sets the record back to Active) sends nothing.

**Recipient rule — committees.** The routine walks every *active committee* (every program except the department-level pseudo-committee). For each:

- If the Member's row in that committee carries the **Admin** status (the status reserved for officers who administer a program without belonging to it), the row is **deleted silently**, no mail.
- Otherwise, if the Member's committee status is **below the cut-off**, the committee's **Chair** is emailed. The cut-off differs: for **Resigned**, any status other than the committee's own Resigned/Deceased statuses (so Full, Trainee, Transitional, Auxiliary, Projects, Emeritus, and the committee-level LOA and Inactive all count as "still in"); for **Deceased**, any status other than Deceased (so a Chair whose roster already shows the Member as Resigned is still told of the death).
- The Chair is the single roster row flagged Chair and active; if none is found, nothing is sent for that committee. The Member's **committee row is not changed** — the code says so in as many words: notify the Chair, do not resign. That is why the mail asks for a reply.

**Recipient rule — sub-committees.** Every *active* sub-committee membership of the Member is **ended** (marked inactive with an end date) and the sub-committee's Chair is emailed "…so they have been removed from your SubCommittee" — an announcement, not an ask. *Finding:* the sub-committee Chair lookup queries the wrong roster (the last committee walked in the loop above, not the sub-committee roster), so the lookup cannot succeed; the shared query helper aborts the request on a failed query. In practice the sub-committee notice never goes out, and any further sub-committee rows are left untouched. Inference from the code, not verified against a mail log.

**Sender and addressing.** Envelope From is the app's single mail identity ("DMV Mail" at the app's own address). Reply-To is **the Records mailbox**. **The app's administrator mailbox is bcc'd** on every notice the short-notice helper sends. On a non-production table prefix the mail is diverted to the developer's own mailbox with hedged wording ("it appears that … MAY HAVE resigned … please check with DMV Records").

**Content.** Subject: *Member of your committee, ‹Committee›, has Resigned from DMV* (or *has Deceased from DMV* — faithful, if ungrammatical). Body: *‹Full name› has Resigned from the DMV on ‹date›. Please update their status to Resigned in your Committee, and respond to confirm. Thank you.* Sub-committee variant: *… has Resigned from the DMV on ‹date›, so they have been removed from your SubCommittee.* English only; plain text rendered as minimal HTML.

**What the Records officer sees.** Nothing about the sends — the action returns OK whether or not a Chair was found or a mail went out. The one exception is the sub-committee abort above: the category entry and the recompute have already been written when the request dies, so the page shows an error while the record has in fact changed. The only record of a send is the shared email-count log.

### v2 mapping

**Trigger.** A Member's `Category` moving **into** `Resigned` or `Deceased` through member administration. See §4 for where that action lives.

**Recipients.** The **Chairs** of every Group where the Member holds a Membership whose `MembershipStatus` is not already departed. Faithfully, the cut-off keeps legacy's two shapes:

| New Category | Notify the Chairs of every Group where the Membership's status is |
|---|---|
| `Resigned` | anything but `resigned` or `deceased` — i.e. `full`, `loa`, `trainee`, `transitional`, `auxiliary`, `projects`, `emeritus`, `inactive`, `donor` |
| `Deceased` | anything but `deceased` — the list above plus `resigned` |

"Chair" means the literal `Role::Chair` rows on that Group's roster (`Member::holdsRole`, not `canActAs`): all of them, so co-chairs each get a copy. Secretaries, parent-Group officers, and super-tier are not recipients — legacy tells one person per committee, and that person is the Chair. Any Chair carrying the no-email flag is skipped, per the map.

**Sub-committees.** v2 has no sub-committee entity — a sub-committee is a child Group (ADR-0010) — so the one rule above covers parent and child alike, and every recipient gets the "please update" form. Legacy's separate auto-removal-plus-announcement for sub-committees is **not** ported: it is a second behaviour for what v2 models as the same thing, and it never worked. Name this in the ADR as a deviation-by-collapse, not a defect fix.

**Withdrawn.** `Category::Withdrawn` sends nothing — faithful. Legacy's Withdrawn is reached through the Applicants feature (§3, *Applicant Withdrawn*) or a plain category entry, neither of which notifies Chairs.

**What the Notice changes.** Nothing beyond `Category`. Memberships are untouched; each Chair updates their own roster. Faithful to legacy's "notify, don't resign".

**"Reply to confirm".** Does not survive. Automatic mail carries no Reply-To in v2 (map item 9), so a reply would go nowhere; and the Chair can act directly — a Chair or Secretary sets a Membership's status on the Group roster today (`PATCH memberships/{membership}` via `UpdateGroupMemberRequest`, authorized by `GroupMemberPolicy::update` → `Member::administers`). The ask becomes *please update their standing in ‹Group›* with a plain link to that roster (map item 17); Records can verify on the roster instead of waiting for a reply.

**Content.** Rendered as chrome in each Chair's saved locale (ADR-0004): Member's name, Group name, the new standing, the effective date, the roster link. The Member's name and the Group name are content and pass through as-is.

## 2. Leave of absence

### Legacy

Confirmed: **no mail.** The same "new category" action with the LOA category runs the ordinary history-append and recompute, and the resign routine is gated on the Resigned/Deceased families only. Reinstate and the record Save send nothing either.

What the LOA action *does* change:

- **Category** becomes LOA; the category **family** stays "current" (LOA is a current-member category, alongside Active, Sustaining, Honourary, Provisional, PreActive), so the Member keeps logging in with full access.
- **Renewal status** is set to LOA. This is remembered so that the Member later sees a "DMV Renewal" link in their menu and returns to Active through the renewal form; a first-time Provisional → Active goes through the same form under a different label. Returning from LOA also sends nothing.
- **Committee rows are untouched.** A committee-level LOA status exists separately and is set by that committee's officers.
- **Default audience.** The Members program's list-and-email selector defaults to **"All Members" = current members except LOA**. "All Members +LOA" is a separate option offered only to Chairs and Records. So an LOA Member drops out of the org-wide default audience but stays in the Directory-equivalent lists and in their committees' own lists.

### v2 mapping

`Category::Loa` already carries the same shape: `AccessTier::Full`, listed in the Directory, `canSignUp()` false; per-Group `MembershipStatus::Loa` is independent. No Notice. The renewal-form return is owed with membership renewal (map item 2). The audience exclusion is a Broadcast concern for the audiences research: the org-wide default Audience should exclude `Category::Loa`, with a "+LOA" variant for Records — noted here, decided there.

## 3. The Records-mailbox notices

All five come from the **Members program**. Four fire from the **Applicants** page (an applicant's dialog, on Save, when the applicant's **Status** field changed); one fires from the member record. Every one goes through the same short-notice helper: envelope From is the app's mail identity, the app administrator mailbox is bcc'd, and on a non-production prefix the mail is diverted to the developer.

| Notice | Legacy trigger | To / cc / Reply-To | Content |
|---|---|---|---|
| **New Provisional** | Applicant Status set to **Accepted** or **Placed** for an applicant with no member record yet. The applicant is copied into the members table as Provisional (current family) in the same request. | To **the Records mailbox**; cc the **mentor**; Reply-To the mentor | *‹Name› has been accepted by Membership as a Provisional DMV member. Their information has been added to the Records database.* Their address. If Placed: *They have been placed in the ‹Committee› committee* plus the applicant comments. *Signed ‹mentor›.* |
| **Applicant Placement** | Applicant Status set to **Placed** for an applicant who already has a member record. | To Records; cc mentor; Reply-To mentor | *‹Name› has been placed in the ‹Committee› committee.* Comments. *Signed ‹mentor›.* |
| **Yellow Card completion** | Applicant Status set to **Active**. Does **not** change the member's Category — Records does that separately. | To Records; cc **the Treasurer mailbox** and the mentor; Reply-To mentor | *‹Name› has completed the Yellow Card and Six months as a Provisional DMV member and is ready to become Active.* Their address. *Signed ‹mentor›.* (A commented-out line once asked a staff member to send a renewal form.) |
| **Applicant Withdrawn** | Applicant Status set to **Incomplete** for an applicant with a member record; the "why incomplete" pick is one of the Withdrawn categories. Does not change the member's Category. | To Records; cc **a ROM staff mailbox** and the mentor; Reply-To mentor | *‹Name› is no longer a DMV member and should be 'Withdrawn' from all records, and ROM Security informed. The reason is: ‹reason›. Signed ‹mentor›.* |
| **Member Now Active** | Member record **Save** when the record was opened with Category **Provisional** and the stored Category is now **Active** (Records added an Active entry in the Category History panel, then saved the record), and the member has an applicant row (which is where the mentor lives). | To the **mentor**; cc Records; Reply-To Records | *This is a notice that Records has changed the new member, ‹name›, from Provisional to Active by ‹initials›.* |

Two things worth carrying into the ADR:

- **The pattern is mentor ⇄ Records.** The four applicant notices are the *mentor* telling Records (Reply-To the mentor, Records the addressee); *now active* is Records telling the mentor. None of the five is addressed to a Chair.
- **Member Now Active can double-send.** The "previous category" is a hidden field set when the dialog opened and never refreshed, so saving the record twice in one sitting sends twice. Also, it fires only if Records saves the record after the category entry — change the category and close, and no mail goes.

### Which have a host in v2

| Notice | Trigger host in v2 | Recipient host in v2 | Verdict |
|---|---|---|---|
| New Provisional | none — no Applicants, no accept-into-members | Records Group exists; **mentor does not** | **Owed**, with intake |
| Applicant Placement | none — no placement | mentor does not | **Owed**, with intake |
| Yellow Card completion | none — no applicant status, no yellow card | Records and a Treasurer role exist; mentor does not | **Owed**, with intake |
| Applicant Withdrawn | none | the staff mailbox is outside the app; mentor does not | **Owed**, with intake |
| Member Now Active | *half*: Category Provisional → Active through member administration, once §4's action exists | **mentor does not exist**; the Records cc is Records notifying itself | **Owed**, with intake — the trigger can be built now, but there is nobody to send to |

So none of the five ships with this map. When intake lands, *Member Now Active* is the one that hooks into §4's action rather than into intake's own flows; the Records cc can be dropped then (self-notification, and the actor sees the change on screen).

## 4. Where the trigger lives in v2

### The premise, corrected

The ticket assumes `Category` "is set by a plain update through member administration". It is not — not yet. `UpdateMemberRequest::rules()` whitelists `first_name`, `last_name`, `email` only, and its docblock says authority fields (category, super-tier, roles) "are set only through their own gated actions". No such action exists: `category` is written by factories and seeders alone. `MemberPolicy::update` (self, or `hasMemberAdminAuthority()`) is the only member-record write authority today.

That is good news for the Notice: there is no existing edit to bolt a side effect onto, and the right home is the action that must be built anyway.

### Proposal

**A dedicated standing-change action**, mirroring how the roster sets a Membership's status:

- **Route** `PATCH members/{member}/standing` → `MemberStandingController::update` (or `MemberController::updateStanding`; one method either way).
- **Form Request** `UpdateMemberStandingRequest`: `authorize()` → a new `MemberPolicy::changeStanding($actor, $target)` = `$actor->hasMemberAdminAuthority()` (super-tier passes via `Gate::before`; self is *not* allowed — a Member never sets their own Category). `rules()`: `category` → `Rule::enum(Category::class)`; `effective_on` → date, defaults to today. Nothing else rides in.
- **Controller**, the same shape as `SignUpController::destroy` (the one Notice the app sends today):

  ```php
  $from = $member->category;
  $member->fill($request->validated())->save();

  if ($member->wasChanged('category') && $member->category->isDeparture()) {
      foreach ($member->departureNoticeRecipients() as [$group, $chair]) {
          Mail::to($chair)->send(new MemberStandingChanged($member, $group, $from, $member->category, $effectiveOn));
      }
  }
  ```

  where `isDeparture()` is a small `Category` method (`Resigned`, `Deceased` → true) beside `accessTier()` and `canSignUp()`, and `departureNoticeRecipients()` is a query over `$member->memberships()->with('group', 'roles')` filtered by the §1 status table and mapped to each Group's `Role::Chair` holders — the precedent for "who gets a Group's Notice" is `Group::schedulers()`.

**No observer, no state machine.** The controller is the one seam, exactly as ADR-0017 frames mutations (Form Request authorizes, controller does the work) and as the cancellation mail is wired. The send is synchronous, inside the request, one mail per Chair (ADR-0002: no queue workers) — for a Member in five Groups that is a handful of sends, well inside a request.

### Duplicate sends

The guard is the **transition itself**, not a sent record:

- `wasChanged('category')` is false when the submitted value equals the stored one, so a **repeated save** (double-click, browser retry, re-submitting the same form) sends nothing.
- `isDeparture()` on the *new* value means `Resigned → Deceased` sends once more, which is correct — the Chairs are told of a death even after a resignation, as legacy does.
- A `Deceased → Resigned` or any move *out* of departure sends nothing (legacy: same — Reinstate is silent).
- Two truly concurrent requests could both read the old value; at tens of concurrent users on a Records-only action that is theoretical. If it ever matters, `lockForUpdate()` on the Member inside `DB::transaction` closes it without any new table.

No sent record for Notices this pass. The Reminder sent record (map item 15) exists because a cron run has to know what an earlier run did; a Notice fires in the request that made the change, and the change is the record. If the Broadcast sent record (map item 12) later grows to cover automatic mail, this Notice writes one row per send there and nothing about the guard changes.

### Effective date

Legacy carries a date on every category entry and recomputes the current Category from the newest one. v2 has a `category` column and no history. The proposal takes `effective_on` on the request and puts it in the mail; whether a category history table lands is the member-administration feature's call, not this Notice's. If it does, the Notice reads the date from the new row instead — the seam is the same.

## Notice table

| Notice | Legacy trigger | Legacy recipients | v2 trigger | v2 recipients | Host in v2 today |
|---|---|---|---|---|---|
| **Resigned** | Category History → new entry in the Resigned family (member record) | Chair of each active committee where the Member's status is below Resigned (Admin rows deleted silently); sub-committee Chairs after auto-removal (never sends — wrong roster queried) | `Category` → `Resigned` through the standing-change action | `Role::Chair` holders of every Group where the Membership status ∉ {`resigned`, `deceased`} | **No** — action unbuilt; enum, Chairs, statuses all built |
| **Deceased** | Same, Deceased family | Same, cut-off below Deceased (a committee-Resigned Member's Chair is still told) | `Category` → `Deceased` | Chairs of every Group where status ≠ `deceased` | **No** — same action |
| **Leave of absence** | Category History → LOA entry; also sets renewal status LOA, drops out of the default audience | *none* | `Category` → `Loa` | *none* (faithful) | n/a |
| **Reinstate** | Departed list → Reinstate (writes a Reinstate and an Active history row, sets the record Active) | *none* | move out of departure | *none* | n/a |
| **Withdrawn** | Category History → Withdrawn entry | *none* | `Category` → `Withdrawn` | *none* (faithful) | n/a |
| **New Provisional** | Applicant Status → Accepted/Placed, no member record yet | Records mailbox; cc mentor | intake: accept an applicant | Records Group; mentor | **No** — owed with intake |
| **Applicant Placement** | Applicant Status → Placed, member record exists | Records; cc mentor | intake: place an applicant | Records; mentor | **No** — owed with intake |
| **Yellow Card completion** | Applicant Status → Active | Records; cc Treasurer mailbox, mentor | intake: mark yellow card done | Records; a Treasurer; mentor | **No** — owed with intake |
| **Applicant Withdrawn** | Applicant Status → Incomplete, member record exists | Records; cc a ROM staff mailbox, mentor | intake: withdraw an applicant | Records; mentor (staff mailbox is outside the app) | **No** — owed with intake |
| **Member Now Active** | Member record Save after a Provisional → Active category entry, applicant row exists | mentor; cc Records | `Category` `Provisional` → `Active` through the standing-change action | mentor | **Half** — trigger buildable with §4; no mentor to address; owed with intake |

## Assumptions

1. **Legacy committee status numbering.** The status reserved for administrators is 0; 1–9 are the live standings (Full, Trainee, Transitional, Auxiliary, Projects, Emeritus, and the committee-level LOA and Inactive, in a per-committee order); Resigned is 10; Deceased is 99. Inferred from the code's cut-offs and the layout of the "Committee StatusCodes" admin table; the vocabulary table itself was not dumped.
2. **The sub-committee notice never sends** in production, because the Chair lookup queries the wrong roster and the query helper aborts on failure. Read from the code; not checked against a mail log or the administrator mailbox's bcc copies.
3. **"Chair" in v2 is the literal `Role::Chair`**, every holder, on the Group itself — not Secretary, not `canActAs`, not parent-Group officers, not super-tier.
4. **Legacy's Admin-row deletion has no v2 analogue.** v2 rosters have no "admin" standing (officers hold roles on an ordinary Membership), so nothing is deleted on departure. Whatever the legacy import maps Admin rows onto is a migration-plan question, not a Notice one.
5. **"Reply to confirm" is dropped**, replaced by a roster link. Forced by "automatic mail has no Reply-To" (map item 9) and by the Chair being able to act in-app; recorded as a deviation for the ADR.
6. **The bcc to the app administrator mailbox is not ported.** It is a debugging copy with no role behind it; the sent record is the v2 answer if one is wanted. Recorded as a deviation.
7. **`Category` has no write path in v2 today**; the standing-change action in §4 is new scope, to be specced with member administration (or with the emailing ADR if it wants a first consumer), not built from this ticket.
8. **No category history in v2 is assumed.** `effective_on` is a request field carried into the mail. If a history table lands, the Notice reads the date from it instead.
9. **All five Records-mailbox notices are owed with intake** (map item 2 already lists "intake with mentor and placement"). The Records cc on *Member Now Active* is self-notification and may be dropped when it lands. The Treasurer-mailbox cc on *Yellow Card completion* maps to a Treasurer role holder in v2, not a mailbox; which Group's Treasurer is intake's question.
10. **LOA and the default Audience.** `Category::Loa` is the v2 equivalent of legacy's "current but on leave"; the org-wide default Audience excludes it, with a "+LOA" variant for Records. Recorded for the audiences research to confirm; not decided here.
11. **Mail wording is chrome, written fresh per locale**, not copied from legacy (ADR-0004). Legacy's "has Deceased from DMV" is not carried over.
12. **Withdrawn is not in the Resigned family.** The resign routine fires on the Resigned and Deceased families only; Withdrawn is read as its own family (the login filter and the category vocabulary treat Resigned, Withdrawn, and Deceased as three exit kinds). The vocabulary table's family letters were not dumped; if Withdrawn were filed under Resigned, legacy would mail Chairs on a Withdrawn entry too and v2 should follow.
13. **Legacy sends one mail per committee and one to one Chair.** v2 sends one mail per (Group, Chair) pair; a co-chaired Group produces two mails. Not a deviation, a correction of an accidental single-recipient lookup.

## Sources

- Legacy, live tree, Members program: the member record dialog (Category History panel, "new category" action, record Save), the departed-members list (Reinstate), the Applicants dialog (Status change on Save), the list-and-email audience selector; the shared short-notice mail helper and the shared query helper in the services layer. Read 2026-09-06; behaviour inferred from code, not from a mail log.
- Legacy notes in the private archaeology repo: authentication and role-model section (category families and access), feature inventory (member category lifecycle).
- v2 on `staging` (2026-09-06): `app/Enums/Category.php`, `app/Enums/MembershipStatus.php`, `app/Enums/Role.php`, `app/Enums/StewardshipFunction.php`, `app/Models/Member.php`, `app/Models/Group.php`, `app/Models/GroupMember.php`, `app/Http/Controllers/MemberController.php`, `app/Http/Requests/UpdateMemberRequest.php`, `app/Http/Requests/UpdateGroupMemberRequest.php`, `app/Policies/MemberPolicy.php`, `app/Policies/GroupMemberPolicy.php`, `app/Http/Controllers/SignUpController.php` and `app/Mail/SignUpCancelled.php` (the one Notice sent today), `docs/adr/0011-authorization-model.md`, `docs/adr/0017-authorization-enforcement.md`.
- Map: #458 ("Fixed by charting" items 2, 9, 16, 17).
