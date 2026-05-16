---
status: accepted
date: 2026-05-15
---

# Inertia.js instead of a separate SPA + REST API

## Context

The rebuild has enough interactive UI — scheduling, document management, member CRUD, committee dashboards — that Blade-only templates would be limiting. Two patterns Laravel teams commonly reach for at that point:

1. **Vue SPA + REST API.** Two codebases, two deploys (or one deploy serving both), API design, API versioning, auth tokens (JWT or session-via-cookie + CSRF), CORS configuration, separate frontend routing, separate test stacks. Standard but expensive.
2. **Inertia.js.** Server-side controllers return Inertia responses; Vue page components receive controller-supplied props directly. The protocol between server and client is implementation detail, not a public surface. One repo, one auth model (Laravel session), one routing layer (`routes/web.php`), no CORS, no API versioning, no token rotation, no separate frontend deploy.

For a single-team, single-app project — no mobile client, no third-party API consumers, ~500 volunteer users — option 1 buys complexity that has no concrete payer.

## Decision

**Use Inertia.js for all internal app pages.** Vue 3 with the Composition API and `<script setup>` lives in `resources/js/Pages/` (one page component per route). Controllers return `Inertia::render('Foo/Bar', [...])`. Auth is standard Laravel session auth; CSRF is handled by Laravel + the Inertia client. No separate REST API for the internal app.

**JSON endpoints are allowed only for specific, named exceptions** where Inertia is structurally wrong:

- **File download streaming** (controller streams file bytes, not a page)
- **Webhook receivers** (third-party services POSTing into the app)
- **Future health-check / status endpoints** if needed by external monitoring

Anything outside that list goes through Inertia. If a new use case suggests "we should expose this as JSON," the right first question is "can this be an Inertia page or a partial-reload?" — not "let's design an API."

## Considered alternatives

- **Vue SPA + Laravel REST API.** Standard, well-trodden. Rejected because the costs (two deploy targets, API versioning, token auth, CORS) all pay for capabilities we don't need (multiple client types, third-party API consumers). The pattern earns its weight when you have multiple front-ends. We have one.
- **Blade-only + Alpine.js for sprinkles.** Lightest weight option. Rejected because the interactive surfaces (scheduling grid, committee dashboards, multi-step document upload) exceed what Alpine is comfortable doing, and we'd end up re-inventing component patterns Vue already has.
- **Livewire.** Server-rendered reactive components, Laravel-native. Rejected because component logic lives in PHP, which couples component portability to Laravel and locks future contributors out of the larger Vue/React ecosystem. Vue knowledge is more portable than Livewire knowledge.
- **React + Inertia.** Inertia supports React too. Rejected because Vue's single-file `.vue` components are friendlier to AI-assisted vibe coding (template + script + style co-located, less indirection) and Vue has lower cognitive overhead for a non-expert frontend developer.

## Consequences

### What we get

- One codebase, one auth model, one routing layer. Adding a feature means a controller + a page component, not a controller + an API endpoint + a frontend route + a frontend fetch call + state synchronization.
- Standard Laravel session auth covers the entire app. No tokens, no expiry rotation, no refresh logic.
- No CORS. No API versioning. No "v1 deprecated, v2 coming" footguns.
- Inertia's partial reloads handle most "refresh part of the page" needs without inventing client-side data fetching.

### What we give up

- **No public API surface.** External integrations (a partner system that wants to query DMV data, a future mobile app, an internal Microsoft Teams notifier) have no first-class entry point. They'd need either an Inertia-aware client (rare) or a parallel JSON API added later.
- **Inertia's prop shape is implicit coupling between controller and component.** No formal contract (no OpenAPI). Refactoring a controller's prop shape silently breaks the page. Mitigation: feature tests that hit the route and assert the prop shape, plus TypeScript on the frontend with declared prop types.
- **Page-level navigation is server-driven.** Inertia visits go through the Laravel router, not a client-side router. This is good (one routing system, real URLs) but means certain SPA-style flourishes (offline navigation, optimistic route transitions across many pages) take more effort.

### Exit triggers

Two conditions move us off the Inertia-only model. Like [ADR-0002](0002-stay-on-stormweb-shared-hosting.md), naming them avoids panic-decisions later.

1. **We need a real API for an external consumer.** A mobile app, a partner integration, or a third-party tool that needs read/write access. **Planned response:** add a `/api/*` route namespace with token auth (Laravel Sanctum) **alongside** Inertia for the internal app. Inertia stays for the internal UI. We don't rewrite the app — we add a parallel surface for the new consumer.
2. **A specific feature can't be expressed cleanly through Inertia.** Hypothetical examples: a Slack-style real-time chat, an extensive offline-first PWA. **Planned response:** that feature gets its own JSON endpoints (added to the named-exceptions list above), or that feature uses a small embedded SPA mounted at one route. The rest of the app stays Inertia.

Neither trigger is on the horizon. Both responses are additive, not replacements.

## References

- `docs/architecture.md § Stack and rationale`, `§ Why not other choices`, `§ Structural rules`
- `docs/conventions.md § Frontend (Vue + Inertia)`
