---
status: accepted
date: 2026-09-26
accepted: 2026-09-26
---

# Tester feedback lives in its own database

From a grilling session on 2026-09-26. Testers on staging find many defects while the app is still being built. They need one place in the app to send them and to see what others already sent. Staging runs `migrate:fresh --seed` on every push ([architecture](../architecture.md), `scripts/deploy.sh`), so anything stored in the main database is lost on the next deploy. This ADR decides where **Feedback items** live and what the first, staging-only version does. See `CONTEXT.md` for **Tester** and **Feedback item**.

## Decision

1. **Feedback items live in a second database that deploys never reset.** It has its own connection and its own migration folder. The deploy runs a plain `migrate` on it, never `migrate:fresh`. Screenshots go to the private `local` disk, which deploys already keep (rsync skips `storage/`).

2. **No links into the main database.** A Feedback item stores the Tester's typed name, the logged-in Member's name and email, and the impersonator's name, all as plain text. Reseeding gives Members new ids, so a foreign key or a stored id would point at the wrong person after the next deploy.

3. **Outside production only.** Routes exist only in `local` and `staging`, and the controller checks the environment again. This is the same two-layer rule as the Role-switcher ([ADR-0009](0009-user-switching-and-support-impersonation.md)). Production has no feedback routes, no connection, and no menu items.

4. **Every logged-in Tester reads everything.** Any logged-in Member can send a Feedback item, read all of them, and comment. No one logs in to send feedback, so login-page bugs cannot be sent. That is accepted.

5. **The Tester types their name.** Testers share seeded logins and switch Personas, so the account does not say who they are. The name is required, the browser remembers it, and the Tester can change it on each submit. Comments use the same name.

6. **Types:** Bug, Feature request, Translation, Missing from legacy, Confusing, Other.

7. **Status:** New, Confirmed, Fixed, Won't fix, Duplicate. Only the **Support-operator** changes status and deletes items or comments. It is a direct predicate (`isSupportOperator()`), not a gate, for the reason in [ADR-0017 §5](0017-authorization-enforcement.md).

8. **Comments are a flat list** under each item. No editing.

9. **Screenshots:** up to 3 images per item, 5 MB each, by file upload or paste from the clipboard. No automatic page capture, because it needs a new package. Each screenshot downloads through a controller with a policy check, as the hard rules require.

10. **Captured with each item:** page URL, page name (route), language, browser, viewport size, the logged-in Member, the impersonator (if any), the app version, and the time. No JavaScript console errors in this pass.

11. **The app gets a version.** The deploy writes the short git commit id and the deploy time. The footer shows them in every environment, production included, and each Feedback item stores them. Semantic version numbers wait for production releases.

12. **The top-bar "?" becomes a menu.** Items: Help for this page (only when the page has a published article), Help centre, then, outside production only, Send feedback and See all feedback. It is always a menu, even with one item. This amends [ADR-0025 §10](0025-help-centre.md).

13. **A Feedback page** lists every item, newest first, filtered by type and status. Each item has a detail page with its screenshots, captured context, and comments. The send dialog links to the list so Testers can check for duplicates first.

14. **The tool's strings are translated** like all chrome ([ADR-0004](0004-chrome-only-translation.md)). The tool has no help article: it is not for Members and not in production.

## Considered alternatives

- **Store items in the main database.** Rejected: every staging push drops it.
- **Stop reseeding staging.** Rejected: it reverses the decision that staging is disposable, which keeps demo data and migrations honest.
- **Each submission becomes a GitHub issue.** Rejected: the repo is public, so Tester names and screenshots would be public, and hundreds of defects would mix with build tickets. The Support-operator copies useful items into GitHub by hand.
- **A floating Feedback button on every page.** Rejected in favour of the help menu: Testers already look there, and it does not add to the app shell.
- **"Copy for GitHub" button, "Me too" votes, and an email per new item.** Deferred. Votes cannot be deduplicated while names are typed.

## Consequences

- The Stormweb account needs one more database, created once by hand in the panel. Its credentials go in the staging `.env`.
- Tests and local development need the second connection too.
- The feedback database grows without limit. Acceptable on staging; revisit if it matters.
- A production version of this tool needs its own decisions: real identities instead of typed names, who can read what, and privacy of screenshots with real data.
