<?php

declare(strict_types=1);

namespace App\Domain\Fees\Services;

use App\Domain\Fees\Enums\FeeRail;
use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Support\Money\Money;
use Illuminate\Support\Str;

/**
 * Resolves and posts platform fees configured in Admin → Platform → Fees.
 *
 * Fees are expressed as basis points and/or a flat minor-unit amount.
 * Zero-fee rails are no-ops so product can go live with free flows.
 */
class PlatformFeeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
    ) {}

    /**
     * @return array{bps: int, flat_minor: int}
     */
    public function rates(FeeRail $rail): array
    {
        $prefix = $rail->value;
        $bps = max(0, (int) config("reton.fees.{$prefix}_bps", 0));
        $flat = max(0, (int) config("reton.fees.{$prefix}_flat_minor", 0));

        // Legacy keys remain readable until admin saves the Fees group.
        if ($rail === FeeRail::Recovery) {
            $bps = max($bps, (int) config('reton.recovery.fee_bps', 0));
        }

        if ($rail === FeeRail::SmsAlert) {
            $flat = max($flat, (int) config('reton.sms.alert_fee_minor', 0));
        }

        return [
            'bps' => $bps,
            'flat_minor' => $flat,
        ];
    }

    public function calculate(FeeRail $rail, Money $amount): Money
    {
        $rates = $this->rates($rail);
        $feeMinor = intdiv($amount->amount * $rates['bps'], 10_000) + $rates['flat_minor'];

        if ($feeMinor <= 0) {
            return Money::zero($amount->currency);
        }

        // Cap percentage+flat fees at the principal for money movements.
        // Flat-only rails (e.g. SMS) may pass a zero principal — do not wipe the fee.
        if ($amount->amount > 0) {
            $feeMinor = min($feeMinor, $amount->amount);
        }

        return Money::of($feeMinor, $amount->currency);
    }

    /**
     * Debit a wallet for a platform fee and credit Fees Revenue.
     * Returns null when the fee is zero.
     */
    public function chargeWallet(
        Wallet $wallet,
        FeeRail $rail,
        Money $principal,
        ?string $idempotencyKey = null,
        ?string $description = null,
    ): ?Transaction {
        $fee = $this->calculate($rail, $principal);

        if ($fee->amount <= 0) {
            return null;
        }

        $key = $idempotencyKey ?? 'fee:'.$rail->value.':'.Str::ulid();

        if (($existing = $this->ledger->findByIdempotencyKey($key)) !== null) {
            return $existing;
        }

        $wallet = $wallet->fresh();
        if ($fee->amount > $wallet->availableMinor()) {
            throw InsufficientFundsException::for(
                (string) $wallet->getKey(),
                $wallet->available(),
                $fee,
            );
        }

        return $this->ledger->post(
            PostingDraft::for(TransactionType::Fee)
                ->describedAs($description ?? ($rail->label().' fee'))
                ->idempotentBy($key)
                ->debit($wallet->ledger_account_id, $fee)
                ->credit($this->system->resolve(SystemAccount::FeesRevenue, $wallet->currency), $fee)
        );
    }
}
