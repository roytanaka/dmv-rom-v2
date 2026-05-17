---
status: accepted
date: 2026-05-16
---

# Development, staging, and deploy strategy

## Context

`docs/architecture.md` § Environments and § Deployment describes a deploy posture of "SSH + git pull + `php artisan migrate`, no CI/CD platform required for v1." That framing predates a closer look at the realities of a single-developer volunteer-pace project and the toolchain weight of Vue + Inertia + Vite (see [ADR-0005](0005-inertia-instead-of-spa-plus-api.md)).

The combination of:
- **Volunteer pace and long pauses** — manual deploy steps that the developer hasn't run in three months are a footgun
- **Vite build step** — the frontend has a real build artifact that needs to be produced somewhere
- **Vue + Inertia stack** — pre-built `public/build/` artifacts are the right thing to ship to a shared host (Node not needed on prod)
- **Future contributors** — even occasional PRs need CI tests and a way to preview running code before merge

…makes "automated, encoded-in-CI deploys" the right posture, not "we'll script it when we need it." Manual deploys age poorly under long pauses; CI workflows are documentation that still works after the developer forgets the procedure.

This ADR documents the dev/staging/deploy strategy and explicitly **revises** the "no CI/CD platform required" framing from architecture.md.

## Decision

### Branch model

Two long-lived branches:

| Branch | Deploys to | Trigger |
|---|---|---|
| `staging` | `staging.dmv-rom.ca` | auto on push |
| `main` | `dmv-rom.ca` (production) | auto on merge from `staging`, **with required-reviewer approval gate** |

Short-lived `feature/*`, `fix/*`, `chore/*` branches per `docs/conventions.md` § Git. Feature branches merge into `staging` via PR; `staging` → `main` is a separate PR that triggers the production deploy.

### CI/CD platform

**GitHub Actions.** Usage stays well under the public-repo free tier.

Workflows live in `.github/workflows/`:
- `ci.yml` — runs on every PR: lint (Pint, ESLint, Prettier), PHP tests, JS tests, Tailwind compile, Vite build. Required to pass before merge into `staging` or `main`.
- `deploy-staging.yml` — runs on push to `staging`: rebuilds artifacts, rsyncs to `staging.dmv-rom.ca` over SSH, runs Composer install + migrations on the server.
- `deploy-production.yml` — runs on push to `main`: same as staging deploy but targeting the production webroot, **gated behind a GitHub Environment** (`production`) with required-reviewer approval. The job pauses on trigger and sends a notification; the developer clicks "approve" from email/mobile/web to release the deploy.

Branch protection on both `staging` and `main`:
- Require PR
- Require CI green
- For `main`: require linear history (no merge commits) so deploy provenance is clear

### Build artifacts

**Built in CI, deployed as artifacts.** GitHub Actions runs `pnpm install` + `pnpm build`, uploads `public/build/` as a workflow artifact, the deploy job downloads it and rsyncs to Stormweb. **No Node on the production server.**

Tailwind builds via Vite (same step). Composer dependencies installed on the server via `composer install --no-dev --optimize-autoloader` post-rsync.

Artifact retention: 7 days for staging, 30 days for production. Cache `~/.composer/cache` and `node_modules` between runs (`actions/cache`) — cuts warm-cache run time roughly in half.

### Database environments

| Environment | DB | Provisioned by | Updated by |
|---|---|---|---|
| Local dev | Docker MariaDB volume | `compose.yaml` + Laravel Sail | factories + seeders (`php artisan db:seed`) |
| Staging | Separate database on the Stormweb account | provisioned during setup | migration scripts during the migration phase, then app writes |
| Production | The rebuild's database (legacy DB retained read-only post-cutover) | migration deploys, then real volunteer activity | migrations + app writes |

Local dev uses **factories + seeders only**, not production snapshots. Pulling scrubbed prod data to local is a future option, not a v1 essential. Production data never leaves the Stormweb account.

### Migration safety

Production deploy workflow runs a **timestamped `mysqldump` of the production DB before `php artisan migrate --force`**. Dump lives in `storage/db-snapshots/` on the server (gitignored, not in webroot). DB is small (~tens of MB), dump completes in seconds.

If a migration goes wrong:
- The pre-migrate dump is the fresh recovery point (minutes-old)
- Stormweb's daily off-site backup is the second tier (up to ~24h old)
- Recovery is investigate + retry (fix-forward) or restore the dump

Migration-specific approval gates (CI flagging new migration files) are not added — the existing production approval gate already requires deliberate human action for every deploy that includes any change.

### PR previews

Out of scope. At single-developer volunteer pace with one feature in flight at a time, the `staging` branch itself serves as the preview surface — merging a feature branch into `staging` auto-deploys it via `deploy-staging.yml`. No standalone PR-preview workflow is needed. Revisit if PR throughput grows enough that previewing multiple PRs in parallel becomes a real bottleneck.

## Considered alternatives

- **Manual SSH deploys, no CI/CD platform (the original architecture.md framing).** Rejected because manual steps age badly under long pauses. The workflow file IS the documentation of how to deploy.
- **Shape 1: single `main` branch with workflow_dispatch promotion to prod.** Lower overhead per change. Rejected in favor of Shape 2 (two long-lived branches) because the explicit `staging` branch matches the developer's mental model from Vercel/PlanetScale workflows and makes "what's in staging vs. prod" a `git log` away. The cost of an extra merge per release is acceptable.
- **Build artifacts on the production server.** Node is technically available on Stormweb, but installing it adds a toolchain to prod (versioning, ~hundreds of MB of `node_modules`, builds running on shared-host CPU) for a build-time concern. Server stays minimal: PHP + Composer + Apache. Break-glass: if CI is ever unavailable, building locally and rsync'ing is a known fallback.
- **Committing `public/build/` to git.** Rejected because every asset change creates a noisy diff, bloats history, and creates merge friction on built files.
- **PR preview workflows (any form).** Both per-PR isolated environments (Vercel-style) and a shared-slot `deploy-pr-to-staging.yml` workflow were considered and dropped. The isolated form requires control-panel API scripting and per-PR DB provisioning — too much engineering for an occasional-contributor reality. The shared-slot form is dropped because `staging` itself already serves as the preview surface at single-feature-at-a-time pace. Revisit if PR throughput grows.
- **Migration approval gate in CI.** Rejected; existing production approval gate is enough.
- **Self-hosted GitHub Actions runner on Stormweb.** Free, no minute limits, but more setup and a maintenance burden for a problem you don't have.

## Consequences

### What this requires

- A `.github/workflows/` directory with the four workflows above. Initial setup is a few hours.
- A deploy SSH key stored in GitHub Actions secrets (`STORMWEB_SSH_KEY`), authorized for the Stormweb account.
- A GitHub Environment named `production` with required reviewers configured.
- A small operator allowlist on Stormweb for maintenance-mode IP exceptions (used at cutover and for break-glass access).

### What this enables

- Deploys survive long pauses. After a 6-month gap, the developer can deploy by merging — no remembered procedure.
- Onboarding contributors is straightforward: open a PR, CI runs tests, reviewer can trigger a staging preview.
- Migration safety is automatic, not aspirational.
- The deploy procedure is auditable (Actions run history) instead of tribal knowledge.

### What we accept

- **Dependency on GitHub Actions.** If GitHub has an outage, deploys are blocked until it recovers. Mitigation: break-glass procedure documented — SSH into Stormweb, manually run the deploy commands. Cost of the dependency is low (GH Actions has excellent uptime; manual fallback exists).
- **Slight friction on production deploys** (the approval gate). Intentional. The merge button is the "I'm being deliberate" gesture; the approval gate is the "I'm too tired to be deploying right now" speed bump. Designed-in cost.

### What this revises in existing docs

This ADR supersedes the "no CI/CD platform required for v1" statement in `docs/architecture.md` § Deployment. That section should be updated to point at this ADR. Other claims in architecture.md (SSH access, Composer install, native Stormweb stack) remain accurate.

## Open implementation questions

- Exact workflow YAML files (the architecture is committed; specific syntax is implementation).
- Notification routing for approval gates (default: GitHub email; could add Slack/Discord later).
- Whether `staging` refresh-from-prod is a manual button or scheduled (post-cutover concern, not v1).
- Whether to add a manual `rollback-production.yml` workflow that flips Stormweb webroot to a previous deploy's artifact (separate from migration rollback). Defer until first time the need is felt.

## References

- `docs/architecture.md` § Environments and § Deployment — current (about-to-be-updated) prose
- `docs/conventions.md` § Git, § Code style and tooling — branch naming and lint hooks
- [ADR-0002](0002-stay-on-stormweb-shared-hosting.md) — Stormweb hosting, backup posture
- [ADR-0005](0005-inertia-instead-of-spa-plus-api.md) — Vue + Inertia + Vite stack (the reason there's a build step at all)

## Amendments

**2026-05-16** — Removed the `deploy-pr-to-staging.yml` workflow from scope during Deploy PRD grilling. Rationale: at single-developer volunteer pace with one feature in flight at a time, the `staging` branch itself is the preview surface; a standalone PR-preview workflow is over-engineered for current operational reality. The "Considered alternatives" entry was generalized to cover both isolated and shared-slot preview forms.
