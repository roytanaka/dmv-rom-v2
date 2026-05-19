---
status: accepted
date: 2026-05-15
---

# Cutover strategy (Phase 5 → 7)

> Resolves the forward reference from [ADR-0001](0001-authentication-and-identity.md) for legacy credential migration. ADR-0004 is reserved for a held bilingual translation workflow ADR.

## Context

The rebuild moves from the legacy app (PHP 7.4, ~40 GB of documents in the legacy webroot) to the new app (same domain post-cutover, PHP 8.4, Laravel storage). The legacy app has been live for years and the principle from the migration plan is unequivocal: **the legacy system stays untouched until cutover.** No partial migration of features, no dual-write, no patches to legacy PHP.

The migration plan commits to a **big-bang cutover with parallel running and a preserved-legacy-DB snapshot for rollback**. This ADR documents *why* that shape over the alternatives, and resolves the open sub-decisions the migration plan leaves loose: credential transition, the cutover window's user experience, what rollback means, and how 40 GB of documents move.

## Decision

The rebuild cuts over **big-bang inside a scheduled maintenance window**. Specifically:

1. **Parallel running before cutover.** The new app runs at `staging.dmv-rom.ca` against the new database for weeks. Recruited volunteers exercise it alongside the still-live legacy app. Issues feed back into ongoing rebuild work.
2. **Pre-copy documents with UUID renaming and DB rows.** During Phase 5, the document tree is copied to `storage/app/documents/` with opaque UUID filenames and a `documents` row per file, populated per [ADR-0003](0003-document-storage-architecture.md). At cutover, a final rsync-style delta pass catches anything uploaded to legacy after the pre-copy.
3. **Legacy fully offline during the cutover window.** Legacy webroot returns a maintenance page (host-level config, not a code change — honors the "legacy untouched" principle). Final delta migration runs against a quiescent legacy DB.
4. **First-login credential challenge.** No bulk credential emails. On first new-app login, the volunteer enters their email plus their legacy credential. The system verifies against a one-time migrated hash, immediately forces a "set your new password and kiosk PIN" flow, and discards the legacy hash. Each volunteer's transition is one login + one short upgrade screen.
5. **Time-bounded rollback window (~4 hours).** If catastrophic problems appear within the first ~4 hours, the webroot flips back to legacy. Volunteers are warned to hold off on heavy data entry during the soak. After the window, the new app is authoritative — fix-forward only. The legacy DB is renamed to an archive name and preserved indefinitely as a forensic/historical asset, not a live rollback target.

## Considered alternatives

- **Dual-write between legacy and new apps.** Both apps write to both databases for a period, then the legacy app is shut off. Rejected because (a) it requires modifying legacy code, violating the "untouched until cutover" principle, (b) the schema gap is large (the legacy DB has hundreds of tables collapsing into a much smaller normalized rebuild schema), making bidirectional sync impractical, (c) it doubles the operational surface for the duration.
- **Strangler pattern (feature-by-feature migration with reverse-proxy routing).** Each feature is rebuilt and the new app takes over routes for that feature while the rest stays legacy. Rejected because the legacy app's session/auth model and schema make per-feature handoff painful, and because the rebuild's value comes from the unified new architecture — running half-rebuilt for months has all of the costs and few of the benefits.
- **Pre-cutover email reset campaign for credentials.** Every volunteer emailed a "set your new password" link weeks before cutover. Rejected as the primary path because volunteers who don't read the email still need a fallback; the first-login challenge IS that fallback and is enough on its own. A pre-cutover heads-up email is still sensible operationally — it just isn't the credential-transition mechanism.
- **Big-bang assigned credentials emailed at cutover.** Temp password + PIN emailed to every volunteer. Rejected because plaintext credentials in email are a poor security posture, family-shared inboxes are still common (despite the unique-email rule that comes with cutover), and support load for lost emails would be heavy.
- **Read-only legacy during the window.** Reads work, writes blocked. Rejected because blocking writes requires patching legacy code.
- **No formal rollback — fix-forward only from the start.** Considered. The 4-hour bounded window was chosen instead because it gives a real (if narrow) escape hatch for catastrophic problems caught immediately, without committing to a long rollback window whose data-loss cost would grow with every hour.
- **Read-only new app during a soak window.** New app accepts reads only for the first few hours after cutover. Rejected because the cost of building and maintaining a read-only feature flag through cutover exceeds the benefit; volunteer-warning + short rollback window achieves a similar outcome more cheaply.

## Consequences

### Cutover-window choreography

The maintenance window (evening or weekend, communicated in advance) runs roughly:

1. **T-0:** Legacy webroot flipped to maintenance page (`.htaccess` returns 503 + an explanation page for all paths except a small operator allowlist).
2. **T+0:00–T+0:15:** Final delta DB migration runs (legacy-migration scripts pick up rows changed since the last staging sync).
3. **T+0:15–T+0:45:** Final document rsync catches new/modified files since pre-copy; new `documents` rows created for any new files.
4. **T+0:45–T+1:00:** Smoke tests on the new app pointed at production data. Spot-check known-good users, documents, committees.
5. **T+1:00:** Webroot flipped to new app. The live domain now serves the new stack. PHP version on the live domain flips 7.4 → 8.4.
6. **T+1:00–T+5:00:** Soak window. Webroot can still be flipped back to legacy if catastrophic problems appear. Volunteers warned to hold off on bulk data entry.
7. **T+5:00:** Soak ends. New app is authoritative. Legacy DB renamed to its archive name (already preserved through the window; this is the formal cutoff).

These timings are illustrative — the actual runbook will refine them based on dry runs during Phase 6.

### What this ADR depends on

- **Unique emails per volunteer** (from [ADR-0001](0001-authentication-and-identity.md)) must be resolved *before* cutover. Every shared-email household needs a second email (or a synthetic fallback) provisioned during the pre-cutover audit. This is a Phase 5 prerequisite, not a cutover-day task.
- **Phase 6 parallel-running accounts.** Volunteers who exercised the staging system have new-app accounts with new-app credentials already. Cutover migration must preserve those credentials (no clobbering) and reconcile against the email-based identity. Operational policy: staging accounts are migrated into production as-is; the first-login challenge is skipped for any volunteer who has already set their new credentials via staging.
- **Apache redirect map for legacy document URLs** (from [ADR-0003](0003-document-storage-architecture.md)) is emitted by the same Phase 5 migration scripts that copy files. Cutover deploys it alongside the webroot flip.
- **Backups before T-0.** Full off-site backup of the legacy DB, the document tree, and the new app's pre-cutover state must complete before the window begins. Stormweb's daily off-site backups (see [ADR-0002](0002-stay-on-stormweb-shared-hosting.md)) plus a manual pre-cutover dump.

### What this ADR explicitly doesn't cover

- **Communication plan with volunteers.** Pre-cutover emails, maintenance window timing, post-cutover follow-up — these are operational and live in the cutover runbook, not this ADR.
- **Per-feature data migration scripts.** Each script's logic (encoding conversion, NULL handling for zero-dates, mapping legacy ownership to new FKs) is a Phase 5 implementation detail.
- **Specific rollback runbook.** What command flips the webroot, who's authorized to make the call, how the rollback is announced — operational, not architectural.
- **Cutover scheduling.** Evening vs. weekend, time-of-year, alignment with DMV calendar — a stakeholder/operational decision, not an architectural one.

## Open implementation questions

- Whether legacy session cookies (volunteers still "logged in" to the old app at cutover) get any kind of cross-app handoff, or whether everyone is forced through a fresh login. Default: fresh login. Sessions don't carry across the webroot flip.
- What "catastrophic" means as a rollback trigger. A documented severity scale before the window is better than an in-the-moment judgment call. Belongs in the cutover runbook.
- Whether the maintenance window's host-level configuration is reusable for future planned maintenance, or one-off scripted. Either is fine.

## References

- [ADR-0001](0001-authentication-and-identity.md) — auth model that the first-login challenge bootstraps into
- [ADR-0002](0002-stay-on-stormweb-shared-hosting.md) — hosting environment + backup posture
