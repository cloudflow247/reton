<?php

declare(strict_types=1);

namespace App\Domain\Cards\Services;

use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Data\CreateVirtualCardPayload;
use App\Domain\Cards\Data\VirtualCardBillingAddress;
use App\Domain\Cards\Enums\VirtualCardStatus;
use App\Domain\Cards\Exceptions\VirtualCardException;
use App\Domain\Cards\Models\VirtualCard;
use App\Domain\Cards\Support\NigerianPhone;
use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VirtualCardService
{
    public function __construct(
        private readonly VirtualCardGateway $gateway,
        private readonly PlatformSettingsService $settings,
        private readonly FxQuoteService $fx,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
    ) {}

    public function isReady(): bool
    {
        return $this->settings->isVirtualCardsReady();
    }

    /** @return Collection<int, VirtualCard> */
    public function forUser(User $user): Collection
    {
        return VirtualCard::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [VirtualCardStatus::Active, VirtualCardStatus::Blocked, VirtualCardStatus::Pending])
            ->orderBy('currency')
            ->get();
    }

    public function forUserAndCurrency(User $user, string $currency): ?VirtualCard
    {
        return VirtualCard::query()
            ->where('user_id', $user->getKey())
            ->where('currency', strtoupper($currency))
            ->whereIn('status', [VirtualCardStatus::Active, VirtualCardStatus::Blocked, VirtualCardStatus::Pending])
            ->first();
    }

    public function primaryFor(User $user): ?VirtualCard
    {
        return $this->forUser($user)->first();
    }

    public function issue(User $user, Wallet $sourceWallet, string $currency): VirtualCard
    {
        if (! $this->isReady()) {
            throw VirtualCardException::notReady();
        }

        $currency = strtoupper($currency);

        if (! in_array($currency, config('reton.cards.currencies', ['NGN', 'USD']), true)) {
            throw VirtualCardException::providerFailed('issue', 'Unsupported card currency.');
        }

        if ($this->forUserAndCurrency($user, $currency) !== null) {
            throw VirtualCardException::alreadyIssued($currency);
        }

        $mobile = NigerianPhone::toInternational($user->phone);
        $email = trim((string) $user->email);

        if ($mobile === null || $email === '') {
            throw VirtualCardException::missingProfile();
        }

        $fundingMinor = (int) config("reton.cards.min_funding_minor.{$currency}", 0);
        $quote = $this->fx->quote($sourceWallet->currency, $currency, $fundingMinor);
        $debit = Money::of($quote->sourceAmountMinor, $quote->sourceCurrency);

        [$firstName, $lastName] = NigerianPhone::splitName($user);
        $cardIdentifier = 'reton-'.Str::lower((string) Str::ulid());
        $cardPin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $existingCardholder = $this->existingCardholderId($user);

        $payload = new CreateVirtualCardPayload(
            pin: $cardPin,
            firstName: $firstName,
            lastName: $lastName,
            nameOnCard: Str::limit(strtoupper($user->name), 25, ''),
            mobileNr: $mobile,
            emailAddress: $email,
            city: 'Lagos',
            state: 'Lagos',
            countryCode: 'NG',
            cardIdentifier: $cardIdentifier,
            currency: $currency,
            fundingAmountMinor: $fundingMinor,
            cardBrand: 'Mastercard',
            cardLimit: (string) config('reton.cards.default_usd_limit', '500000'),
        );

        $linkedWallet = $this->walletForCurrency($user, $currency) ?? $sourceWallet;

        return DB::transaction(function () use ($user, $linkedWallet, $sourceWallet, $payload, $cardPin, $cardIdentifier, $currency, $debit, $fundingMinor, $existingCardholder, $quote): VirtualCard {
            $this->wallets->ensureCanSpend($sourceWallet, $debit);

            $issueRef = 'card-issue-'.$cardIdentifier;

            $this->ledger->post(
                PostingDraft::for(TransactionType::CardFunding)
                    ->describedAs("Issue {$currency} virtual card - initial load")
                    ->idempotentBy($issueRef)
                    ->withMetadata([
                        'card_identifier' => $cardIdentifier,
                        'wallet_id' => $sourceWallet->getKey(),
                        'card_amount_minor' => $fundingMinor,
                        'card_currency' => $currency,
                        'fx' => $quote->toArray(),
                    ])
                    ->debit($sourceWallet->ledger_account_id, $debit)
                    ->credit($this->system->resolve(SystemAccount::SettlementPayable, $sourceWallet->currency), $debit)
            );

            $cardholderId = $this->gateway->ensureCardholder($payload, $existingCardholder);
            $issued = $this->gateway->createPrepaid($payload, $cardholderId);

            $providerCardId = $issued->providerCardId ?? $cardIdentifier;

            $card = VirtualCard::create([
                'user_id' => $user->getKey(),
                'wallet_id' => $linkedWallet->getKey(),
                'provider' => 'bridgecard',
                'card_identifier' => $cardIdentifier,
                'provider_card_id' => $providerCardId,
                'provider_cardholder_id' => $issued->providerCardholderId ?? $cardholderId,
                'status' => VirtualCardStatus::Active,
                'currency' => $currency,
                'scheme' => $currency === 'USD' ? 'mastercard' : 'mastercard',
                'pan_last4' => substr(preg_replace('/\D/', '', $issued->pan) ?: '0000', -4),
                'pan_masked' => $this->maskPan($issued->pan),
                'pan_encrypted' => VirtualCard::encryptSecret($issued->pan),
                'cvv_encrypted' => VirtualCard::encryptSecret($issued->cvv),
                'cvv2_encrypted' => $issued->cvv2 ? VirtualCard::encryptSecret($issued->cvv2) : null,
                'expiry' => $issued->expiry,
                'seq_nr' => $issued->seqNr,
                'customer_id' => $issued->customerId,
                'name_on_card' => Str::limit(strtoupper($user->name), 25, ''),
                'card_pin_encrypted' => VirtualCard::encryptSecret($cardPin),
                'metadata' => [
                    'card_balance_minor' => $fundingMinor,
                    'card_balance_synced_at' => now()->toIso8601String(),
                    'billing_address' => ($issued->billingAddress ?? VirtualCardBillingAddress::defaultFor($currency))->toArray(),
                    'brand' => $issued->brand,
                ],
                'activated_at' => now(),
            ]);

            return $card->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function reveal(VirtualCard $card): array
    {
        $details = null;

        if (str_contains($card->pan(), '*')) {
            $details = $this->gateway->fetchDetails($card->providerCardId());
            $metadata = is_array($card->metadata) ? $card->metadata : [];

            if ($details->billingAddress !== null) {
                $metadata['billing_address'] = $details->billingAddress->toArray();
            }

            if ($details->brand !== '') {
                $metadata['brand'] = $details->brand;
            }

            $card->update([
                'pan_encrypted' => VirtualCard::encryptSecret($details->pan),
                'cvv_encrypted' => VirtualCard::encryptSecret($details->cvv),
                'pan_last4' => substr(preg_replace('/\D/', '', $details->pan) ?: '0000', -4),
                'pan_masked' => $this->maskPan($details->pan),
                'metadata' => $metadata,
            ]);
        }

        return [
            'pan' => $card->formattedPan(),
            'cvv' => $card->cvv(),
            'cvv2' => $card->cvv2(),
            'expiry' => $card->expiryDisplay(),
            'name_on_card' => $card->name_on_card,
            'currency' => $card->currency,
            'brand' => is_array($card->metadata) ? (string) ($card->metadata['brand'] ?? 'Mastercard') : 'Mastercard',
            'card_type' => 'virtual',
            'billing_address' => $card->billingAddress(),
        ];
    }

    public function freeze(VirtualCard $card): VirtualCard
    {
        $this->gateway->block($card->providerCardId());
        $card->update(['status' => VirtualCardStatus::Blocked]);

        return $card->refresh();
    }

    public function unfreeze(VirtualCard $card): VirtualCard
    {
        $this->gateway->unblock($card->providerCardId());
        $card->update(['status' => VirtualCardStatus::Active]);

        return $card->refresh();
    }

    public function syncBalance(VirtualCard $card): VirtualCard
    {
        try {
            $balance = $this->gateway->balance($card->providerCardId());
            $metadata = is_array($card->metadata) ? $card->metadata : [];
            $billing = is_array($metadata['billing_address'] ?? null) ? $metadata['billing_address'] : null;

            if ($billing === null || ($billing['line1'] ?? '') === '') {
                $details = $this->gateway->fetchDetails($card->providerCardId());

                if ($details->billingAddress !== null) {
                    $metadata['billing_address'] = $details->billingAddress->toArray();
                }
            }

            $metadata['card_balance_minor'] = $balance->availableMinor;
            $metadata['card_balance_synced_at'] = now()->toIso8601String();

            $card->update(['metadata' => $metadata]);
        } catch (\Throwable) {
            // Best-effort sync.
        }

        return $card->refresh();
    }

    private function existingCardholderId(User $user): ?string
    {
        return VirtualCard::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('provider_cardholder_id')
            ->value('provider_cardholder_id');
    }

    private function walletForCurrency(User $user, string $currency): ?Wallet
    {
        return Wallet::query()
            ->where('user_id', $user->getKey())
            ->where('currency', $currency)
            ->first();
    }

    private function maskPan(string $pan): string
    {
        $digits = preg_replace('/\D/', '', $pan) ?: $pan;

        if (strlen($digits) < 8) {
            return $pan;
        }

        return substr($digits, 0, 6).str_repeat('*', max(0, strlen($digits) - 10)).substr($digits, -4);
    }
}
