# CLAUDE.md

Entry point for Claude Code in this repo. Kept lean. Detailed rules live in `docs/`.

## What this project is

A rebuild of the DMV-ROM volunteer management web app. The legacy app is hand-rolled PHP + jQuery on shared hosting. This rebuild replaces it with Laravel + Inertia + Vue while preserving current functionality first ("structural rebuild" — same features, new architecture), then adding new features per the UI/UX committee's vision.

The app serves ~500 volunteers at the Department of Museum Volunteers, Royal Ontario Museum. Tens of users concurrent at peak. Bilingual (English/French).

## Stack

- PHP 8.4, Laravel 12
- Inertia.js + Vue 3 + Tailwind CSS + shadcn-vue
- MariaDB 10.6 (matches production)
- Vite for asset building
- Docker (Laravel Sail) runs the PHP/Laravel app + MariaDB; the frontend toolchain (Vite/pnpm) runs on the host (see "Things that are easy to get wrong here")

## Required reading before non-trivial work

- `docs/architecture.md` — stack rationale, structural rules, what we don't do
- `docs/conventions.md` — code patterns, naming, how we do things
- `docs/adr/` — accepted architecture decisions
- `CONTRIBUTING.md` — process for contributors

## Operating principles

- Prefer Laravel and Inertia conventions over custom patterns. The framework's recommended way is the right answer unless there's a specific reason otherwise.
- Keep architecture conservative. Don't reach for new packages, abstractions, or patterns until a need is demonstrated three times.
- Read before writing. Before adding code in an area, read related files to match existing patterns.
- When uncertain about scope, ask. Resist the temptation to "just also add X."
- Surface tradeoffs. If a request has implications the user may not have considered (security, performance, maintenance burden, scope), name them before implementing.
- Reach for shadcn-vue components before building UI primitives from scratch. If a needed component isn't installed, add it via the shadcn-vue CLI rather than hand-rolling. Customize the component source after adding it — it lives in our repo, we own it.

## Hard rules

- Never write raw SQL outside of migrations and explicit legacy-migration scripts. Use Eloquent.
- Never store user-facing filenames as disk paths (see `docs/conventions.md` § Documents).
- Never bypass authorization checks for document downloads. Every download routes through a controller with a policy check.
- Never commit secrets, `.env` files, database dumps, or files from the legacy upload tree.
- The legacy database is read-only from this app's perspective. Application code never reads from legacy tables — only migration scripts in `database/migrations/legacy/` do.
- Never add a package without flagging it for review first.

## Things that are easy to get wrong here

- Split dev environment: the PHP/Laravel app and MariaDB run in Docker (Sail), but the frontend toolchain (Vite, vue-tsc) runs on the **host** with pnpm — never in the container. Run backend commands and tests via Sail (`pnpm sail …`, `pnpm sail test`); run the frontend CI gates on the host (`pnpm typecheck`, `pnpm build`, `pnpm dev`). `node_modules` carries host-arch native bindings, so running Vite inside the Linux container fails with a missing-native-binding error.
- Character encoding: legacy data is in latin1 / cp1252. New DB is utf8mb4. Migration scripts must convert deliberately.
- Permissive SQL mode in legacy: artifacts like zero-dates may exist. Migration scripts must handle them.
- Bilingual content: every user-facing string is translatable. See `docs/conventions.md` § Internationalization.
- Bilingual URLs: English is canonical at the root; French lives under `/fr/` with translated path segments (`/volunteers/123` ↔ `/fr/benevoles/123`). See [ADR-0008](docs/adr/0008-bilingual-url-routing.md).

## Agent skills

### Issue tracker

Issues live in GitHub Issues at `roytanaka/dmv-rom-v2`, managed via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default canonical labels (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`), plus a `prd` label to distinguish PRDs from regular issues. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout — `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
