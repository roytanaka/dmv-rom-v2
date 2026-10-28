---
status: accepted
date: 2026-09-26
accepted: 2026-09-26
---

# Tester feedback lives in its own database

> **Amendment (2026-10-25, [ADR-0035](0035-beta-and-cutover.md) §8).** Feedback also runs on **Beta**, the production install before Cutover. Beta writes to the same feedback database as staging. A Beta item records the Tester's real name, and only a **Support-operator** reads Beta items, because their screenshots show real Member data. On staging, §4 is unchanged: every Tester reads every item.

> **Amendment (2026-10-27, §15).** Testers can follow a Feedback item by email and get each new comment. This also changes the Beta read rule in [ADR-0035](0035-beta-and-cutover.md) §8.

From a grilling session on 2026-09-26. Testers on staging find many defects while the app is still being built. They need one place in the app to send them and to see what others already sent. Staging runs `migrate:fresh --seed` on every push ([architecture](../architecture.md), `scripts/deploy.sh`), so anything stored in the main database is lost on the next deploy. This ADR decides where **Feedback items** live and what the first, staging-only version does. See `GLOSSARY.md` for **Tester** and **Feedback item**.

## Decision

1. **Feedback items live in a second database that deploys never reset.** It has its own connection and its own migration folder. The deploy runs a plain `migrate` on it, never `migrate:fresh`. Screenshots go to the private `local` disk, which deploys already keep (rsync skips `storage/`).

2. **No links into the main database.** A Feedback item stores the Tester's typed name, the logged-in Member's name and email, and the impersonator's name, all as plain text. Reseeding gives Members new ids, so a foreign key or a stored id would point at the wrong person after the next deploy.

3. **Outside production only.** Routes exist only in `local` and `staging`, and the controller checks the environment again. This is the same two-layer rule as the Role-switcher ([ADR-0009](0009-user-switching-and-support-impersonation.md)). Production has no feedback routes, no connection, and no menu items.

4. **Every logged-in Tester reads everything.** Any logged-in Member can send a Feedback item, read all of them, and comment. No one logs in to send feedback, so login-page bugs cannot be sent. That is accepted.

5. **The Tester types their name.** Testers share seeded logins and switch Personas, so the account does not say who they are. The name is required, the browser remembers it, and the Tester can change it on each submit. Comments use the same name.

6. **Types:** Bug, Feature request, Translation, Missing from new site, Confusing, Other. "Missing from new site" is for a feature the old app had that the new one does not. Testers are mostly retired and do not know the word "legacy", so the label uses plain words.

7. **Status:** New, Confirmed, Fixed, Won't fix, Duplicate. Only the **Support-operator** changes status and deletes items or comments. It is a direct predicate (`isSupportOperator()`), not a gate, for the reason in [ADR-0017 §5](0017-authorization-enforcement.md).

8. **Comments are a flat list** under each item. No editing.

9. **Screenshots:** up to 3 images per item, 5 MB each, by file upload, drag and drop, or paste from the clipboard. No automatic page capture, because it needs a new package. Drag and drop uses the browser's own drop events, so it needs no package. Each screenshot downloads through a controller with a policy check, as the hard rules require.

10. **Captured with each item:** page URL, page name (route), language, browser, viewport size, the logged-in Member, the impersonator (if any), the app version, and the time. No JavaScript console errors in this pass.

11. **The app gets a version.** The deploy writes the short git commit id and the deploy time. The footer shows them in every environment, production included, and each Feedback item stores them. Semantic version numbers wait for production releases.

12. **The top-bar "?" becomes a menu.** Items: Help for this page (only when the page has a published article), Help centre, then, outside production only, Send feedback and See all feedback. It is always a menu, even with one item. This amends [ADR-0025 §10](0025-help-centre.md).

13. **A Feedback page** lists every item, newest first, filtered by type and status. Each item has a detail page with its screenshots, captured context, and comments. The send dialog links to the list so Testers can check for duplicates first.

14. **The tool's strings are translated** like all chrome ([ADR-0004](0004-chrome-only-translation.md)). The tool has no help article: it is not for Members and not in production.

15. **Followers** _(added 2026-10-27)_. A **Follower** is an email address that gets an email for each new comment on one Feedback item. See `GLOSSARY.md`.
    - **Who follows.** The send form and the comment form have an "Email me updates" checkbox. On staging the Tester types the address. On **Beta** the app uses the account's email and shows only the checkbox.
    - **What sends.** A new comment only. A status change sends nothing. The Support-operator adds a comment when they change status.
    - **Sent inside the request.** This is an exception to [ADR-0024 §4](0024-emailing-model.md). The `deliveries` queue lives in the main database, which staging wipes on every push and Beta overwrites every night, so a queued email can be lost. Comments are few, so the hourly cap is not at risk. A mail error is reported and never stops the comment from saving.
    - **Staging sends real mail for this only.** These emails name the `smtp` mailer. The default mailer on staging stays `log`.
    - **The email** has the comment text, the commenter's name, the item's current status, and a plain link to the item. It uses the language the Follower used when they followed. From is the app mailbox, with no Reply-To. The text tells the reader to reply on the site.
    - **No email to the author.** The app skips a Follower whose address matches the comment's author. On staging it knows the author's address only when they ticked the box.
    - **One Follower per item per address.** The address is trimmed and lowercased. A second follow with the same address changes nothing. Follower addresses appear on no page.
    - **No confirmation step.** After the send, the page shows "Updates go to <address>". A "You're following <title>" email goes out at once, in every environment. A Tester who does not get it knows the address is wrong.
    - **Stopping.** Every email has a signed link to a page with a "Stop updates" button. A bare one-click link is not used, because mail scanners open links. The email link is the only way to stop: staging logins are shared, so the site cannot tell who follows.
    - **Deletes.** Deleting an item deletes its Followers. A deleted comment does not recall emails already sent.
    - **Beta Support-operator.** A `members:grant-operator` command sets the `support_operator` marker. It runs once on Beta after the first import. The import keeps the marker ([ADR-0035](0035-beta-and-cutover.md) §4).

## Considered alternatives

- **Store items in the main database.** Rejected: every staging push drops it.
- **Stop reseeding staging.** Rejected: it reverses the decision that staging is disposable, which keeps demo data and migrations honest.
- **Each submission becomes a GitHub issue.** Rejected: the repo is public, so Tester names and screenshots would be public, and hundreds of defects would mix with build tickets. The Support-operator copies useful items into GitHub by hand.
- **A floating Feedback button on every page.** Rejected in favour of the help menu: Testers already look there, and it does not add to the app shell.
- **"Copy for GitHub" button, "Me too" votes, and an email per new item.** Deferred. _(2026-10-27: email per new comment to Followers is added in §15. An email per new item to the Support-operator is still deferred.)_ Votes cannot be deduplicated while names are typed.
- **Email Followers on a status change too** (2026-10-27). Rejected: a status change and the comment that explains it would send two emails. Only the Support-operator changes status, and they add a comment.
- **Confirm the address before the first email** (2026-10-27). Rejected: a token flow and a "check your inbox" step lose older Testers. A typo sends a stranger a few emails, each with a stop link.
- **Queue Follower emails** in `deliveries` or in a new table in the feedback database (2026-10-27). Rejected: `deliveries` is wiped with the main database, and a second queue is more to build than the volume needs.

## Consequences

- The Stormweb account needs one more database, created once by hand in the panel. Its credentials go in the staging `.env`.
- Tests and local development need the second connection too.
- The feedback database grows without limit. Acceptable on staging; revisit if it matters.
- A production version of this tool needs its own decisions: real identities instead of typed names, who can read what, and privacy of screenshots with real data.
