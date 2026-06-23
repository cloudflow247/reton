# Reton — Customer Web App

React + TypeScript + Vite customer wallet for the Reton platform, wired to the
`/api/v1` backend. Design identity: a dark "vault" canvas with a mint
"protection-active" glow; Space Grotesk (display/figures) + Inter (body).

Stack: React Router, TanStack Query, Zustand, Axios, Framer Motion, Tailwind v4.

## Run locally

The frontend expects the API at `http://127.0.0.1:8000/api/v1`
(override with `VITE_API_URL`).

### 1. Backend (from `../backend`)

A local SQLite profile is provided so the API runs without Docker:

```bash
cd ../backend
APP_ENV=localrun php artisan migrate:fresh --seed   # first time
APP_ENV=localrun php artisan serve --host=127.0.0.1 --port=8000
```

`.env.localrun` points at `database/local.sqlite` with cache/queue on
file/sync and the AlatPay gateway in `fake` mode.

### 2. Frontend

```bash
npm install
npm run dev      # http://127.0.0.1:5173
```

## Pages

- **Login / Register** — Sanctum token auth (stored in Zustand + localStorage).
- **Dashboard** — protected balance, held funds, copyable wallet ID, recent activity.
- **Send** — Normal vs **Protected** transfer (the callback differentiator), PIN-authorised.
- **Add money** — initiates an AlatPay deposit and shows the virtual account to pay into.
- **Activity** — transfers (with protection status) + wallet statement.
- **PIN** — set/change the transaction PIN.
