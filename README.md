# Reton

**Payments you can take back.**

Reton is Africa’s trust-first digital banking platform. We help people send, receive, and recover money with clarity - not just speed.

Flagship product: **Callback Protection**. Hold a payment until the sender is ready to release it, with a full timeline at every step. If something goes wrong, the money still has a path back.

Built by **RETON PTE LTD** (Founder & CEO: **Gabriel Rotimi Mogaji** · Co-Founder: **Aina Christana Olajumoke**) for the [ALATPay Buildathon](https://alatpay.ng/), settling on licensed rails via **ALAT by Wema**.

Live product: [retonpay.com](https://retonpay.com)

> This repository is proprietary. It is public so buildathon judges can review production-minded code. See [LICENSE](LICENSE).

---

## Judge demo path (90 seconds)

1. Open [retonpay.com](https://retonpay.com) (or local demo via `composer demo` below).
2. Fund or use a sandbox wallet, then send a **Protected** transfer.
3. On **Protection**, confirm the recipient’s full name and the final warning, then release - or raise a callback.
4. Follow the **timeline** on the case.
5. Open **Activity → receipt** and copy Reference / Transaction ID.

Pitch materials stay local (not published in this repository). Engineering deep-dive: [docs/ENGINEERING.md](docs/ENGINEERING.md)

---

## Why Reton

Most wallets optimise for “send and forget.” We optimise for the moment something goes wrong:

| Capability | What judges should see |
|------------|------------------------|
| **Callback Protection** | Protected transfers stay pending until release or recall - with recipient confirmation and a hard final warning before release |
| **Wrong-transfer recovery** | Eligible mistakes can be held, tracked, and resolved with a visible case timeline |
| **Fraud signals** | Rule-based scoring on risky moves; alerts surface in admin |
| **KYC tiers** | CBN-aligned limits; BVN verification unlocks funding |
| **Double-entry wallet** | Every balance change is ledger-backed - never a silent mutate |

We are not cloning Opay, Kuda, or Moniepoint. Reton is built to feel like a funded fintech: calm, clear, and careful with real money.

---

## Trust engineering

Architecture choices are judged by how they protect money - not by how many patterns we name.

| Principle | How Reton applies it |
|-----------|----------------------|
| **Domain-driven design** | Payment, wallet, callback, recovery, fraud, and KYC logic live in `app/Domain/*`. Controllers only validate, authorize, delegate, and respond. |
| **SOLID (applied, not recited)** | Single-purpose services and actions; payment providers behind gateway interfaces (live vs fake); new rails extend the domain without rewriting controllers; policies keep authorization closed for modification. |
| **Double-entry ledger** | `LedgerService` posts balanced entries for every money movement. Wallet balances are projections of the ledger - not free-hand updates. |
| **Atomic money ops** | Transfer, release, refund, deposit, and payout paths run inside database transactions. Partial money states are not allowed to stick. |
| **Idempotent payments** | Payment APIs accept idempotency keys so retries cannot double-credit or double-debit. |
| **Protected-transfer state machine** | Held → released / refunded / completed, with open callbacks blocking unsafe release. Expiry jobs enforce the 72-hour windows. |
| **Signed external rails** | ALATPay (and other providers) are reached only through domain gateways. Webhooks are signature-validated and replay-safe. |
| **Auditability** | Financial state changes produce timelines and audit trails judges can follow in Protection and Activity. |

Deeper layout notes for code reviewers: [docs/ENGINEERING.md](docs/ENGINEERING.md).

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

---

## Repository layout

```
app/Domain/          Bounded contexts (Wallet, Transfers, Callback, Recovery, Fraud, Payments, …)
app/Http/            Thin controllers, form requests, API resources
resources/js/        Inertia React product UI
routes/              Web, API, admin, console schedule
infra/               Docker Compose and container definitions
docs/                Deploy + engineering notes
tests/               Pest coverage focused on money paths
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

Sandbox credentials live only in local `.env` (see `.env.example` for `RETON_DEMO_*`). **Never enable demo mode on a public production site.**

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
| SMS / alerts | Reton’s messaging stack for auth and alerts |

See `.env.example` and [docs/DEPLOY.md](docs/DEPLOY.md). **Never commit real API keys, webhook secrets, or `.env` files.**

---

## Testing

```bash
php artisan test
```

Priority coverage is money: happy path, authorization denial, validation failure, idempotency, webhook replay, and state-machine transitions (protected hold → release / callback / auto-expiry).

---

## Security posture

- PIN confirmation on money movement
- Rate limits on auth and payment endpoints
- Idempotency keys on payment APIs
- Webhook signature validation
- Encrypted sensitive fields at rest (including BVN and integration secrets)
- Policies on financial resources; audit logs on financial state changes
- Callback Protection release shows the recipient’s full name and a final irreversible warning

Treat every environment that can move real money as production.

---

## Documentation

| Doc | Purpose |
|-----|---------|
| [LICENSE](LICENSE) | Proprietary copyright notice |
| [docs/ENGINEERING.md](docs/ENGINEERING.md) | Domain layout and money-path architecture for reviewers |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Laravel Cloud deploy guide |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [roadmap.md](roadmap.md) | Product and compliance roadmap |

---

## Company and contact

| | |
|--|--|
| Legal entity | **RETON PTE LTD** |
| Founder and CEO | **Gabriel Rotimi Mogaji** |
| Co-Founder | **Aina Christana Olajumoke** |
| Product | [retonpay.com](https://retonpay.com) |
| Support | support@retonpay.com |
| Office | 7, Greenland Estate, Ikorodu, Lagos State, Nigeria |

© 2026 RETON PTE LTD. All rights reserved.
