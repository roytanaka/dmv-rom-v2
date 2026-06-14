# Coding Standards

Loaded by the reviewer agent. The authoritative rules live in the repo — this is
a pointer plus the things most likely to be violated by an autonomous agent.

## Read these (in the repo)

- `CLAUDE.md` — entry point and **hard rules** (non-negotiable)
- `docs/architecture.md` — structural rules, what we don't do
- `docs/conventions.md` — code patterns, naming, Documents, Internationalization
- `docs/adr/` — accepted decisions; do not contradict them

## Hard rules (most relevant to autonomous-loop work)

- **Eloquent only.** Never write raw SQL outside migrations / legacy-migration
  scripts.
- **Bilingual.** Every user-facing string is translatable (English canonical,
  French under `/fr/`). No hard-coded copy.
- **Authorization.** Every document download routes through a controller with a
  policy check — never bypass it. Never store user-facing filenames as disk paths.
- **Framework first.** Prefer Laravel / Inertia conventions over custom patterns.
  Reach for shadcn-vue components before hand-rolling UI primitives.
- **No new packages** without flagging for review. If the issue seems to need one,
  stop and leave a comment rather than adding it.
- **Conservative.** Don't introduce new abstractions until a need is demonstrated.
  Keep changes small and scoped to the issue.

## Style & tests

- Formatting/linting is enforced by the gate (Pint, ESLint, Prettier, vue-tsc).
  The code must pass all of them before the PR opens.
- Tests run on in-memory SQLite via Pest. New or changed behaviour needs test
  coverage; prefer a failing-test-first (red-green) flow.
