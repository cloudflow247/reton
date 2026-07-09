# Reton

Trust-based African fintech platform — a Laravel + Inertia + React monolith.

## Stack

- **Laravel 12** (PHP 8.4) — domain logic, HTTP, queue, scheduler
- **Inertia + React 18 + TypeScript** — server-driven SPA, built with Vite
- **PostgreSQL** + **Redis** — database, cache, queue, sessions
- **Tailwind CSS 4** — styling

## Layout

```
.                  Laravel application root (app/, config/, database/, routes/, ...)
├── resources/     Inertia React pages, components, and Blade entrypoint
├── public/        Web root + compiled Vite assets (public/build)
├── infra/         Docker Compose stack and container definitions
└── docs/          Build roadmap, milestone specs, and implementation plans
```

## Getting started

### Local demo (fastest)

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
# Enable the Windows/SQLite block in .env (or use your existing .env with RETON_DEMO_MODE=true)
composer demo          # migrate:fresh + Ada/Bola demo accounts + sample listings
composer dev           # app, queue, reverb, vite
```

Open http://127.0.0.1:8000/login — tap **Ada Obi** or **Bola Ade**, or sign in manually:

| | |
|--|--|
| Password | `demo1234` |
| Transaction PIN | `1234` |

### Standard setup

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run build      # or: npm run dev   for the Vite dev server
php artisan serve
```

### With Docker

```bash
docker compose -f infra/docker-compose.yml up --build
```

App is served on http://localhost:8080. Horizon dashboard: http://localhost:8080/horizon (sign in first). Reverb WebSockets: `ws://localhost:8081`.

### Native Windows PHP (no Docker)

Horizon cannot run on Windows PHP (`pcntl` / `posix` are unavailable). Reton uses `queue:work` instead and the **database** queue driver so you do not need Redis locally.

```bash
# One-time: use SQLite + database queue in .env (see .env.example Windows block)
cp .env.example .env   # then enable the Windows block
php artisan migrate

composer dev
```

This starts the app, a database queue worker, Reverb WebSockets (`ws://localhost:8081`), log tailing, and Vite.

Manual terminals:

```bash
php artisan serve
php artisan queue:work database --tries=3
php artisan reverb:start
npm run dev
```

Set `BROADCAST_CONNECTION=reverb` and the `REVERB_*` / `VITE_REVERB_*` variables. Trust-protection updates on Dashboard and Protection pages reload live over Reverb.

### Linux / macOS (without Docker)

Requires Redis for Horizon.

```bash
composer dev
```

Or run `php artisan horizon` instead of `queue:work`.

Set `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`, and the `REVERB_*` / `VITE_REVERB_*` variables from `.env.example`.

## Testing

```bash
php artisan test
```

See `CHANGELOG.md` for release notes and `docs/release-2026-06-30/TEAM_BRIEF.md` for screenshots, topology, and a team demo script.
