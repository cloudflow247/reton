<?php

declare(strict_types=1);

namespace App\Domain\Cards\Services;

use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Exceptions\VirtualCardException;
use App\Domain\Cards\Models\VirtualCard;
use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CardFundingService
{
    public function __construct(
        private readonly VirtualCardGateway $gateway,
        private readonly FxQuoteService $fx,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
    ) {}

    public function fund(
        VirtualCard $card,
        Wallet $sourceWallet,
        int $cardAmountMinor,
        ?string $idempotencyKey = null,
    ): void {
        if ($cardAmountMinor <= 0) {
            throw VirtualCardException::providerFailed('fund', 'Enter an amount greater than zero.');
        }

        $quote = $this->fx->quote($sourceWallet->currency, $card->currency, $cardAmountMinor);
        $debit = Money::of($quote->sourceAmountMinor, $quote->sourceCurrency);
        $reference = $idempotencyKey ?? 'card-fund-'.Str::lower((string) Str::ulid());

        if (($replay = $this->ledger->findByIdempotencyKey($reference)) !== null) {
            return;
        }

        DB::transaction(function () use ($card, $sourceWallet, $debit, $cardAmountMinor, $reference, $quote): void {
            $this->wallets->ensureCanSpend($sourceWallet, $debit);

            $this->ledger->post(
                PostingDraft::for(TransactionType::CardFunding)
                    ->describedAs("Fund {$card->currency} virtual card")
                    ->idempotentBy($reference)
                    ->withMetadata([
                        'virtual_card_id' => $card->getKey(),
                        'wallet_id' => $sourceWallet->getKey(),
                        'card_amount_minor' => $cardAmountMinor,
                        'card_currency' => $card->currency,
                        'fx' => $quote->toArray(),
                    ])
                    ->debit($sourceWallet->ledger_account_id, $debit)
                    ->credit($this->system->resolve(SystemAccount::SettlementPayable, $sourceWallet->currency), $debit)
            );

            $this->gateway->fund(
                $card->providerCardId(),
                $cardAmountMinor,
                $card->currency,
                $reference,
            );
        });
    }
}
