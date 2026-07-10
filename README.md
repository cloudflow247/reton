# Reton

**Payments you can trust.**

Reton is Africa’s trust-first payment platform. We help people send, receive, and recover money with clarity — not just speed. Our flagship idea is **Callback Protection**: hold a payment until the sender is ready to release it, with a full timeline every step of the way.

Built for the [ALATPay Buildathon](https://alatpay.ng/) as a production-minded MVP on licensed rails (ALAT by Wema).

---

## Why Reton

Most wallets optimise for “send and forget.” Reton optimises for the moment something goes wrong:

- **Callback Protection** — protected transfers stay pending until release or recall
- **Wrong-transfer recovery** — report a mistake, hold funds when eligible, track the case
- **Fraud signals** — rule-based scoring with admin visibility
- **KYC tiers** — CBN-aligned limits; BVN verification via ALATPay OTP (NIN via Dojah when needed)
- **Double-entry wallet** — every movement is ledger-backed, never a silent balance tweak

We are not cloning Opay, Kuda, or Moniepoint. The product should feel like a funded fintech — calm, clear, and serious about real money.

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
docs/                Deploy guide, release notes, historical specs
tests/               Pest feature and unit tests
```

---

## Getting started

### Fastest path — local demo

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
# Enable the Windows/SQLite block in .env if you are on Windows without Docker
composer demo          # fresh migrate + Ada/Bola demo accounts
composer dev           # app, queue, Reverb, Vite
```

Open [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login). Use one-click demo accounts when `RETON_DEMO_MODE=true`, or sign in with:

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
npm run build          # or: npm run dev
php artisan serve
```

### Docker

```bash
docker compose -f infra/docker-compose.yml up --build
```

App: [http://localhost:8080](http://localhost:8080) · Horizon: `/horizon` (after sign-in) · Reverb: `ws://localhost:8081`

### Windows (native PHP, no Docker)

Horizon needs `pcntl` / `posix`, so Windows uses `queue:work` with the database queue driver instead of Redis/Horizon.

```bash
cp .env.example .env   # enable the Windows block
php artisan migrate
composer dev
```

Or run terminals separately:

```bash
php artisan serve
php artisan queue:work database --tries=3
php artisan reverb:start
npm run dev
```

Set `BROADCAST_CONNECTION=reverb` and the `REVERB_*` / `VITE_REVERB_*` variables from `.env.example` for live trust updates on Dashboard and Protection.

### Linux / macOS (without Docker)

Prefer Redis + Horizon:

```bash
composer dev
# or: php artisan horizon
```

Use `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`, and the Reverb variables from `.env.example`.

---

## Configuration highlights

Integration credentials and business rules can be managed from the **admin dashboard** (Integrations, Platform, Site). Environment variables remain fallbacks until values are saved.

| Concern | Notes |
|---------|--------|
| ALATPay | Live driver `http` for production payments and BVN OTP; `fake` for local/demo |
| KYC BVN | Default provider `alatpay` (`KYC_BVN_PROVIDER`); Dojah remains available for NIN / alternate BVN |
| Demo mode | `RETON_DEMO_MODE=false` in production — never expose demo logins publicly |
| Termii | Reton’s own SMS/WhatsApp (auth, alerts). **Not** used for ALATPay BVN OTP — ALATPay sends that SMS |

See `.env.example` and [docs/DEPLOY.md](docs/DEPLOY.md) for production variables.

---

## Testing

```bash
php artisan test
```

We aim for solid coverage on money paths: happy path, auth denial, validation, idempotency, webhook replay, and state transitions. Helpers like `ensureVerifiedBvn()` and `readyUserWithWallet()` live in `tests/Pest.php`.

---

## Documentation

| Doc | What it’s for |
|-----|----------------|
| [docs/DEPLOY.md](docs/DEPLOY.md) | Laravel Cloud deploy, env, scheduler, troubleshooting |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [roadmap.md](roadmap.md) | Product & compliance roadmap |
| [docs/release-2026-06-30/TEAM_BRIEF.md](docs/release-2026-06-30/TEAM_BRIEF.md) | June 2026 release walkthrough + screenshots |
| [docs/superpowers/](docs/superpowers/) | Historical design specs and build plans |

---

## Security posture

- PIN confirmation on money movement
- Rate limits on auth and payment endpoints
- Idempotency keys on payment APIs
- Webhook signature validation
- Encrypted sensitive fields (BVN, API secrets)
- Audit logs for financial state changes

Treat every environment that can move real money as production.

---

## Licence & contact

Proprietary — Reton / RetonPay. For partnership or support: **support@retonpay.com**.

Office: 7, Greenland Estate, Ikorodu, Lagos State, Nigeria.
