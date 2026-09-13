---
status: accepted
date: 2026-09-13
accepted: 2026-09-13
---

# Help centre: in-app Markdown articles, treated as chrome

From a grilling session on 2026-09-13. The app has a `/help` link in the top bar that lands on a Coming Soon stub, no user-facing documentation anywhere, and features shipping faster than one person can track. This ADR decides where help lives, what it is made of, who sees it, how screenshots get made, and how an article becomes part of a feature's definition of done.

**No product code ships from this ADR.** The spec does.

## Context

Two audiences asked for the same thing. Volunteers (about 500, non-technical, many rotating through officer roles) need "how do I" pages with screenshots and steps. The sole engineer needs a ledger of what is built, because the feature list has outgrown memory. A separate internal inventory would need keeping in sync with the help pages. One artifact serving both is cheaper.

[ADR-0004](0004-chrome-only-translation.md) draws the translation line: **chrome** is authored by the project, the same for every Volunteer, and translated; **content** is what a Member types into a row and is never translated. Help articles are authored by the project and identical for everyone. They are chrome. But they are long-form prose with images, and the `lang/{en,fr}/*.php` message store is the wrong shape for them.

The host is shared hosting ([ADR-0002](0002-stay-on-stormweb-shared-hosting.md)). No search server, no extra services. The repo already carries `league/commonmark` through Laravel, so Markdown rendering costs no new package.

Browser automation in this repo is the `agent-browser` CLI, allowlisted but with nothing scripted. It has a known quirk: clicks do not open Radix overlays; those need in-page JS. Playwright would be a new dev dependency.

## Decision

1. **One help centre, inside the app, at `/help`** (`/fr/aide`). Login required. No public pages: sign-in is standard and volunteers get told how by email. It replaces the Coming Soon stub and the top-bar link.

2. **Help articles are chrome.** They ship in both locales and follow ADR-0004's workflow: the French article is machine-translated in the same PR as the English one and shipped unreviewed, flagged as such until a human reviews it. This **amends ADR-0004**: chrome now has a second source of truth beyond `lang/`, the help article tree. An amendment note is folded onto that ADR.

3. **Articles are Markdown files in the repo**, one per locale per article, rendered server-side with the CommonMark library Laravel already depends on. No CMS, no DB rows, no editor UI. The article ships in the same PR as the feature it describes. Consequence, stated plainly: nobody edits help copy without a pull request.

4. **One manifest file is the index and the ledger.** An ordered PHP array lists every article with its section, required role, status, French state, and the app route it explains. Markdown files hold only prose; the title is the H1. A Pest test keeps manifest and files honest (every entry has both locale files, every referenced screenshot exists).

5. **Sections mirror the app's navigation.** Getting started, then one section per app area (Dashboard, My Hours, Directory, News, Groups, Scheduling, Hours and reports, Emailing, Settings). One article per task, about one screen of reading. One short overview article per section.

6. **Every logged-in Member sees every article.** No role gating. Members rotate through roles often and should read about a role before they take it on. Each article carries a **Required role** badge (a Group role such as Scheduler or Chair, Super-tier, or Support operator; empty means every Member) shown at the top of the article and beside its title in the index. The badge text is chrome and translated.

7. **Article status is `draft` or `published`.** Drafts are hidden from the index and the contextual link but reachable by direct URL, so review happens on staging with real chrome. French state is `machine-translated` or `reviewed`.

8. **Screenshots are scripted and committed.** One `agent-browser` step script per article (persona to log in as, URL, clicks, shot names) plus one shared runner that logs in with the seeded persona and executes a script. Output is committed under `public/help/<slug>/`. English UI only, desktop width only, no drawn annotations; the caption carries the meaning. A wrong shot is replaced by hand; the script stays as the record of intent. When a feature changes, its article's script is re-run. No CI screenshot job yet.

9. **A ledger page at `/help-status`**, super-tier only, English-only and non-localized like the Mail status page. It lists every manifest entry with its status, French state, screenshot count against references, and mapped route, then every page route with no article. This is the "what have I lost track of" view.

10. **The top-bar "?" is contextual.** It opens the article mapped to the current route, or the index when none is mapped.

11. **Definition of done is a soft rule.** A pull request template gains a checkbox: help article added or not applicable. `CLAUDE.md` points agents at the convention. A repo skill, `help-article`, written with the `writing-for-agents` skill, does the whole job in one invocation: manifest entry, English article, French article, screenshot script, run, PR checkbox. No CI gate on routes without articles; the ledger page shows them instead.

12. **No search in this pass.** Not help-only, not global. Article bodies stay plain Markdown so a later global search can index them. Global search gets its own grilling session.

13. **Backfill in five batches**, each one ticket and one PR: foundation (routes, renderer, manifest, index, contextual link, ledger page, skill, PR template, Getting started); volunteer basics; Groups and Scheduling for members; officer tasks; emailing and support operations.

## Considered alternatives

- **A static documentation site** (VitePress or similar) on a subdomain. Rejected: a second toolchain and deploy, no login, no role badge from the app's own vocabulary, and the bilingual routing would be rebuilt from scratch.
- **A database-backed article editor** in the app. Rejected: it breaks the link between the feature PR and its article, and builds an editor nobody asked for. Revisit only if the UI/UX committee wants to edit copy without a PR.
- **Storing articles in `lang/{en,fr}/*.php`.** Rejected: the message store is for short strings, and a Markdown article with images does not fit the `laravel-vue-i18n` compile step.
- **YAML front matter per file instead of one manifest.** Rejected: the ledger would be scattered across a hundred files. One manifest shows the whole state in one place and fixes the order.
- **Role-gated articles** (hidden and 404 for Members without the role). Rejected: Members should read ahead of a promotion. The badge replaces the gate.
- **English first, French later, with an "English only" note.** Rejected: contradicts ADR-0004's "an unverified Canadian-French string beats falling back to English". Machine translation in the same PR is cheap for an agent.
- **Playwright for screenshots.** Rejected for now: a new package for a job `agent-browser` already does, with hand replacement covering the cases where it fights back. Revisit if screenshot drift or Radix overlays become a recurring cost.
- **Help-only search now, client-side.** Deferred: the user chose to scope search out entirely and grill global search on its own.
- **A CI gate that fails when a new route has no article.** Rejected: not every route deserves an article; the ledger page surfaces the gap without blocking merges.
- **French screenshots as well as English.** Deferred until French articles are reviewed rather than machine-translated.

## Consequences

- The repo grows by one PNG set per article. Acceptable for now; revisit format or storage if the bundle becomes a deploy cost.
- Screenshots drift when the UI changes. The manifest maps articles to routes, so the ledger page can flag an article whose page changed after its shots were taken. Manual re-runs until that hurts.
- ADR-0004 gains a note: chrome includes help articles, stored outside `lang/`.
- The `help` entry leaves `$stubRoutes`; `nav.help` stops being a placeholder.
- A `.github/pull_request_template.md` is created (none exists today).
- `CONTEXT.md` gains the terms Help article, Help section, Required role, Article status, and Help ledger.
- Global search, contextual tooltips, French screenshots, CI screenshot diffs, and committee copy editing are all out of scope and each needs its own decision before it starts.
