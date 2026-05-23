# Architecture

## Stack and rationale

- **PHP 8.4** — on PHP's active bugfix support window (until Dec 2026). Policy: stay on an actively-supported minor version, ideally one behind the latest once that version has matured (~12 months in the wild). Revisit before late 2026 when 8.4 approaches the end of active support.
- **Laravel 12** — strong conventions reduce drift in AI-assisted development. Excellent documentation. Claude Code is fluent in it.
- **Inertia.js** — gives us a Vue frontend without the overhead of a separate REST API. Controllers return Inertia responses; Vue components receive props directly. No API versioning, no token auth, no CORS.
- **Vue 3 (Composition API)** — modern, well-documented, AI-friendly.
- **Tailwind CSS** — utility-first, pairs well with Vue and Inertia. Claude Code handles it cleanly.
- **shadcn-vue** — copy-paste component library built on Tailwind and Reka UI. Components are added via CLI and live in our source tree (`resources/js/Components/ui/`). We own and edit them freely. Provides accessible, well-designed primitives (buttons, dialogs, dropdowns, forms, tables) without the lock-in of a traditional component library.
- **MariaDB 10.6** — matches production. We do not use MySQL-only or Postgres-only features.
- **Vite** — Laravel's default asset bundler.
- **Docker (local dev only)** — replicates the production PHP/MariaDB versions via Laravel Sail. Production uses the native shared-host stack, not Docker.

## Why not other choices

- **Not Blade-only:** the app has enough interactive UI (scheduling, document management, member CRUD) that Vue earns its keep.
- **Not a separate Vue SPA + REST API:** Inertia eliminates a whole category of complexity (API design, auth tokens, versioning) that adds no value for a single-team app.
- **Not Livewire:** it's a fine choice but locks us into PHP for component logic. Vue is more portable knowledge for future contributors.
- **Not React:** Vue's single-file components are friendlier to vibe coding; Claude Code handles both well but Vue's structure is more locally readable.
- **Not a VPS or container hosting:** the existing shared hosting works for the load (~500 users, tens concurrent), costs little, and the team is happy with the host. Don't fix what isn't broken.
- **Not a traditional component library (PrimeVue, Vuetify, Element Plus):** they bundle large amounts of CSS, fight Tailwind, and lock us into their design system. shadcn-vue gives us components we own, styled with the same Tailwind we use everywhere else.

## Structural rules

- **Thin controllers.** Controllers handle HTTP — request validation (via form requests), calling a service or action, returning a response. Business logic lives elsewhere.
- **Services or actions for business logic.** Multi-step operations (creating a member, processing a document upload, sending committee notifications) live in `app/Services/` or `app/Actions/`. Pick one and be consistent.
- **Eloquent models for data access.** No raw SQL in application code. Migrations and explicit legacy-import scripts are the only places raw SQL is acceptable.
- **Form requests for validation.** Every controller action that accepts user input uses a dedicated form request class.
- **Policies for authorization.** Every protected resource has a policy. Controllers call `$this->authorize(...)` or use middleware.
- **Inertia for views.** JSON endpoints only for the named exceptions in [ADR-0005](adr/0005-inertia-instead-of-spa-plus-api.md): file download streaming, webhook receivers, and future health-check endpoints.
- **Vue pages map to routes.** Each route renders one page component in `resources/js/Pages/`. Components in `resources/js/Components/` are reusable building blocks.
- **No DB queries in Vue components.** Data comes from Inertia props. If a page needs more data, it goes in the controller.
- **No business logic in Blade or Vue templates.** Display logic only. Computed values come from the controller, a model accessor, or a Vue computed property.

## What we don't do

- **No Repository pattern.** Eloquent models are the data layer.
- **No service container abstractions for things that aren't services.** Don't bind interfaces unless we have multiple implementations.
- **No custom packages or in-house mini-frameworks.** Use Laravel as it ships.
- **No premature abstraction.** A pattern earns extraction after appearing three times.
- **No Pinia/Vuex unless and until** we have a clear cross-page state need that Inertia shared props can't handle.
- **No custom build steps beyond Vite defaults.** No Webpack configs, no PostCSS plugins beyond what Tailwind needs.
- **No queue workers, websockets, or background services in v1.** Email sends synchronously via Laravel's mail. We add complexity only when we need it.
- **No third-party SaaS integrations in v1** (no Stripe, no Mailchimp, no Algolia). We add them only when a feature requires them.
- **No hand-rolled UI primitives when shadcn-vue has one.** Don't build a custom dialog, dropdown, or combobox from scratch. Add the shadcn-vue version and customize it.
- **No additional UI component libraries.** shadcn-vue is the one. Don't mix in Headless UI, Radix, PrimeVue, etc.

## Project layout

```
app/
  Http/
    Controllers/
    Requests/
    Middleware/
  Models/
  Services/             # business logic
  Policies/
database/
  migrations/           # new schema
  migrations/legacy/    # one-time scripts that read the legacy DB during migration
  seeders/
  factories/
resources/
  js/
    Pages/              # Inertia pages, one per route
    Components/         # reusable Vue components
        ui/             # shadcn-vue components (owned source)
    Layouts/            # app shell layouts
    composables/        # shared Vue composition functions
  css/
  views/                # minimal Blade (auth pages, error pages)
lang/
  en/
  fr/
routes/
  web.php
  auth.php
storage/
  app/
    documents/          # uploaded documents (NOT in webroot)
docs/
tests/
  Feature/
  Unit/
```

## Document storage architecture

Documents do not live in the webroot. They are stored in `storage/app/documents/` with opaque filenames (UUID-based). The database holds the human-readable original filename. Downloads are served by a controller that:

1. Authenticates the user.
2. Authorizes via a policy (visibility: public / members / committee-specific).
3. Logs the access.
4. Streams the file with `Content-Disposition: attachment; filename="<original_filename>"`.

The user sees the friendly filename in their downloads folder. The disk filename is never exposed.

See `docs/conventions.md` § Documents for implementation details.

## Bilingual (i18n) architecture

- All user-facing strings come from `lang/en/*.php` and `lang/fr/*.php` files. No hardcoded strings in templates or components.
- The user's locale preference is stored on the `Volunteer` record (`locale` column, default `'en'`) and applied via middleware.
- URLs are bilingual too: English is canonical at the root, French lives under `/fr/` with translated path segments (`/fr/benevoles/123`). Route segments are translated via `lang/en/routes.php` + `lang/fr/routes.php` and resolved by the `mcamara/laravel-localization` package. See [ADR-0008](adr/0008-bilingual-url-routing.md) and `docs/conventions.md` § Internationalization → URL routing.
- Document titles and descriptions can be stored bilingually (per-document fields for `title_en`, `title_fr`, etc.). Decide per-feature whether content is translatable or single-language.
- Dates and numbers use locale-aware formatting (`Carbon` for PHP, `Intl.DateTimeFormat` for JS).

## Environments

- **Local:** Laravel Sail (Docker Compose) with PHP 8.4 + MariaDB 10.6. Matches production versions.
- **Staging:** A separate subdomain on the same Stormweb account, with its own database. Stormweb's PHP version is per-domain, so staging can run PHP 8.4 independent of the live legacy domain until cutover.
- **Production:** Cuts over to the rebuild's database at migration time. Legacy DB retained as read-only backup. The live domain's PHP version updates as part of cutover.

## Deployment

Automated via GitHub Actions. See [ADR-0007](adr/0007-dev-staging-deploy-strategy.md) for the full strategy. In brief:

- Two long-lived branches: `staging` (auto-deploys to staging) and `main` (auto-deploys to production with an approval gate).
- CI runs lint + tests + build on every PR; branch protection requires green. "Tests" here means PHP (Pest); JS tests are intentionally deferred to a follow-on PRD and their absence is not an oversight.
- Vite builds in CI; artifacts (`public/build/`) are rsync'd to Stormweb. Node is not installed on the production server.
- Composer install + `php artisan migrate` run on the server via SSH from the deploy workflow.
- Production deploys take a `mysqldump` of the DB before running migrations (fresh recovery point).
