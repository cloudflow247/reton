# Deploying Reton to Laravel Cloud

This guide walks through a production-ready deploy of Reton on [Laravel Cloud](https://cloud.laravel.com/). Reton is a Laravel 12 + Inertia (React 19 / Vite) app. You will need PostgreSQL, Redis, the scheduler, a Vite asset build, and — for live payments — ALATPay credentials.

---

## 1. Provision resources

In your Laravel Cloud environment, attach:

| Resource | Why |
|----------|-----|
| **PostgreSQL** | Primary database (`DB_CONNECTION=pgsql`) |
| **Redis** | Cache, queues, and scheduler locks (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`) |

Laravel Cloud injects `DB_*` and `REDIS_*` for attached resources. Do not set those by hand unless you know you are overriding them on purpose.

Enable a **queue worker** (Horizon or `queue:work`) so webhooks, notifications, and async side effects can run. Enable the **scheduler** so funding polls and marketplace expiry commands keep running.

---

## 2. Environment variables

Set these in the environment **Variables** tab. Start from `.env.example`. Production essentials:

```ini
APP_NAME=Reton
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-domain>
APP_KEY=                      # Generate in the dashboard, or: php artisan key:generate --show

SESSION_DRIVER=database
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# ── ALATPay (Wema) — required for live payments & BVN OTP ──
ALATPAY_DRIVER=http
ALATPAY_BASE_URL=https://api.alatpay.ng
ALATPAY_API_KEY=<secret>
ALATPAY_BUSINESS_ID=<secret>
ALATPAY_BUSINESS_BVN=<secret>
ALATPAY_WEBHOOK_SECRET=<secret>
ALATPAY_TIMEOUT=12

# ── KYC ──
KYC_BVN_PROVIDER=alatpay      # alatpay (default) or dojah

# ── Reton ──
RETON_DEFAULT_CURRENCY=NGN
RETON_DEMO_MODE=false         # keep OFF on public production
```

You can also store ALATPay and other integration secrets in **Admin → Integrations** after the first deploy. Env values remain fallbacks until admin settings are saved.

Configure real mail (SMTP or a provider) before sending verification and support email. The default `log` mailer is fine only for staging.

---

## 3. Deploy command

Laravel Cloud typically runs `composer install` and the Vite build (`npm ci && npm run build`) from `package.json`. Set the **Deploy Command** to:

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

- `static-accounts:poll` — credits inbound ALATPay static-account funding (idempotent, overlap-safe)
- Marketplace and protection expiry commands

Without the scheduler, deposits and time-based trust flows will stall.

---

## 5. Health check

Point the Cloud health check at `/up` (registered in `bootstrap/app.php`).

---

## 6. Demo mode (staging only)

Demo mode shows one-click logins so reviewers can try the product quickly. It needs **both** the env flag and seeded accounts. **Never enable this on a public production site** — demo credentials are well known.

1. Set:

   ```ini
   RETON_DEMO_MODE=true
   RETON_DEMO_PASSWORD=demo1234
   RETON_DEMO_PIN=1234
   ```

2. Seed once from the Commands runner:

   ```bash
   php artisan db:seed --class=DemoSeeder --force
   ```

3. Sign-in shows demo buttons. Password `demo1234`, PIN `1234`.

To disable, set `RETON_DEMO_MODE=false` and redeploy. Seeded users remain in the database but are no longer offered on the login screen.

---

## 7. Troubleshooting

### Composer install fails with HTTP 400/403 from GitHub

Often rate-limiting on unauthenticated dist downloads. Redeploy first. If it keeps happening, add a GitHub PAT (classic, no scopes required for public packages):

```ini
COMPOSER_AUTH={"github-oauth":{"github.com":"ghp_yourtokenhere"}}
```

### 500 on first database query (SQLite fallback)

If Postgres is not attached, Laravel may fall back to SQLite and look for a missing `database.sqlite` file. Attach PostgreSQL, remove any leftover `DB_CONNECTION=sqlite`, redeploy, then `php artisan migrate --force`.

### “Something went wrong” or blank dashboard after login

1. Hard-refresh after a deploy so the browser picks up the new Vite manifest.
2. Check the browser console for React errors.
3. Confirm Postgres is healthy and migrations have run.
4. Reverb is optional: without `VITE_REVERB_*` at build time, live trust reloads are off, but Dashboard and Protection should still render.

### BVN OTP not arriving

- ALATPay driver must be `http` with a valid API key and Business ID (env or Admin → Integrations).
- ALATPay sends the BVN OTP SMS — Termii is not involved in that path.
- If the driver is `fake`, no SMS is sent; use the demo code shown in the UI (`123456`).

---

## 8. First-deploy checklist

- [ ] Postgres + Redis attached
- [ ] Queue worker / Horizon running
- [ ] `APP_KEY` set, `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `RETON_DEMO_MODE=false`
- [ ] ALATPay live credentials configured
- [ ] Deploy command includes `migrate --force` and cache warmers
- [ ] Scheduler enabled
- [ ] Health check → `/up`
- [ ] Custom domain + TLS; `APP_URL` matches the public URL
- [ ] Mail transport configured for verification emails
