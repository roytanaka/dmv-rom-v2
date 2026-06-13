---
status: accepted
date: 2026-06-13
---

# Autonomous night-shift agents via local sandcastle

## Context

This is a single-engineer rebuild with no live users — only the maintainer sees the staging app ([ADR-0007](0007-dev-staging-deploy-strategy.md)). That makes it safe to let coding agents run unattended against the backlog and land their own work, as long as the existing CI gate stays in front of every merge.

The intended shape is a **day-shift / night-shift** split: the human does the high-judgement work in the day (grilling, PRDs, triaging issues to `ready-for-agent`), and an autonomous loop does the mechanical implementation at night (pick up a ready issue, implement, self-review, open a PR, land it on green). The work to be automated is exactly the tracer-bullet vertical slices the issue tracker already produces.

[mattpocock/sandcastle](https://github.com/mattpocock/sandcastle)'s `sequential-reviewer` template matches this almost exactly: a local loop that runs a RALPH-style implementer agent followed by a reviewer agent, each in an isolated Docker sandbox, on a fresh per-iteration branch. The template stops at "leave a branch"; our pipeline already has CI on PRs and auto-deploy on push to `staging`, so we extend the loop to open and land PRs.

## Decision

Run the night shift **locally on the maintainer's machine** via sandcastle's `sequential-reviewer` template (Docker sandbox provider), not as cloud GitHub Actions.

Per iteration, serialized:

1. **Implementer** (Claude Code, **Opus**) picks one `ready-for-agent` issue, implements it red-green-refactor, commits.
2. **Reviewer** (Claude Code, **Sonnet**) reviews the diff *in the same sandbox* and fixes it — review happens **pre-PR**, locally, not as a PR-triggered Action.
3. Push the branch, `gh pr create` into **`staging`**, **poll CI** (`ci.yml`), and on green `gh pr merge --squash`. Then `git pull` `staging` and start the next iteration off the merged tree.

The loop is the gate-keeper itself (poll-then-merge), so no branch protection is added to `staging` — protection would also block the maintainer's own day-shift direct commits.

The sandbox **Dockerfile mirrors `ci.yml`'s setup** — PHP 8.4 + Composer + sqlite extension + Node 24 + pnpm + git + `gh` + Claude Code — and deliberately **omits MariaDB**, because the test suite runs against in-memory SQLite (`phpunit.xml`). Setup hooks run `composer install`, `pnpm install --frozen-lockfile`, `cp .env.example .env`, `key:generate`. The host `node_modules` is **not** copied into the sandbox; a clean in-container `pnpm install` is used instead, because host bindings are macOS-arch and break in Linux (the `optionalDependencies` already pin the linux-x64 bindings for this).

Claude Code authenticates in the sandbox with **subscription credentials** via a long-lived `claude setup-token`, stored in `.sandcastle/.env` (gitignored). The rest of `.sandcastle/` (orchestrator, prompts, Dockerfile) is committed.

## Considered options

- **Cloud GitHub Actions for the whole loop** (issue-label and PR-event triggered, Claude Code Action). Rejected for now: more moving parts to wire, and the local template already delivers the day/night split. Revisit if keeping the Mac awake overnight becomes the bottleneck.
- **PR-triggered review Action instead of in-sandbox review.** Rejected: the template reviews+fixes before the PR exists, which is simpler and leaves the PR already-clean. CI remains the only post-PR gate.
- **GitHub native auto-merge (`--auto`) instead of poll-then-merge.** Rejected: `--auto` is asynchronous, so the next iteration would branch off `staging` before the previous PR merged, racing on shared files. Serialized poll-then-merge eliminates the race and avoids adding branch protection.
- **Human merge gate (PRs left open for morning review).** Rejected: later iterations would branch off un-merged `staging` and PRs would pile up. The in-sandbox reviewer is the quality gate; CI is the safety gate; the morning check is a spot-check, not a per-PR gate.

## Consequences

- Each merge to `staging` triggers `deploy-staging.yml` → **staging auto-deploys overnight**. A green-but-wrong PR will deploy to `staging.dmv-rom.ca`. Acceptable while the maintainer is the only viewer; revisit before there is any external audience.
- The loop is **inert until the backlog is stocked** with `ready-for-agent` issues — the day shift feeds the night shift.
- Requires the Mac awake (`caffeinate`) with Docker Desktop running; the loop must pause-and-resume on subscription rate-limit (429) rather than dying.
- `@ai-hero/sandcastle` is a new devDependency (flagged per the repo's package-review rule).
