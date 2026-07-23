# Reton engineering notes

Short guide for buildathon reviewers and engineers opening the codebase. Product story stays in the [README](../README.md); this page explains how money is protected in code.

---

## Design intent

Reton is not a thin UI over a payment API. It is a **trust layer** on licensed rails (ALAT by Wema via ALATPay):

1. Money enters through verified funding paths.
2. Protected transfers hold funds until release, callback resolution, or scheduled expiry.
3. Every movement is recorded on a double-entry ledger with a timeline humans can read.

Assume real money. Prefer correctness and auditability over clever shortcuts.

---

## Domain layout (`app/Domain/*`)

Business logic is organised by bounded context. Controllers do not own payment rules.

| Context | Responsibility |
|---------|----------------|
| `Wallet` | Balances, funding surfaces, available vs held funds |
| `Ledger` | Double-entry postings; source of truth for money |
| `Transfers` | Instant and protected sends; hold / release / refund |
| `Callback` | Callback Protection initiate / accept / reject / expire |
| `Recovery` | Wrong-transfer recovery eligibility and cases |
| `Fraud` | Rule-based scoring and alerts |
| `Payments` | ALATPay (and other) gateways, deposits, payouts, webhooks |
| `Kyc` | Tiers, BVN / identity verification |
| `Marketplace` | Protected listings and digital / physical escrow where enabled |
| `Settings` | Admin-managed platform rules (fallback to env) |

Typical flow inside a context:

`Form Request` → thin controller → **Service / Action** → ledger / wallet → events / jobs → API resource or Inertia props.

### SOLID in practice (not a slogan)

| Letter | Where it shows up in Reton |
|--------|----------------------------|
| **S** | Services/actions own one money workflow (send, release, refund, expire) instead of god-controllers |
| **O** | New payment rails plug in via gateway contracts; existing transfer/callback flows stay stable |
| **L** | Fake gateways substitute for live HTTP gateways in tests and local demo without changing callers |
| **I** | Narrow contracts (`AlatpayGateway`, bill/card gateways) instead of one mega-provider interface |
| **D** | Domain services depend on abstractions and config-bound drivers - not hard-coded vendor SDKs in controllers |

If a change forces controllers to know ALATPay request shapes, that is a design regression.

---

## Callback Protection money path

```text
Sender sends protected
        │
        ▼
Transfer Held  +  receiver held_balance
        │
        ├── Sender releases (PIN + recipient name + final warning)
        │         → Completed, hold released to recipient
        │
        ├── Sender raises callback
        │         → Receiver accept  → refund sender
        │         → Receiver reject  → escalate to admin
        │         → No response ~72h → refund sender (default)
        │
        └── No release ~72h (no open callback)
                  → auto-release to recipient
```

Scheduled commands (every 5 minutes): `callbacks:expire`, then `transfers:auto-release`.

Config lives under `config/reton.php` → `callback.*` (also tunable in admin Platform settings).

---

## Ledger rule (non-negotiable)

Never mutate `wallets.balance` or `held_balance` as a standalone write for a business event.

Correct path:

1. Open a DB transaction.
2. Post balanced ledger entries via the ledger services.
3. Update wallet projections consistently with those entries.
4. Record timeline / audit side effects (sync or queued).

If a code path updates balances without ledger posts, treat it as a defect.

---

## External providers

Providers are behind domain gateways (live HTTP vs fake for local/tests):

- Controllers never call ALATPay, Termii, Dojah, etc. directly.
- Webhooks validate signatures before mutating state.
- Idempotent handling prevents replay double-credits.

---

## What to demo in code review

| Path | Start here |
|------|------------|
| Protected send / release / refund | `app/Domain/Transfers/Services/TransferService.php` |
| Callback lifecycle | `app/Domain/Callback/Services/CallbackService.php` |
| Expiry fairness / windows | `app/Domain/Callback/Services/ProtectionFairnessService.php` |
| Ledger posting | `app/Domain/Ledger/` |
| ALATPay deposits / webhooks | `app/Domain/Payments/` |
| Protection UI | `resources/js/Pages/Protection.tsx` |
| Receipt (copyable reference + transaction id) | `resources/js/Pages/Activity/Show.tsx` |
| Money-path tests | `tests/Feature/Transfers/`, `tests/Feature/Callback/`, `tests/Feature/Scheduler/` |

---

## Testing bar

Pest feature tests should cover:

- Happy path for money movement
- Authorization denial
- Validation failure
- Idempotency
- Webhook replay
- Protected / callback state transitions

Run: `php artisan test`

---

© 2026 RETON PTE LTD.
