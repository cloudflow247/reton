> **Proprietary** � Copyright 2026 RETON PTE LTD. Founder & CEO: Gabriel Rotimi Mogaji. See [LICENSE](../../../LICENSE).
>
> **Historical notes.** Early planning. For current setup, see [README](../../../README.md), [roadmap](../../../roadmap.md), and [deploy guide](../../DEPLOY.md).

# Static Wallet Funding (AlatPay) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give each wallet a permanent AlatPay static account (provisioned via a 2-step OTP/BVN flow) that, when funded by bank transfer, credits the wallet through the existing audited ledger via polling.

**Architecture:** A new `StaticAccount` aggregate in `App\Domain\Payments`. Three methods added to the existing `AlatpayGateway` (provision, verify, fetch-transactions; Fake + Http). `StaticAccountService` owns provisioning (OTP) and poll-driven crediting, reusing `Deposit` + `WalletService::fund()`. A `static-accounts:poll` command drives funding. No webhook-router changes (poll-first).

**Tech Stack:** Laravel 12, PHP 8.4, Pest, PostgreSQL, Sanctum. Money in minor units via `App\Support\Money\Money`.

## Global Constraints

- `declare(strict_types=1);` at the top of every PHP file.
- All money is integer **minor units** (kobo). BUT AlatPay static-account transaction `amount` is **MAJOR** units (e.g. `100.00` = ₦100) — convert with `(int) round($amountMajor * 100)`.
- No balance mutation outside the ledger — credit only via `WalletService::fund()`.
- Idempotency key for a static credit = AlatPay's `staticAccountTransactionId`.
- **BVN is never persisted.** Collection BVN from `config('services.alatpay.business_bvn')`; individual BVN supplied per request, used once, discarded.
- The AlatPay static `account_number` is the EXTERNAL payable account — distinct from the internal `wallets.account_number` (NUBAN-style). Never conflate them.
- Domain code under `backend/app/Domain/Payments/`; HTTP under `backend/app/Http/...`; commands under `backend/app/Console/Commands/`.
- Tests are Pest under `backend/tests/Feature/Payments/`, using `RefreshDatabase` and the in-memory `FakeAlatpayGateway` bound via `$this->app->instance(AlatpayGateway::class, ...)`.
- Follow existing patterns exactly (`Deposit` model/migration, `AlatpayDepositService`, `DepositController`, `DepositPolicy`, `ReconcileDeposits` are the references).
- Run commands from `backend/`. Test runner: `./vendor/bin/pest`.

---

### Task 1: StaticAccount persistence (enums, migration, model)

**Files:**
- Create: `backend/database/migrations/2026_06_23_000200_create_static_accounts_table.php`
- Create: `backend/app/Domain/Payments/Enums/StaticWalletType.php`
- Create: `backend/app/Domain/Payments/Enums/StaticAccountStatus.php`
- Create: `backend/app/Domain/Payments/Models/StaticAccount.php`
- Test: `backend/tests/Feature/Payments/StaticAccountModelTest.php`

**Interfaces:**
- Produces:
  - `StaticWalletType: string { Individual='individual'; Collection='collection' }` with `providerCode(): int` (Individual→1, Collection→2).
  - `StaticAccountStatus: string { PendingOtp='pending_otp'; Active='active'; Failed='failed' }` with `isActive(): bool`.
  - `StaticAccount` model: fillable + casts below; relations `requester()` (alias `user()`), `wallet()`; `isActive(): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a static account with enum casts and relations', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $account = StaticAccount::create([
        'wallet_id' => $wallet->getKey(),
        'user_id' => $user->getKey(),
        'provider' => 'alatpay',
        'wallet_type' => StaticWalletType::Collection,
        'status' => StaticAccountStatus::PendingOtp,
    ]);

    expect($account->wallet_type)->toBe(StaticWalletType::Collection)
        ->and($account->wallet_type->providerCode())->toBe(2)
        ->and($account->status)->toBe(StaticAccountStatus::PendingOtp)
        ->and($account->isActive())->toBeFalse()
        ->and($account->wallet->is($wallet))->toBeTrue()
        ->and($account->user->is($user))->toBeTrue();
});

it('maps the individual wallet type to provider code 1', function () {
    expect(StaticWalletType::Individual->providerCode())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountModelTest.php`
Expected: FAIL — class `StaticAccount` not found.

- [ ] **Step 3: Create the migration**

`backend/database/migrations/2026_06_23_000200_create_static_accounts_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')->unique()->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('provider', 32)->default('alatpay');
            $table->string('provider_reference')->nullable()->index(); // AlatPay staticWalletId

            $table->string('wallet_type', 16);  // individual | collection
            $table->string('status', 16);        // pending_otp | active | failed

            $table->string('account_number')->nullable(); // AlatPay external payable account
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();

            $table->string('otp_tracking_id')->nullable();
            $table->string('email')->nullable();

            $table->timestamp('last_polled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_accounts');
    }
};
```

- [ ] **Step 4: Create the enums**

`backend/app/Domain/Payments/Enums/StaticWalletType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum StaticWalletType: string
{
    case Individual = 'individual';
    case Collection = 'collection';

    public function providerCode(): int
    {
        return match ($this) {
            self::Individual => 1,
            self::Collection => 2,
        };
    }
}
```

`backend/app/Domain/Payments/Enums/StaticAccountStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum StaticAccountStatus: string
{
    case PendingOtp = 'pending_otp';
    case Active = 'active';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
```

- [ ] **Step 5: Create the model**

`backend/app/Domain/Payments/Models/StaticAccount.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaticAccount extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'provider',
        'provider_reference',
        'wallet_type',
        'status',
        'account_number',
        'account_name',
        'bank_name',
        'otp_tracking_id',
        'email',
        'last_polled_at',
        'metadata',
    ];

    protected $casts = [
        'wallet_type' => StaticWalletType::class,
        'status' => StaticAccountStatus::class,
        'last_polled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isActive(): bool
    {
        return $this->status === StaticAccountStatus::Active;
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountModelTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_06_23_000200_create_static_accounts_table.php \
        backend/app/Domain/Payments/Enums/StaticWalletType.php \
        backend/app/Domain/Payments/Enums/StaticAccountStatus.php \
        backend/app/Domain/Payments/Models/StaticAccount.php \
        backend/tests/Feature/Payments/StaticAccountModelTest.php
git commit -m "feat(payments): static_accounts table, enums, model"
```

---

### Task 2: Gateway static-account capability (DTOs, contract, Fake, Http, config)

**Files:**
- Create: `backend/app/Domain/Payments/Alatpay/Data/StaticAccountRequest.php`
- Create: `backend/app/Domain/Payments/Alatpay/Data/StaticAccountProvisionResponse.php`
- Create: `backend/app/Domain/Payments/Alatpay/Data/StaticAccountVerifyRequest.php`
- Create: `backend/app/Domain/Payments/Alatpay/Data/StaticAccountResponse.php`
- Create: `backend/app/Domain/Payments/Alatpay/Data/StaticAccountTransaction.php`
- Modify: `backend/app/Domain/Payments/Alatpay/Contracts/AlatpayGateway.php`
- Modify: `backend/app/Domain/Payments/Alatpay/Gateways/FakeAlatpayGateway.php`
- Modify: `backend/app/Domain/Payments/Alatpay/Gateways/HttpAlatpayGateway.php`
- Modify: `backend/config/services.php:38-46` (add `business_bvn`)
- Test: `backend/tests/Feature/Payments/FakeStaticAccountGatewayTest.php`

**Interfaces:**
- Produces (readonly DTOs):
  - `StaticAccountRequest(int $walletType, ?string $bvn, ?string $email, string $reference)`
  - `StaticAccountProvisionResponse(string $staticWalletId, ?string $otpTrackingId, ?string $accountNumber, ?string $accountName)`
  - `StaticAccountVerifyRequest(string $staticWalletId, string $otp, string $trackingId)`
  - `StaticAccountResponse(string $providerReference, string $accountNumber, ?string $accountName, string $bankName = 'Wema Bank')`
  - `StaticAccountTransaction(string $transactionId, int $status, string $accountNumber, float $amountMajor, ?string $narration, ?string $notificationEmail)` with `isSuccessful(): bool` (`status === 1`) and `amountMinor(): int` (`(int) round($amountMajor * 100)`)
- Produces (contract methods, implemented in Fake + Http):
  - `provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse`
  - `verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse`
  - `fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array` (`array<int, StaticAccountTransaction>`)
- Produces (Fake test helpers): `recordStaticTransaction(string $accountNumber, int $status, float $amountMajor, string $transactionId): void` (inject a transaction with any status); `markStaticFunded(string $accountNumber, float $amountMajor, string $transactionId): void` (delegates with `status = 1`); `provisionReturnsImmediately(bool $immediate): void` (forces the no-OTP Collection path).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;

it('provisions with an OTP step then verifies into a live account number', function () {
    $gateway = new FakeAlatpayGateway();

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(
        walletType: 1,
        bvn: '12345678901',
        email: 'user@example.com',
        reference: 'SA-ABC',
    ));

    expect($provision->staticWalletId)->not->toBeEmpty()
        ->and($provision->otpTrackingId)->not->toBeNull()
        ->and($provision->accountNumber)->toBeNull();

    $verified = $gateway->verifyStaticAccount(new StaticAccountVerifyRequest(
        staticWalletId: $provision->staticWalletId,
        otp: '123456',
        trackingId: (string) $provision->otpTrackingId,
    ));

    expect($verified->accountNumber)->not->toBeEmpty()
        ->and($verified->providerReference)->toBe($provision->staticWalletId);
});

it('rejects a wrong OTP', function () {
    $gateway = new FakeAlatpayGateway();
    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(1, '12345678901', null, 'SA-X'));

    $gateway->verifyStaticAccount(new StaticAccountVerifyRequest($provision->staticWalletId, '000000', (string) $provision->otpTrackingId));
})->throws(AlatpayException::class);

it('can provision a collection wallet that returns an account number immediately', function () {
    $gateway = new FakeAlatpayGateway();
    $gateway->provisionReturnsImmediately(true);

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(2, '12345678901', null, 'SA-COL'));

    expect($provision->otpTrackingId)->toBeNull()
        ->and($provision->accountNumber)->not->toBeNull();
});

it('reports recorded transactions in major units with a minor-unit helper', function () {
    $gateway = new FakeAlatpayGateway();
    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(1, '12345678901', null, 'SA-T'));
    $verified = $gateway->verifyStaticAccount(new StaticAccountVerifyRequest($provision->staticWalletId, '123456', (string) $provision->otpTrackingId));

    $gateway->markStaticFunded($verified->accountNumber, 100.00, 'txn-1');
    $txns = $gateway->fetchStaticAccountTransactions($verified->accountNumber);

    expect($txns)->toHaveCount(1)
        ->and($txns[0]->isSuccessful())->toBeTrue()
        ->and($txns[0]->amountMajor)->toBe(100.00)
        ->and($txns[0]->amountMinor())->toBe(10000)
        ->and($txns[0]->transactionId)->toBe('txn-1');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/FakeStaticAccountGatewayTest.php`
Expected: FAIL — `StaticAccountRequest` / methods not found.

- [ ] **Step 3: Create the DTOs**

`backend/app/Domain/Payments/Alatpay/Data/StaticAccountRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A request to AlatPay to begin provisioning a static (permanent) account.
 */
final readonly class StaticAccountRequest
{
    public function __construct(
        public int $walletType,
        public ?string $bvn,
        public ?string $email = null,
        public string $reference = '',
    ) {}
}
```

`backend/app/Domain/Payments/Alatpay/Data/StaticAccountProvisionResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * AlatPay's response to a provision request. Either an OTP is pending
 * (otpTrackingId present, accountNumber null) or the account is created
 * immediately (accountNumber present).
 */
final readonly class StaticAccountProvisionResponse
{
    public function __construct(
        public string $staticWalletId,
        public ?string $otpTrackingId,
        public ?string $accountNumber = null,
        public ?string $accountName = null,
    ) {}
}
```

`backend/app/Domain/Payments/Alatpay/Data/StaticAccountVerifyRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A request to AlatPay to validate an OTP and finalise a static account.
 */
final readonly class StaticAccountVerifyRequest
{
    public function __construct(
        public string $staticWalletId,
        public string $otp,
        public string $trackingId,
    ) {}
}
```

`backend/app/Domain/Payments/Alatpay/Data/StaticAccountResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A finalised static account: the permanent payable account number plus the
 * AlatPay-side reference used to poll transactions.
 */
final readonly class StaticAccountResponse
{
    public function __construct(
        public string $providerReference,
        public string $accountNumber,
        public ?string $accountName = null,
        public string $bankName = 'Wema Bank',
    ) {}
}
```

`backend/app/Domain/Payments/Alatpay/Data/StaticAccountTransaction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A single inbound payment into a static account, as reported by AlatPay.
 * NOTE: AlatPay reports amount in MAJOR units (e.g. 100.00 = NGN 100).
 */
final readonly class StaticAccountTransaction
{
    public function __construct(
        public string $transactionId,
        public int $status,
        public string $accountNumber,
        public float $amountMajor,
        public ?string $narration = null,
        public ?string $notificationEmail = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 1;
    }

    public function amountMinor(): int
    {
        return (int) round($this->amountMajor * 100);
    }
}
```

- [ ] **Step 4: Add the methods to the contract**

In `backend/app/Domain/Payments/Alatpay/Contracts/AlatpayGateway.php`, add the imports:

```php
use App\Domain\Payments\Alatpay\Data\StaticAccountProvisionResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
```

Add to the interface body:

```php
    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse;

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse;

    /** @return array<int, \App\Domain\Payments\Alatpay\Data\StaticAccountTransaction> */
    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array;
```

- [ ] **Step 5: Implement in FakeAlatpayGateway**

In `backend/app/Domain/Payments/Alatpay/Gateways/FakeAlatpayGateway.php`, add imports:

```php
use App\Domain\Payments\Alatpay\Data\StaticAccountProvisionResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
```

Add these properties near the existing `$transactions`/`$transfers` arrays:

```php
    private bool $provisionImmediate = false;

    /** @var array<string, array{accountNumber: ?string, otpTrackingId: ?string}> */
    private array $staticWallets = [];

    /** @var array<string, array<int, StaticAccountTransaction>> keyed by account number */
    private array $staticTransactions = [];
```

Add these methods:

```php
    public function provisionReturnsImmediately(bool $immediate): void
    {
        $this->provisionImmediate = $immediate;
    }

    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse
    {
        $staticWalletId = 'SW-'.$request->reference;
        $accountNumber = '04'.substr(preg_replace('/\D/', '', $request->reference).'00000000', 0, 8);

        if ($this->provisionImmediate) {
            $this->staticWallets[$staticWalletId] = ['accountNumber' => $accountNumber, 'otpTrackingId' => null];

            return new StaticAccountProvisionResponse($staticWalletId, null, $accountNumber, 'RETON STATIC');
        }

        $this->staticWallets[$staticWalletId] = ['accountNumber' => $accountNumber, 'otpTrackingId' => 'OTP-'.$request->reference];

        return new StaticAccountProvisionResponse($staticWalletId, 'OTP-'.$request->reference, null, null);
    }

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        if ($request->otp !== '123456') {
            throw AlatpayException::requestFailed('verifyStaticAccount', 400);
        }

        $wallet = $this->staticWallets[$request->staticWalletId] ?? null;

        if ($wallet === null || $wallet['accountNumber'] === null) {
            throw AlatpayException::requestFailed('verifyStaticAccount', 404);
        }

        return new StaticAccountResponse(
            providerReference: $request->staticWalletId,
            accountNumber: $wallet['accountNumber'],
            accountName: 'RETON STATIC',
        );
    }

    public function recordStaticTransaction(string $accountNumber, int $status, float $amountMajor, string $transactionId): void
    {
        $this->staticTransactions[$accountNumber][] = new StaticAccountTransaction(
            transactionId: $transactionId,
            status: $status,
            accountNumber: $accountNumber,
            amountMajor: $amountMajor,
            narration: 'ALAT TRANSFER',
            notificationEmail: null,
        );
    }

    public function markStaticFunded(string $accountNumber, float $amountMajor, string $transactionId): void
    {
        $this->recordStaticTransaction($accountNumber, 1, $amountMajor, $transactionId);
    }

    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array
    {
        return $this->staticTransactions[$accountNumber] ?? [];
    }
```

Note: `AlatpayException` is already imported in this file.

- [ ] **Step 6: Implement in HttpAlatpayGateway**

In `backend/app/Domain/Payments/Alatpay/Gateways/HttpAlatpayGateway.php`, add the same five DTO imports as the contract plus `StaticAccountTransaction`. Add these methods (endpoint paths follow docs.alatpay.ng/static-wallet; the transactions path is best-effort and confirmed at integration time — defensive `data.*` mapping mirrors `createCollection`):

```php
    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse
    {
        $response = $this->client()->post('/alatpay-wallet/api/v1/staticaccount', [
            'businessId' => config('services.alatpay.business_id'),
            'staticWalletType' => $request->walletType,
            'bvn' => $request->bvn,
            'email' => $request->email,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('provisionStaticAccount', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        $staticWalletId = (string) ($data['id'] ?? '');

        if ($staticWalletId === '') {
            throw AlatpayException::requestFailed('provisionStaticAccount', $response->status());
        }

        return new StaticAccountProvisionResponse(
            staticWalletId: $staticWalletId,
            otpTrackingId: isset($data['otpTrackingId']) ? (string) $data['otpTrackingId'] : null,
            accountNumber: isset($data['accountNumber']) ? (string) $data['accountNumber'] : null,
            accountName: isset($data['accountName']) ? (string) $data['accountName'] : null,
        );
    }

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        $response = $this->client()->post('/alatpay-wallet/api/v1/staticaccount/validateAndCreate', [
            'staticWalletId' => $request->staticWalletId,
            'businessId' => config('services.alatpay.business_id'),
            'otp' => $request->otp,
            'trackingId' => $request->trackingId,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('verifyStaticAccount', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        $accountNumber = (string) ($data['accountNumber'] ?? '');

        if ($accountNumber === '') {
            throw AlatpayException::requestFailed('verifyStaticAccount', $response->status());
        }

        return new StaticAccountResponse(
            providerReference: (string) ($data['id'] ?? $request->staticWalletId),
            accountNumber: $accountNumber,
            accountName: isset($data['accountName']) ? (string) $data['accountName'] : null,
        );
    }

    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array
    {
        $response = $this->client()->get('/alatpay-wallet/api/v1/staticaccount/transactions', [
            'businessId' => config('services.alatpay.business_id'),
            'accountNumber' => $accountNumber,
            'pageNumber' => $page,
            'limit' => $limit,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('fetchStaticAccountTransactions', $response->status());
        }

        $rows = (array) $response->json('staticAccountTransactionResponses', $response->json('data.staticAccountTransactionResponses', []));

        return array_map(static fn (array $row): StaticAccountTransaction => new StaticAccountTransaction(
            transactionId: (string) ($row['staticAccountTransactionId'] ?? ''),
            status: (int) ($row['status'] ?? 0),
            accountNumber: (string) ($row['accountNumber'] ?? $accountNumber),
            amountMajor: (float) ($row['amount'] ?? 0),
            narration: isset($row['narration']) ? (string) $row['narration'] : null,
            notificationEmail: isset($row['notificationEmail']) ? (string) $row['notificationEmail'] : null,
        ), $rows);
    }
```

- [ ] **Step 7: Add the `business_bvn` config key**

In `backend/config/services.php`, inside the `'alatpay' => [...]` block (after `'business_id'`):

```php
        'business_bvn' => env('ALATPAY_BUSINESS_BVN'),
```

- [ ] **Step 8: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/FakeStaticAccountGatewayTest.php`
Expected: PASS (4 examples).

- [ ] **Step 9: Commit**

```bash
git add backend/app/Domain/Payments/Alatpay backend/config/services.php \
        backend/tests/Feature/Payments/FakeStaticAccountGatewayTest.php
git commit -m "feat(payments): AlatPay static-account gateway methods (provision, verify, transactions)"
```

---

### Task 3: StaticAccountService — provisioning (provision + verify)

**Files:**
- Create: `backend/app/Domain/Payments/Services/StaticAccountService.php`
- Test: `backend/tests/Feature/Payments/StaticAccountProvisioningTest.php`

**Interfaces:**
- Consumes: `AlatpayGateway::provisionStaticAccount/verifyStaticAccount`, `StaticAccount`, `StaticWalletType`, `StaticAccountStatus`, `Wallet`, `User`.
- Produces (on `StaticAccountService`):
  - `provision(User $user, Wallet $wallet, StaticWalletType $type, ?string $bvn = null): StaticAccount`
  - `verify(StaticAccount $account, string $otp): StaticAccount`
  - (Task 4 adds `poll()` and `credit()` to this same class.)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $this->gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

function staticAccounts(): StaticAccountService
{
    return app(StaticAccountService::class);
}

/** @return array{0: User, 1: \App\Domain\Wallet\Models\Wallet} */
function staticOwner(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('provisions an individual static account in pending_otp state', function () {
    [$user, $wallet] = staticOwner();

    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    expect($account->status)->toBe(StaticAccountStatus::PendingOtp)
        ->and($account->wallet_type)->toBe(StaticWalletType::Individual)
        ->and($account->provider_reference)->not->toBeNull()
        ->and($account->otp_tracking_id)->not->toBeNull()
        ->and($account->account_number)->toBeNull();
});

it('verifies an account with the correct OTP and activates it', function () {
    [$user, $wallet] = staticOwner();
    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    $account = staticAccounts()->verify($account, '123456');

    expect($account->status)->toBe(StaticAccountStatus::Active)
        ->and($account->account_number)->not->toBeEmpty();
});

it('leaves the account pending when the OTP is wrong', function () {
    [$user, $wallet] = staticOwner();
    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    try {
        staticAccounts()->verify($account, '000000');
    } catch (AlatpayException) {
        // expected
    }

    expect($account->fresh()->status)->toBe(StaticAccountStatus::PendingOtp);
});

it('activates immediately when the provider returns an account number without an OTP', function () {
    [$user, $wallet] = staticOwner();
    $this->gateway->provisionReturnsImmediately(true);

    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Collection, null);

    expect($account->status)->toBe(StaticAccountStatus::Active)
        ->and($account->account_number)->not->toBeEmpty();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountProvisioningTest.php`
Expected: FAIL — `StaticAccountService` not found.

- [ ] **Step 3: Implement the service (provisioning half)**

`backend/app/Domain/Payments/Services/StaticAccountService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Provisions and funds permanent AlatPay static accounts.
 *
 * Provisioning is a two-step OTP flow (provision -> verify), except when the
 * provider returns an account number immediately (e.g. a Collection wallet that
 * needs no OTP), in which case the account is activated on provision. Funding is
 * poll-driven: see poll()/credit() — every credit flows through the audited
 * WalletService ledger path.
 */
class StaticAccountService
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly \App\Domain\Wallet\Services\WalletService $wallets,
    ) {}

    public function provision(User $user, Wallet $wallet, StaticWalletType $type, ?string $bvn = null): StaticAccount
    {
        $bvn = $type === StaticWalletType::Collection
            ? (string) config('services.alatpay.business_bvn')
            : (string) $bvn;

        $account = StaticAccount::create([
            'wallet_id' => $wallet->getKey(),
            'user_id' => $user->getKey(),
            'provider' => self::PROVIDER,
            'wallet_type' => $type,
            'status' => StaticAccountStatus::PendingOtp,
            'email' => $user->email,
        ]);

        $response = $this->gateway->provisionStaticAccount(new StaticAccountRequest(
            walletType: $type->providerCode(),
            bvn: $bvn,
            email: (string) $user->email,
            reference: 'SA-'.Str::upper((string) Str::ulid()),
        ));

        $attributes = [
            'provider_reference' => $response->staticWalletId,
            'otp_tracking_id' => $response->otpTrackingId,
        ];

        // No OTP required: the provider already returned a live account number.
        if ($response->otpTrackingId === null && $response->accountNumber !== null) {
            $attributes['account_number'] = $response->accountNumber;
            $attributes['account_name'] = $response->accountName;
            $attributes['status'] = StaticAccountStatus::Active;
        }

        $account->update($attributes);

        return $account->refresh();
    }

    public function verify(StaticAccount $account, string $otp): StaticAccount
    {
        if ($account->isActive()) {
            return $account;
        }

        $response = $this->gateway->verifyStaticAccount(new StaticAccountVerifyRequest(
            staticWalletId: (string) $account->provider_reference,
            otp: $otp,
            trackingId: (string) $account->otp_tracking_id,
        ));

        $account->update([
            'account_number' => $response->accountNumber,
            'account_name' => $response->accountName,
            'bank_name' => $response->bankName,
            'status' => StaticAccountStatus::Active,
        ]);

        return $account->refresh();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountProvisioningTest.php`
Expected: PASS (4 examples).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Payments/Services/StaticAccountService.php \
        backend/tests/Feature/Payments/StaticAccountProvisioningTest.php
git commit -m "feat(payments): StaticAccountService provisioning (provision + OTP verify)"
```

---

### Task 4: StaticAccountService — poll-driven funding (poll + credit)

**Files:**
- Modify: `backend/app/Domain/Payments/Services/StaticAccountService.php`
- Test: `backend/tests/Feature/Payments/StaticAccountFundingTest.php`

**Interfaces:**
- Consumes: `AlatpayGateway::fetchStaticAccountTransactions`, `WalletService::fund`, `Deposit`, `DepositStatus`, `StaticAccount`, `StaticAccountTransaction`, `Money`.
- Produces (on `StaticAccountService`): `poll(StaticAccount $account): int` (number of new credits applied).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $this->gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

/** @return array{0: \App\Domain\Payments\Models\StaticAccount, 1: \App\Domain\Wallet\Models\Wallet} */
function activeStaticAccount(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $svc = app(StaticAccountService::class);
    $account = $svc->provision($user, $wallet, StaticWalletType::Individual, '12345678901');
    $account = $svc->verify($account, '123456');

    return [$account, $wallet];
}

it('credits the wallet for a new successful transaction, converting major to minor units', function () {
    [$account, $wallet] = activeStaticAccount();
    $this->gateway->markStaticFunded($account->account_number, 100.00, 'txn-1');

    $credited = app(StaticAccountService::class)->poll($account);

    expect($credited)->toBe(1)
        ->and($wallet->fresh()->balance)->toBe(10000) // NGN 100.00 -> 10000 kobo
        ->and(Deposit::where('provider', 'alatpay_static')->where('provider_reference', 'txn-1')->count())->toBe(1);
});

it('does not double-credit when the same transaction is polled twice', function () {
    [$account, $wallet] = activeStaticAccount();
    $this->gateway->markStaticFunded($account->account_number, 100.00, 'txn-dup');

    app(StaticAccountService::class)->poll($account);
    $second = app(StaticAccountService::class)->poll($account->fresh());

    expect($second)->toBe(0)
        ->and($wallet->fresh()->balance)->toBe(10000);
});

it('ignores transactions that are not successful', function () {
    [$account, $wallet] = activeStaticAccount();
    // status 2 = not successful; status 1 = successful. Only the successful one credits.
    $this->gateway->recordStaticTransaction($account->account_number, 2, 999.00, 'txn-failed');
    $this->gateway->recordStaticTransaction($account->account_number, 1, 50.00, 'txn-ok');

    $credited = app(StaticAccountService::class)->poll($account);

    expect($credited)->toBe(1)
        ->and($wallet->fresh()->balance)->toBe(5000); // only the status==1 txn credited
});

it('stamps last_polled_at', function () {
    [$account] = activeStaticAccount();

    app(StaticAccountService::class)->poll($account);

    expect($account->fresh()->last_polled_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountFundingTest.php`
Expected: FAIL — `poll()` not defined.

- [ ] **Step 3: Add `poll()` and `credit()` to the service**

In `backend/app/Domain/Payments/Services/StaticAccountService.php`, add imports:

```php
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Models\Deposit;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
```

(`StaticAccountStatus` is already imported from Task 3 — do not duplicate.)

Add a class constant for the static provider key near `PROVIDER`:

```php
    private const STATIC_PROVIDER = 'alatpay_static';
```

Add these methods:

```php
    public function poll(StaticAccount $account): int
    {
        if (! $account->isActive() || $account->account_number === null) {
            return 0;
        }

        $credited = 0;

        foreach ($this->gateway->fetchStaticAccountTransactions($account->account_number) as $txn) {
            if (! $txn->isSuccessful() || $txn->amountMinor() <= 0) {
                continue;
            }

            $alreadyRecorded = Deposit::where('provider', self::STATIC_PROVIDER)
                ->where('provider_reference', $txn->transactionId)
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            $this->credit($account, $txn);
            $credited++;
        }

        $account->update(['last_polled_at' => now()]);

        return $credited;
    }

    private function credit(StaticAccount $account, StaticAccountTransaction $txn): void
    {
        DB::transaction(function () use ($account, $txn): void {
            $wallet = Wallet::findOrFail($account->wallet_id);
            $amount = Money::of($txn->amountMinor(), $wallet->currency);

            $deposit = Deposit::create([
                'reference' => 'SDEP-'.$txn->transactionId,
                'user_id' => $account->user_id,
                'wallet_id' => $account->wallet_id,
                'provider' => self::STATIC_PROVIDER,
                'provider_reference' => $txn->transactionId,
                'status' => DepositStatus::Pending,
                'amount' => $txn->amountMinor(),
                'currency' => $wallet->currency,
                'metadata' => [
                    'channel' => 'static_account',
                    'static_account_id' => $account->id,
                    'narration' => $txn->narration,
                ],
            ]);

            $transaction = $this->wallets->fund(
                $wallet,
                $amount,
                $txn->transactionId, // ledger idempotency key
                ['deposit_id' => $deposit->id, 'provider' => self::STATIC_PROVIDER],
            );

            $deposit->update([
                'status' => DepositStatus::Completed,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);
        });
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountFundingTest.php`
Expected: PASS (4 examples).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Payments/Services/StaticAccountService.php \
        backend/tests/Feature/Payments/StaticAccountFundingTest.php
git commit -m "feat(payments): StaticAccountService poll-driven funding (credit via ledger, dedup)"
```

---

### Task 5: HTTP API (requests, resource, policy, controller, routes)

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/Payment/ProvisionStaticAccountRequest.php`
- Create: `backend/app/Http/Requests/Api/V1/Payment/VerifyStaticAccountRequest.php`
- Create: `backend/app/Http/Resources/Api/V1/StaticAccountResource.php`
- Create: `backend/app/Domain/Payments/Policies/StaticAccountPolicy.php`
- Create: `backend/app/Http/Controllers/Api/V1/Payment/StaticAccountController.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` (register policy)
- Modify: `backend/routes/api/v1.php` (routes)
- Test: `backend/tests/Feature/Payments/StaticAccountApiTest.php`

**Interfaces:**
- Consumes: `StaticAccountService::provision/verify`, `StaticAccount`, `StaticWalletType`, `Wallet`, `ApiResponse`.
- Produces: REST endpoints under `/api/v1/static-accounts` (auth); `StaticAccountPolicy::view` (owner-only).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway());
});

/** @return array{0: User, 1: \App\Domain\Wallet\Models\Wallet} */
function apiStaticOwner(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('provisions an individual static account and returns pending_otp', function () {
    [$user, $wallet] = apiStaticOwner();

    $this->actingAs($user)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'individual',
        'bvn' => '12345678901',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending_otp')
        ->assertJsonPath('data.wallet_type', 'individual');
});

it('requires a bvn for individual wallets', function () {
    [$user, $wallet] = apiStaticOwner();

    $this->actingAs($user)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'individual',
    ])->assertStatus(422);
});

it('forbids provisioning against a wallet the user does not own', function () {
    [, $wallet] = apiStaticOwner();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'collection',
    ])->assertStatus(403);
});

it('verifies a pending account via OTP and returns the account number', function () {
    [$user, $wallet] = apiStaticOwner();
    $account = app(StaticAccountService::class)->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    $this->actingAs($user)->postJson('/api/v1/static-accounts/'.$account->id.'/verify', [
        'otp' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['account_number']]);
});

it('lists only the callers static accounts', function () {
    [$user, $wallet] = apiStaticOwner();
    app(StaticAccountService::class)->provision($user, $wallet, StaticWalletType::Individual, '12345678901');
    [$other, $otherWallet] = apiStaticOwner();
    app(StaticAccountService::class)->provision($other, $otherWallet, StaticWalletType::Individual, '12345678901');

    $this->actingAs($user)->getJson('/api/v1/static-accounts')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('forbids viewing someone elses static account', function () {
    [$user, $wallet] = apiStaticOwner();
    $account = app(StaticAccountService::class)->provision($user, $wallet, StaticWalletType::Individual, '12345678901');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->getJson('/api/v1/static-accounts/'.$account->id)
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountApiTest.php`
Expected: FAIL — route not defined (404).

- [ ] **Step 3: Create the form requests**

`backend/app/Http/Requests/Api/V1/Payment/ProvisionStaticAccountRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionStaticAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
            'wallet_type' => ['required', 'in:individual,collection'],
            'bvn' => ['required_if:wallet_type,individual', 'prohibited_unless:wallet_type,individual', 'nullable', 'digits:11'],
        ];
    }
}
```

`backend/app/Http/Requests/Api/V1/Payment/VerifyStaticAccountRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class VerifyStaticAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp' => ['required', 'digits:6'],
        ];
    }
}
```

- [ ] **Step 4: Create the resource**

`backend/app/Http/Resources/Api/V1/StaticAccountResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\StaticAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaticAccount
 */
class StaticAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'wallet_type' => $this->wallet_type->value,
            'status' => $this->status->value,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'bank_name' => $this->bank_name,
            'created_at' => $this->created_at,
        ];
    }
}
```

- [ ] **Step 5: Create the policy**

`backend/app/Domain/Payments/Policies/StaticAccountPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\StaticAccount;
use App\Models\User;

class StaticAccountPolicy
{
    public function view(User $user, StaticAccount $staticAccount): bool
    {
        return (string) $staticAccount->user_id === (string) $user->getKey();
    }
}
```

- [ ] **Step 6: Create the controller**

`backend/app/Http/Controllers/Api/V1/Payment/StaticAccountController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\ProvisionStaticAccountRequest;
use App\Http\Requests\Api\V1\Payment\VerifyStaticAccountRequest;
use App\Http\Resources\Api\V1\StaticAccountResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaticAccountController extends Controller
{
    public function __construct(private readonly StaticAccountService $accounts) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $accounts = StaticAccount::where('user_id', $user->getKey())->latest()->paginate(20);

        return ApiResponse::paginated($accounts, StaticAccountResource::collection($accounts));
    }

    public function store(ProvisionStaticAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $account = $this->accounts->provision(
            $user,
            $wallet,
            StaticWalletType::from($request->string('wallet_type')->toString()),
            $request->input('bvn'),
        );

        return ApiResponse::created(new StaticAccountResource($account), 'Static account provisioning started.');
    }

    public function show(Request $request, StaticAccount $staticAccount): JsonResponse
    {
        $this->authorize('view', $staticAccount);

        return ApiResponse::success(new StaticAccountResource($staticAccount));
    }

    public function verify(VerifyStaticAccountRequest $request, StaticAccount $staticAccount): JsonResponse
    {
        $this->authorize('view', $staticAccount);

        $account = $this->accounts->verify($staticAccount, $request->string('otp')->toString());

        return ApiResponse::success(new StaticAccountResource($account), 'Static account activated.');
    }
}
```

- [ ] **Step 7: Register the policy**

In `backend/app/Providers/AppServiceProvider.php`, add imports near the other policy imports:

```php
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Policies\StaticAccountPolicy;
```

In `boot()`, after the existing `Gate::policy(...)` lines:

```php
        Gate::policy(StaticAccount::class, StaticAccountPolicy::class);
```

- [ ] **Step 8: Add the routes**

In `backend/routes/api/v1.php`, add the import:

```php
use App\Http\Controllers\Api\V1\Payment\StaticAccountController;
```

Add the authenticated group (alongside the `deposits` / `payment-requests` groups):

```php
Route::middleware('auth:sanctum')->prefix('static-accounts')->name('static-accounts.')->group(function (): void {
    Route::get('/', [StaticAccountController::class, 'index'])->name('index');
    Route::post('/', [StaticAccountController::class, 'store'])->name('store');
    Route::get('{staticAccount}', [StaticAccountController::class, 'show'])->name('show');
    Route::post('{staticAccount}/verify', [StaticAccountController::class, 'verify'])->name('verify');
});
```

- [ ] **Step 9: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/StaticAccountApiTest.php`
Expected: PASS (6 examples).

- [ ] **Step 10: Commit**

```bash
git add backend/app/Http/Requests/Api/V1/Payment/ProvisionStaticAccountRequest.php \
        backend/app/Http/Requests/Api/V1/Payment/VerifyStaticAccountRequest.php \
        backend/app/Http/Resources/Api/V1/StaticAccountResource.php \
        backend/app/Domain/Payments/Policies/StaticAccountPolicy.php \
        backend/app/Http/Controllers/Api/V1/Payment/StaticAccountController.php \
        backend/app/Providers/AppServiceProvider.php \
        backend/routes/api/v1.php \
        backend/tests/Feature/Payments/StaticAccountApiTest.php
git commit -m "feat(payments): static-accounts API (provision, verify, list, show)"
```

---

### Task 6: Scheduled poll command

**Files:**
- Create: `backend/app/Console/Commands/PollStaticAccounts.php`
- Test: `backend/tests/Feature/Payments/PollStaticAccountsCommandTest.php`

**Interfaces:**
- Consumes: `StaticAccountService::poll`, `StaticAccount`, `StaticAccountStatus`, `FakeAlatpayGateway::markStaticFunded`.
- Produces: artisan command `static-accounts:poll`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('credits funded transactions across active static accounts', function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $gateway);

    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $svc = app(StaticAccountService::class);
    $account = $svc->verify($svc->provision($user, $wallet, StaticWalletType::Individual, '12345678901'), '123456');

    $gateway->markStaticFunded($account->account_number, 75.00, 'txn-cmd');

    $this->artisan('static-accounts:poll')->assertExitCode(0);

    expect($wallet->fresh()->balance)->toBe(7500);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/PollStaticAccountsCommandTest.php`
Expected: FAIL — command `static-accounts:poll` not found.

- [ ] **Step 3: Create the command**

`backend/app/Console/Commands/PollStaticAccounts.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\StaticAccountService;
use Illuminate\Console\Command;

/**
 * Polls active AlatPay static accounts for new inbound payments and credits the
 * owning wallets. This is the primary funding path for static accounts (AlatPay
 * exposes a transactions endpoint rather than a webhook for these).
 */
class PollStaticAccounts extends Command
{
    protected $signature = 'static-accounts:poll';

    protected $description = 'Poll active AlatPay static accounts and credit new inbound payments';

    public function handle(StaticAccountService $accounts): int
    {
        $credited = 0;

        StaticAccount::query()
            ->where('status', StaticAccountStatus::Active->value)
            ->whereNotNull('account_number')
            ->orderBy('last_polled_at')
            ->each(function (StaticAccount $account) use ($accounts, &$credited): void {
                $credited += $accounts->poll($account);
            });

        $this->info("Credited {$credited} static-account payment(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/PollStaticAccountsCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full backend suite + static analysis**

Run: `./vendor/bin/pest`
Expected: PASS (entire suite green).

Run: `./vendor/bin/phpstan analyse` (or the project's `composer stan` script if present)
Expected: no new errors.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Console/Commands/PollStaticAccounts.php \
        backend/tests/Feature/Payments/PollStaticAccountsCommandTest.php
git commit -m "feat(payments): static-accounts:poll command for poll-driven funding"
```

---

## Self-Review

**Spec coverage:**
- §3 provisioning (2-step OTP, immediate-active fallback) → Task 3.
- §4 polling → ledger credit (major→minor, dedup, three guards) → Task 4.
- §5 data model (table, two enums, model) → Task 1.
- §6 gateway extension (5 DTOs, 3 methods, Fake+Http, `business_bvn` config) → Task 2.
- §7 service (`provision`/`verify`/`poll`/`credit`) → Tasks 3 & 4.
- §8 HTTP API (provision/verify/list/show, validation, policy, resource) → Task 5.
- §9 scheduled command → Task 6.
- §10 error handling (gateway failure, wrong OTP, status!=1, amount→0, dedup) → covered by tests in Tasks 3, 4.
- §11 testing scenarios 1–9 → distributed: 1–4 Task 3 & Task 2; 5–7 Task 4; 8 Task 5; 9 Task 6.

**Deferred (per spec §1):** webhook crediting, KYC auto-select, multi-account-per-wallet, BVN persistence, de-provisioning — no tasks, intentional.

**Type consistency:** `provisionStaticAccount`/`verifyStaticAccount`/`fetchStaticAccountTransactions` signatures identical across contract (Task 2), Fake/Http (Task 2), and service (Tasks 3, 4). `StaticWalletType::providerCode()` (1/2) used in Task 2 test and Task 3 service. `StaticAccountStatus` cases (`PendingOtp`/`Active`/`Failed`) consistent across model, service, controller, command. `StaticAccountTransaction::amountMinor()` (`(int) round(amountMajor*100)`) used in Task 2 and Task 4. `provider='alatpay_static'` and idempotency key `staticAccountTransactionId` consistent between Task 4 service and its tests. Fake helpers `markStaticFunded`/`provisionReturnsImmediately` defined in Task 2 and used in Tasks 3, 4, 6.

**Placeholder scan:** none — every step has complete code or an exact command. The only deferred details are AlatPay's exact transactions endpoint path and whether Collection skips OTP (spec §12 open questions), both flagged and non-blocking because the Fake gateway drives all tests.

**Negative-path coverage:** the Fake exposes `recordStaticTransaction(accountNumber, status, amountMajor, transactionId)` so Task 4's "ignores unsuccessful transactions" test injects a real `status != 1` row alongside a successful one and asserts only the successful one credits — genuinely exercising the `isSuccessful()` filter in `poll()`. `markStaticFunded` delegates to it with `status = 1` for the happy-path tests.
