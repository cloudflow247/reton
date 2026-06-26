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

App is served on http://localhost:8080.

## Testing

```bash
php artisan test
```

See `docs/` for the build roadmap and milestone specs.
