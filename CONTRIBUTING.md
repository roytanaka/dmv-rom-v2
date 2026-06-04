# Contributing

Thanks for considering contributing to the DMV-ROM volunteer system. This document describes how the project is built and what's expected of contributors.

## Who maintains this

This project is maintained primarily by volunteers using AI-assisted development tools (specifically Claude Code). The expectation is that future maintainers will work the same way. The codebase is intentionally structured to be approachable by people who are comfortable directing AI tools but who may not be experts in PHP, Laravel, or Vue.

If you are an experienced Laravel/Vue developer joining the project, welcome — please read `docs/architecture.md` and `docs/conventions.md` first, because some patterns are chosen for AI-tool friendliness over conventional "best practice."

## Ways of working

The project runs on version control, code review, and explicit scope. The aim is a system any one contributor can pick up after a long pause and understand from the docs and the diff history alone.

**Feature requests** go through the project lead, not directly to whoever is writing code at the moment. Requests are queued, prioritized, and scoped before implementation.

**Scope is negotiated.** Not every "yes" is free. Every feature has a maintenance cost; that cost is part of the conversation when deciding what to build.

**Maintenance is a feature.** Time spent on tests, refactoring, documentation, and dependency updates is real work, not overhead.

**Pull requests, not direct pushes.** Every change goes through a branch and a review, even if the reviewer is the same person who wrote it (self-review forces a second look).

## Branch model

During the structural-rebuild phase, `staging` is the integration trunk — branch off it, target PRs at it, and it auto-deploys to staging.dmv-rom.ca. `main` is reserved as the release branch and stays dormant until go-live, when `staging` is promoted into it. Until then, treat `staging` as "the trunk."

## Development workflow

1. Pick up an issue from the project board, or open one for discussion before writing code.
2. Branch from `staging`: `git checkout -b feature/short-description` or `fix/short-description`.
3. Write the change. If using Claude Code, follow the rules in `CLAUDE.md` and the docs it references.
4. Run tests locally: `pnpm sail vendor/bin/sail php artisan test`.
5. Open a pull request. Describe what changed and why. Link the issue.
6. Self-review the diff before requesting review from others.
7. Merge after approval and passing CI.

## Standards

- Follow the conventions in `docs/conventions.md`. Do not invent new patterns without discussion.
- Write tests for new controller actions and significant service-class behavior. UI-only changes don't need tests.
- Run `vendor/bin/sail pint` (or rely on the pre-commit hook) before committing PHP.
- Run the configured Vue/JS formatter before committing frontend code.
- Update documentation when behavior changes. Out-of-date docs are worse than no docs.
- Never commit secrets, database dumps, or document files from the legacy system.

## Communication

- Technical decisions and tradeoffs are documented in `docs/adr/`. If you make a non-obvious decision, write an ADR.
- Questions about feature priorities or scope: project lead.
- Questions about UI/UX: the UI/UX committee.

## Bilingual content

The app must work fully in English and French. When adding user-facing text, add translations to both `lang/en/` and `lang/fr/` files. Don't ship English-only strings.

URLs are bilingual too: English at the root, French under `/fr/` with translated path segments. When adding a new route, register both locales and add the segment translations to `lang/{en,fr}/routes.php`. See [ADR-0008](docs/adr/0008-bilingual-url-routing.md).

## Security

- Don't disclose vulnerabilities in public issues. Email the project lead directly.
- Never commit credentials, API keys, or production database dumps.
- All document downloads must go through authorization checks. No exceptions.
- Personal member data (names, contact info, photos) is treated as sensitive even when not regulated.
