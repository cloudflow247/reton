# Deploying Reton to Laravel Cloud

A practical production deploy guide for Reton on [Laravel Cloud](https://cloud.laravel.com/).

Reton is a Laravel 12 + Inertia (React 19 / Vite) product from **RETON PTE LTD**. You will need PostgreSQL, Redis, the scheduler, a Vite asset build, and - for live payments - production rail credentials stored in environment variables or Admin → Integrations (never in git).

---

## 1. Provision resources

In your Laravel Cloud environment, attach:

| Resource | Why |
|----------|-----|
| **PostgreSQL** | Primary database (`DB_CONNECTION=pgsql`) |
| **Redis** | Cache, queues, and scheduler locks |

Laravel Cloud injects `DB_*` and `REDIS_*` for attached resources. Do not override them unless you intend to.

Enable a **queue worker** (Horizon or `queue:work`) so webhooks, notifications, and async work can run. Enable the **scheduler** so funding polls and marketplace expiry commands keep running.

---

## 2. Environment variables

Set these in the environment **Variables** tab. Start from `.env.example`. Production essentials:

```ini
APP_NAME=Reton
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=                      # Generate in the dashboard, or: php artisan key:generate --show

SESSION_DRIVER=database
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Payment rail - live only; paste real secrets in the Cloud UI, never commit them
ALATPAY_DRIVER=http
ALATPAY_BASE_URL=https://api.alatpay.ng
ALATPAY_API_KEY=
ALATPAY_BUSINESS_ID=
ALATPAY_BUSINESS_BVN=
ALATPAY_WEBHOOK_SECRET=
ALATPAY_TIMEOUT=12

KYC_BVN_PROVIDER=alatpay

RETON_DEFAULT_CURRENCY=NGN
RETON_DEMO_MODE=false         # must stay OFF on public production
```

You can also store integration secrets in **Admin → Integrations** after the first deploy. Env values remain fallbacks until admin settings are saved.

Configure real mail before sending verification and support email. The `log` mailer is fine only for staging.

---

## 3. Deploy command

Laravel Cloud typically runs `composer install` and the Vite build from `package.json`. Set the **Deploy Command** to:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

`migrate --force` is required because deploys are non-interactive. Compiled assets in `public/build` are git-ignored and rebuilt on every deploy.

---

## 4. Scheduler

Turn on the **Scheduler** toggle. It runs `schedule:run` every minute and drives jobs such as:

- Static-account funding polls (idempotent, overlap-safe)
- Marketplace and protection expiry commands

Without the scheduler, deposits and time-based trust flows will stall.

---

## 5. Health check

Point the Cloud health check at `/up`.

---

## 6. Demo mode (private staging only)

Demo mode shows one-click logins for reviewers. It needs the env flag **and** seeded accounts.

**Never enable this on a public production site.** Sandbox passwords live only in your private environment variables (`RETON_DEMO_*` in `.env.example` as placeholders).

1. Set `RETON_DEMO_MODE=true` and the matching `RETON_DEMO_*` values in Cloud Variables (not in git).
2. Seed once: `php artisan db:seed --class=DemoSeeder --force`
3. Disable with `RETON_DEMO_MODE=false` and redeploy when you go live.

---

## 7. Troubleshooting

### Composer install fails with HTTP 400/403 from GitHub

Often rate-limiting on unauthenticated downloads. Redeploy first. If it continues, add a GitHub token as a Cloud secret via Composer's auth config - do not paste tokens into this repo.

### 500 on first database query

If Postgres is not attached, Laravel may fall back to SQLite. Attach PostgreSQL, remove any leftover `DB_CONNECTION=sqlite`, redeploy, then `php artisan migrate --force`.

### "Something went wrong" or blank dashboard after login

1. Hard-refresh after a deploy so the browser picks up the new Vite manifest.
2. Check the browser console for React errors.
3. Confirm Postgres is healthy and migrations have run.
4. Reverb is optional: without `VITE_REVERB_*` at build time, live trust reloads are off, but pages should still render.

### BVN OTP not arriving

- Payment driver must be `http` with valid credentials (env or Admin → Integrations).
- The payment rail sends the BVN OTP SMS - Reton's own SMS stack is not involved in that path.
- If the driver is `fake`, no SMS is sent; use the on-screen sandbox code in local/demo only.

---

## 8. First-deploy checklist

- [ ] Postgres + Redis attached
- [ ] Queue worker / Horizon running
- [ ] `APP_KEY` set, `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `RETON_DEMO_MODE=false`
- [ ] Live payment credentials configured (Cloud Variables or Admin)
- [ ] Deploy command includes `migrate --force` and cache warmers
- [ ] Scheduler enabled
- [ ] Health check → `/up`
- [ ] Custom domain + TLS; `APP_URL` matches the public URL
- [ ] Mail transport configured for verification emails
- [ ] No secrets committed to git

---

## Upsun (formerly Platform.sh)

Reton ships with Upsun config in the repo:

| File | Purpose |
|------|---------|
| `.upsun/config.yaml` | PHP 8.4 app, PostgreSQL 16, Redis, Horizon worker, scheduler cron, Vite build |
| `.environment` | Maps Upsun service relationships to Laravel `DB_*` / `REDIS_*` |

### First deploy checklist

1. Link the GitHub repo in Upsun (already done if you see Activity builds).
2. In Upsun **Variables**, set at least:
   - `APP_KEY` - **same value as Laravel Cloud** (required for encrypted fields)
   - `APP_URL` - your Upsun / custom domain URL
   - Payment and mail secrets (`ALATPAY_*`, `MAIL_*`, etc.)
3. Push `main` - build must find `.upsun/` (this repo includes it).
4. Import the Laravel Cloud Postgres dump into the empty Upsun DB (Custom format via tunnel + `pg_restore`, or Plain SQL via `upsun sql < dump.sql`).
5. Redeploy or run `php artisan migrate --force` only after import if needed.
6. Point DNS and update ALATPay webhooks when smoke tests pass.

Do **not** enable `RETON_DEMO_MODE` on Upsun production.

Official guides: [Laravel on Upsun](https://developer.upsun.com/docs/get-started/stacks/laravel/get-started), [PostgreSQL import](https://developer.upsun.com/docs/add-services/postgresql).

---

© 2026 RETON PTE LTD. All rights reserved.
