> **Proprietary** � Copyright 2026 RETON PTE LTD. Founder & CEO: Gabriel Rotimi Mogaji. See [LICENSE](../../../LICENSE).
>
> **Historical notes.** Early planning. For current setup, see [README](../../../README.md), [roadmap](../../../roadmap.md), and [deploy guide](../../DEPLOY.md).

# Request Money (AlatPay Payment Link) � Design Spec

**Date:** 2026-06-23
**Milestone:** M4 (Surround) — AlatPay-native depth
**Status:** Approved for planning

## 1. Goal & non-goals

### Goal

Let a Reton user (the **requester**) raise a fixed-amount money request, backed by an
AlatPay-hosted **payment link**. Anyone holding the link (the **payer** — no Reton
account required) pays through any AlatPay channel (card, bank transfer, USSD). On
AlatPay's signature-verified webhook, the requester's wallet is credited through the
existing audited double-entry ledger path.

This is an **AlatPay-native** feature: it deepens AlatPay usage (a scoring axis for the
buildathon — see context below) and reinforces Reton's trust story without introducing
any non-AlatPay vendor.

### Non-goals (explicitly deferred — YAGNI)

- **Reusable / multi-use links** (one link, many payers). V1 is single-payer: the first
  successful payment fulfils the request.
- **Variable / "pay what you want" amounts.** V1 is a fixed amount set at creation.
- **Split-among-friends / group requests.**
- **Merchant payment requests / invoicing.** (Belongs with the Merchant domain, not built
  yet.)
- **Fraud scoring on the inbound payment.** Optional hook noted but not required for V1.
- **In-app payer experience.** The payer uses AlatPay's hosted page; Reton only exposes a
  thin public details endpoint.

### Context

Reton is an entry for the **AlatPay Buildathon**, aiming to win 1st. AlatPay's confirmed
API surface is collections + payouts + split payment + payment links — it has **no**
bills/airtime/VAS/RRR API. Request Money was chosen as the first AlatPay-native feature
because it is self-contained (no Merchant domain dependency), demo-friendly, and uses
AlatPay's *Payment Link via API* product directly.

## 2. Decisions locked

- **V1 scope:** fixed-amount, single-payer, single-use link.
- **Crediting reuses `WalletService.fund()`** (debit System Cash, credit requester wallet)
  — no new credit path. Idempotency key = the request's business reference.
- **One new gateway method** on the existing `AlatpayGateway` contract; implemented in both
  the Fake and Http gateways. No parallel vendor or service layer.
- **Webhook handling is refactored** into a router that admits/dedups once, then dispatches
  by `provider_reference`. The current `type`-prefix routing cannot distinguish a
  payment-link collection from a deposit collection.
- **Amounts in minor units** (kobo), `char(3)` currency — consistent with the codebase.

## 3. Flow

```
Requester ──create──▶ PaymentRequest (pending)
                          │
                          ▼
                 AlatpayGateway.createPaymentLink()  ──▶ AlatPay Payment Link via API
                          │                                    │
                          ◀────────── link URL + ref ──────────┘
   share link  ▼
Payer ──pays via AlatPay channel──▶ AlatPay
                                       │  webhook (HMAC-signed)
                                       ▼
                        POST /webhooks/alatpay ──▶ AlatpayWebhookRouter
                                       │  admit+dedup (AlatpayWebhookGuard)
                                       ▼  match provider_reference → payment_requests
                        PaymentRequestService.process()
                                       │
                                       ▼
                        WalletService.fund(requester wallet)  ──▶ ledger posting
                                       │  (idempotency key = request reference)
                                       ▼
                        PaymentRequest → paid, transaction_id set, paid_at stamped
```

Reconciliation fallback: a scheduled/triggered `reconcile()` calls
`AlatpayGateway.fetchTransaction(provider_reference)` and credits if AlatPay reports the
payment successful with a matching amount/currency — mirroring `AlatpayDepositService::reconcile()`.

## 4. Domain & data

Lives in the existing `app/Domain/Payments/` bounded context (alongside `Deposit`,
`Payout`, `WebhookEvent`).

### `payment_requests` table

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `reference` | string, unique | business ref, `REQ-<ULID>` |
| `requester_user_id` | fk users, cascade | who raised the request |
| `wallet_id` | fk wallets, restrict | wallet credited on payment |
| `provider` | string(32), default `alatpay` | |
| `provider_reference` | string, nullable, indexed | AlatPay link/transaction id |
| `status` | string(16) | `pending \| paid \| expired \| cancelled` |
| `amount` | bigInteger | minor units |
| `currency` | char(3) | |
| `title` | string | shown on the pay page |
| `description` | string, nullable | |
| `payment_link_url` | string, nullable | returned by AlatPay |
| `payer_name` | string, nullable | captured from webhook |
| `payer_email` | string, nullable | captured from webhook |
| `transaction_id` | fk transactions, nullOnDelete, nullable | ledger txn once paid |
| `metadata` | json, nullable | |
| `expires_at` | timestamp, nullable | |
| `paid_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |

Indexes: `['requester_user_id', 'status']`; unique `['provider', 'provider_reference']`
(de-dupe guard, matching `deposits`).

### `PaymentRequestStatus` enum

`Pending`, `Paid`, `Expired`, `Cancelled`, with `isOpen()` / `isPaid()` helpers, mirroring
`DepositStatus`.

### `PaymentRequest` model

Belongs to requester (`User`) and `Wallet`; `transaction()` relation; status cast to the
enum; `isOpen()` / `isPaid()` convenience.

## 5. Gateway extension

Add to `AlatpayGateway` contract:

```php
public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse;
```

- **`PaymentLinkRequest`** (readonly DTO): `reference`, `amount` (Money), `title`,
  `description`, `customerEmail` (requester, for AlatPay record), optional `redirectUrl`,
  `expiresAt`.
- **`PaymentLinkResponse`** (readonly DTO): `providerReference`, `paymentLinkUrl`,
  optional `expiresAt`.

**`FakeAlatpayGateway`**: returns a deterministic URL
(`https://pay.alatpay.test/{reference}`) and `providerReference` derived from the request
reference — makes the entire flow runnable in tests and the demo without network.

**`HttpAlatpayGateway`**: POSTs to AlatPay's *Payment Link via API* endpoint. Exact path,
request fields, and response keys to be confirmed from `docs.alatpay.ng` (Others → Payment
Link via API) at implementation time; mapped defensively like the existing
`createCollection` (best-effort `data.*` extraction with fallbacks). Auth header
`Ocp-Apim-Subscription-Key`, base URL from config — same `client()` helper.

> Implementation note: if AlatPay's payment-link response does not include a transaction
> id until payment occurs, `provider_reference` is set to the link id at creation and
> reconciled to the transaction id on webhook; the unique `(provider, provider_reference)`
> guard and the reference-based idempotency key both still hold.

## 6. Webhook routing refactor

**Problem:** `AlatpayWebhookController` currently routes by `type` prefix
(`transfer*` → payouts, else → deposits). A payment-link payment is a *collection*, so
`type` cannot distinguish it from a deposit.

**Solution:** introduce `AlatpayWebhookRouter`:

1. Calls `AlatpayWebhookGuard::admit()` **once** (HMAC verify + dedupe → `WebhookEvent`).
   Single admit preserves the "process at most once" guarantee.
2. If not fresh → return (replay no-op).
3. Dispatch on decoded payload:
   - `type` starts with `transfer` → `PayoutService::processFromEvent()`.
   - else (collection) → match `data.reference` against `payment_requests`
     (`provider_reference` or `reference`); if found → `PaymentRequestService`; else →
     `AlatpayDepositService`.

To keep the single-admit guarantee while moving dispatch into the router, the existing
`handleWebhook()` methods on `AlatpayDepositService` / `PayoutService` are refactored to
expose a `process(WebhookEvent, array $data)` entry the router calls after admitting. The
controller becomes a thin pass-through to the router. `AlatpayWebhookGuard` is unchanged.

`PaymentRequestService::process()` mirrors `AlatpayDepositService::process()`:

- No matching open request → mark event `ignored`.
- Already paid → mark event `processed` (idempotent).
- Success requires `status == completed` **and** matching `amount` **and** `currency`.
- On success: `WalletService.fund()` inside a DB transaction, capture `payer_*`, set
  `status = paid`, `transaction_id`, `paid_at`; mark event `processed`.

## 7. API surface (`/api/v1`)

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/payment-requests` | sanctum | create; returns request + `payment_link_url` |
| `GET` | `/payment-requests` | sanctum | list the caller's requests (paginated) |
| `GET` | `/payment-requests/{paymentRequest}` | sanctum | show (policy: owner) |
| `POST` | `/payment-requests/{paymentRequest}/cancel` | sanctum | cancel while `pending` |
| `GET` | `/pay/{reference}` | public | payer-facing details: title, amount, currency, status, `payment_link_url` |
| `POST` | `/webhooks/alatpay` | HMAC | existing route, now via `AlatpayWebhookRouter` |

- **Validation:** `amount` positive integer (minor units) within KYC limits; `currency`
  supported; `title` required; `description` optional.
- **Resources:** `PaymentRequestResource` (owner view, full) and `PublicPaymentRequestResource`
  (payer view, no requester PII beyond display name). Standard success/error/validation/
  pagination envelopes.
- **Policy:** `PaymentRequestPolicy` — view/cancel restricted to the requester, mirroring
  `DepositPolicy`.
- **Cancel semantics:** only `pending` → `cancelled`. A webhook arriving for a cancelled
  request is treated as `ignored` (request no longer open); refund/return is out of scope
  for V1 (AlatPay link should be disabled on cancel where the API allows).

## 8. Error handling

- Gateway failure on create → `AlatpayException::requestFailed(...)` (existing); request row
  is created `pending` first so a retry can re-attempt link creation without losing the ref.
- Invalid webhook signature → `InvalidWebhookSignatureException` (existing), 4xx, no state
  change.
- Amount/currency mismatch on webhook → event `failed`, no credit.
- Double-payment / replay → blocked by three independent guards: `WebhookEvent` unique
  `(provider, event_id)`, `PaymentRequest.status`, and the ledger idempotency key.
- Expired request paid late → if `expires_at` passed and status not `paid`, treat as
  `ignored` (no credit); surfaced for manual review via metadata.

## 9. Testing

Feature tests over `FakeAlatpayGateway` (registered in the test container), money-path
coverage target 90%:

1. Create returns a `payment_link_url` and persists a `pending` request.
2. Signed webhook credits the requester's wallet exactly once; `status = paid`,
   `transaction_id` set.
3. Replayed webhook (same `event_id`) is a no-op (no second credit).
4. Wrong/missing signature is rejected; no state change.
5. Amount mismatch / currency mismatch → `failed`, no credit.
6. Cancel moves `pending → cancelled`; a subsequent webhook is `ignored` (no credit).
7. Expiry → late payment `ignored`.
8. Authorization: a user cannot view/cancel another user's request.
9. `reconcile()` credits a `pending` request when AlatPay reports success with a matching
   amount; no-ops otherwise.
10. Router unit/feature test: a collection webhook matching a payment_request goes to the
    request handler, a deposit reference goes to deposits, a `transfer*` event goes to
    payouts.

## 10. Out-of-scope follow-ups

- Notifications to the requester on payment (depends on the Notifications domain — separate
  M4 item).
- Reusable links, variable amounts, group/split requests.
- Merchant invoicing on top of payment links.
- Fraud scoring of inbound link payments.

## 11. Open questions to confirm at implementation time

- AlatPay *Payment Link via API* exact endpoint path, request body, and response keys
  (confirm from `docs.alatpay.ng`).
- Whether AlatPay fires a distinct webhook `type` for payment-link payments (would simplify
  routing) or reuses the generic collection event (the reference-match router handles both).
- Whether AlatPay supports disabling/expiring a link on cancel via API.
