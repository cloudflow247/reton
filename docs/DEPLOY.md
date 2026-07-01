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

## 7. Troubleshooting

### Build fails downloading dependencies (`composer install`, HTTP 400/403)

Symptom — the build aborts partway through `composer install` with errors like:

```
Failed to download laravel/serializable-closure from dist: The
"https://codeload.github.com/..." file could not be downloaded (HTTP/2 400)
Source fallback is disabled. Not trying alternative sources.
```

This is **not** a code/lockfile problem (the same `composer.lock` installs fine
locally). It's GitHub rate-limiting **unauthenticated** dist downloads from the
build runner — intermittent, so the build often reaches 70–90% first.

1. **Redeploy.** These 400s are frequently transient; a retry often passes.
2. **If it recurs, authenticate Composer** (durable fix). Create a GitHub
   Personal Access Token (classic — **no scopes needed** for public packages;
   the token alone lifts the anonymous rate limit), then add to the
   environment's Variables:

   ```ini
   COMPOSER_AUTH={"github-oauth":{"github.com":"ghp_yourtokenhere"}}
   ```

   (single-line JSON). Redeploy — Composer now authenticates every codeload
   request and stops hitting the throttle.

### 500 on the first DB query — Postgres not attached (SQLite fallback)

Symptom — pages that don't query the database render fine, but the first real
query (e.g. signing in) 500s with:

```
Database file at path [/var/www/html/database/database.sqlite] does not exist.
(Connection: sqlite, ...)
```

`config/database.php` defaults to `env('DB_CONNECTION', 'sqlite')`. With **no
Postgres attached** the connection falls back to SQLite, pointing at a database
file that does not exist on the Cloud runtime — so every query fails.

1. Attach a **PostgreSQL** database to the environment. Laravel Cloud then
   injects `DB_CONNECTION=pgsql` + `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`.
2. In Variables, ensure there is **no leftover `DB_CONNECTION=sqlite`** (or a
   `DB_DATABASE` pointing at a `.sqlite` file) overriding the injected values.
3. Redeploy (so `config:cache` picks up the new vars), then run
   `php artisan migrate --force` (and `db:seed --class=DemoSeeder --force` if
   using demo mode) from the Commands tab.

### Blank dashboard after login (Inertia shell loads, page is empty)

Symptom — login succeeds and the URL is `/dashboard`, but the content area is
empty (no nav, no balance card). The browser tab title may still update.

1. **Open DevTools → Console** on the deployed site. A JavaScript error on the
   Dashboard chunk (often from Reverb/Echo) prevents React from mounting.
2. **Reverb is optional for v1 Cloud deploys.** The frontend only connects when
   `VITE_REVERB_APP_KEY` was present at **build time**. If you have not
   provisioned Reverb yet, redeploy after pulling the latest code — Dashboard
   and Protection work without WebSockets; live trust reloads are simply disabled.
3. **When you add Reverb later**, set build-time variables on Laravel Cloud
   (same values as runtime):

   ```ini
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=reton
   REVERB_APP_KEY=<generated>
   REVERB_APP_SECRET=<generated>
   REVERB_HOST=<your-reverb-host>
   REVERB_PORT=443
   REVERB_SCHEME=https
   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"
   ```

4. Confirm **Postgres is attached** (see above) — a 500 on the first dashboard
   query can also present as a blank Inertia page if the error body is empty.

## 8. First-deploy checklist

- [ ] Postgres + Redis attached
- [ ] `APP_KEY` generated, `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `RETON_DEMO_MODE=false`
- [ ] ALATPay credentials set
- [ ] Deploy command includes `migrate --force` + cache warmers
- [ ] Scheduler enabled
- [ ] Health check → `/up`
- [ ] Custom domain + TLS, `APP_URL` matches
