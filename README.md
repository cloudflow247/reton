# Reton

**Payments you can take back.**

Reton is a trust-first digital banking platform for Africa. We help people send, receive, and recover money with clarity — not just speed.

Our flagship idea is **Callback Protection**: hold a payment until the sender is ready to release it, with a full timeline at every step.

Built by **RETON PTE LTD** (Founder & CEO: **Gabriel Rotimi Mogaji**) for the [ALATPay Buildathon](https://alatpay.ng/), on licensed rails (ALAT by Wema).

> This repository is proprietary. It is public so buildathon judges can review the code. See [LICENSE](LICENSE).

---

## Why Reton

Most wallets optimise for “send and forget.” We optimise for the moment something goes wrong:

- **Callback Protection** — protected transfers stay pending until release or recall
- **Wrong-transfer recovery** — report a mistake, hold funds when eligible, track the case
- **Fraud signals** — rule-based scoring with admin visibility
- **KYC tiers** — CBN-aligned limits; BVN verification for funding unlocks
- **Double-entry wallet** — every movement is ledger-backed

We are not cloning Opay, Kuda, or Moniepoint. The product should feel like a serious fintech — calm, clear, and careful with real money.

---

## Stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Inertia.js v2, React 19, TypeScript, Tailwind CSS v4 |
| Data | PostgreSQL, Redis |
| Queues | Laravel Horizon |
| Auth | Laravel Sanctum |
| Realtime | Laravel Reverb |
| Deploy | Laravel Cloud |

Domain logic lives under `app/Domain/*`. Controllers stay thin: validate, authorize, delegate, respond.

---

## Repository layout

```
app/                 Domain services, HTTP, providers
resources/js/        Inertia React pages and UI
routes/              Web and API routes
infra/               Docker Compose and container definitions
docs/                Deploy guide and release notes
tests/               Pest feature and unit tests
```

---

## Getting started

### Local demo (fastest)

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
composer demo          # migrate + seed sandbox accounts
composer dev           # app, queue, Reverb, Vite
```

Open [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login).

Sandbox credentials are defined only in your local `.env` (see `.env.example` for `RETON_DEMO_*`). **Never enable demo mode on a public production site.**

### Standard setup

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run build          # or: npm run dev
php artisan serve
```

### Docker

```bash
docker compose -f infra/docker-compose.yml up --build
```

App: [http://localhost:8080](http://localhost:8080)

### Windows (native PHP, no Docker)

Horizon needs `pcntl` / `posix`, so Windows uses `queue:work` with the database queue driver:

```bash
cp .env.example .env   # enable the Windows block if needed
php artisan migrate
composer dev
```

### Linux / macOS (without Docker)

Prefer Redis + Horizon:

```bash
composer dev
# or: php artisan horizon
```

Use `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`, and the Reverb variables from `.env.example`.

---

## Configuration

Integration credentials and business rules can be managed from the **admin dashboard** (Integrations, Platform, Site). Environment variables remain fallbacks until values are saved in admin.

| Concern | Notes |
|---------|--------|
| Payment rails | Live HTTP driver for production; fake drivers for local/demo |
| KYC / BVN | Configured via admin Integrations and `KYC_BVN_PROVIDER` |
| Demo mode | Keep `RETON_DEMO_MODE=false` in production |
| SMS / alerts | Reton’s own messaging stack for auth and alerts |

See `.env.example` and [docs/DEPLOY.md](docs/DEPLOY.md). **Never commit real API keys, webhook secrets, or `.env` files.**

---

## Testing

```bash
php artisan test
```

We focus coverage on money paths: happy path, auth denial, validation, idempotency, webhook replay, and state transitions.

---

## Documentation

| Doc | Purpose |
|-----|---------|
| [LICENSE](LICENSE) | Proprietary copyright notice |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Laravel Cloud deploy guide |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [roadmap.md](roadmap.md) | Product & compliance roadmap |

---

## Security posture

- PIN confirmation on money movement
- Rate limits on auth and payment endpoints
- Idempotency keys on payment APIs
- Webhook signature validation
- Encrypted sensitive fields at rest
- Audit logs for financial state changes

Treat every environment that can move real money as production.

---

## Company & contact

| | |
|--|--|
| Legal entity | **RETON PTE LTD** |
| Founder & CEO | **Gabriel Rotimi Mogaji** |
| Support | support@retonpay.com |
| Office | 7, Greenland Estate, Ikorodu, Lagos State, Nigeria |

© 2026 RETON PTE LTD. All rights reserved.
