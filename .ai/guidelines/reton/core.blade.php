## Reton — Trust-First FinTech

Reton is Africa's trust-first payment platform (ALATPay Buildathon MVP). Real money flows through this system — never generate demo or placeholder payment code.

### Architecture (required)

- Business logic lives in `app/Domain/*` using DDD: Actions, Services, Repositories (when warranted), DTOs, Events/Listeners, Policies.
- Controllers are HTTP-only: validate, authorize, delegate, respond.
- Every payment operation must be **atomic** (DB transactions) and ledger-backed via `LedgerService` — never mutate balances without ledger entries.
- External providers (ALATPay, Termii, Dojah, Interswitch, Bridgecard, Remita) are accessed only through domain gateways/services, never from controllers.

### Flagship features (demo-polished)

1. **Callback Protection** — protected transfers, release/callback flows, receiver accept/reject/evidence, admin intervention, full timeline.
2. **Wrong Transfer Recovery** — use `app/Domain/Recovery/*` patterns.
3. **Wallet** — double-entry ledger, beneficiaries, statements, transaction timeline on every movement.
4. **Fraud** — extend `app/Domain/Fraud/*` rule engine; surface alerts in admin.
5. **Merchant verification** — trust score, blue badge, business profile.

### Stack

- Laravel 12, PHP 8.4, PostgreSQL, Redis, Horizon, Sanctum, Reverb.
- Frontend: Inertia v2, React 19, TypeScript, Tailwind v4, shadcn-style UI in `resources/js/`.
- Admin integration credentials and site settings are configured via the admin dashboard (`PlatformSettingsService`); env vars are fallbacks until saved.

### Security (non-negotiable)

- PIN confirmation for transfers; rate limiting on auth and money endpoints.
- Idempotency keys on payment endpoints; webhook signature validation.
- Policies on every resource; audit logs for financial state changes.
- Encrypt sensitive fields at rest (BVN, API secrets).

### Testing

- Pest feature tests for happy path, authorization denial, validation failure, idempotency, webhook replay, and state transitions.
- Use `ensureVerifiedBvn()` / `readyUserWithWallet()` helpers in `tests/Pest.php` for payment flows requiring verified BVN.

### UI

- Production fintech aesthetic (Stripe / Cash App / Linear references): generous spacing, rounded corners, dark + light mode, WCAG AA.
- Do not clone Opay, Kuda, or Moniepoint UX patterns.
