---
status: accepted
date: 2026-10-25
accepted: 2026-10-25
---

# Beta and Cutover: a nightly one-way import, then a domain switch

This ADR replaces [ADR-0006](0006-cutover-strategy.md). It comes from a grilling session on 2026-10-25. ADR-0006 planned a short period of parallel running on staging, followed by one big cutover night. Two things changed since then. Staging now runs `migrate:fresh --seed` on every push ([ADR-0007](0007-dev-staging-deploy-strategy.md)), so it can never hold real data. And the **Legacy app**'s credential is not a password: it is the last 4 characters of the Member's ROM membership number, stored as plain text, with no way to change it. ADR-0006 assumed a migrated password hash.

The new plan puts a **Beta** stage between staging and **Cutover**. During Beta, Members try v2 with real data that is copied from the Legacy app every night. See `GLOSSARY.md` for **Legacy app**, **Beta**, **Cutover** and **Tester**.

## Decision

1. **Four stages.**
   - **Staging.** Invited Members and Testers use fake data. Staging never holds real data. This closes the "staging refresh-from-prod" open question in ADR-0007.
   - **Beta.** Testers use v2 at `beta.dmv-rom.ca` with real data that the import refreshes every night.
   - **Cutover.** v2 becomes the live app at `dmv-rom.ca`.
   - **Archive.** The Legacy app stays reachable at `legacy.dmv-rom.ca`.

2. **Gates.**
   - Staging to Beta: the items in the feature-parity spec are done, and the import runs cleanly against a copy of real data.
   - Beta to Cutover: no open bug blocks Cutover, the import runs cleanly on two nights in a row, and the President approves.

3. **Beta is the production install.** It has its own folder and database on the Stormweb account ([ADR-0002](0002-stay-on-stormweb-shared-hosting.md)). It deploys from `main` through the production path of `scripts/deploy.sh` (no reseed, a database snapshot, then `migrate`) behind an approval step. At Cutover, `dmv-rom.ca` points at this install and `beta.dmv-rom.ca` redirects to it. The production deploy workflow that ADR-0007 deferred must exist before Beta starts.

4. **The import is one-way and idempotent.** It copies the Legacy app into v2. To run it many times has the same effect as to run it once. It runs every night during Beta and one last time at Cutover.
   - **The Legacy app is the system of record during Beta.** Members do real work in the Legacy app only. Nothing goes back from v2 to the Legacy app.
   - **It mirrors deletions.** A row that is gone from the Legacy app is removed from v2.
   - **It overwrites only fields that come from the Legacy app.** A field that exists only in v2 survives every run: a v2 password, the `support_operator` marker, and any later v2-only setting.

5. **Documents follow the same rule.** The import copies only files that a Legacy document row refers to (about 4.2k of 19.5k files, [ADR-0030](0030-document-library.md)). It adds new files every night. The copy is also idempotent. Files that no Legacy document refers to have no owner and no access rule, so v2 cannot serve them safely. They stay in the archive. The redirect map for old document links is unchanged ([ADR-0003](0003-document-storage-architecture.md)).

6. **Email on Beta.** Account email sends: password reset and the first sign-in. The daily mail pass (shift reminders and the empty-desk alert) and invitation email are off. A new environment setting controls this. The Legacy app keeps sending its own reminders, so no Member gets the same reminder twice.

7. **First sign-in.** A Member signs in to v2 for the first time with their email and the last 4 characters of their ROM membership number. Then they must set a new password.
   - v2 stores only a hash of those 4 characters. It deletes the hash after the first successful sign-in, and the import never restores it.
   - After 5 wrong attempts, the challenge locks for that Member, from any network.
   - "Forgot password" works for every Member, also before their first sign-in. It is the way in for a locked Member and for a Member whose membership number is missing or wrong.
   - Every Member needs a unique email before the first Beta import, not only before Cutover ([ADR-0001](0001-authentication-and-identity.md)). The import needs a unique email to match each Member.

8. **Feedback on Beta.** Beta sends Feedback items to the same feedback database as staging. A Beta item records the Tester's real name. Only a **Support-operator** reads Beta items, because their screenshots show real Member data. This amends [ADR-0029](0029-tester-feedback.md).

9. **Cutover day.**
   1. The Legacy app shows a maintenance page. This is host configuration, not a code change.
   2. The final import and file copy run.
   3. Smoke test.
   4. `dmv-rom.ca` points at the v2 install. `beta.dmv-rom.ca` redirects there.
   5. A rollback window of about 4 hours. If a severe problem appears, `dmv-rom.ca` points back at the Legacy app. After the window, v2 is the only live app and problems are fixed forward.
   6. The Legacy app comes back at `legacy.dmv-rom.ca`, behind a server password, on a database user that can only read.

10. **The archive has no end date.** The Legacy app stays reachable as the historical record of the DMV's earlier years. It runs on an end-of-life PHP version with known defects, so it is never open without the server password. The read-only database user stops writes without a change to Legacy code.

11. **Kept from ADR-0006.** A full backup of the Legacy database, the document tree and the v2 database before the maintenance page goes up. No patches to Legacy code at any stage.

## Considered options

- **Parallel running on staging (ADR-0006).** Staging reseeds on every push, so it cannot hold real data for weeks. Testers would only see fake data until Cutover night.
- **Testers enter real work in Beta.** That needs a sync from v2 back to the Legacy app: the dual-write that ADR-0006 rejected. Instead, the nightly import overwrites Beta, and the Legacy app is the system of record.
- **A separate Beta install, and a new production install at Cutover.** It was rejected because Cutover would then be a first deploy. With Beta as the production install, Cutover is a domain switch plus one last import, and Beta tests the production deploy for weeks.
- **An email link for first sign-in, with no 4-digit challenge.** Proof of the inbox is stronger than 4 digits. The challenge was kept because Members already know it from the Legacy app. The per-Member lock and "Forgot password" limit the risk.
- **Copy every Legacy file.** Disk space is not a limit. It was rejected because orphaned files have no owner or access rule, and the archive already keeps them reachable.
- **Take the Legacy app offline and archive the database (ADR-0006).** It was rejected so that the Legacy app stays reachable as a historical record.
- **Beta sends no email at all.** It was rejected because Testers then cannot reset a password.

## Consequences

- The import needs a stable key for each Legacy row so that a re-run updates rows instead of adding them. Each imported table needs to know which v2 fields come from the Legacy app. The data-migration spec decides how.
- The daily mail pass and invitation email need an environment check. Today `RunDailyMailPass` and `DrainDeliveries` have none.
- The feedback form needs to work on Beta. Today its routes exist only in `local` and `staging` (ADR-0029 §3).
- The first-sign-in challenge, its per-Member lock and the hash of the 4 characters are new auth work. ADR-0001's email and password model is unchanged after the first sign-in.
- Feature-parity and data-migration specs do not exist yet. Both block Beta.

## Not covered here

- The email to Members that announces Cutover, and the Cutover date. These are operational.
- The rollback runbook: who decides, which command switches the domain, and what counts as severe.
- How each table and field maps from the Legacy app to v2. The data-migration spec covers this.
