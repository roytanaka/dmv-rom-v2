---
status: accepted
date: 2026-05-15
---

# Stay on Stormweb shared hosting

## Context

The legacy app has run on Stormweb shared hosting (Vancouver, BC; Enterprise package, $22.99 CAD/mo) for years. A reasonable default for a rebuild of this scope would be to move to a VPS, container host, or major cloud (Canadian region) "while we're already rewriting everything." We are deliberately not doing that.

The constraints that shaped this decision:
- **Canadian data residency** is mandatory — member PII must stay in Canada. Stormweb's Vancouver datacenter satisfies this and the residency story is verifiable by a single technical contact.
- **~500 Volunteers, tens concurrent at peak.** Load is small and predictable.
- **No queue workers, websockets, or background services in v1** (see `docs/architecture.md § What we don't do`). This is a deliberate scope choice, not a forced one.
- **Stormweb supports PHP 8.3–8.5 and MariaDB 10.6** — the exact stack Laravel 12 wants.
- **PHP version is per-domain** on Stormweb, so staging can run a newer PHP while the live legacy domain stays on its current version until cutover (one control-panel dropdown flip).
- **SSH + key auth + daily off-site backups** are included.
- **The single technical maintainer is comfortable with the host.** Familiarity reduces operational handoff risk during the rebuild.

## Decision

Run the rebuild on the **same Stormweb shared hosting account** as the legacy app, in a separate domain and database (`staging.dmv-rom.ca` + a separate database). At cutover, the live `dmv-rom.ca` domain flips its document root + PHP version, and the legacy database is retained read-only as backup.

## Considered alternatives

- **Stormweb VPS plan (same vendor, more capacity).** Reserved as the first response if we hit the load-ceiling trigger below. Not chosen now because there is no demonstrated need.
- **Canadian VPS (OVH Canada, Vultr Toronto, Vercel/Cloudflare Canadian POPs with a Canadian DB).** Real residency story but adds operational surface (OS patching, web-server config, mail config) for no current benefit. Worth revisiting only on the vendor-failure trigger.
- **Major cloud Canadian region (AWS ca-central-1, GCP northamerica-northeast1).** Pricing and complexity overhead are unjustified for the load. The "future-proofing" argument for cloud assumes growth we have no signal of.
- **Self-hosted on ROM infrastructure.** Out of scope — ROM IT does not host volunteer-org apps and this is a volunteer-run project.

## Consequences

### What we accept by staying on shared hosting

These are deliberate trade-offs, **not** exit triggers. They are constraints that shape the application architecture:

- **No queue workers.** Email sends synchronously via Laravel's mail. Long-running operations (bulk-mailing all volunteers, batch document processing) must either fit within an HTTP request budget or be chunked across cron-triggered passes.
- **No websockets / live updates.** Any UI that wants real-time presence-style behavior must use polling or be designed without it.
- **No persistent background services.** No long-running PHP processes, no daemon scripts. Scheduled work runs via control-panel cron only.
- **No Docker on production.** Local dev uses Docker (via Laravel Sail) to mirror PHP 8.4 + MariaDB 10.6, but production runs the native Stormweb stack.
- **Limited observability.** Logs and error tracking sit on the host filesystem unless we add an external provider. We accept this for v1.

If a future feature needs one of these, the right move is usually to design the feature around the constraint, not to migrate hosts.

### Exit triggers

Two conditions move us off Stormweb shared hosting. They are the only triggers; everything else above is a trade-off we live with.

1. **Concurrent load exceeds shared-host limits.** Symptoms: sustained slow page loads under normal traffic, 503s at peak, host-killed processes, repeated resource-limit warnings. **Planned response:** upgrade to a Stormweb VPS plan first (same vendor, same residency, same admin familiarity). Only consider lateral migration to a different host if the VPS path also fails.
2. **Vendor failure.** Prolonged Stormweb outage, change to their residency or data-handling posture, acquisition that changes the operating model, or shutdown. **Planned response:** restore from daily off-site backups onto another Canadian VPS provider (OVH Canada or comparable). A migration runbook needs to exist before this trigger fires — capturing it is part of operational readiness, not part of this ADR.

Neither trigger is on the horizon. The point of naming them is so a future maintainer can recognize the conditions and respond deliberately rather than panic-migrating at the first incident.

### What this ADR does not commit to

- The application architecture is **not** locked to shared hosting. Laravel + Inertia + MariaDB runs anywhere. If a trigger fires, the rewrite cost of moving is small relative to the cost of the wrong host choice now.
- Email-sending provider, object storage, and other infra-adjacent decisions are recorded in their own ADRs and may evolve independently of where the app runs.

## References

- `docs/architecture.md § Stack and rationale`, `§ Environments`, `§ Deployment`
