> **Historical document.** Captures early design and planning decisions. For current product setup, start with the [README](../../README.md), [roadmap](../../roadmap.md), and [deploy guide](../DEPLOY.md).

# Static Wallet Funding (AlatPay) — Design Spec

**Date:** 2026-06-23
**Milestone:** M4 (Surround) — AlatPay-native depth
**Status:** Approved for planning

## 1. Goal & non-goals

### Goal

Give each Reton wallet a **permanent AlatPay static account** the user can fund by
ordinary bank transfer — any amount, any number of times, without generating new payment
details each time. Reton **polls** AlatPay's static-account transactions endpoint and
credits the wallet for each new successful payment through the existing audited
double-entry ledger path (a `Deposit` row, `provider = 'alatpay_static'`).

This is an **AlatPay-native** feature (deepens AlatPay usage for the buildathon) and a
high-polish funding UX: a stable "account number to fund anytime," versus today's
one-time virtual account minted per deposit.

### Non-goals (deferred — YAGNI)

- **Auto-selecting the wallet type by KYC tier.** The type is an explicit parameter; the
  "Individual once KYC-verified, else Collection" policy is deferred (KYC domain not built).
- **Webhook-based crediting.** Docs are poll-first; whether AlatPay also fires a webhook
  on static credits is unconfirmed. Polling is the V1 primary; webhook push is a later add.
- **More than one active static account per wallet.**
- **Persisting BVN.** BVN is passed to AlatPay and discarded, never stored.
- **De-provisioning / closing a static account.**

### Context

Reton is an AlatPay Buildathon entry (aim: win 1st). AlatPay Static Wallet API confirmed
from docs.alatpay.ng/static-wallet on 2026-06-23 — see the `alatpay-static-wallet-api`
project memory for the full contract.

## 2. Decisions locked

- **Poll-first funding.** Crediting is driven by polling the static-account transactions
  endpoint (like the existing `ReconcileDeposits` safety-net, but as the primary path).
- **One active static account per wallet** (`static_accounts.wallet_id` unique).
- **Both wallet types supported**, chosen by an explicit `wallet_type` parameter
  (`individual` = AlatPay type 1; `collection` = type 2). Auto-selection deferred.
- **Crediting reuses `Deposit` + `WalletService::fund()`.** `provider = 'alatpay_static'`,
  idempotency key = AlatPay's `staticAccountTransactionId`.
- **Amount unit conversion.** Static-account transaction `amount` is in **MAJOR** units
  (e.g. `100.00` = ₦100), unlike every other AlatPay API (kobo). Convert with
  `(int) round($amount * 100)`.
- **BVN never persisted.** Collection BVN from config (`services.alatpay.business_bvn`);
  individual BVN supplied per request, used once, discarded.

## 3. Provisioning flow (2-step OTP)

```
provision(User, Wallet, type, bvn?)
   └─ POST /alatpay-wallet/api/v1/staticaccount {businessId, staticWalletType, bvn, email?}
        ← {id (=staticWalletId), otpTrackingId, accountNumber:null, message:"OTP sent..."}
   └─ StaticAccount row: status=pending_otp, provider_reference=staticWalletId, otp_tracking_id

verify(StaticAccount, otp)
   └─ POST /alatpay-wallet/api/v1/staticaccount/validateAndCreate
        {staticWalletId, businessId, otp, trackingId}
        ← {accountNumber:"0412345678", accountName:"Your Business – David_Mark", id}
   └─ StaticAccount row: status=active, account_number, account_name
```

If the provision response already carries an `accountNumber` and no `otpTrackingId` (e.g. a
Collection wallet that does not require OTP — to confirm at implementation), `provision`
stores the account number and sets status `active` directly; `verify` is then a no-op that
returns the already-active account.

## 4. Funding flow (polling → ledger credit)

```
poll(StaticAccount)                          (scheduled: static-accounts:poll)
   └─ fetchStaticAccountTransactions(account_number, page, limit)
        ← [ {staticAccountTransactionId, status, accountNumber, amount (MAJOR), narration, ...} ]
   └─ for each txn where status == 1 AND not already recorded (dedup by staticAccountTransactionId):
        amountMinor = (int) round(amount * 100)
        Deposit.create(provider='alatpay_static', provider_reference=staticAccountTransactionId,
                       status=completed-after-credit, amount=amountMinor, ...)
        WalletService::fund(wallet, Money::of(amountMinor, currency),
                            idempotencyKey = staticAccountTransactionId,
                            metadata = {static_account_id, narration})
   └─ stamp last_polled_at
```

Three independent double-credit guards hold: unique `(provider, provider_reference)` on
`deposits`, the deposit status, and the ledger idempotency key (`transactions.idempotency_key`
is DB-unique and `LedgerService::post()` re-checks in-transaction).

## 5. Data model

New `static_accounts` table; `StaticAccount` model in `app/Domain/Payments/`.

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `wallet_id` | fk wallets, restrict, **unique** | one active static account per wallet |
| `user_id` | fk users, cascade | owner (for authz + listing) |
| `provider` | string(32), default `alatpay` | |
| `provider_reference` | string, nullable, indexed | AlatPay `staticWalletId` |
| `wallet_type` | string(16) | `individual` \| `collection` |
| `status` | string(16) | `pending_otp` \| `active` \| `failed` |
| `account_number` | string, nullable | null until OTP-verified; AlatPay payable account |
| `account_name` | string, nullable | |
| `bank_name` | string, nullable | AlatPay/Wema |
| `otp_tracking_id` | string, nullable | from provision step; used in verify |
| `email` | string, nullable | optional notification email |
| `last_polled_at` | timestamp, nullable | |
| `metadata` | json, nullable | |
| `created_at` / `updated_at` | timestamps | |

Index `['user_id', 'status']`. The `account_number` here is AlatPay's external payable
account — distinct from the internal `wallets.account_number` (NUBAN-style, for internal
wallet-to-wallet lookup). Keep the two clearly separate; do not conflate.

Enums in `app/Domain/Payments/Enums/`:
- `StaticWalletType: string { Individual='individual'; Collection='collection' }` with
  `providerCode(): int` → 1 / 2.
- `StaticAccountStatus: string { PendingOtp='pending_otp'; Active='active'; Failed='failed' }`
  with `isActive(): bool`.

## 6. Gateway extension

Add to the `AlatpayGateway` contract (implemented in Fake + Http), reusing the existing
`client()` helper and `Ocp-Apim-Subscription-Key` auth:

- `provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse`
  → `POST /alatpay-wallet/api/v1/staticaccount`.
- `verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse`
  → `POST /alatpay-wallet/api/v1/staticaccount/validateAndCreate`.
- `fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array`
  → returns `array<StaticAccountTransaction>`. Endpoint path/params confirmed at
  implementation (list shape is known: `staticAccountTransactionResponses[]`).

DTOs (readonly) in `app/Domain/Payments/Alatpay/Data/`:
- `StaticAccountRequest(int $walletType, ?string $bvn, ?string $email, string $reference)`.
- `StaticAccountProvisionResponse(string $staticWalletId, ?string $otpTrackingId, ?string $accountNumber, ?string $accountName)`.
- `StaticAccountVerifyRequest(string $staticWalletId, string $otp, string $trackingId)`.
- `StaticAccountResponse(string $providerReference, string $accountNumber, ?string $accountName, string $bankName = 'Wema Bank')`.
- `StaticAccountTransaction(string $transactionId, int $status, string $accountNumber, float $amountMajor, ?string $narration, ?string $notificationEmail)` with `isSuccessful(): bool` (`status === 1`) and `amountMinor(): int` (`(int) round($amountMajor * 100)`).

The **Http** gateway maps defensively (best-effort `data.*` / documented keys), like
`createCollection`. The **Fake** gateway:
- `provisionStaticAccount`: stores a pending wallet keyed by `staticWalletId`; returns a
  deterministic `otpTrackingId`; `accountNumber` null (OTP path).
- `verifyStaticAccount`: accepts a fixed test OTP `123456` (rejects others with
  `AlatpayException`); returns a deterministic `accountNumber` (`04` + 8 digits derived from
  the reference) and `accountName`.
- `fetchStaticAccountTransactions`: returns transactions recorded for that account.
- test helpers: `markStaticFunded(string $accountNumber, float $amountMajor, string $txnId)`
  to simulate an inbound payment; `seedCollectionImmediate()` behaviour via a flag so a test
  can exercise the no-OTP Collection path.

## 7. Service

`StaticAccountService` (in `app/Domain/Payments/Services/`), constructor-injecting
`AlatpayGateway` and `WalletService`:

- `provision(User $user, Wallet $wallet, StaticWalletType $type, ?string $bvn = null): StaticAccount`
  — Collection resolves BVN from `config('services.alatpay.business_bvn')`; Individual uses
  `$bvn`. Creates the row, calls the gateway, stores `staticWalletId`/`otpTrackingId`; if the
  response already has an account number, sets `active` immediately.
- `verify(StaticAccount $account, string $otp): StaticAccount` — calls the gateway, stores
  `account_number`/`account_name`, flips to `active`. No-op (returns as-is) if already active.
- `poll(StaticAccount $account): int` — fetches transactions, credits each new successful one
  via `credit()`, returns the number credited; stamps `last_polled_at`. Only polls `active`
  accounts.
- `private credit(StaticAccount $account, StaticAccountTransaction $txn): void` — inside a
  `DB::transaction`: create the `Deposit`, call `WalletService::fund()` with the txn id as
  idempotency key, mark the deposit completed. Mirrors `AlatpayDepositService::creditDeposit()`.

## 8. HTTP API (`/api/v1`)

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/static-accounts` | sanctum | provision (body `wallet_id`, `wallet_type`, `bvn?`) → status + (account if active) |
| `POST` | `/static-accounts/{staticAccount}/verify` | sanctum | submit `otp` → activate, return account number |
| `GET` | `/static-accounts` | sanctum | list the caller's static accounts (paginated) |
| `GET` | `/static-accounts/{staticAccount}` | sanctum | show (owner-only) |

- **Validation:** `wallet_id` exists; `wallet_type` in `individual,collection`; `bvn`
  required+`digits:11` when `wallet_type=individual`, prohibited otherwise; `otp` `digits:6`.
- `store` resolves the wallet and calls `$this->authorize('operate', $wallet)` (same as
  `DepositController`). BVN is validated but never stored.
- `StaticAccountPolicy` (`view`, owner-only via `user_id`), registered in
  `AppServiceProvider::boot()`. `verify`/`show` authorize `view`.
- `StaticAccountResource` (owner view: type, status, account_number, account_name,
  bank_name, created_at). No BVN, no `otp_tracking_id`.
- Standard `ApiResponse` envelopes.

## 9. Scheduled command

`static-accounts:poll` (in `app/Console/Commands/`, mirrors `ReconcileDeposits`): iterate
`active` static accounts and call `StaticAccountService::poll()` on each; report the count
credited. Registered on the scheduler is out of scope for this plan (manual/cron for now).

## 10. Error handling

- Provision/verify gateway failure → `AlatpayException::requestFailed(...)`; the
  `static_accounts` row is created first (status `pending_otp`/`failed`) so a retry can
  re-attempt without losing the reference. A failed verify leaves the row `pending_otp` for
  retry; persistent failure can be set `failed`.
- Wrong OTP → the Fake throws `AlatpayException`; the controller surfaces a 4xx; row stays
  `pending_otp`.
- Poll: transactions with `status != 1` are skipped; already-recorded transactions
  (dedup by `staticAccountTransactionId`) are no-ops; a malformed amount that rounds to 0 is
  skipped and logged.
- Double-credit prevented by the three guards in §4.

## 11. Testing

Pest feature tests over the Fake gateway (`$this->app->instance(AlatpayGateway::class, …)`),
money-path coverage target 90%:

1. `provision` (individual) creates a `pending_otp` row with an OTP tracking id, no account
   number yet.
2. `verify` with the correct OTP activates the account and stores the account number.
3. `verify` with a wrong OTP throws and leaves status `pending_otp`.
4. A Collection wallet whose provision returns an account number immediately is `active`
   without an OTP step.
5. `poll` credits the wallet for a new successful transaction exactly once; re-polling the
   same transaction does not double-credit (dedup by `staticAccountTransactionId`).
6. Major→minor conversion is exact: a ₦100.00 transaction credits 10000 minor units.
7. Transactions with `status != 1` are not credited.
8. Authorization: a user cannot provision against another user's wallet (403); cannot
   verify/view another user's static account (403).
9. The `static-accounts:poll` command credits pending transactions across active accounts.

## 12. Open questions (confirm at implementation)

- Does **Collection** (type 2) also require OTP, or return the account number immediately?
  (The flow handles both; the Fake exercises both via a flag.)
- Exact **transactions** endpoint path and query params for a single static account (the
  response shape `staticAccountTransactionResponses[]` is known).
- Whether AlatPay **also fires a webhook** on static credits (would enable push-based
  crediting as a later enhancement; poll is the V1 primary).
- Confirm `config('services.alatpay.business_bvn')` is added to `config/services.php` and
  `.env.example` during implementation.
