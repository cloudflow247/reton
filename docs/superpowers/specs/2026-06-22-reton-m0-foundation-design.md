> **Proprietary** - Copyright 2026 RETON PTE LTD. Founder & CEO: Gabriel Rotimi Mogaji · Co-Founder: Aina Christana Olajumoke. See [LICENSE](../../../LICENSE).
>
> **Historical notes.** Early planning. For current setup, see [README](../../../README.md), [roadmap](../../../roadmap.md), and [deploy guide](../../DEPLOY.md).

# Reton M0 - Foundation (Design Spec)

**Date:** 2026-06-22
**Status:** Approved (shape) - pending user review
**Parent:** `2026-06-22-reton-build-roadmap.md` - Milestone M0
**Scope:** Project skeleton only. **No business logic.** A runnable, CI-green,
contract-first foundation that every later milestone inherits.

---

## 1. Goal & non-goals

### Goal
Stand up a production-shaped skeleton so that M1 (the ledger/money substrate) can be
built without re-litigating structure. When M0 is done, a new engineer can clone, run
`docker compose up`, hit a health endpoint, register/login a user, see CI run lint +
static analysis + tests, and read the committed ERD and OpenAPI contract.

### Non-goals (explicitly deferred)
- No ledger, wallet, transfers, or any money logic (that is M1+).
- No Golang, MinIO, Meilisearch, Reverb, Prometheus/Grafana (deferred per roadmap section 2).
- No real KYC/AlatPay integration - only the module folders that will hold them later.
- Auth scaffold ships **register / login / logout / me** only. PIN, 2FA, device
  fingerprinting, email/phone verification are stubbed interfaces, not implementations.

---

## 2. Decisions locked for M0

| Decision | Choice | Consequence |
|---|---|---|
| Module structure | **Hand-rolled `app/Domains/{Domain}`** with Application / Domain / Infrastructure layers, PSR-4. | No package lock-in; explicit boundaries; enforced by an architecture test. |
| Repo + CI | **GitHub + GitHub Actions** | One workflow: lint → static analysis → tests, on every push and PR. |
| API contracts | **Spec-first hand-written OpenAPI YAML** under `backend/openapi/` | Contract is authored before code; frontend generates a typed client from it; a CI step asserts implemented routes match the spec. |
| DB | **PostgreSQL** (Docker) | Financial consistency first; UUID v7 primary keys; minor-units integers for money (no floats ever). |
| Cache/queue | **Redis** (Docker) | Sanctum token storage is DB; Redis backs cache + queue from the start. |
| Auth | **Laravel Sanctum** (token-based for API/SPA) | Stateless API tokens for the React clients. |
| PHP/Laravel | **PHP 8.4, Laravel 12** | Per CLAUDE.md. |

---

## 3. Repository layout

Single monorepo.

```
reton/
├── backend/                  # Laravel 12 application
│   ├── app/
│   │   ├── Domains/          # ← DDD bounded contexts live here
│   │   │   └── Authentication/
│   │   │       ├── Application/      # use-cases / commands / handlers / DTOs
│   │   │       ├── Domain/           # entities, value objects, domain events, contracts
│   │   │       └── Infrastructure/   # Eloquent models, repositories, external adapters
│   │   ├── Http/             # thin controllers, form requests, API resources, middleware
│   │   ├── Providers/
│   │   └── Support/          # cross-cutting: ApiResponse envelope, Idempotency, etc.
│   ├── openapi/
│   │   └── v1/openapi.yaml   # spec-first contract
│   ├── database/migrations/
│   ├── routes/api.php        # /api/v1 group
│   ├── tests/                # Pest: Unit / Feature / Architecture
│   ├── phpstan.neon          # Larastan, level 8
│   ├── pint.json             # code style
│   └── composer.json
├── frontend/                 # React + TypeScript (skeleton only in M0)
│   ├── src/
│   │   ├── api/              # generated typed client from openapi.yaml
│   │   ├── app/              # router, providers (TanStack Query, Zustand)
│   │   └── features/auth/    # login/register wired to the auth scaffold
│   ├── package.json
│   └── tsconfig.json
├── infra/
│   ├── docker/
│   │   ├── nginx/
│   │   └── php/Dockerfile
│   └── docker-compose.yml
├── docs/
│   ├── superpowers/specs/    # this spec + roadmap
│   └── erd/reton-erd.md      # Mermaid ERD (M0 scope: identity tables only)
├── .github/workflows/ci.yml
└── README.md
```

### Domain module convention (the template every future domain copies)

`app/Domains/{Domain}/` always has three layers:

- **Domain/** - pure PHP. Entities, value objects (e.g. `Money`, `Email`), domain
  events, and **interfaces** (e.g. `UserRepository`). No framework, no Eloquent.
- **Application/** - use-cases as single-purpose handlers (e.g. `RegisterUserHandler`),
  command/DTO objects, orchestration. Depends on Domain interfaces, not infrastructure.
- **Infrastructure/** - Eloquent models, repository implementations, external API
  adapters, queue jobs. Implements Domain interfaces.

**Dependency rule (enforced):** `Domain` depends on nothing; `Application` depends only
on `Domain`; `Http` and `Infrastructure` depend inward. A Pest **architecture test**
fails CI if `Domain/` imports Eloquent/Illuminate or if `Application/` imports
`Infrastructure/`.

---

## 4. Docker Compose (M0 services only)

```
services:
  app        # php-fpm 8.4, Laravel
  nginx      # serves app, :8080
  postgres   # 16, named volume, healthcheck
  redis      # 7, cache + queue
  queue      # php artisan queue:work (same image as app)
```

- `.env.example` committed; secrets never committed.
- Postgres and Redis use healthchecks; `app` waits on them.
- One command to a working stack: `docker compose up` → `GET /api/v1/health` returns
  `200` with the standard success envelope.

Kubernetes is **not** set up in M0, but the image is built so it is migration-ready
(12-factor config via env, stateless app container, no host-path coupling).

---

## 5. API conventions (set once, used everywhere)

All `/api/v1` responses use a single envelope, implemented in `app/Support/ApiResponse`:

```jsonc
// success
{ "data": { ... }, "meta": { ... } }
// error
{ "error": { "code": "string", "message": "human readable" } }
// validation error (422)
{ "error": { "code": "validation_error", "message": "...", "fields": { "email": ["..."] } } }
// paginated
{ "data": [ ... ], "meta": { "page": 1, "per_page": 20, "total": 0 } }
```

- **Versioning:** `/api/v1` route group from day one.
- **API Resources** for every serialized model (no raw model JSON).
- **Idempotency middleware** scaffold: reads `Idempotency-Key` header, stores
  request/response keyed by it in Redis. M0 ships the middleware + storage; money
  endpoints adopt it in M2. (Built early so the convention exists before it's needed.)
- **Rate limiting** via Laravel's limiter on auth routes.
- **Correlation ID** middleware: every request gets/propagates an `X-Request-Id`,
  attached to all structured logs.

---

## 6. Authentication scaffold (M0 surface area)

Domain: `app/Domains/Authentication/` + a minimal `Users` concern.

Endpoints (all in `openapi.yaml` first):

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/v1/auth/register` | Create user, return Sanctum token |
| `POST` | `/api/v1/auth/login` | Authenticate, return token |
| `POST` | `/api/v1/auth/logout` | Revoke current token |
| `GET`  | `/api/v1/auth/me` | Current user (auth required) |
| `GET`  | `/api/v1/health` | Liveness (no auth) |

- Password hashing via Laravel (bcrypt/argon).
- `users` table: `id` (uuid v7), `name`, `email` (unique, citext), `phone`,
  `password`, `email_verified_at` (nullable), timestamps. KYC tier and limits are
  **not** added here - they arrive in M1's KYC work. Keep the table minimal.
- Stubbed-but-typed interfaces for later: `DeviceFingerprintService`,
  `TwoFactorService`, `PhoneVerificationService` - defined in `Domain/`, with a
  no-op/throw implementation, so M3 security work has seams to fill.

---

## 7. ERD (M0 scope)

`docs/erd/reton-erd.md` - Mermaid. M0 commits **only** the identity tables (`users`,
`personal_access_tokens`, `audit_logs`). The full financial ERD (ledger_accounts,
ledger_entries, wallets, …) is designed in M1 where the decisions actually matter.
`audit_logs` is created in M0 (append-only) because the convention "everything
money-touching is audit-logged" needs the table to exist before M1.

---

## 8. CI pipeline (`.github/workflows/ci.yml`)

Runs on every push and PR. Jobs:

1. **Lint** - Laravel Pint (`--test`), Prettier/ESLint for frontend.
2. **Static analysis** - Larastan/PHPStan **level 8**, TypeScript `tsc --noEmit`.
3. **Test** - Pest (Unit + Feature + **Architecture** tests) against a Postgres
   service container; frontend Vitest.
4. **Contract check** - assert every registered `/api/v1` route exists in
   `openapi.yaml` and vice versa (a small artisan command or spectral + a route-diff
   step). Fails the build on drift.

Coverage is reported but **not gated** in M0 (the 90% gate is risk-weighted and lands
in M6 for money-path domains).

---

## 9. Exit criteria (M0 is done when ALL are true)

1. `docker compose up` yields a healthy stack; `GET /api/v1/health` → `200` success
   envelope.
2. A user can `register`, `login`, call `me`, and `logout` against the API.
3. The React skeleton can register/login a user using a **typed client generated from
   `openapi.yaml`** (proves contract-first works end to end).
4. CI is green: lint, Larastan level 8, Pest (incl. architecture test), contract check.
5. The architecture test **fails** if someone imports Eloquent into a `Domain/` layer
   (i.e. the guardrail is proven to work, not just present).
6. ERD (identity tables) and `openapi.yaml` are committed and reviewed.
7. `README.md` documents: clone → run → test in under 10 minutes for a new engineer.

---

## 10. Task breakdown (suggested implementation order)

1. Repo skeleton, `.gitignore`, `README` stub, branch protection.
2. `infra/docker-compose.yml` + PHP/nginx Dockerfiles; Postgres + Redis healthchecks.
3. `composer create-project` Laravel 12; Pint, Larastan (level 8), Pest installed.
4. `app/Support/ApiResponse` envelope + exception handler mapping to the error shape.
5. Correlation-ID + rate-limit + idempotency middleware scaffolds.
6. `app/Domains/Authentication` three-layer skeleton + `Users` minimal table/migration.
7. Sanctum install; register/login/logout/me endpoints + form requests + resources.
8. `audit_logs` table (append-only) + a tiny `AuditLogger` writing on auth events.
9. Pest: Feature tests for auth happy/sad paths + the **architecture** test.
10. `openapi/v1/openapi.yaml` for health + auth endpoints; contract-check CI step.
11. Frontend skeleton: Vite + TS + router + TanStack Query + Zustand; generate typed
    client from the YAML; wire login/register screens.
12. `.github/workflows/ci.yml` (lint, static, test, contract).
13. ERD (identity tables) in `docs/erd/`.
14. README: setup + run + test instructions; verify the 10-minute claim.

---

## 11. Open questions before starting M0

- **UUID strategy:** UUID v7 (time-ordered, index-friendly) vs ULID. Recommendation:
  **UUID v7** - native, sortable, no extra dependency.
- **`citext` for email:** enable the Postgres `citext` extension for case-insensitive
  unique emails, or normalize in app code? Recommendation: **`citext` extension** - DB
  enforces it, can't be bypassed.
- **Frontend in M0:** full skeleton now, or just enough to prove the generated client?
  Recommendation: **just enough** - auth screens + generated client. The full customer
  app is M5; building UI now risks rework against later design-system decisions.
