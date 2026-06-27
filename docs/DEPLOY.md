# Deploying Reton to Laravel Cloud

Reton is a Laravel 12 + Inertia (React 18 / Vite) app. It needs Postgres,
Redis, the Laravel scheduler, and a Vite asset build. There is **no SSR** and
**no queue worker is required** (nothing dispatches `ShouldQueue` jobs yet —
the scheduler runs commands synchronously).

## 1. Provision resources

In the Laravel Cloud environment, attach:

| Resource   | Why                                                            |
|------------|---------------------------------------------------------------|
| PostgreSQL | Primary database (`DB_CONNECTION=pgsql`).                      |
| Redis      | Cache + scheduler `withoutOverlapping` locks (`CACHE_STORE=redis`). |

Laravel Cloud injects the `DB_*` and `REDIS_*` connection variables for
attached resources automatically — do **not** set them by hand.

## 2. Environment variables

Set these in the environment's **Variables** tab. Start from `.env.example`;
the production-specific values are:

```ini
APP_NAME=Reton
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-domain>
APP_KEY=                      # use "Generate" in the dashboard, or `php artisan key:generate --show`

SESSION_DRIVER=database       # sessions table ships in the default users migration
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# ── ALATPay (Wema) — required for live payments ──
ALATPAY_DRIVER=http
ALATPAY_BASE_URL=https://api.alatpay.ng
ALATPAY_API_KEY=<secret>
ALATPAY_BUSINESS_ID=<secret>
ALATPAY_BUSINESS_BVN=<secret>
ALATPAY_WEBHOOK_SECRET=<secret>

# ── Reton ──
RETON_DEFAULT_CURRENCY=NGN
RETON_DEMO_MODE=false         # keep OFF in production (hides demo logins)
```

Mail defaults to the `log` driver; point it at a real transport before sending
user mail.

## 3. Build / deploy command

Laravel Cloud auto-detects `composer install` and the Vite build
(`npm ci && npm run build`) from `package.json`. Set the **Deploy Command** to:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

`migrate --force` is required because deploys run non-interactively. Asset
output (`public/build`) is git-ignored and produced fresh on every deploy.

## 4. Scheduler

Enable the **Scheduler** toggle on the app. It runs `schedule:run` each minute,
which drives `static-accounts:poll` — the primary funding path for AlatPay
static accounts (it polls every minute; crediting is idempotent and serialised
with `withoutOverlapping`). Without the scheduler, inbound deposits are not
credited.

## 5. Health check

The app exposes `/up` (configured in `bootstrap/app.php`). Point the Cloud
health check at it.

## 6. Enabling demo mode (staging only)

Demo mode surfaces one-click demo logins on the sign-in screen so reviewers can
try the app instantly. It is gated on **both** an env flag and seeded accounts —
migrations alone do not create them. **Never enable on a public/production
environment**: the demo accounts share publicly-known credentials.

1. Set the variable (saving triggers a redeploy so `config:cache` picks it up):

   ```ini
   RETON_DEMO_MODE=true
   # optional — these are the defaults:
   RETON_DEMO_PASSWORD=demo1234
   RETON_DEMO_PIN=1234
   ```

2. Seed the demo accounts once, via the environment's Commands runner:

   ```bash
   php artisan db:seed --class=DemoSeeder --force
   ```

   Idempotent (skips existing accounts); only needed once per database.

3. The sign-in page now shows the demo buttons. Credentials: password
   `demo1234`, transaction PIN `1234`. Ada Obi is funded ₦750,000, Bola Ade
   ₦120,000.

To disable, set `RETON_DEMO_MODE=false` and redeploy — the buttons disappear;
the seeded accounts remain in the database but are no longer surfaced.

## 7. First-deploy checklist

- [ ] Postgres + Redis attached
- [ ] `APP_KEY` generated, `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `RETON_DEMO_MODE=false`
- [ ] ALATPay credentials set
- [ ] Deploy command includes `migrate --force` + cache warmers
- [ ] Scheduler enabled
- [ ] Health check → `/up`
- [ ] Custom domain + TLS, `APP_URL` matches
