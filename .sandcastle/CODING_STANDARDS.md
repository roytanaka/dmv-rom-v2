# Sandbox coding standards

Loaded by both agents in the autonomous loop. `CLAUDE.md` is already in your
context and holds the project's hard rules — this file holds only what
`CLAUDE.md` cannot: how this sandbox differs from the developer's machine, and
where to look things up.

## This sandbox is not the dev environment

`CLAUDE.md` tells a human developer to run backend commands through Sail.
**That does not apply here.** This container has PHP 8.4, Node 24, and SQLite,
and no Docker — there is no Sail to call, and any `pnpm sail …` command will
fail. Run the tools directly:

- `php artisan …`, `vendor/bin/pest`, `vendor/bin/pint` — no prefix
- `pnpm …` — no prefix; `node_modules` here is Linux-native and was installed
  inside this container

## The gate

Run all six and fix every failure before committing:

```
vendor/bin/pint --test
pnpm exec eslint .
pnpm run format:check
pnpm run typecheck
pnpm run build
vendor/bin/pest
```

Green here is necessary but not sufficient. The merge is decided by
`.github/workflows/ci.yml`, which runs `php artisan test --parallel` against
**MariaDB 10.6** — while `vendor/bin/pest` here runs against **in-memory
SQLite** (`phpunit.xml`). Anything resting on engine behaviour — column types,
collation and case-sensitivity, date handling, strict-mode rejections, `groupBy`
semantics, JSON functions — can pass in this sandbox and fail the merge. Write
those tests engine-agnostic, and reach for Eloquent over anything hand-tuned to
one engine.

## Where the rules live

- `CLAUDE.md` — hard rules, non-negotiable. Already in context. Four of them
  bite most often in autonomous work: raw SQL, untranslated strings,
  document-download authorization, and adding a package. Re-read those four
  before you commit.
- `docs/conventions.md` — read before writing code in an area you have not
  touched this iteration: naming, Documents, Internationalization.
- `docs/architecture.md` — read when your change adds a layer, a package, or an
  abstraction.
- `docs/adr/` — 21 accepted decisions. Grep it for the subsystem you are
  touching and read every ADR that names it. A change that contradicts an
  accepted ADR is a defect, however clean the code.

## Tests

Pest, in-memory SQLite. New or changed behaviour needs coverage, written
red-green. Test observable behaviour through the public interface — HTTP routes,
Inertia responses, model API — so the test survives an internal refactor.
