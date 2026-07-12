> **Proprietary** � Copyright 2026 RETON PTE LTD. Founder & CEO: Gabriel Rotimi Mogaji � Co-Founder: Aina Christana Olajumoke. See [LICENSE](../../../LICENSE).
>
> **Historical notes.** Early planning. For current setup, see [README](../../../README.md), [roadmap](../../../roadmap.md), and [deploy guide](../../DEPLOY.md).

# Request Money (AlatPay Payment Link) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a Reton user raise a fixed-amount AlatPay-backed payment link; when anyone pays it, credit the requester's wallet through the existing audited ledger path.

**Architecture:** A new `PaymentRequest` aggregate in the existing `App\Domain\Payments` context mirrors the `Deposit` flow. One method is added to the existing `AlatpayGateway` contract (Fake + Http). Inbound payments arrive on the existing signed AlatPay webhook, now dispatched by a new `AlatpayWebhookRouter` that admits/dedups once and routes by reference (payouts vs. payment-requests vs. deposits). Crediting reuses `WalletService::fund()`.

**Tech Stack:** Laravel 12, PHP 8.4, Pest, PostgreSQL, Sanctum. Money in minor units via `App\Support\Money\Money`.

## Global Constraints

- PHP `declare(strict_types=1);` at the top of every PHP file.
- All money is integer **minor units** (kobo); currency is `char(3)`, default `NGN`.
- No balance mutation outside the ledger — credit only via `WalletService::fund()`.
- Idempotency key for a credit = the `PaymentRequest.reference` (business ref).
- Domain code lives under `backend/app/Domain/Payments/`; HTTP under `backend/app/Http/...`.
- Tests are Pest, under `backend/tests/Feature/Payments/`, using `RefreshDatabase` and the in-memory `FakeAlatpayGateway` bound via `$this->app->instance(AlatpayGateway::class, ...)`.
- Follow existing patterns exactly (see `Deposit`, `AlatpayDepositService`, `DepositController`).
- Run commands from `backend/`. Test runner: `./vendor/bin/pest`.

---

### Task 1: PaymentRequest persistence (migration, enum, model)

**Files:**
- Create: `backend/database/migrations/2026_06_23_000100_create_payment_requests_table.php`
- Create: `backend/app/Domain/Payments/Enums/PaymentRequestStatus.php`
- Create: `backend/app/Domain/Payments/Models/PaymentRequest.php`
- Test: `backend/tests/Feature/Payments/PaymentRequestModelTest.php`

**Interfaces:**
- Produces: `PaymentRequest` model (fillable + casts below); `PaymentRequestStatus` enum with cases `Pending='pending'`, `Paid='paid'`, `Expired='expired'`, `Cancelled='cancelled'` and methods `isOpen(): bool` (true for `Pending`), `isPaid(): bool`. Model methods `isOpen(): bool`, `isPaid(): bool`, relations `requester()`, `wallet()`, `transaction()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a payment request with enum status and relations', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $request = PaymentRequest::create([
        'reference' => 'REQ-TEST1',
        'requester_user_id' => $user->getKey(),
        'wallet_id' => $wallet->getKey(),
        'provider' => 'alatpay',
        'status' => PaymentRequestStatus::Pending,
        'amount' => 250_00,
        'currency' => 'NGN',
        'title' => 'Lunch money',
    ]);

    expect($request->status)->toBe(PaymentRequestStatus::Pending)
        ->and($request->isOpen())->toBeTrue()
        ->and($request->isPaid())->toBeFalse()
        ->and($request->amount)->toBe(25000)
        ->and($request->requester->is($user))->toBeTrue()
        ->and($request->wallet->is($wallet))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/PaymentRequestModelTest.php`
Expected: FAIL — class `PaymentRequest` not found.

- [ ] **Step 3: Create the migration**

`backend/database/migrations/2026_06_23_000100_create_payment_requests_table.php`:

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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();

            $table->string('provider', 32)->default('alatpay');
            $table->string('provider_reference')->nullable()->index();

            $table->string('status', 16); // pending | paid | expired | cancelled
            $table->bigInteger('amount');
            $table->char('currency', 3);

            $table->string('title');
            $table->string('description')->nullable();
            $table->string('payment_link_url')->nullable();

            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();

            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['requester_user_id', 'status']);
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
```

- [ ] **Step 4: Create the enum**

`backend/app/Domain/Payments/Enums/PaymentRequestStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum PaymentRequestStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
```

- [ ] **Step 5: Create the model**

`backend/app/Domain/Payments/Models/PaymentRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'requester_user_id',
        'wallet_id',
        'provider',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'title',
        'description',
        'payment_link_url',
        'payer_name',
        'payer_email',
        'transaction_id',
        'metadata',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentRequestStatus::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PaymentRequestStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentRequestStatus::Paid;
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/PaymentRequestModelTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_06_23_000100_create_payment_requests_table.php \
        backend/app/Domain/Payments/Enums/PaymentRequestStatus.php \
        backend/app/Domain/Payments/Models/PaymentRequest.php \
        backend/tests/Feature/Payments/PaymentRequestModelTest.php
git commit -m "feat(payments): payment_requests table, status enum, model"
```

---

### Task 2: Gateway payment-link capability (DTOs, contract, Fake, Http)

**Files:**
- Create: `backend/app/Domain/Payments/Alatpay/Data/PaymentLinkRequest.php`
- Create: `backend/app/Domain/Payments/Alatpay/Data/PaymentLinkResponse.php`
- Modify: `backend/app/Domain/Payments/Alatpay/Contracts/AlatpayGateway.php`
- Modify: `backend/app/Domain/Payments/Alatpay/Gateways/FakeAlatpayGateway.php`
- Modify: `backend/app/Domain/Payments/Alatpay/Gateways/HttpAlatpayGateway.php`
- Test: `backend/tests/Feature/Payments/FakePaymentLinkGatewayTest.php`

**Interfaces:**
- Consumes: `App\Support\Money\Money`.
- Produces:
  - `PaymentLinkRequest(string $reference, Money $amount, string $title, string $description = '', string $customerEmail = '', ?string $redirectUrl = null, ?string $expiresAt = null)` (readonly).
  - `PaymentLinkResponse(string $providerReference, string $paymentLinkUrl, ?string $expiresAt = null)` (readonly).
  - `AlatpayGateway::createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse`.
  - `FakeAlatpayGateway`: records the link as a pending transaction keyed by `providerReference` so the existing `fetchTransaction()` / `markPaid()` helpers work for payment requests too. Provider ref format: `ALT-LINK-<reference>`; URL: `https://pay.alatpay.test/<reference>`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Support\Money\Money;

it('creates a deterministic payment link and tracks it as a pending transaction', function () {
    $gateway = new FakeAlatpayGateway();

    $response = $gateway->createPaymentLink(new PaymentLinkRequest(
        reference: 'REQ-ABC',
        amount: Money::of(250_00, 'NGN'),
        title: 'Lunch money',
        customerEmail: 'requester@example.com',
    ));

    expect($response->providerReference)->toBe('ALT-LINK-REQ-ABC')
        ->and($response->paymentLinkUrl)->toBe('https://pay.alatpay.test/REQ-ABC');

    // The link is reconcilable via the same transaction lookup deposits use.
    $gateway->markPaid($response->providerReference, 250_00);
    $remote = $gateway->fetchTransaction($response->providerReference);

    expect($remote)->not->toBeNull()
        ->and($remote->isSuccessful())->toBeTrue()
        ->and($remote->amount)->toBe(25000);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/FakePaymentLinkGatewayTest.php`
Expected: FAIL — `PaymentLinkRequest` / `createPaymentLink` not found.

- [ ] **Step 3: Create the DTOs**

`backend/app/Domain/Payments/Alatpay/Data/PaymentLinkRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

use App\Support\Money\Money;

/**
 * A request to AlatPay to mint a hosted payment link for a money request.
 */
final readonly class PaymentLinkRequest
{
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $title,
        public string $description = '',
        public string $customerEmail = '',
        public ?string $redirectUrl = null,
        public ?string $expiresAt = null,
    ) {}
}
```

`backend/app/Domain/Payments/Alatpay/Data/PaymentLinkResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * AlatPay's response to a payment-link request: the hosted URL the payer opens,
 * plus the provider-side reference used to reconcile the inbound payment.
 */
final readonly class PaymentLinkResponse
{
    public function __construct(
        public string $providerReference,
        public string $paymentLinkUrl,
        public ?string $expiresAt = null,
    ) {}
}
```

- [ ] **Step 4: Add the method to the contract**

In `backend/app/Domain/Payments/Alatpay/Contracts/AlatpayGateway.php`, add imports and the method:

```php
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkResponse;
```

Add to the interface body (after `createCollection`):

```php
    public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse;
```

- [ ] **Step 5: Implement in FakeAlatpayGateway**

In `backend/app/Domain/Payments/Alatpay/Gateways/FakeAlatpayGateway.php`, add imports:

```php
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkResponse;
```

Add this method (after `createCollection`):

```php
    public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse
    {
        $providerReference = 'ALT-LINK-'.$request->reference;

        $this->transactions[$providerReference] = [
            'currency' => $request->amount->currency,
            'amount' => $request->amount->amount,
            'status' => 'pending',
        ];

        return new PaymentLinkResponse(
            providerReference: $providerReference,
            paymentLinkUrl: 'https://pay.alatpay.test/'.$request->reference,
            expiresAt: $request->expiresAt,
        );
    }
```

- [ ] **Step 6: Implement in HttpAlatpayGateway**

In `backend/app/Domain/Payments/Alatpay/Gateways/HttpAlatpayGateway.php`, add imports:

```php
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkResponse;
```

Add this method (after `createCollection`). The endpoint path/fields follow AlatPay's *Payment Link via API* product; confirm exact keys from docs.alatpay.ng and adjust the `data.*` extraction if needed (defensive mapping mirrors `createCollection`):

```php
    public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse
    {
        $response = $this->client()->post('/payment-link/api/v1/links', [
            'businessId' => config('services.alatpay.business_id'),
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'orderId' => $request->reference,
            'title' => $request->title,
            'description' => $request->description,
            'customer' => ['email' => $request->customerEmail],
            'redirectUrl' => $request->redirectUrl,
            'expiresAt' => $request->expiresAt,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('createPaymentLink', $response->status());
        }

        $data = (array) $response->json('data', []);

        return new PaymentLinkResponse(
            providerReference: (string) ($data['transactionId'] ?? $data['linkId'] ?? $request->reference),
            paymentLinkUrl: (string) ($data['url'] ?? $data['paymentLink'] ?? ''),
            expiresAt: isset($data['expiredAt']) ? (string) $data['expiredAt'] : $request->expiresAt,
        );
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/FakePaymentLinkGatewayTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Domain/Payments/Alatpay
git add backend/tests/Feature/Payments/FakePaymentLinkGatewayTest.php
git commit -m "feat(payments): AlatPay createPaymentLink gateway method (contract, fake, http)"
```

---

### Task 3: PaymentRequestService (create, process, webhook, reconcile)

**Files:**
- Create: `backend/app/Domain/Payments/Services/PaymentRequestService.php`
- Test: `backend/tests/Feature/Payments/PaymentRequestServiceTest.php`

**Interfaces:**
- Consumes: `AlatpayGateway::createPaymentLink()`, `AlatpayGateway::fetchTransaction()`, `WalletService::fund()`, `AlatpayWebhookGuard::admit()`, `PaymentRequest`, `PaymentRequestStatus`, `WebhookEvent`, `Money`.
- Produces:
  - `create(User $user, Wallet $wallet, Money $amount, string $title, ?string $description = null): PaymentRequest`
  - `handleWebhook(string $rawPayload, ?string $signature): WebhookEvent`
  - `process(WebhookEvent $event, array $data): void` (public — called by the router in Task 4)
  - `reconcile(PaymentRequest $request): bool`
  - `cancel(PaymentRequest $request): PaymentRequest`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

function paymentRequests(): PaymentRequestService
{
    return app(PaymentRequestService::class);
}

/** @return array{0: User, 1: \App\Domain\Wallet\Models\Wallet} */
function requester(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

function signedLinkPayload(string $providerRef, int $amount, string $eventId = 'evt_link_1', string $status = 'completed'): array
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transaction.'.$status,
        'data' => [
            'reference' => $providerRef,
            'amount' => $amount,
            'currency' => 'NGN',
            'status' => $status,
            'customer' => ['name' => 'Ada Payer', 'email' => 'ada@example.com'],
        ],
    ]);

    return [$payload, app(AlatpaySignatureVerifier::class)->sign($payload)];
}

it('creates a payment request and returns a link', function () {
    [$user, $wallet] = requester();

    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    expect($request->status)->toBe(PaymentRequestStatus::Pending)
        ->and($request->amount)->toBe(25000)
        ->and($request->provider_reference)->not->toBeNull()
        ->and($request->payment_link_url)->not->toBeEmpty();
});

it('credits the requester wallet when a valid payment webhook arrives', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 25000);
    paymentRequests()->handleWebhook($payload, $signature);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($request->fresh()->transaction_id)->not->toBeNull()
        ->and($request->fresh()->payer_email)->toBe('ada@example.com')
        ->and($wallet->fresh()->balance)->toBe(25000);
});

it('processes a duplicate payment webhook only once', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 25000, 'evt_link_dup');
    paymentRequests()->handleWebhook($payload, $signature);
    paymentRequests()->handleWebhook($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(25000);
});

it('rejects a payment webhook with an invalid signature', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload] = signedLinkPayload($request->provider_reference, 25000);
    paymentRequests()->handleWebhook($payload, 'bad-signature');
})->throws(InvalidWebhookSignatureException::class);

it('does not credit when the webhook amount does not match', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 999_00);
    paymentRequests()->handleWebhook($payload, $signature);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Pending)
        ->and($wallet->fresh()->balance)->toBe(0);
});

it('cancels a pending request and then ignores a late payment', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    paymentRequests()->cancel($request);
    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Cancelled);

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 25000, 'evt_after_cancel');
    paymentRequests()->handleWebhook($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(0);
});

it('reconciles a pending request AlatPay reports as paid', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->gateway->markPaid($request->provider_reference, 25000);

    expect(paymentRequests()->reconcile($request->fresh()))->toBeTrue()
        ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($wallet->fresh()->balance)->toBe(25000);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/PaymentRequestServiceTest.php`
Expected: FAIL — `PaymentRequestService` not found.

- [ ] **Step 3: Implement the service**

`backend/app/Domain/Payments/Services/PaymentRequestService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates "request money" via AlatPay payment links.
 *
 * A requester raises a fixed-amount request; AlatPay mints a hosted link any
 * payer can settle. Inbound money is only ever credited through the audited
 * WalletService ledger path, and only after a signature-verified, de-duplicated
 * webhook (or a reconciliation confirming the payment). Three independent guards
 * prevent double-credit: the webhook-event unique key, the request status, and
 * the ledger idempotency key.
 */
class PaymentRequestService
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly WalletService $wallets,
        private readonly AlatpayWebhookGuard $guard,
    ) {}

    public function create(User $user, Wallet $wallet, Money $amount, string $title, ?string $description = null): PaymentRequest
    {
        $request = PaymentRequest::create([
            'reference' => 'REQ-'.Str::upper((string) Str::ulid()),
            'requester_user_id' => $user->getKey(),
            'wallet_id' => $wallet->getKey(),
            'provider' => self::PROVIDER,
            'status' => PaymentRequestStatus::Pending,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
            'title' => $title,
            'description' => $description,
        ]);

        $link = $this->gateway->createPaymentLink(new PaymentLinkRequest(
            reference: $request->reference,
            amount: $amount,
            title: $title,
            description: (string) $description,
            customerEmail: (string) $user->email,
        ));

        $request->update([
            'provider_reference' => $link->providerReference,
            'payment_link_url' => $link->paymentLinkUrl,
            'expires_at' => $link->expiresAt,
        ]);

        return $request->refresh();
    }

    public function handleWebhook(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->guard->admit($rawPayload, $signature);

        if (! $fresh) {
            return $event;
        }

        $this->process($event, (array) ($payload['data'] ?? []));

        return $event->refresh();
    }

    public function reconcile(PaymentRequest $request): bool
    {
        if (! $request->isOpen() || $request->provider_reference === null) {
            return false;
        }

        $remote = $this->gateway->fetchTransaction($request->provider_reference);

        if ($remote === null || ! $remote->isSuccessful() || $remote->amount !== $request->amount) {
            return false;
        }

        $this->credit($request, []);

        return true;
    }

    public function cancel(PaymentRequest $request): PaymentRequest
    {
        if ($request->isOpen()) {
            $request->update(['status' => PaymentRequestStatus::Cancelled]);
        }

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function process(WebhookEvent $event, array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $request = PaymentRequest::where('provider', self::PROVIDER)
            ->where('provider_reference', $reference)
            ->first();

        if (! $request instanceof PaymentRequest) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        if ($request->isPaid()) {
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return;
        }

        if (! $request->isOpen()) {
            // Cancelled or expired: no longer collectible.
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        $succeeded = ($data['status'] ?? null) === 'completed'
            && (int) ($data['amount'] ?? 0) === $request->amount
            && (string) ($data['currency'] ?? '') === $request->currency;

        if (! $succeeded) {
            $event->update(['status' => 'failed', 'processed_at' => now()]);

            return;
        }

        $this->credit($request, (array) ($data['customer'] ?? []));
        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function credit(PaymentRequest $request, array $customer): void
    {
        DB::transaction(function () use ($request, $customer): void {
            $wallet = Wallet::findOrFail($request->wallet_id);

            $transaction = $this->wallets->fund(
                $wallet,
                Money::of($request->amount, $request->currency),
                $request->reference, // ledger idempotency key
                ['payment_request_id' => $request->id, 'provider' => self::PROVIDER],
            );

            $request->update([
                'status' => PaymentRequestStatus::Paid,
                'transaction_id' => $transaction->id,
                'payer_name' => isset($customer['name']) ? (string) $customer['name'] : $request->payer_name,
                'payer_email' => isset($customer['email']) ? (string) $customer['email'] : $request->payer_email,
                'paid_at' => now(),
            ]);
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/PaymentRequestServiceTest.php`
Expected: PASS (all 7 examples).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Domain/Payments/Services/PaymentRequestService.php \
        backend/tests/Feature/Payments/PaymentRequestServiceTest.php
git commit -m "feat(payments): PaymentRequestService (create, webhook credit, reconcile, cancel)"
```

---

### Task 4: Webhook router (dispatch collections to deposits vs. payment requests)

**Files:**
- Modify: `backend/app/Domain/Payments/Services/AlatpayDepositService.php` (make `process` public)
- Modify: `backend/app/Domain/Payments/Services/PayoutService.php` (make `process` public)
- Create: `backend/app/Domain/Payments/Services/AlatpayWebhookRouter.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Payment/AlatpayWebhookController.php`
- Test: `backend/tests/Feature/Payments/AlatpayWebhookRouterTest.php`

**Interfaces:**
- Consumes: `AlatpayWebhookGuard::admit()`, `AlatpayDepositService::process()`, `PayoutService::process()`, `PaymentRequestService::process()`, `PaymentRequest`.
- Produces: `AlatpayWebhookRouter::handle(string $rawPayload, ?string $signature): WebhookEvent`.
- Note: `AlatpayDepositService::process()` and `PayoutService::process()` change visibility from `private` to `public`. Their existing signatures stay `process(WebhookEvent $event, array $data): void`. Existing `handleWebhook()` methods remain unchanged (used by their own tests).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Payments\Services\AlatpayWebhookRouter;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway());
});

function router(): AlatpayWebhookRouter
{
    return app(AlatpayWebhookRouter::class);
}

function signedCollection(string $providerRef, int $amount, string $eventId): array
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transaction.completed',
        'data' => ['reference' => $providerRef, 'amount' => $amount, 'currency' => 'NGN', 'status' => 'completed'],
    ]);

    return [$payload, app(AlatpaySignatureVerifier::class)->sign($payload)];
}

it('routes a payment-request collection to the request handler', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch');

    [$payload, $signature] = signedCollection($request->provider_reference, 25000, 'evt_router_req');
    router()->handle($payload, $signature);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($wallet->fresh()->balance)->toBe(25000);
});

it('routes a deposit collection to the deposit handler', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $deposit = app(AlatpayDepositService::class)->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload, $signature] = signedCollection($deposit->provider_reference, 50000, 'evt_router_dep');
    router()->handle($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(50000);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/AlatpayWebhookRouterTest.php`
Expected: FAIL — `AlatpayWebhookRouter` not found.

- [ ] **Step 3: Make `process` public in AlatpayDepositService**

In `backend/app/Domain/Payments/Services/AlatpayDepositService.php`, change:

```php
    private function process(WebhookEvent $event, array $data): void
```
to:
```php
    public function process(WebhookEvent $event, array $data): void
```

- [ ] **Step 4: Make `process` public in PayoutService**

In `backend/app/Domain/Payments/Services/PayoutService.php`, change:

```php
    private function process(WebhookEvent $event, array $data): void
```
to:
```php
    public function process(WebhookEvent $event, array $data): void
```

- [ ] **Step 5: Create the router**

`backend/app/Domain/Payments/Services/AlatpayWebhookRouter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Support\Str;

/**
 * The single entry point for inbound AlatPay webhooks. Admits and de-duplicates
 * each event exactly once via the guard, then dispatches by intent:
 *  - transfer.* events settle payouts;
 *  - collection events that match a payment request credit that request;
 *  - all other collections credit deposits.
 *
 * Routing by matching the provider reference (not just the event type) is what
 * lets payment-link payments and wallet-funding deposits share one webhook URL.
 */
class AlatpayWebhookRouter
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayWebhookGuard $guard,
        private readonly AlatpayDepositService $deposits,
        private readonly PayoutService $payouts,
        private readonly PaymentRequestService $paymentRequests,
    ) {}

    public function handle(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->guard->admit($rawPayload, $signature);

        if (! $fresh) {
            return $event;
        }

        /** @var array<string, mixed> $data */
        $data = (array) ($payload['data'] ?? []);
        $type = (string) ($payload['type'] ?? '');

        if (Str::startsWith($type, 'transfer')) {
            $this->payouts->process($event, $data);

            return $event->refresh();
        }

        $reference = (string) ($data['reference'] ?? '');
        $isPaymentRequest = $reference !== '' && PaymentRequest::query()
            ->where('provider', self::PROVIDER)
            ->where('provider_reference', $reference)
            ->exists();

        if ($isPaymentRequest) {
            $this->paymentRequests->process($event, $data);
        } else {
            $this->deposits->process($event, $data);
        }

        return $event->refresh();
    }
}
```

- [ ] **Step 6: Wire the controller to the router**

Replace the body of `backend/app/Http/Controllers/Api/V1/Payment/AlatpayWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Services\AlatpayWebhookRouter;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) AlatPay webhook receiver. Trust is established by the
 * HMAC signature, verified inside the router/guard. The router dispatches each
 * event to the owning domain (payouts, payment requests, or deposits).
 */
class AlatpayWebhookController extends Controller
{
    public function __construct(private readonly AlatpayWebhookRouter $router) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Alatpay-Signature');
        $signature = is_string($signature) ? $signature : null;

        $this->router->handle($request->getContent(), $signature);

        return ApiResponse::success(null, 'Webhook received.');
    }
}
```

- [ ] **Step 7: Run the router test AND the existing payment suite (no regressions)**

Run: `./vendor/bin/pest tests/Feature/Payments`
Expected: PASS — new router tests plus all existing deposit/payout/API tests stay green.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Domain/Payments/Services/AlatpayWebhookRouter.php \
        backend/app/Domain/Payments/Services/AlatpayDepositService.php \
        backend/app/Domain/Payments/Services/PayoutService.php \
        backend/app/Http/Controllers/Api/V1/Payment/AlatpayWebhookController.php \
        backend/tests/Feature/Payments/AlatpayWebhookRouterTest.php
git commit -m "feat(payments): AlatpayWebhookRouter dispatches collections to deposits vs payment requests"
```

---

### Task 5: HTTP API (request, resources, policy, controller, routes)

**Files:**
- Create: `backend/app/Http/Requests/Api/V1/Payment/CreatePaymentRequestRequest.php`
- Create: `backend/app/Http/Resources/Api/V1/PaymentRequestResource.php`
- Create: `backend/app/Http/Resources/Api/V1/PublicPaymentRequestResource.php`
- Create: `backend/app/Domain/Payments/Policies/PaymentRequestPolicy.php`
- Create: `backend/app/Http/Controllers/Api/V1/Payment/PaymentRequestController.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` (register policy)
- Modify: `backend/routes/api/v1.php` (routes)
- Test: `backend/tests/Feature/Payments/PaymentRequestApiTest.php`

**Interfaces:**
- Consumes: `PaymentRequestService::create()`, `PaymentRequestService::cancel()`, `PaymentRequest`, `Wallet`, `Money`, `ApiResponse`.
- Produces: REST endpoints under `/api/v1/payment-requests` (auth) + `/api/v1/pay/{reference}` (public). `PaymentRequestPolicy::view` and `::cancel` (owner-only).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway());
});

/** @return array{0: User, 1: \App\Domain\Wallet\Models\Wallet} */
function apiRequester(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('creates a payment request and returns the link', function () {
    [$user, $wallet] = apiRequester();

    $this->actingAs($user)->postJson('/api/v1/payment-requests', [
        'wallet_id' => $wallet->id,
        'amount' => 250_00,
        'title' => 'Lunch money',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonStructure(['data' => ['reference', 'payment_link_url']]);
});

it('forbids creating a request against a wallet the user does not own', function () {
    [, $wallet] = apiRequester();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/payment-requests', [
        'wallet_id' => $wallet->id,
        'amount' => 250_00,
        'title' => 'Nope',
    ])->assertStatus(403);
});

it('validates the create payload', function () {
    [$user] = apiRequester();

    $this->actingAs($user)->postJson('/api/v1/payment-requests', [
        'amount' => 0,
    ])->assertStatus(422);
});

it('lists only the callers own requests', function () {
    [$user, $wallet] = apiRequester();
    app(PaymentRequestService::class)->create($user, $wallet, Money::of(100_00, 'NGN'), 'Mine');
    [$other, $otherWallet] = apiRequester();
    app(PaymentRequestService::class)->create($other, $otherWallet, Money::of(100_00, 'NGN'), 'Theirs');

    $this->actingAs($user)->getJson('/api/v1/payment-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows a public pay page without auth', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->getJson('/api/v1/pay/'.$request->reference)
        ->assertOk()
        ->assertJsonPath('data.title', 'Lunch money')
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['payment_link_url']]);
});

it('cancels a pending request', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->actingAs($user)->postJson('/api/v1/payment-requests/'.$request->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Cancelled);
});

it('forbids cancelling someone elses request', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/payment-requests/'.$request->id.'/cancel')
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/PaymentRequestApiTest.php`
Expected: FAIL — route `/api/v1/payment-requests` not defined (404).

- [ ] **Step 3: Create the form request**

`backend/app/Http/Requests/Api/V1/Payment/CreatePaymentRequestRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequestRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 4: Create the resources**

`backend/app/Http/Resources/Api/V1/PaymentRequestResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentRequest
 */
class PaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'title' => $this->title,
            'description' => $this->description,
            'payment_link_url' => $this->payment_link_url,
            'payer_name' => $this->payer_name,
            'payer_email' => $this->payer_email,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
```

`backend/app/Http/Resources/Api/V1/PublicPaymentRequestResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payer-facing view of a payment request — no requester PII.
 *
 * @mixin PaymentRequest
 */
class PublicPaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'title' => $this->title,
            'description' => $this->description,
            'payment_link_url' => $this->payment_link_url,
        ];
    }
}
```

- [ ] **Step 5: Create the policy**

`backend/app/Domain/Payments/Policies/PaymentRequestPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\PaymentRequest;
use App\Models\User;

class PaymentRequestPolicy
{
    public function view(User $user, PaymentRequest $paymentRequest): bool
    {
        return (string) $paymentRequest->requester_user_id === (string) $user->getKey();
    }

    public function cancel(User $user, PaymentRequest $paymentRequest): bool
    {
        return (string) $paymentRequest->requester_user_id === (string) $user->getKey();
    }
}
```

- [ ] **Step 6: Create the controller**

`backend/app/Http/Controllers/Api/V1/Payment/PaymentRequestController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\CreatePaymentRequestRequest;
use App\Http\Resources\Api\V1\PaymentRequestResource;
use App\Http\Resources\Api\V1\PublicPaymentRequestResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentRequestController extends Controller
{
    public function __construct(private readonly PaymentRequestService $requests) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $requests = PaymentRequest::where('requester_user_id', $user->getKey())->latest()->paginate(20);

        return ApiResponse::paginated($requests, PaymentRequestResource::collection($requests));
    }

    public function store(CreatePaymentRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $paymentRequest = $this->requests->create(
            $user,
            $wallet,
            Money::of($request->integer('amount'), $wallet->currency),
            $request->string('title')->toString(),
            $request->input('description'),
        );

        return ApiResponse::created(new PaymentRequestResource($paymentRequest), 'Payment request created.');
    }

    public function show(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('view', $paymentRequest);

        return ApiResponse::success(new PaymentRequestResource($paymentRequest));
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('cancel', $paymentRequest);

        $paymentRequest = $this->requests->cancel($paymentRequest);

        return ApiResponse::success(new PaymentRequestResource($paymentRequest), 'Payment request cancelled.');
    }

    public function publicShow(string $reference): JsonResponse
    {
        $paymentRequest = PaymentRequest::where('reference', $reference)->firstOrFail();

        return ApiResponse::success(new PublicPaymentRequestResource($paymentRequest));
    }
}
```

- [ ] **Step 7: Register the policy**

In `backend/app/Providers/AppServiceProvider.php`, add the import near the other policy imports:

```php
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Policies\PaymentRequestPolicy;
```

And in `boot()`, after `Gate::policy(Payout::class, PayoutPolicy::class);`:

```php
        Gate::policy(PaymentRequest::class, PaymentRequestPolicy::class);
```

- [ ] **Step 8: Add the routes**

In `backend/routes/api/v1.php`, add the import near the other controller imports:

```php
use App\Http\Controllers\Api\V1\Payment\PaymentRequestController;
```

Add the public pay route next to the webhook route (it needs no auth):

```php
// Public payer-facing details for a payment request (no auth).
Route::get('pay/{reference}', [PaymentRequestController::class, 'publicShow'])->name('pay.show');
```

And add the authenticated group (place it alongside the `deposits` / `payouts` groups):

```php
Route::middleware('auth:sanctum')->prefix('payment-requests')->name('payment-requests.')->group(function (): void {
    Route::get('/', [PaymentRequestController::class, 'index'])->name('index');
    Route::post('/', [PaymentRequestController::class, 'store'])->name('store');
    Route::get('{paymentRequest}', [PaymentRequestController::class, 'show'])->name('show');
    Route::post('{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel'])->name('cancel');
});
```

- [ ] **Step 9: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/PaymentRequestApiTest.php`
Expected: PASS (all examples).

- [ ] **Step 10: Commit**

```bash
git add backend/app/Http/Requests/Api/V1/Payment/CreatePaymentRequestRequest.php \
        backend/app/Http/Resources/Api/V1/PaymentRequestResource.php \
        backend/app/Http/Resources/Api/V1/PublicPaymentRequestResource.php \
        backend/app/Domain/Payments/Policies/PaymentRequestPolicy.php \
        backend/app/Http/Controllers/Api/V1/Payment/PaymentRequestController.php \
        backend/app/Providers/AppServiceProvider.php \
        backend/routes/api/v1.php \
        backend/tests/Feature/Payments/PaymentRequestApiTest.php
git commit -m "feat(payments): payment-requests API (create, list, show, cancel, public pay page)"
```

---

### Task 6: Reconcile command (safety net for missed webhooks)

**Files:**
- Create: `backend/app/Console/Commands/ReconcilePaymentRequests.php`
- Test: `backend/tests/Feature/Payments/ReconcilePaymentRequestsCommandTest.php`

**Interfaces:**
- Consumes: `PaymentRequestService::reconcile()`, `PaymentRequest`, `PaymentRequestStatus`, `FakeAlatpayGateway::markPaid()`.
- Produces: artisan command `payment-requests:reconcile`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('credits a pending request that AlatPay reports as paid', function () {
    $gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $gateway);

    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch');

    // Webhook never arrived, but AlatPay received the money.
    $gateway->markPaid($request->provider_reference, 25000);
    PaymentRequest::whereKey($request->id)->update(['created_at' => now()->subMinutes(10)]);

    $this->artisan('payment-requests:reconcile')->assertExitCode(0);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($wallet->fresh()->balance)->toBe(25000);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Payments/ReconcilePaymentRequestsCommandTest.php`
Expected: FAIL — command `payment-requests:reconcile` not found.

- [ ] **Step 3: Create the command**

`backend/app/Console/Commands/ReconcilePaymentRequests.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Services\PaymentRequestService;
use Illuminate\Console\Command;

/**
 * Reconciles pending payment requests against AlatPay — a safety net for missed
 * or delayed webhooks. Only requests old enough to have settled are checked.
 */
class ReconcilePaymentRequests extends Command
{
    protected $signature = 'payment-requests:reconcile';

    protected $description = 'Reconcile pending AlatPay payment requests against the provider';

    public function handle(PaymentRequestService $requests): int
    {
        $credited = 0;

        PaymentRequest::query()
            ->where('status', PaymentRequestStatus::Pending->value)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('created_at')
            ->each(function (PaymentRequest $request) use ($requests, &$credited): void {
                if ($requests->reconcile($request)) {
                    $credited++;
                }
            });

        $this->info("Reconciled {$credited} payment request(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Payments/ReconcilePaymentRequestsCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full payments suite + static analysis**

Run: `./vendor/bin/pest tests/Feature/Payments`
Expected: PASS (entire payments suite green).

Run: `./vendor/bin/phpstan analyse` (or the project's `composer stan` script if present)
Expected: no new errors.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Console/Commands/ReconcilePaymentRequests.php \
        backend/tests/Feature/Payments/ReconcilePaymentRequestsCommandTest.php
git commit -m "feat(payments): payment-requests:reconcile command for missed webhooks"
```

---

## Self-Review

**Spec coverage:**
- §3 flow (create → link → pay → webhook → credit) → Tasks 2, 3, 4.
- §4 data model (table, enum, model) → Task 1.
- §5 gateway extension (DTOs, contract, Fake, Http) → Task 2.
- §6 webhook router refactor → Task 4.
- §7 API surface (create/list/show/cancel/public pay) → Task 5.
- §8 error handling (signature, mismatch, replay, cancel, expiry) → covered by tests in Tasks 3 & 4 (invalid signature, amount mismatch, duplicate, cancel-then-ignore).
- §9 testing (all 10 scenarios) → distributed across Tasks 2–6; reconcile → Tasks 3 & 6; router dispatch → Task 4.
- Reconciliation safety net (mirrors `ReconcileDeposits`) → Task 6.

**Deferred (per spec §1 non-goals, §10):** notifications, reusable/variable links, merchant invoicing, fraud scoring — no tasks, intentionally.

**Type consistency:** `createPaymentLink(PaymentLinkRequest): PaymentLinkResponse` used identically in contract (Task 2), Fake/Http (Task 2), and service (Task 3). `process(WebhookEvent, array): void` signature matches across deposit/payout/payment-request services and the router (Task 4). `PaymentRequestStatus` cases (`Pending/Paid/Expired/Cancelled`) used consistently in model, service, command, and tests. Service method names (`create`, `handleWebhook`, `process`, `reconcile`, `cancel`) match controller and router call sites.

**Placeholder scan:** none — every step contains complete code or an exact command. The only deferred detail is the AlatPay *Payment Link via API* endpoint path/keys in the Http gateway (spec §11 open question), which is explicitly flagged and does not block tests (the Fake gateway drives all tests).
