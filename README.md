# DMV-ROM Volunteer Management System

A web application for managing volunteers, schedules, documents, and committee activities at the Department of Museum Volunteers, Royal Ontario Museum.

## About

The DMV-ROM has approximately 500 active volunteers contributing to museum tours, education programs, and special events. This application supports the day-to-day operations of the volunteer organization: scheduling, member management, committee coordination, document sharing, and communications.

This repository contains a rebuild of the original application (in production at https://www.dmv-rom.ca), modernizing the technology stack while preserving functionality. The rebuild aims to make the codebase maintainable by future volunteer contributors using AI-assisted development.

## Status

In active development. The legacy production application remains live during the rebuild.

## Tech stack

- **Backend:** PHP 8.4, Laravel 12
- **Frontend:** Inertia.js, Vue 3, Tailwind CSS, shadcn-vue components
- **Database:** MariaDB 10.6
- **Build:** Vite
- **Local dev:** Docker via Laravel Sail
- **Hosting:** Stormweb shared hosting (Vancouver, BC), SSH deploy

## Quick start

Prerequisites: Docker, Git, Node.js 20+, pnpm (`brew install pnpm` or via Corepack).

```bash
git clone https://github.com/roytanaka/dmv-rom-v2.git
cd dmv-rom-v2
cp .env.example .env
pnpm install
pnpm sail:up
pnpm sail vendor/bin/sail composer install
pnpm sail vendor/bin/sail php artisan key:generate
pnpm sail vendor/bin/sail php artisan migrate
pnpm dev
```

The app is available at http://localhost:80 (or `APP_PORT` if customized). Vite dev server runs on http://localhost:5173.

## Project structure

Standard Laravel layout. Key locations:

- `app/Http/Controllers/` — thin controllers
- `app/Services/` — business logic
- `app/Models/` — Eloquent models
- `resources/js/Pages/` — Inertia/Vue pages
- `resources/js/Components/` — reusable Vue components
- `resources/js/Components/ui/` — shadcn-vue components (owned source, edit freely)
- `database/migrations/` — schema migrations for the new app
- `database/migrations/legacy/` — one-time scripts that read from the legacy database during migration
- `docs/` — architecture, conventions, ADRs

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for process and expectations. This project is built primarily through AI-assisted development (Claude Code); contributors using Claude Code should read [CLAUDE.md](CLAUDE.md) to understand the project's conventions for AI agents.

## License

[MIT](LICENSE)
